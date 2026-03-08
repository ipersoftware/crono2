# Lista d'attesa

## Panoramica

Quando una sessione è esaurita, il sistema può raccogliere le prenotazioni in uno stato **IN\_LISTA\_ATTESA** anziché rifiutarle. Al liberarsi di un posto (annullamento di una prenotazione confermata), il sistema gestisce il primo candidato in coda secondo la policy configurata dall'operatore.

La lista d'attesa è gestita direttamente sulla tabella `prenotazioni`, sfruttando gli stati già previsti nell'enum e due campi aggiuntivi (`posizione_lista_attesa`, `notificato_at`). Non esiste una tabella separata.

---

## Configurazione sulla sessione

La lista d'attesa si attiva per sessione nel pannello admin (Sessioni → Modifica).

| Campo | Tipo | Descrizione |
|---|---|---|
| `attiva_lista_attesa` | boolean | Attiva il flusso lista d'attesa per questa sessione |
| `tipo_conferma` | enum | Vedi valori sotto |
| `lista_attesa_finestra_conferma_ore` | integer | Ore di validità del link di conferma (solo per `PRENOTAZIONE_DA_CONFERMARE`) |

### Valori di `tipo_conferma`

| Valore | Comportamento al liberarsi di un posto |
|---|---|
| `NESSUNA` | Non viene fatto nulla. Le prenotazioni IN\_LISTA\_ATTESA rimangono in coda senza promozione automatica. |
| `PRENOTAZIONE_AUTOMATICA` | Il primo in coda con posti sufficienti viene promosso automaticamente a `CONFERMATA` e riceve l'email **PRENOTAZIONE\_APPROVATA**. |
| `PRENOTAZIONE_DA_CONFERMARE` | Il primo in coda riceve l'email **LISTA\_ATTESA\_POSTO\_DISPONIBILE** con un link di conferma valido per `lista_attesa_finestra_conferma_ore`. Passa a stato `NOTIFICATO`. Se non conferma entro la scadenza, viene marcato `SCADUTA` e si tenta il successivo. |

---

## Flusso utente (booking frontend)

### Iscrizione alla lista d'attesa

1. L'utente apre la pagina di prenotazione di una sessione esaurita con `attiva_lista_attesa = true`.
2. Il form mostra un banner **"Sessione esaurita — lista d'attesa attiva"** e permette comunque di selezionare posti e inserire i dati personali.
3. Al submit, il frontend chiama `POST /api/prenotazioni/lista-attesa` (invece del normale flusso lock → store).
4. Il sistema crea una `Prenotazione` con stato `IN_LISTA_ATTESA`, assegna `posizione_lista_attesa` e genera `codice`/`token_accesso`.
5. Il sistema invia l'email **LISTA\_ATTESA\_ISCRIZIONE** con la posizione in coda.
6. Il counter `posti_in_attesa` sulla sessione viene incrementato.

> **Nota**: non viene acquisito alcun lock temporaneo — non c'è nulla da riservare finché un posto non si libera.

### Conferma posto (solo `PRENOTAZIONE_DA_CONFERMARE`)

1. Quando scatta la promozione, l'utente riceve un'email con un link `{frontend_url}/lista-attesa/{token_accesso}/conferma`.
2. Il frontend chiama `POST /api/lista-attesa/{token}/conferma`.
3. Se il token è valido e la scadenza non è passata, la prenotazione passa a `CONFERMATA`; i posti vengono scalati dai contatori.
4. Viene inviata l'email **PRENOTAZIONE\_APPROVATA**.

### Uscita dalla lista d'attesa

L'utente può rimuoversi dalla lista usando il normale endpoint di annullamento (`DELETE /api/prenotazioni/{codice}`). Lo stato `IN_LISTA_ATTESA` e `NOTIFICATO` sono sempre annullabili (senza vincoli di ore), e non restituiscono `posti_disponibili` (vengono decrementati solo i `posti_in_attesa`).

---

## Flusso di promozione automatica

Il trigger è **`ListaAttesaService::processaPromozione()`**, chiamato ogni volta che una prenotazione *reale* (CONFERMATA / DA\_CONFERMARE) viene annullata.

```
annullaUtente / annullaAdmin
        │
        ├── [era lista attesa] → decrement posti_in_attesa, NO processaPromozione
        │
        └── [era prenotazione reale] → increment posti_disponibili
                                         → processaPromozione(sessione)
                                               │
                                               ├── tipo_conferma = NESSUNA → nulla
                                               │
                                               ├── tipo_conferma = PRENOTAZIONE_AUTOMATICA
                                               │     └── per ogni candidato IN_LISTA_ATTESA (ordinati per posizione):
                                               │           se posti_disponibili >= posti_richiesti:
                                               │             → stato = CONFERMATA
                                               │             → decrement posti_disponibili + tipologie
                                               │             → decrement posti_in_attesa
                                               │             → email PRENOTAZIONE_APPROVATA
                                               │
                                               └── tipo_conferma = PRENOTAZIONE_DA_CONFERMARE
                                                     se nessun NOTIFICATO attivo:
                                                       → primo IN_LISTA_ATTESA → stato = NOTIFICATO
                                                       → scadenza_riserva = now + ore
                                                       → email LISTA_ATTESA_POSTO_DISPONIBILE
```

---

## Gestione delle scadenze (comando schedulato)

Il comando artisan `lista-attesa:processa-scadute` viene eseguito ogni 5 minuti dallo scheduler.

Trova tutte le prenotazioni in stato `NOTIFICATO` con `scadenza_riserva <= now()`:
1. Marca la prenotazione come `SCADUTA`.
2. Invia l'email **LISTA\_ATTESA\_SCADENZA**.
3. Chiama `processaPromozione()` per tentare il prossimo in coda.

---

## Template email coinvolti

| Tipo | Quando |
|---|---|
| `LISTA_ATTESA_ISCRIZIONE` | Subito dopo l'iscrizione in lista |
| `LISTA_ATTESA_POSTO_DISPONIBILE` | Quando la prenotazione passa a `NOTIFICATO` (solo `PRENOTAZIONE_DA_CONFERMARE`) |
| `LISTA_ATTESA_SCADENZA` | Quando la finestra di conferma scade senza risposta |
| `PRENOTAZIONE_APPROVATA` | Quando la prenotazione viene promossa a `CONFERMATA` |
| `PRENOTAZIONE_ANNULLATA_UTENTE` / `PRENOTAZIONE_ANNULLATA_OPERATORE` | Quando l'utente/operatore rimuove l'iscrizione dalla lista |

### Placeholder disponibili per email lista attesa

| Placeholder | Valore |
|---|---|
| `{{nome_utente}}`, `{{cognome_utente}}`, `{{email_utente}}` | Dati del richiedente |
| `{{titolo_evento}}`, `{{data_sessione}}`, `{{ora_inizio}}`, `{{ora_fine}}` | Dati evento/sessione |
| `{{luogo_evento}}`, `{{indirizzo_luogo}}` | Luogo della sessione |
| `{{posti_richiesti}}` | Numero posti richiesti |
| `{{posizione_lista_attesa}}` | Posizione in coda al momento dell'iscrizione |
| `{{link_conferma_attesa}}` | Link `{frontend_url}/lista-attesa/{token}/conferma` (valido per ore configurate) |
| `{{scadenza_conferma}}` | Data/ora scadenza link di conferma |
| `{{codice_prenotazione}}` | Codice univoco (es. CRN-2026-00042) |
| `{{nome_ente}}`, `{{email_ente}}`, `{{link_vetrina}}` | Dati ente |

---

## Modello dati (tabella `prenotazioni`)

La lista d'attesa reusa la tabella `prenotazioni` con stati dedicati:

| Campo rilevante | Ruolo per lista attesa |
|---|---|
| `stato` | `IN_LISTA_ATTESA` → `NOTIFICATO` → `CONFERMATA` oppure `SCADUTA` / `ANNULLATA_*` |
| `posizione_lista_attesa` | Ordine in coda; `NULL` quando non in lista |
| `notificato_at` | Timestamp invio email "posto disponibile" |
| `scadenza_riserva` | Deadline di conferma per `NOTIFICATO` (riutilizzato) |
| `token_accesso` | Usato come token nel link di conferma (riutilizzato) |
| `posti_prenotati`, `costo_totale` | Già valorizzati all'iscrizione |
| `prenotazione_posti` (relazione) | Dettaglio per tipologia, già creato all'iscrizione |

### Regole sui contatori sessione

| Azione | `posti_disponibili` | `posti_in_attesa` |
|---|---|---|
| Iscrizione lista attesa | invariato | +N |
| Promozione automatica / conferma manuale | −N | −N |
| Annullamento di una prenotazione reale | +N | invariato |
| Annullamento di una prenotazione IN\_LISTA\_ATTESA / NOTIFICATO | invariato | −N |
| Scadenza notifica (SCADUTA) | invariato | invariato (già decrementato se confermata) |

> `posti_in_attesa` è un contatore **informativo** per il monitoraggio; non blocca la prenotazione normale.

---

## Monitoraggio (operatore)

La vista monitoraggio (`GET /api/enti/{ente}/eventi/{evento}/monitoraggio`) espone per ogni sessione:

- `in_lista_attesa`: numero di prenotazioni in stato `IN_LISTA_ATTESA` o `NOTIFICATO`
- `attiva_lista_attesa`: flag boolean
- `posti_in_attesa`: contatore denormalizzato sulla sessione

Nel riepilogo globale evento è presente il totale `in_lista_attesa` su tutte le sessioni.

---

## API

| Metodo | Endpoint | Autenticazione | Descrizione |
|---|---|---|---|
| `POST` | `/api/prenotazioni/lista-attesa` | opzionale | Iscrive l'utente alla lista d'attesa |
| `POST` | `/api/lista-attesa/{token}/conferma` | nessuna | Conferma il posto tramite link email |
| `DELETE` | `/api/prenotazioni/{codice}` | opzionale (token guest) | Rimuove l'iscrizione dalla lista |

### Body `POST /api/prenotazioni/lista-attesa`

```json
{
  "sessione_id": 42,
  "nome": "Mario",
  "cognome": "Rossi",
  "email": "mario@example.com",
  "telefono": "3382781823",
  "posti": [
    { "tipologia_id": 1, "quantita": 2 }
  ],
  "privacy_ok": true
}
```

### Risposta `201`

```json
{
  "message": "Iscrizione alla lista d'attesa registrata.",
  "posizione": 3,
  "codice": "CRN-2026-00042",
  "email": "mario@example.com"
}
```

---

## Edge case gestiti

- **Sessione con `tipo_conferma = NESSUNA`**: i dati vengono raccolti ma nessuna promozione automatica. L'operatore può gestire manualmente (es. approvando via admin).

- **Posti liberati insufficienti per il primo in coda (`PRENOTAZIONE_AUTOMATICA`)**: il candidato viene saltato e si tenta il successivo che ne richiede di meno. Esempio: si libera 1 posto, il primo in lista ne chiede 3 → viene promosso il secondo che ne chiede 1.

- **Posti liberati insufficienti per il primo in coda (`PRENOTAZIONE_DA_CONFERMARE`)**: il sistema cerca il primo candidato compatibile (i cui `posti_prenotati` rientrano nei posti disponibili), rispettando comunque l'ordine di posizione. Se nessun candidato in lista rientra nei posti disponibili, la notifica non viene inviata e si attende il prossimo annullamento. Questo evita che l'utente riceva un'email "posto disponibile" per poi ottenere un errore al momento della conferma.

  | Esempio | Azione |
  |---|---|
  | Liberati 100 posti, primo in lista ne chiede 110 | Nessuna notifica; si attende altro annullamento |
  | Liberati 100 posti, primo chiede 110, secondo chiede 80 | Notificato il secondo (80 ≤ 100), rispettando l'ordine |

- **Doppia iscrizione**: verificato per email + sessione_id — viene restituito 422.

- **Sessione ancora con posti liberi**: il frontend smette di mostrare la lista attesa; se richiesta via API, viene restituito 422 ("procedi con la prenotazione normale").

- **Conferma scaduta**: 422 con messaggio esplicito; la prenotazione viene marcata `SCADUTA` dal comando schedulato, che poi tenta la promozione del successivo in lista.

- **Annullamento di IN\_LISTA\_ATTESA / NOTIFICATO**: non libera `posti_disponibili` (non erano mai stati scalati); decrementa solo `posti_in_attesa`. Non innesca `processaPromozione` (nessun posto reale si è liberato).
