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

### 2.6 Scelta implementativa: DB + Scheduler

**Implementazione prescelta**: **Database puro + Scheduler Laravel**

La tabella `prenotazioni_temporanee` nel database principale + job schedulato ogni minuto per cleanup.

**Razionale**:
- ✅ **Semplicità deployment**: nessuna dipendenza aggiuntiva (Redis)
- ✅ **Traffico adeguato**: sistema B2B con volumi moderati (non Ticketmaster)
- ✅ **Persistenza garantita**: transazioni ACID, nessuna perdita lock
- ✅ **Audit trail**: cronologia lock visibile in DB per debug
- ✅ **Time-to-market**: implementazione più veloce per MVP

**Quando considerare migrazione a Redis**:
- Eventi con > 500 posti che fanno sold-out in < 5 minuti
- Contesa su tabella `prenotazioni_temporanee` rilevata da metriche
- Necessità di scalare a migliaia di prenotazioni simultanee

**Architettura**: l'interfaccia `LockDriver` è progettata per permettere il passaggio a Redis con un semplice cambio di configurazione, senza riscrivere la logica business.

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
    → Primo in lista: stato → NOTIFICATO, scadenza_conferma_at = NOW() + finestra_conferma_ore
    → Email: "Un posto è disponibile! Hai {X} ore per confermare."
[4a] Utente conferma entro scadenza:
     → Prenotazione creata (stato CONFERMATA), posizione rimossa dalla lista
[4b] Utente non conferma entro scadenza:
     → stato → SCADUTO, sistema passa al successivo in lista
```

**Manuale** (alternativa):
- L'Operatore/Admin Ente visualizza la lista d'attesa e gestisce manualmente
  le offerte di posto (notifica e promozione).

> **Modalità**: automatico vs manuale configurabile per Ente (TBD implementazione).

### 3.4 Finestra di conferma configurabile

La durata della finestra di conferma per utenti in lista d'attesa è **configurabile a livello di sessione**:

| Campo | Tipo | Default | Note |
|---|---|---|---|
| `lista_attesa_finestra_conferma_ore` | INT | 24 | Ore disponibili per confermare dopo notifica |

**Esempi**:
- `lista_attesa_finestra_conferma_ore = 24` → l'utente ha 24 ore per confermare
- `lista_attesa_finestra_conferma_ore = 12` → finestra ridotta per eventi imminenti
- `lista_attesa_finestra_conferma_ore = 72` → finestra ampia per eventi con molto anticipo

**Calcolo scadenza**:
```
scadenza_conferma_at = data_notifica + lista_attesa_finestra_conferma_ore
```

L'Admin Ente può configurare questo valore durante la creazione/modifica della sessione, bilanciando:
- **Finestra corta**: scorrimento rapido della lista, rischio che utenti non vedano notifica in tempo
- **Finestra lunga**: più tempo per decidere, rischio che i posti rimangano bloccati più a lungo

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

## 5. Prenotazione Guest e creazione account opzionale

### 5.1 Modalità di prenotazione

L'Admin Ente può configurare **per ogni evento** se permettere prenotazioni guest tramite il campo `consenti_prenotazione_guest`:

- **`true` (default)**: utenti anonimi possono prenotare senza registrazione
- **`false`**: solo utenti registrati e autenticati possono prenotare

**Quando disabilitata**:
- Il pulsante "Prenota" sulla vetrina pubblica richiede login/registrazione
- Messaggio: "Per prenotare questo evento devi essere registrato. [Accedi] o [Registrati]"

**Casi d'uso**:
- ✅ Abilitata: eventi aperti al pubblico, conferenze gratuite, spettacoli
- ❌ Disabilitata: corsi continuativi, abbonamenti, eventi che richiedono tracciamento utente

### 5.2 Approccio ibrido: opt-in volontario per creazione account

Durante il checkout come **guest**, l'utente visualizza:

```
┌─────────────────────────────────────────────────────┐
│ ☐ Crea un account per accedere allo storico        │
│   prenotazioni e gestire le tue future prenotazioni │
│   (riceverai un'email con il link per impostare     │
│   la password)                                      │
└─────────────────────────────────────────────────────┘
```

**Checkbox opt-in volontario** (NON pre-selezionato per conformità GDPR):

- ✅ **Se selezionato**: 
  - Sistema crea account con `stato = 'attivo'` e `ruolo = 'utente'`
  - Email di conferma prenotazione include link "Imposta la tua password"
  - L'utente può impostare password e accedere all'area personale
  
- ❌ **Se NON selezionato**:
  - Prenotazione completata senza creazione account permanente
  - Email di conferma include link "Vuoi gestire le tue prenotazioni? Crea un account"
  - L'utente può attivare l'account in un secondo momento se lo desidera

### 5.3 Accesso alle prenotazioni senza account

Per utenti che hanno prenotato come guest senza creare account:

**Pagina pubblica "Visualizza le tue prenotazioni":**
```
┌─────────────────────────────────────┐
│  Inserisci i dati per visualizzare │
│  la tua prenotazione:               │
│                                     │
│  Email: [________________]          │
│  Codice prenotazione: [_______]     │
│  [Visualizza]                       │
└─────────────────────────────────────┘
```

Permette visualizzazione temporanea dei dettagli e cancellazione (se nei termini).

### 5.4 Vantaggi dell'approccio

✅ **Privacy-first**: l'utente decide se vuole o no un account permanente  
✅ **Riduce attrito**: checkout veloce per chi preferisce non registrarsi  
✅ **Fidelizzazione**: incentiva la creazione account senza forzarla  
✅ **GDPR-compliant**: consenso esplicito per conservazione dati  
✅ **Evita duplicati**: riconosce email esistenti e propone login  

---

## 6. Regole di prenotazione configurabili per sessione/evento

| Regola | Dove si configura | Default |
|---|---|---|
| Posti totali | Sessione | — (0 = illimitato) |
| Overbooking (percentuale o assoluta) | Sessione | NULL (disabilitato) |
| Finestra prenotabilità (dal / al) | Evento / Sessione | — |
| Numero massimo posti per prenotazione | Evento | 1 |
| Consenti prenotazione multi-sessione | Evento | false |
| Richiede approvazione manuale | Evento | false |
| Lista d'attesa abilitata | Sessione | false |
| Finestra conferma lista d'attesa (ore) | Sessione | 24 |
| Durata lock temporale (minuti) | Sessione | 15 |
| Soglia chiusura automatica (N posti rimasti) | Sessione | — |
| Cancellazione consentita (fino a X ore prima) | Evento | NULL (sempre) |
| Prenotazione guest abilitata | Evento | true |
| Blocco prenotazioni duplicate | (sempre attivo) | — |

> Vedi [§10 Overbooking](./06-prenotazioni.md#10-overbooking-configurabile), 
> [§11 Multi-sessione](./06-prenotazioni.md#11-prenotazione-multi-sessione), 
> [§12 Blocco duplicati](./06-prenotazioni.md#12-blocco-prenotazioni-duplicate).

---

## 7. Form di prenotazione personalizzabile

### 7.1 Campi di sistema (sempre presenti, non rimovibili)

| Campo | Tipo | Obbligatorio |
|---|---|---|
| `nome` | TEXT | ✅ |
| `cognome` | TEXT | ✅ |
| `email` | EMAIL | ✅ |
| `telefono` | PHONE | ⚙️ configurabile |
| `posti_prenotati` | NUMBER | ✅ (se sessione ha posti limitati) |
| `note` | TEXTAREA | ⚙️ on/off per evento |

### 7.2 Campi custom (configurati dall'Admin Ente per evento)

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

## 8. Cancellazione prenotazione da parte dell'utente

### 8.1 Modalità di cancellazione

L'utente può cancellare una prenotazione in **due modi**:

#### Per utenti registrati:
- **Area personale** → "Le mie prenotazioni" → [Dettaglio prenotazione] → Pulsante "Annulla prenotazione"

#### Per utenti guest:
- **Link univoco nell'email di conferma**: ogni email contiene un link sicuro tipo:
  ```
  https://crono.app/prenotazioni/{codice}/{token}/annulla
  ```
- **Pagina pubblica "Visualizza prenotazioni"**: inserendo email + codice prenotazione, l'utente accede al dettaglio e può cancellare

### 8.2 Regole di cancellazione configurabili

L'Admin Ente configura a livello di **evento** le regole di cancellazione:

| Configurazione | Campo DB | Default | Note |
|---|---|---|---|
| **Cancellazione consentita** | `evento.cancellazione_consentita_ore` | `NULL` | Numero ore prima dell'evento entro cui è permessa; NULL = sempre consentita; -1 = mai consentita |
| **Rilascio automatico posti** | (sempre attivo) | — | I posti tornano disponibili immediatamente |
| **Notifica operatore** | `ente.config.notifica_cancellazioni` | `true` | Invia email a operatori quando utente cancella |

> Il campo `cancellazione_consentita_ore` è definito nella tabella `eventi`. Vedi [05-gestione-eventi.md](./05-gestione-eventi.md).

### 8.3 Flusso di cancellazione

```
[1] Utente clicca "Annulla prenotazione"
       │
       ▼
[2] Sistema verifica:
    ├── Prenotazione esiste e non è già annullata?
    ├── `cancellazione_consentita_ore` != -1? (cancellazione abilitata)
    ├── Se `cancellazione_consentita_ore` valorizzato:
    │    └── (data_sessione - NOW) >= cancellazione_consentita_ore?
    └── (opzionale) Richiesta conferma con motivo
       │
       ▼
[3] Sistema:
    a) Aggiorna stato → ANNULLATA
    b) Valorizza `data_annullamento`, `motivo_annullamento`, `annullata_da_user_id`
    c) Incrementa `sessioni.posti_disponibili` del numero di posti liberati
    d) Decrementa `sessioni.posti_prenotati`
    e) Invia email conferma cancellazione all'utente
    f) Notifica operatori Ente (se configurato)
       │
       ▼
[4] Se lista d'attesa attiva per quella sessione:
    → Sistema verifica primo in lista
    → Invia notifica "Posto disponibile" (flusso § 3.3)
       │
       ▼
[5] Pagina conferma cancellazione
```

### 8.4 Gestione delle finestre temporali

**Esempio configurazioni**:

| Configurazione | Comportamento |
|---|---|
| `cancellazione_consentita_ore = NULL` | Cancellazione sempre consentita, anche 5 minuti prima |
| `cancellazione_consentita_ore = 24` | Cancellazione consentita fino a 24h prima della sessione |
| `cancellazione_consentita_ore = 0` | Cancellazione consentita fino all'inizio della sessione |
| `cancellazione_consentita_ore = -1` | Nessuna cancellazione da utente (solo operatore) |

**Messaggi all'utente**:
- ✅ **Entro termini**: "Confermi di voler annullare questa prenotazione? I posti torneranno disponibili."
- ❌ **Oltre termini**: "Spiacenti, non è più possibile cancellare questa prenotazione (chiusura cancellazioni: {X} ore prima). Per assistenza contatta [email ente]."
- ❌ **Cancellazione disabilitata**: "La cancellazione non è consentita per questo evento. Contatta l'organizzatore."

### 8.5 Cancellazione per prenotazioni guest

Per prenotazioni guest (senza account):
- Il link nell'email di conferma include un **token sicuro univoco** (hash)
- Il token permette l'accesso diretto alla prenotazione senza login
- La pagina mostra il dettaglio e il pulsante "Annulla" (se entro termini)
- Dopo la cancellazione, il link diventa inattivo

### 8.6 Tracciamento cancellazioni

Nel database (`prenotazioni`):

| Campo | Valorizzato |
|---|---|
| `stato` | `ANNULLATA` |
| `data_annullamento` | Timestamp cancellazione |
| `motivo_annullamento` | (opzionale) Motivazione fornita dall'utente |
| `annullata_da_user_id` | ID utente (se registrato); NULL se guest |

> Questo permette reportistica sulle cancellazioni per evento/periodo.

### 8.7 Differenza cancellazione utente vs operatore

| Aspetto | Cancellazione utente | Cancellazione operatore |
|---|---|---|
| **Chi cancella** | Utente finale | Operatore/Admin Ente |
| **Vincoli temporali** | Soggetta a `cancellazione_ore_prima` | Sempre consentita |
| **Notifiche** | Email all'utente + operatore | Email all'utente |
| **Template email** | `PRENOTAZIONE_ANNULLATA_UTENTE` | `PRENOTAZIONE_ANNULLATA_OPERATORE` |
| **Campo `annullata_da_user_id`** | ID utente (o NULL) | ID operatore |
| **Motivazione** | Opzionale | Obbligatoria (buona pratica) |

---

## 9. Notifiche prenotazione

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

## 10. Overbooking configurabile

### 10.1 Configurazione a livello di sessione

L'Admin Ente può configurare una **soglia di overbooking** per ogni sessione, permettendo prenotazioni oltre la capienza nominale:

| Campo | Tipo | Default | Note |
|---|---|---|---|
| `soglia_overbooking_percentuale` | INT nullable | NULL | Percentuale oltre `posti_totali` consentita |
| `soglia_overbooking_assoluta` | INT nullable | NULL | Numero assoluto posti extra consentiti |

**Esempi**:
- Sessione con 100 posti + `soglia_overbooking_percentuale = 10` → accetta fino a 110 prenotazioni
- Sessione con 100 posti + `soglia_overbooking_assoluta = 5` → accetta fino a 105 prenotazioni
- Se entrambi valorizzati, viene usato il **più restrittivo**
- Se NULL, overbooking disabilitato (comportamento predefinito)

**Caso d'uso**: eventi in cui l'Ente sa che una percentuale di prenotati non si presenterà (es. conferenze gratuite, eventi sportivi).

**Logica di calcolo disponibilità**:
```
posti_massimi = posti_totali + overbooking_effettivo
disponibili = posti_massimi - posti_prenotati_confermati - posti_riservati
```

**UI**: nella vetrina, quando si è in overbooking, il badge disponibilità mostra:
- Verde: posti disponibili entro capienza nominale
- Giallo: solo posti overbooking disponibili
- Rosso: esaurito anche con overbooking

---

## 11. Prenotazione multi-sessione

### 11.1 Configurazione a livello di evento

L'Admin Ente può abilitare la **prenotazione multi-sessione** per eventi che richiedono partecipazione a più date (es. corsi, workshop multi-giornata):

| Campo | Tipo | Default | Note |
|---|---|---|---|
| `consenti_multi_sessione` | BOOLEAN | false | Abilita prenotazione multipla dello stesso utente |

**Quando abilitato**:
- Un utente può prenotare più sessioni dello stesso evento in un'unica transazione
- Il form di prenotazione mostra checkbox di selezione per tutte le sessioni disponibili
- Viene creata una prenotazione separata per ogni sessione, con codici distinti
- (Opzionale) Possibilità di "pacchetto" con prezzo scontato per N sessioni

**Quando disabilitato** (default):
- Un utente può prenotare solo una sessione per evento
- Se tenta di prenotare una seconda sessione, il sistema blocca e propone di cancellare la prima

**Esempi**:
- ✅ Corso di yoga 4 incontri → `consenti_multi_sessione = true`, utente prenota tutte le date
- ❌ Spettacolo teatrale 3 repliche → `consenti_multi_sessione = false`, utente sceglie una sola data

---

## 12. Blocco prenotazioni duplicate

### 12.1 Problema in Crono1

In Crono1, un utente può prenotare più volte la stessa sessione dello stesso evento, creando duplicati non intenzionali.

### 12.2 Soluzione Crono2

**Validazione server-side** prima di confermare una prenotazione:

```sql
SELECT COUNT(*) FROM prenotazioni
WHERE sessione_id = ? 
  AND (user_id = ? OR email = ?)
  AND stato IN ('CONFERMATA', 'DA_CONFERMARE', 'RISERVATA')
```

Se il conteggio > 0 e `evento.consenti_multi_sessione = false`:
- **Blocco**: "Hai già una prenotazione per questa sessione. Non è possibile prenotare più volte."
- **Proposta**: "Vuoi cancellare la prenotazione esistente e crearne una nuova?"

**Identificazione utente**:
- **Utenti registrati**: controllo su `user_id`
- **Guest**: controllo su `email` (case-insensitive)

**Edge case gestiti**:
- Prenotazione in stato `ANNULLATA` o `SCADUTO`: non blocca (utente può prenotare di nuovo)
- Sessioni diverse dello stesso evento: consentite solo se `consenti_multi_sessione = true`
- Lock temporale attivo (`RISERVATA`): impedisce doppia riserva simultanea

---

## 13. Codice prenotazione e identificazione

### 13.1 Generazione automatica

Ogni prenotazione riceve sempre un **codice univoco alfanumerico** generato automaticamente:

**Formato proposto**: `CRN-{ANNO}-{SEQUENZA}`

Esempi:
```
CRN-2026-00001
CRN-2026-00042
CRN-2026-12345
```

**Caratteristiche**:
- **Univoco globale** (constraint UNIQUE sul DB)
- **Human-readable**: facile da comunicare via telefono
- **Sequenziale per anno**: reset contatore a gennaio
- **Prefisso fisso**: riconoscibilità brand

**Utilizzo**:
- Identificazione prenotazione in email e comunicazioni
- Ricerca prenotazione nella pagina pubblica (email + codice)
- Link diretto: `https://crono.app/prenotazioni/{codice}`
- Base per generazione QR code (futura integrazione)

### 13.2 QR Code (TBD - fase futura)

Il codice prenotazione può essere usato per generare un QR code per check-in rapido:
- Formato: URL `https://crono.app/checkin/{codice}` o JSON con metadati
- Generazione: on-demand o allegato automatico nell'email
- Scansione: app operatore o pagina web dedicata
- Validazione: verifica stato prenotazione + match sessione

> Decisione implementativa rimandata a fase successiva. Il campo `codice` è già presente per supportarlo.

---

## Aperto / Da decidere

- [ ] **Lista d'attesa**: modalità automatica vs manuale configurabile per Ente?
- [ ] **Check-in**: funzione per spuntare i presenti all'evento (da pannello operatore)?
- [ ] **Gestione pagamenti**: integrazione Stripe/PayPal come fase 2? Il campo `costo_totale` va esposto già nel form?
- [ ] **Risposta form**: tabella `risposte_form` normalizzata o JSON `risposte` nella prenotazione?

---

> Documento: `06-prenotazioni.md` — Versione 0.1 (brainstorming) — febbraio 2026
