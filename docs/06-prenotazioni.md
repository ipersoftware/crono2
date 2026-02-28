# 06 — Prenotazioni

## Scopo

Descrivere il flusso completo di prenotazione, gli stati della prenotazione,
le regole di disponibilità, il **locking temporale dei posti** e la gestione
della **lista d'attesa**.

---

## 1. Flusso di prenotazione (utente finale)

### 1.1 Happy path (posti disponibili, utente registrato)

```
[1] Utente apre pagina evento
       │
       ▼
[2] Sceglie la sessione desiderata
    └── sistema verifica disponibilità in tempo reale
       │
       ▼
[3] Sceglie il numero di posti
    └── sistema acquisisce il LOCK TEMPORALE
        (crea record in prenotazioni_temporanee, scadenza = ora + TTL)
        └── posti_riservati++ su sessione
       │
       ▼
[4] Compila il form di prenotazione
    ├── campi di sistema: nome, cognome, email, telefono
    └── campi custom configurati dall'Ente per questo evento
       │
       ▼
[5] Conferma / Invia
    └── sistema:
        a) verifica che il lock sia ancora valido (non scaduto)
        b) crea la prenotazione (stato: CONFERMATA o DA_CONFERMARE)
        c) decrementa posti_disponibili
        d) elimina record prenotazioni_temporanee
        e) invia email di conferma
       │
       ▼
[6] Pagina di riepilogo con codice prenotazione
```

### 1.2 Lock scaduto durante la compilazione

```
[4] Utente compila il form ... prende troppo tempo ...
       │
       ▼ (il lock è scaduto: scheduler/cleanup ha rilasciato i posti)
[5] Utente prova a confermare
       │
       ▼
[ERRORE] "Spiacenti, i posti selezionati non sono più disponibili."
    └── Opzioni proposte:
        a) Torna alla selezione sessione e riprova (se ci sono ancora posti)
        b) Iscriviti alla lista d'attesa (se abilitata)
```

### 1.3 Posti esauriti alla selezione

```
[2] Utente sceglie sessione esaurita
       │
       ▼
    [Se lista d'attesa DISABILITATA]
        → Messaggio: "Sessione al completo"

    [Se lista d'attesa ABILITATA]
        → Proposta: "Vuoi iscriverti alla lista d'attesa?"
        → Flusso lista d'attesa (§ 3)
```

---

## 2. Locking temporale dei posti

### 2.1 Problema

In Crono1, tra il momento in cui l'utente **sceglie i posti** e quello in cui
**conferma il form**, intercorre un gap temporale non gestito.
Risultato: due utenti possono prenotare contemporaneamente gli ultimi posti,
portando la disponibilità in negativo o causando overbooking.

### 2.2 Soluzione proposta: lock ottimistico con TTL

Quando l'utente sceglie il numero di posti e procede al form:

1. Il sistema crea un record in `prenotazioni_temporanee`:
   ```
   sessione_id  = X
   posti        = N
   token        = <uuid univoco per questa sessione browser>
   scadenza_at  = NOW() + durata_lock_minuti (default: 15 min, configurabile per sessione)
   ```
2. Il contatore `sessioni.posti_riservati` viene incrementato di N.
3. La **disponibilità visibile** all'esterno è: `posti_disponibili - posti_riservati`.
4. A ogni richiesta di verifica disponibilità, il sistema esegue prima una
   **pulizia lazy** dei lock scaduti (o lo fa lo scheduler ogni minuto).

### 2.3 Pulizia lock scaduti

Due meccanismi complementari:

| Meccanismo | Quando | Come |
|---|---|---|
| **Lazy cleanup** | Ad ogni check disponibilità | Query: `DELETE FROM prenotazioni_temporanee WHERE scadenza_at < NOW()` → aggiorna `posti_riservati` |
| **Scheduler Laravel** | Ogni minuto (`schedule:run`) | Job `RilasciaLockScaduti` |

### 2.4 Token lock e continuità sessione

Il `token` del lock è mantenuto lato frontend (localStorage o cookie di sessione).
Se l'utente chiude il browser e riapre, il lock viene rilevato come scaduto.
Se l'utente riapre entro la scadenza, il token permette di riprendere dal form.

### 2.5 Configurazione per sessione

Il campo `durata_lock_minuti` è configurabile a livello di sessione (default 15).
L'Admin Ente può aumentarlo per eventi con form lunghi o diminuirlo per eventi
con alta concorrenza.

---

## 3. Lista d'attesa

### 3.1 Configurazione

La lista d'attesa è **configurabile per sessione** tramite il campo `attiva_lista_attesa`
(boolean). L'Admin Ente decide se abilitarla al momento della creazione o modifica
della sessione.

### 3.2 Flusso iscrizione lista d'attesa

```
[1] Utente tenta di prenotare sessione esaurita
[2] Sistema propone lista d'attesa
[3] Utente compila form (nome, cognome, email, posti_richiesti)
[4] Record creato in lista_attesa (stato: IN_ATTESA, posizione assegnata)
[5] Email di conferma iscrizione alla lista d'attesa
```

### 3.3 Flusso scorrimento lista

**Automatico** (proposto):
```
[1] Una prenotazione viene annullata → posti_disponibili++
[2] Sistema verifica lista_attesa per quella sessione
[3] Se ci sono iscritti e i posti sono sufficienti:
    → Primo in lista: stato → NOTIFICATO, scadenza_conferma_at = NOW() + X ore
    → Email: "Un posto è disponibile! Hai X ore per confermare."
[4a] Utente conferma entro scadenza:
     → Prenotazione creata (stato CONFERMATA), posizione rimossa dalla lista
[4b] Utente non conferma entro scadenza:
     → stato → SCADUTO, sistema passa al successivo in lista
```

**Manuale** (alternativa):
- L'Operatore/Admin Ente visualizza la lista d'attesa e gestisce manualmente
  le offerte di posto (notifica e promozione).

> **Da decidere**: automatico vs manuale, o entrambi configurabili per Ente?

---

## 4. Stati della prenotazione

```
                    ┌──────────────┐
                    │  RISERVATA   │ ← lock temporale attivo
                    └──────┬───────┘
                    scaduta│        │confermata
                           ▼        ▼
                    [posti rilasciati] ┌──────────────────┐
                                      │  CONFERMATA      │
                                      └────────┬─────────┘
                                               │ annullamento
                                    ┌──────────▼─────────┐
                                    │    ANNULLATA        │
                                    └─────────────────────┘

                    ┌──────────────────────┐
                    │  DA_CONFERMARE       │ ← se l'Ente richiede approvazione
                    └──────┬───────────────┘
                  approvata│        │rifiutata
                           ▼        ▼
                    CONFERMATA   ANNULLATA

                    ┌──────────────────────┐
                    │  IN_LISTA_ATTESA     │
                    └──────┬───────────────┘
                  posto avail│
                             ▼
                    NOTIFICATO → CONFERMATA | SCADUTO
```

| Stato | Descrizione |
|---|---|
| `RISERVATA` | Lock temporale attivo — posti bloccati, form in compilazione |
| `DA_CONFERMARE` | Prenotazione inviata, in attesa di approvazione manuale dall'Ente |
| `CONFERMATA` | Prenotazione valida e confermata |
| `ANNULLATA` | Cancellata da utente o operatore |
| `IN_LISTA_ATTESA` | In coda, in attesa di disponibilità |
| `NOTIFICATO` | Utente in lista d'attesa avvisato che un posto è disponibile |
| `SCADUTO` | Notifica lista d'attesa non confermata entro scadenza |

---

## 5. Regole di prenotazione configurabili per sessione/evento

| Regola | Dove si configura | Default |
|---|---|---|
| Posti totali | Sessione | — (0 = illimitato) |
| Finestra prenotabilità (dal / al) | Evento / Sessione | — |
| Numero massimo posti per prenotazione | Evento | 1 |
| Richiede approvazione manuale | Evento | false |
| Lista d'attesa abilitata | Sessione | false |
| Durata lock temporale (minuti) | Sessione | 15 |
| Soglia chiusura automatica (N posti rimasti) | Sessione | — |
| Cancellazione consentita fino a X ore prima | Evento | — |
| Form guest abilitato | Evento / Ente | TBD |

---

## 6. Form di prenotazione personalizzabile

### 6.1 Campi di sistema (sempre presenti, non rimovibili)

| Campo | Tipo | Obbligatorio |
|---|---|---|
| `nome` | TEXT | ✅ |
| `cognome` | TEXT | ✅ |
| `email` | EMAIL | ✅ |
| `telefono` | PHONE | ⚙️ configurabile |
| `posti_prenotati` | NUMBER | ✅ (se sessione ha posti limitati) |
| `note` | TEXTAREA | ⚙️ on/off per evento |

### 6.2 Campi custom (configurati dall'Admin Ente per evento)

L'Admin Ente accede all'editor del form e aggiunge campi:

| Tipo | Descrizione |
|---|---|
| `TEXT` | Campo testo libero |
| `TEXTAREA` | Testo lungo |
| `SELECT` | Menu a tendina (lista valori configurabile) |
| `RADIO` | Scelta singola |
| `CHECKBOX` | Scelta multipla |
| `DATE` | Data |
| `NUMBER` | Numero (con min/max opzionali) |
| `EMAIL` | Indirizzo email aggiuntivo |
| `PHONE` | Numero di telefono aggiuntivo |

Per ogni campo si configura:
- **Etichetta** (testo mostrato all'utente)
- **Placeholder**
- **Obbligatorio** (sì/no)
- **Ordine** (drag & drop nell'editor)
- **Opzioni** (per SELECT/RADIO/CHECKBOX)
- **Validazione** (min, max, pattern regex)

> Dati memorizzati nella tabella `campi_form` (definitione) e
> `risposte_form` o JSON `risposte` nella prenotazione (TBD).

---

## 7. Notifiche prenotazione

| Evento | Destinatario | Template |
|---|---|---|
| Prenotazione confermata | Utente | `PRENOTAZIONE_CONFERMATA` |
| Prenotazione annullata (da utente) | Utente + Operatore | `PRENOTAZIONE_ANNULLATA` |
| Prenotazione annullata (da operatore) | Utente | `PRENOTAZIONE_ANNULLATA_OPERATORE` |
| Nuova prenotazione (notifica staff) | Operatori Ente | `PRENOTAZIONE_NOTIFICA` |
| Posto disponibile (lista attesa) | Utente in lista | `LISTA_ATTESA_POSTO_DISPONIBILE` |
| Scadenza conferma lista attesa | Utente in lista | `LISTA_ATTESA_SCADENZA` |
| Reminder evento | Utente | `REMINDER_EVENTO` (TBD) |

> Dettaglio nel documento [07-notifiche.md](./07-notifiche.md).

---

## Aperto / Da decidere

- [ ] **Locking**: DB puro + scheduler vs Redis? Redis garantisce TTL atomico ma non è sempre disponibile.
- [ ] **Overbooking tollerato**: l'Ente può impostare una soglia di overbooking (es. +10%)?
- [ ] **Prenotazione multi-sessione**: un utente può prenotare più sessioni dello stesso evento? (es. corso multi-giornata)
- [ ] **Lista d'attesa**: automatica con finestra di conferma o gestione manuale dall'operatore?
- [ ] **Durata finestra di conferma** lista d'attesa: fissa (es. 24h) o configurabile per Ente?
- [ ] **Cancellazione**: regola "entro X ore prima" configurabile per evento? Chi può cancellare dopo la scadenza?
- [ ] **Codice prenotazione**: formato (es. `CRN-2026-00042`)? QR code basato sul codice per check-in?
- [ ] **Check-in**: funzione per spuntare i presenti all'evento (da pannello operatore)?
- [ ] **Gestione pagamenti**: integrazione Stripe/PayPal come fase 2? Il campo `costo_totale` va esposto già nel form?
- [ ] **Risposta form**: tabella `risposte_form` normalizzata o JSON `risposte` nella prenotazione?

---

> Documento: `06-prenotazioni.md` — Versione 0.1 (brainstorming) — febbraio 2026
