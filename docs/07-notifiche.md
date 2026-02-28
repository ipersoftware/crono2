# 07 — Notifiche

## Scopo

Descrivere il sistema di notifiche email di Crono2: quali email vengono inviate,
quando, a chi, tramite quali template e come l'Admin Ente li personalizza.

---

## 1. Principi

- Ogni Ente ha i propri **template email** (tabella `mail_templates`, scopata per `ente_id`).
- Se un Ente non ha personalizzato un template, il sistema usa il **template di sistema**
  (record con `ente_id = NULL`). La risoluzione è: Ente → Sistema.
- Le email sono inviate tramite la **coda Laravel** (Queue) per non bloccare il flusso
  di prenotazione.
- Ogni invio è tracciato in `notifiche_log` con stato e eventuali errori.
- I template supportano **placeholder** sostituiti a runtime con i dati reali.

---

## 2. Catalogo notifiche

### 2.1 Notifiche legate alla Prenotazione

| Template | Trigger | Destinatario | Auto |
|---|---|---|:---:|
| `PRENOTAZIONE_CONFERMATA` | Prenotazione creata con stato `CONFERMATA` | Utente | ✅ |
| `PRENOTAZIONE_DA_CONFERMARE` | Prenotazione creata con stato `DA_CONFERMARE` | Utente | ✅ |
| `PRENOTAZIONE_APPROVATA` | Operatore approva una prenotazione in attesa | Utente | ✅ |
| `PRENOTAZIONE_ANNULLATA_UTENTE` | Utente cancella la propria prenotazione | Utente | ✅ |
| `PRENOTAZIONE_ANNULLATA_OPERATORE` | Operatore/Admin annulla una prenotazione | Utente | ✅ |
| `PRENOTAZIONE_NOTIFICA_STAFF` | Ogni nuova prenotazione confermata | Operatori Ente | ✅ (configurabile) |

### 2.2 Notifiche legate all'Evento

| Template | Trigger | Destinatario | Auto |
|---|---|---|:---:|
| `EVENTO_ANNULLATO` | Evento o sessione portata in stato ANNULLATO | Tutti i prenotati della sessione | ✅ |
| `REMINDER_EVENTO` | X giorni/ore prima dell'evento | Utente prenotato | ✅ (scheduler) |

### 2.3 Notifiche legate alla Lista d'Attesa

| Template | Trigger | Destinatario | Auto |
|---|---|---|:---:|
| `LISTA_ATTESA_ISCRIZIONE` | Utente aggiunto alla lista d'attesa | Utente | ✅ |
| `LISTA_ATTESA_POSTO_DISPONIBILE` | Posto liberato, utente scalato in lista | Utente in lista | ✅ |
| `LISTA_ATTESA_SCADENZA` | N ore prima della scadenza di conferma | Utente in lista | ✅ (scheduler) |

### 2.4 Notifiche di sistema

| Template | Trigger | Destinatario | Auto |
|---|---|---|:---:|
| `REGISTRAZIONE_CONFERMATA` | Nuovo account creato | Nuovo utente | ✅ |
| `RESET_PASSWORD` | Richiesta reset password | Utente | ✅ |

---

## 3. Placeholder disponibili nei template

I template supportano variabili nel formato `{{placeholder}}` sostituite a runtime:

### Utente
| Placeholder | Valore |
|---|---|
| `{{nome_utente}}` | Nome del prenotante |
| `{{cognome_utente}}` | Cognome del prenotante |
| `{{email_utente}}` | Email del prenotante |

### Evento e Sessione
| Placeholder | Valore |
|---|---|
| `{{titolo_evento}}` | Titolo dell'evento |
| `{{data_sessione}}` | Data della sessione (es. "15 marzo 2026") |
| `{{ora_inizio}}` | Ora inizio (es. "21:00") |
| `{{ora_fine}}` | Ora fine (es. "23:00") |
| `{{luogo_evento}}` | Nome del luogo |
| `{{indirizzo_luogo}}` | Indirizzo completo del luogo |
| `{{descrizione_sessione}}` | Descrizione della specifica sessione |

### Prenotazione
| Placeholder | Valore |
|---|---|
| `{{codice_prenotazione}}` | Codice univoco (es. CRN-2026-00042) |
| `{{posti_prenotati}}` | Numero totale posti |
| `{{dettaglio_posti}}` | Lista tipologie: "2x Intero (15€), 1x Ridotto (8€)" |
| `{{costo_totale}}` | Importo totale (es. "38,00 €") |
| `{{note_prenotazione}}` | Note inserite dall'utente |
| `{{link_prenotazione}}` | URL pannello prenotazione (per operatore) |
| `{{link_annullamento}}` | URL per auto-cancellazione (token univoco) |
| `{{motivo_annullamento}}` | Motivo annullamento (per email annullamento) |

### Ente
| Placeholder | Valore |
|---|---|
| `{{nome_ente}}` | Nome dell'ente |
| `{{email_ente}}` | Email pubblica dell'ente |
| `{{telefono_ente}}` | Telefono dell'ente |
| `{{link_vetrina}}` | URL vetrina pubblica dell'ente |

---

## 4. Personalizzazione template per Ente

L'Admin Ente accede dalla sezione **"Comunicazioni → Template email"** del pannello.

### 4.1 Vista lista template

- Lista di tutti i tipi di template
- Per ogni tipo: indicatore "Personalizzato" (template ente) o "Default" (sistema)
- Pulsante "Personalizza" / "Modifica" / "Ripristina default"

### 4.2 Editor template

- Campo **Oggetto** (con supporto placeholder)
- Editor **corpo email** (HTML con supporto placeholder — TBD: WYSIWYG o codice?)
- Pulsante **Anteprima** con dati fittizi
- Pulsante **Invia test** (invia a email dell'Admin Ente)
- **Ripristina default** (elimina il template personalizzato, torna a quello di sistema)

---

## 5. Configurazione invio notifiche Staff

La notifica `PRENOTAZIONE_NOTIFICA_STAFF` è opzionale e configurabile:

| Opzione | Comportamento |
|---|---|
| Disabilitata | Nessuna email allo staff per ogni prenotazione |
| Abilitata — tutti gli operatori | Email a tutti gli utenti con `ente_id = ente.id` e ruolo `operatore_ente` / `admin_ente` |
| Abilitata — email specifica | Email inviata a uno o più indirizzi configurati manualmente |

---

## 6. Reminder eventi

Il reminder è un'email di promemoria inviata automaticamente X tempo prima
della sessione prenotata.

**Configurazione per evento:**
- Attivo / Non attivo
- Anticipo: es. "1 giorno prima", "2 ore prima" (configurabile)

**Meccanismo**: scheduler Laravel (`schedule:run`) esegue ogni ora un job
`InviaReminderEventi` che cerca le prenotazioni `CONFERMATE` con sessione
a distanza `[anticipo, anticipo + 1h]` dalla data corrente e non ancora notificate.

---

## 7. Coda e affidabilità

- Tutte le email sono inviate via **Laravel Queue** (driver `database` di default,
  espandibile a Redis/Horizon).
- In caso di errore SMTP, il job viene ritentato N volte (configurabile in `.env`).
- Ogni invio è loggato in `notifiche_log` con stato `INVIATA` o `ERRORE`.
- L'Admin Ente può consultare il log di invio dal pannello (TBD: sezione log comunicazioni).
- Il driver email è configurabile: SMTP nativo, Mailgun, SES, Postmark.

---

## 8. Flusso tecnico invio email

```
[Evento applicativo]  es. PrenotazioneConfermata
       │
       ▼
[Job: InviaNotifica]  → accodato su Laravel Queue
       │
       ▼
[Risolvi template]
  1. cerca mail_templates WHERE ente_id = X AND tipo = Y → usa se trovato
  2. fallback: mail_templates WHERE ente_id IS NULL AND tipo = Y
       │
       ▼
[Sostituisci placeholder]
       │
       ▼
[Invia email via Mailable Laravel]
       │
       ├─► SUCCESS → notifiche_log.stato = INVIATA
       └─► ERROR   → retry / notifiche_log.stato = ERRORE
```

---

## Aperto / Da decidere

- [ ] **Editor template**: WYSIWYG (es. TipTap, CKEditor) o semplice textarea con HTML?
- [ ] **Driver email produzione**: SMTP, Mailgun, SES, Postmark? (configurabile per ente o globale?)
- [ ] **Notifica staff per ogni prenotazione**: rischio di spam se evento molto frequentato. Opzione "digest giornaliero" invece di email per ogni prenotazione?
- [ ] **Reminder**: configurazione a livello di evento (sì/no, anticipo) o anche a livello di sessione?
- [ ] **Email multilingua**: template in più lingue? (per ora out of scope)
- [ ] **SMS**: notifiche via SMS oltre alle email? (terza fase)
- [ ] **Log comunicazioni**: sezione nel pannello Admin Ente per visualizzare lo storico email inviate?
- [ ] **`EVENTO_ANNULLATO`**: quante prenotazioni possono esserci? invio in batch per evitare blocchi?

---

> Documento: `07-notifiche.md` — Versione 0.1 (brainstorming) — febbraio 2026
