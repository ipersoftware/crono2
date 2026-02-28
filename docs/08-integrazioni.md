# 08 — Integrazioni con Sistemi Esterni

## Scopo

Descrivere le integrazioni di Crono2 con sistemi esterni, i flussi di comunicazione,
le API esposte e consumate, e i casi d'uso supportati.

---

## 1. Panoramica Integrazioni

Crono2 si integra con i seguenti sistemi esterni:

| Sistema | Funzione | Modalità | Stato |
|---------|----------|----------|-------|
| **Keycloak** | Autenticazione SSO | OAuth 2.0 / OpenID Connect | ✅ Attivo |
| **Ermes** | Sistema di messaggistica | API REST (Crono2 → Ermes) | 🔄 Pianificato |
| **Smartpass** | Pass digitali | API REST (bidirezionale) | 🔲 Futuro |

---

## 2. Integrazione con Keycloak (SSO)

### 2.1 Panoramica

Keycloak gestisce l'autenticazione per i ruoli gestionali:
- `operatore_ente`
- `admin_ente`
- `admin`

Gli utenti con ruolo `utente` usano autenticazione locale Laravel.

### 2.2 Flusso di autenticazione

```
┌─────────────┐          ┌─────────────┐          ┌─────────────┐
│   Frontend  │          │   Laravel   │          │  Keycloak   │
│   (Vue.js)  │          │   Backend   │          │             │
└──────┬──────┘          └──────┬──────┘          └──────┬──────┘
       │                        │                        │
       │  Login (SSO)           │                        │
       ├────────────────────────┼───────────────────────►│
       │                        │                        │
       │                        │   ◄─── JWT Token ──────┤
       │  ◄───── JWT ───────────┤                        │
       │                        │                        │
       │  API Request + JWT     │                        │
       ├───────────────────────►│                        │
       │                        │  Verify JWT            │
       │                        ├───────────────────────►│
       │                        │  ◄─── Valid ───────────┤
       │  ◄─── Response ────────┤                        │
       │                        │                        │
```

### 2.3 Sincronizzazione ruoli

I **Realm Roles** di Keycloak vengono sincronizzati nel campo `role` della tabella `users`.

La sincronizzazione avviene:
- Al primo login dell'utente
- Periodicamente via comando artisan (opzionale)
- Su richiesta manuale da parte dell'admin

> Dettagli tecnici nel documento [09-autenticazione.md](./09-autenticazione.md).

---

## 3. Integrazione con Ermes (Messaggistica)

### 3.1 Panoramica

**Ermes** è il sistema di messaggistica utilizzato per inviare comunicazioni massive
(newsletter, notifiche, campagne) agli utenti di Crono2.

**Tipo di integrazione:** API REST esposta da Crono2, consumata da Ermes.

### 3.2 Caso d'uso: Newsletter massiva ai prenotati

Un Admin o Operatore deve inviare una newsletter a tutti i prenotati di un evento.

**Flusso operativo:**

1. L'operatore accede a **Ermes**
2. Crea una nuova campagna/newsletter
3. Nella selezione destinatari, sceglie **"Importa da Crono2"**
4. Seleziona l'evento di interesse
5. **Ermes chiama l'API di Crono2** per recuperare la lista dei prenotati
6. Ermes mostra l'anteprima dei destinatari
7. L'operatore compone il messaggio e invia

```
┌─────────────────┐                    ┌─────────────────┐
│     Ermes       │                    │     Crono2      │
│  (Messaggistica)│                    │   (Prenotazioni)│
└────────┬────────┘                    └────────┬────────┘
         │                                      │
         │  GET /api/v1/events/{id}/subscribers │
         │  Authorization: Bearer {token}       │
         ├─────────────────────────────────────►│
         │                                      │
         │                                      │  Query DB
         │                                      │  (prenotazioni)
         │                                      │
         │  ◄─── JSON (lista prenotati) ────────┤
         │                                      │
         │  {                                   │
         │    "event": {...},                   │
         │    "subscribers": [                  │
         │      {                               │
         │        "email": "...",               │
         │        "name": "...",                │
         │        "phone": "...",               │
         │        ...                           │
         │      }                               │
         │    ]                                 │
         │  }                                   │
         │                                      │
    [Compone e invia newsletter]                │
         │                                      │
```

### 3.3 Endpoint API esposti da Crono2

#### `GET /api/v1/events/{event_id}/subscribers`

Restituisce la lista dei prenotati per un evento specifico.

**Autenticazione:** Bearer token (API token configurato per Ermes)

**Parametri query (opzionali):**
- `status` — filtra per stato prenotazione: `confirmed`, `pending`, `cancelled`
- `session_id` — filtra per sessione specifica
- `fields` — campi da includere: `email,name,phone` (default: tutti)

**Response:**

```json
{
  "event": {
    "id": 123,
    "title": "Concerto di Primavera",
    "ente": {
      "id": 5,
      "name": "Teatro Comunale"
    }
  },
  "subscribers": [
    {
      "id": 456,
      "email": "mario.rossi@example.com",
      "name": "Mario Rossi",
      "phone": "+39 123 456 7890",
      "booking_code": "ABC123",
      "session": {
        "id": 78,
        "date": "2026-03-15",
        "time": "21:00"
      },
      "status": "confirmed",
      "created_at": "2026-02-20T10:30:00Z"
    },
    // ... altri prenotati
  ],
  "total": 150,
  "filtered": 150
}
```

**Errori:**

- `401 Unauthorized` — token mancante o non valido
- `403 Forbidden` — token valido ma senza permessi per l'evento/ente
- `404 Not Found` — evento non esistente

### 3.4 Autenticazione API per Ermes

Crono2 genera un **API token dedicato** per Ermes con i seguenti permessi:
- Lettura eventi
- Lettura prenotazioni (solo email, nome, telefono — dati non sensibili)

Il token è configurato a livello di sistema dall'Admin e ha accesso a tutti gli Enti.

**Configurazione in .env:**

```env
ERMES_API_ENABLED=true
ERMES_API_TOKEN=secret_token_generated_by_crono2
```

Il token può essere ruotato dall'Admin via comando artisan:

```bash
php artisan ermes:rotate-token
```

### 3.5 Privacy e GDPR

I dati personali (email, nome, telefono) sono trasmessi a Ermes solo per il tempo
necessario all'invio della comunicazione.

**Responsabilità:**
- **Crono2** è Data Controller per i dati delle prenotazioni
- **Ermes** è Data Processor per l'invio delle comunicazioni
- Necessario accordo di Data Processing tra le due piattaforme

**Consensi:**
- Gli utenti devono acconsentire alla ricezione di comunicazioni marketing
- Il campo `marketing_consent` nella tabella `users` o `bookings` indica il consenso
- L'API filtra automaticamente gli utenti che non hanno dato il consenso (TBD)

---

## 4. Integrazione con Smartpass (Pass Digitali)

### 4.1 Panoramica

**Smartpass** è un sistema per la generazione e gestione di pass digitali
(QR code, Apple Wallet, Google Pay) associati alle prenotazioni.

**Tipo di integrazione:** API REST bidirezionale (TBD)

### 4.2 Casi d'uso (da definire)

- Generazione automatica di un pass digitale al momento della conferma prenotazione
- Invio del pass via email o scaricamento diretto
- Validazione del pass all'ingresso dell'evento (scan QR)
- Aggiornamento del pass in caso di modifica sessione/orario

### 4.3 Flusso di integrazione (bozza)

```
┌─────────────┐          ┌─────────────┐          ┌─────────────┐
│   Crono2    │          │  Smartpass  │          │   Utente    │
└──────┬──────┘          └──────┬──────┘          └──────┬──────┘
       │                        │                        │
       │  Prenotazione          │                        │
       │  confermata            │                        │
       │                        │                        │
       │  POST /passes          │                        │
       ├───────────────────────►│                        │
       │  {booking_data}        │                        │
       │                        │  Genera pass           │
       │                        │                        │
       │  ◄─ pass_url, QR ──────┤                        │
       │                        │                        │
       │  Email + pass          │                        │
       ├────────────────────────┼───────────────────────►│
       │                        │                        │
       │                        │  Scan QR (ingresso)    │
       │                        │◄───────────────────────┤
       │                        │                        │
       │  ◄─ Webhook (validated)┤                        │
       │                        │                        │
   [Segna come "partecipato"]   │                        │
       │                        │                        │
```

> **Stato:** 🔲 Integrazione futura — specifiche da definire con fornitore Smartpass.

---

## 5. Altre Integrazioni Future

| Sistema | Funzione | Priorità |
|---------|----------|----------|
| Google Calendar | Sync eventi pubblici | Bassa |
| Stripe / PayPal | Pagamenti online | Media |
| Mailchimp | Export contatti per newsletter | Bassa |
| Zapier / Make | Automazioni generiche | Bassa |

---

## Aperto / Da decidere

- [ ] **Ermes**: filtrare automaticamente utenti che non hanno dato consenso marketing, o lasciare la responsabilità a Ermes?
- [ ] **Smartpass**: quale fornitore? Specs API disponibili?
- [ ] **Webhook da Ermes** verso Crono2 per tracciare aperture/click delle email?
- [ ] **Rate limiting** sulle API esposte (quante chiamate al minuto per Ermes)?
- [ ] **Caching**: cache delle liste prenotati per evitare query ripetute?
- [ ] **Log delle chiamate API**: tracciare tutte le richieste da sistemi esterni per audit?

---

> Documento: `08-integrazioni.md` — Versione 0.1 (brainstorming) — febbraio 2026
