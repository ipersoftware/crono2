# Integrazione Crono2 ↔ Ermes — Newsletter dai prenotati

## Panoramica

**Crono2** è il sistema di gestione eventi e prenotazioni.  
**Ermes** è il sistema di messaggistica/newsletter.  
**Governance DB** è il database condiviso che determina quali servizi sono abilitati per ogni ente.  
**Keycloak** gestisce il Single Sign-On (già operativo e funzionante per entrambe le applicazioni).

Il flusso permette a un operatore Crono2 di creare una newsletter Ermes pre-popolata con i contatti dei prenotati filtrati, senza dover esportare/importare manualmente i dati.

---

## Flusso completo (utente)

```
Operatore in Crono2 → applica filtri nella lista prenotazioni
  → clicca "📨 Crea Newsletter"
  → Crono2 salva i filtri in cache con un token (15 min)
  → apre Ermes in nuova scheda: {ERMES_URL}/newsletter/new?crono2_token=XXX&crono2_url=YYY
  → Ermes mostra form "Nuova newsletter" pre-compilato con i destinatari
  → (back-channel) Ermes chiama GET /api/v1/newsletter/{token}/subscribers
  → Crono2 applica i filtri e restituisce la lista dei prenotati
  → Ermes usa l'elenco come destinatari della newsletter
```

---

## Lato Crono2 (già implementato)

### Variabili d'ambiente (`.env`)

```dotenv
# Ermes — sistema di messaggistica
ERMES_URL=http://localhost:8001
ERMES_API_TOKEN=un_token_segreto_condiviso
```

- `ERMES_URL` — URL base di Ermes (senza slash finale)
- `ERMES_API_TOKEN` — secret condiviso che Ermes deve includere come `Authorization: Bearer <token>` nelle chiamate back-channel verso Crono2. Può essere vuoto in sviluppo (la verifica viene saltata).

### Governance DB — abilitazione servizio

Il pulsante "Crea Newsletter" in Crono2 appare **solo** se nel database `governance` la tabella `enti_servizi` ha una riga con:

```
ente_id     = governance_id dell'ente (campo nella tabella enti di Crono2)
servizio_id = 'ermes'
attivo      = 1
```

Se questa riga manca o `attivo = 0`, il pulsante non viene mostrato e il link Ermes non compare nella navbar.

### Endpoint esposti da Crono2

#### 1. `GET /api/enti/{ente}/newsletter/ermes-attivo`
**Auth**: Sanctum (utente loggato)  
**Scopo**: Controlla se l'ente ha Ermes attivo nel governance DB.

Risposta `200`:
```json
{
  "attivo": true,
  "ermes_url": "http://localhost:8001"
}
```
Se non attivo: `{ "attivo": false, "ermes_url": null }`

---

#### 2. `POST /api/enti/{ente}/newsletter/snapshot`
**Auth**: Sanctum (utente loggato)  
**Scopo**: Salva i filtri correnti in cache e restituisce un token temporaneo (15 minuti).

Body:
```json
{
  "filtri": {
    "evento_id":  123,
    "sessione_id": null,
    "stato":      "CONFERMATA",
    "cerca":      "",
    "data_dal":   "2026-01-01",
    "data_al":    "2026-12-31"
  }
}
```

Risposta `200`:
```json
{
  "token":   "AbCd1234...48char",
  "expires": "2026-03-14T12:30:00.000Z"
}
```

Il token è una stringa random di 48 caratteri. Scade dopo 15 minuti.  
La chiave cache è `newsletter_snapshot:{token}`.

---

#### 3. `GET /api/v1/newsletter/{token}/subscribers`
**Auth**: Bearer token (`Authorization: Bearer <ERMES_API_TOKEN>`)  
**Scopo**: Endpoint back-channel consumato da Ermes per recuperare la lista dei destinatari.

Risposta `200`:
```json
{
  "ente": {
    "id":   5,
    "nome": "Comune di Esempio"
  },
  "filtri": { ... },
  "total": 42,
  "subscribers": [
    {
      "email":         "mario.rossi@example.com",
      "nome":          "Mario",
      "cognome":       "Rossi",
      "nome_completo": "Rossi Mario",
      "telefono":      "3331234567",
      "codice":        "PRE-2026-001",
      "sessione": {
        "id":          88,
        "data_inizio": "2026-04-10T10:00:00"
      },
      "evento": {
        "id":     123,
        "titolo": "Concerto di Primavera"
      }
    }
  ]
}
```

**Note sulla lista:**
- Include solo prenotazioni con stato `CONFERMATA` o `DA_CONFERMARE`
- Viene deduplicata per `email` (un utente con più prenotazioni appare una sola volta)
- Filtri applicabili: `evento_id`, `sessione_id`, `stato`, `cerca` (ricerca libera su codice/nome/cognome/email), `data_dal`, `data_al`

**Errori:**
- `401` — Token Bearer assente o non valido
- `404` — Token snapshot non trovato o scaduto (dopo 15 min)

---

### Deep-link verso Ermes

Quando l'operatore clicca "📨 Crea Newsletter", Crono2 apre in nuova scheda:

```
{ERMES_URL}/newsletter/new?crono2_token={token}&crono2_url={encodeURIComponent(location.origin)}
```

Esempio:
```
http://localhost:8001/newsletter/new?crono2_token=AbCd1234...&crono2_url=http%3A%2F%2Flocalhost%3A8002
```

Parametri:
| Param | Descrizione |
|---|---|
| `crono2_token` | Token snapshot (48 char, valido 15 min) |
| `crono2_url` | URL base di Crono2 (URL-encoded), usato da Ermes per sapere dove chiamare |

---

### File modificati in Crono2

| File | Modifica |
|---|---|
| `app/Http/Controllers/NewsletterController.php` | Creato — 3 metodi: `ermesAttivo`, `creaSnapshot`, `subscribers` |
| `routes/api.php` | Aggiunte 3 route (2 auth Sanctum + 1 pubblica v1) |
| `config/services.php` | Aggiunta chiave `ermes` con `url` e `api_token` |
| `.env` | `ERMES_URL`, `ERMES_API_TOKEN` |
| `resources/js/api/admin.js` | Aggiunto `newsletterApi` (2 metodi) |
| `resources/js/views/admin/Prenotazioni.vue` | Pulsante "Crea Newsletter" + logica |
| `resources/js/App.vue` | Link Ermes in navbar (solo se abilitato) + stile |

---

---

## Lato Ermes (da implementare)

Questa sezione descrive tutto quello che Ermes deve fare per integrarsi correttamente.

### 1. Variabili d'ambiente in Ermes

Aggiungere al `.env` di Ermes:

```dotenv
# Crono2 — sistema di prenotazioni
CRONO2_API_TOKEN=un_token_segreto_condiviso   # stesso valore di ERMES_API_TOKEN in Crono2
```

> Il valore deve essere **identico** a `ERMES_API_TOKEN` in Crono2. In sviluppo può essere lasciato vuoto (Crono2 salta la verifica).

---

### 2. Route da aggiungere in Ermes

```
GET /newsletter/new
```

Questa route riceve il deep-link da Crono2. Deve:
1. Leggere `crono2_token` e `crono2_url` dai query params
2. Conservare i valori nello stato della pagina/sessione
3. Chiamare l'API Crono2 per recuperare i destinatari (vedi §3)
4. Mostrare il form "Nuova newsletter" pre-compilato con i destinatari

---

### 3. Chiamata back-channel verso Crono2

Ermes deve chiamare Crono2 per ottenere la lista subscriber:

```
GET {crono2_url}/api/v1/newsletter/{crono2_token}/subscribers
Authorization: Bearer {CRONO2_API_TOKEN}
Accept: application/json
```

Esempio (axios/fetch):
```js
const res = await axios.get(
  `${crono2_url}/api/v1/newsletter/${crono2_token}/subscribers`,
  {
    headers: {
      Authorization: `Bearer ${process.env.CRONO2_API_TOKEN}`,
    }
  }
)
const { subscribers, ente, filtri, total } = res.data
```

Oppure lato server (Laravel):
```php
$response = Http::withToken(config('services.crono2.api_token'))
    ->get("{$crono2Url}/api/v1/newsletter/{$token}/subscribers");

$data = $response->json();
// $data['subscribers'] — array dei destinatari
// $data['ente']        — ente di provenienza
// $data['total']       — numero destinatari
```

**Gestione errori:**
- `401` → token non valido (verificare configurazione `CRONO2_API_TOKEN`)
- `404` → token scaduto (il token dura 15 min) → mostrare messaggio "Link scaduto, torna su Crono2 e riprova"

---

### 4. Struttura subscriber ricevuti

Ogni subscriber nell'array ha questi campi (tutti possono essere `null`):

```json
{
  "email":         "string",       // sempre presente
  "nome":          "string|null",
  "cognome":       "string|null",
  "nome_completo": "string|null",  // "{cognome} {nome}" già formattato
  "telefono":      "string|null",
  "codice":        "string|null",  // codice prenotazione Crono2
  "sessione": {
    "id":          "int",
    "data_inizio": "datetime"
  },
  "evento": {
    "id":          "int",
    "titolo":      "string"
  }
}
```

La lista è già **deduplicata per email** da Crono2. Non serve filtrare ulteriormente.

---

### 5. UX consigliata per la pagina `/newsletter/new`

```
┌─────────────────────────────────────────────────────┐
│  📨 Nuova Newsletter                                │
│                                                     │
│  Importati da Crono2: 42 destinatari               │
│  Ente: Comune di Esempio                           │
│  Evento: Concerto di Primavera                     │
│                                                     │
│  Oggetto: [________________________]               │
│  Corpo:   [editor WYSIWYG]                         │
│                                                     │
│  Destinatari: [lista preview collassabile]         │
│                                                     │
│  [ Invia ora ]  [ Salva bozza ]  [ Annulla ]       │
└─────────────────────────────────────────────────────┘
```

Comportamento suggerito:
- Mostrare un riepilogo "N destinatari importati da Crono2 — Ente: …"
- L'operatore può rimuovere singoli destinatari prima di inviare
- Se il token è scaduto mostrare: *"Il link di Crono2 è scaduto (15 min). Torna su Crono2 e clicca di nuovo 'Crea Newsletter'."*

---

### 6. Single Sign-On con Keycloak

Il SSO con Keycloak è **già operativo** tra Crono2 ed Ermes. Non occorre nessuna modifica per l'autenticazione utente: quando l'operatore clicca il link in Crono2 ed Ermes si apre in nuova scheda, Keycloak riconosce la sessione già attiva e non richiede un nuovo login.

L'unica autenticazione aggiuntiva è il **Bearer token machine-to-machine** (`CRONO2_API_TOKEN`) usato esclusivamente per la chiamata back-channel `GET /api/v1/newsletter/{token}/subscribers`. Questo è separato dal SSO utente.

---

### 7. Config consigliata in Ermes (Laravel)

In `config/services.php`:
```php
'crono2' => [
    'api_token' => env('CRONO2_API_TOKEN', ''),
],
```

In `routes/web.php` (o `api.php`):
```php
Route::get('/newsletter/new', [NewsletterController::class, 'nuovo'])
    ->middleware('auth'); // Keycloak SSO già gestisce il middleware auth
```

Controller di esempio:
```php
public function nuovo(Request $request)
{
    $token    = $request->query('crono2_token');
    $crono2Url = $request->query('crono2_url');

    if (!$token || !$crono2Url) {
        return view('newsletter.nuovo', ['subscribers' => [], 'ente' => null]);
    }

    try {
        $response = Http::withToken(config('services.crono2.api_token'))
            ->get("{$crono2Url}/api/v1/newsletter/{$token}/subscribers");

        if ($response->status() === 404) {
            return view('newsletter.nuovo')->with('error', 'Link Crono2 scaduto. Torna su Crono2 e riprova.');
        }

        $data = $response->json();

        return view('newsletter.nuovo', [
            'subscribers' => $data['subscribers'],
            'ente'        => $data['ente'],
            'filtri'      => $data['filtri'],
        ]);
    } catch (\Exception $e) {
        return view('newsletter.nuovo')->with('error', 'Impossibile contattare Crono2.');
    }
}
```

---

## Checklist di integrazione

### Crono2 (già fatto ✅)
- [x] `NewsletterController` con 3 endpoint
- [x] Route `GET /api/enti/{ente}/newsletter/ermes-attivo`
- [x] Route `POST /api/enti/{ente}/newsletter/snapshot`
- [x] Route `GET /api/v1/newsletter/{token}/subscribers`
- [x] `.env`: `ERMES_URL`, `ERMES_API_TOKEN`
- [x] Pulsante "Crea Newsletter" in lista prenotazioni (visibile solo se Ermes abilitato nel governance DB)
- [x] Link Ermes in navbar (solo per enti abilitati)

### Ermes (da fare ❌)
- [ ] Aggiungere `CRONO2_API_TOKEN` al `.env`
- [ ] Aggiungere `services.crono2.api_token` in `config/services.php`
- [ ] Creare route `GET /newsletter/new`
- [ ] Creare controller/view per gestire il deep-link
- [ ] Chiamata back-channel a Crono2 con Bearer token
- [ ] Gestione errore 404 (token scaduto)
- [ ] Form nuova newsletter pre-compilato con i destinatari importati
- [ ] Verificare nel governance DB che `servizi` abbia la riga `id='ermes'` con `url` valorizzato

---

## Edge case noti

### Utente admin senza ente_id (Ermes)
L'utente amministratore di Ermes può non avere un `ente_id` associato nel DB. In questo caso:
- `enteId()` nel `NewsletterController` di Ermes deve controllare il `null` esplicitamente e rispondere con `422` + messaggio leggibile (es. *"Nessun ente associato all'utente. Impersonifica un ente prima di procedere."*) anziché lanciare un `TypeError` → `500`.
- Nel frontend di Ermes, il `Promise.all` in `onMounted` va wrappato con `.catch(() => [])` su ogni singola chiamata, così il fallimento di `fetchMailAccounts()` non blocca l'intera inizializzazione e l'importazione da Crono2 viene sempre raggiunta.

---

## Note di sicurezza

1. **`ERMES_API_TOKEN`** non è un token utente — è un segreto machine-to-machine. Va tenuto fuori dal controllo di versione (solo in `.env`).
2. Il token snapshot scade in **15 minuti** — questo limita la finestra di esposizione dei dati in cache.
3. L'endpoint `/api/v1/newsletter/{token}/subscribers` non richiede autenticazione Keycloak (è chiamato da server a server), ma richiede il Bearer token.
4. `crono2_url` ricevuto da Ermes come query param dovrebbe essere validato contro una whitelist di URL consentiti per evitare SSRF.
5. Ogni token snapshot viene letto e scartato dopo l'uso, oppure scade dopo 15 min — non c'è persistenza nel DB.
