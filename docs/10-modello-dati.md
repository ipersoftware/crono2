# 10 — Modello Dati

## Scopo

Analisi dello schema di database di **Crono1** (produzione) come base di partenza
per la progettazione del modello dati di **Crono2**.

---

## 1. Analisi schema Crono1

Crono1 è un sistema multi-tenant: ogni entità principale è agganciata a un `customerID`
(che in Crono2 diventa `ente_id`, e la tabella `customers` diventa `enti`).

> **Nota**: l'analisi descrive lo schema del DB di produzione. Lo stack tecnologico di Crono2
> è **Laravel 11 + Vue.js 3**, indipendentemente da quello usato in Crono1.

### 1.1 Mappa delle tabelle

| Tabella | Ruolo | Righe (prod) |
|---|---|---|
| `customers` → **`enti`** | Ente — tenant principale | ~4 |
| `users` | Utenti del sistema | ~23 |
| `auth_identities` | Credenziali degli utenti (email/password) | ~3 |
| `auth_groups_users` | Ruoli degli utenti (gruppi) | ~4 |
| `auth_logins` | Log accessi | ~129 |
| `auth_remember_tokens` | Token "ricordami" | — |
| `auth_permissions_users` | Permessi individuali | 0 |
| `eventi` | Anagrafica eventi | ~55 |
| `eventi_date` | Sessioni/date di un evento | ~50 |
| `prenotazioni` | Prenotazioni effettuate | ~119 |
| `serie` | Rassegne — raggruppamento di eventi | ~7 |
| `luoghi` | Luoghi/sedi dove si svolgono gli eventi | ~11 |
| `persone` | Relatori, artisti, figure degli eventi | ~3 |
| `sponsor` | Sponsor/partner degli eventi | ~6 |
| `moduli` | Definizione form personalizzati | ~2 |
| `model_templates` | Template/schema per le entità (JSON-driven) | ~45 |
| `model_tags` | Vocabolari tag per customer | ~27 |
| `mailtemplate` | Template email per notifiche | ~5 |
| `ana_anagrafica` | Rubrica contatti (PF/PG) | ~7 |
| `contatti` | Richieste di contatto dal form pubblico | ~29 |
| `com_liste` | Liste destinatari per newsletter | ~9 |
| `com_newsletter` | Campagne newsletter | ~8 |
| `documents` | Documenti CKEditor | ~39 |
| `settings` | Configurazioni di sistema | 0 |

---

### 1.2 Dettaglio tabelle core

#### `customers` → **`enti`** in Crono2

```sql
stato            ENUM(ATTIVO, SOSPESO, CANCELLATO)
name             VARCHAR             -- ragione sociale
hashTag          VARCHAR             -- slug univoco per URL pubblico
classificazione  VARCHAR
descrizione      VARCHAR
maps             JSON                -- coordinate geografiche
layout           LONGTEXT            -- contenuto CKEditor (HTML)
tags             JSON
licenza          ENUM(GRATUITA, PREMIUM_TEMPO, ENTERPRISE_TEMPO)
linkPubblico     VARCHAR             -- URL pagina vetrina
fatturazione     JSON
config           JSON                -- configurazioni specifiche per tenant
attivoDal / attivoAl  DATETIME
ckEmail / ckPassword / ckLicenceKey  -- integrazione CKEditor licenziata
quotaMailGiorno / quotaMailMese      -- limiti invio email
```

> Il `layout` CKEditor è la **pagina vetrina** in Crono1.

---

#### `users` — Utenti del sistema

```sql
customerID       INT                 -- Ente principale (→ ente_id in Crono2)
customerIDList   JSON                -- lista enti (→ tabella pivot in Crono2)
username         VARCHAR(30)
status           VARCHAR
active           TINYINT
privacyOK        TINYINT
newsletterOK     TINYINT
last_active      DATETIME
```

> **Note**: email e password in `auth_identities` (separato). Nome/cognome assenti — grave lacuna.
> La `customerIDList` JSON sarà normalizzata in Crono2 con una tabella pivot `ente_user`.

---

#### `eventi` — Anagrafica Evento

```sql
customerID         INT               -- Ente proprietario (→ ente_id in Crono2)
idRassegna         INT               -- FK → serie (raggruppamento)
stato              ENUM(IN_ATTESA, PUBBLICATO, SOSPESO, ANNULLATO)
ordinamento        INT
titolo             VARCHAR
descrizione        TEXT
hashTag            VARCHAR           -- slug URL
linkPubblico       VARCHAR
pubblico           TINYINT           -- visibile pubblicamente
mostraDisponibilita TINYINT          -- mostrare posti rimasti?
visibileDal / visibileAl    DATETIME -- finestra visibilità
prenotabileDal / prenotabileAl DATETIME -- finestra prenotabilità
struttura / indirizzo / citta / cap / telefono  -- location inline
tags               JSON
attributi          JSON              -- attributi custom (flessibili)
layout             LONGTEXT          -- contenuto CKEditor
abilitaNote        TINYINT           -- campo note libere in prenotazione
noteEtichetta      VARCHAR           -- etichetta del campo note
```

---

#### `eventi_date` — Sessioni / Date dell'Evento

```sql
idEvento           INT               -- FK → eventi
dataEvento         DATETIME          -- data principale
dataInizio / dataFine  DATETIME      -- orario
sessione           VARCHAR           -- etichetta (es. "Mattina", "Turno A")
descrizione        TEXT
luoghi             JSON              -- luoghi associati alla sessione
persone            JSON              -- relatori/artisti della sessione
attivaQuantitaPostiGlobale  TINYINT  -- abilita gestione posti?
rendiNonDisponibile         TINYINT  -- forza chiusura
rendiNonDisponibileNumeroPrenotazioni INT -- soglia auto-chiusura
postiTotali / postiDisponibili  INT  -- contatori posti
posti              JSON              -- mappa posti (es. platea numerata)
```

> Un **Evento** ha N **Date/Sessioni**. I posti sono gestiti per sessione, non per evento globale.

---

#### `prenotazioni` — La Prenotazione

```sql
customerID         INT
stato              ENUM(CONFERMATA, ANNULLATA, DA_CONFERMARE)
codice             VARCHAR           -- codice univoco prenotazione
dataPrenotazione   DATETIME
-- Dati prenotante (denormalizzati, no FK a users):
organizzazione     VARCHAR
cognome / nome     VARCHAR
indirizzo / telefono / email / pec  VARCHAR
-- Riferimenti evento (denormalizzati):
eventoID           INT               -- id evento
eventoTitolo       VARCHAR           -- titolo copiato al momento
eventoHashTag      VARCHAR
eventoDataID       INT               -- id della sessione
dataEvento         DATETIME          -- data copiata al momento
-- Dettagli prenotazione:
postiPrenotati     INT
note               TEXT
campiDinamici      JSON              -- risposte a form personalizzato
posti              JSON              -- dettaglio posti scelti
luoghi             JSON              -- luoghi snapshot
persone            JSON              -- persone snapshot
costoTotale        DECIMAL(10,2)
link               VARCHAR           -- link gestione prenotazione (token URL)
-- Annullamento:
dataAnnullamento   DATETIME
motivoAnnullamento VARCHAR
opAnnullamento     VARCHAR           -- chi ha annullato
```

> **Osservazioni chiave:**
> - La prenotazione **non richiede un account utente** — chiunque può prenotare.
> - I dati evento sono **denormalizzati** per preservare lo storico.
> - `campiDinamici` JSON supporta form personalizzati per raccolta dati aggiuntivi.
> - `costoTotale` è presente ma non integrato con pagamenti.

---

#### `serie` — Rassegne / Cicli di eventi

```sql
customerID         INT               -- → ente_id in Crono2
stato              ENUM(IN_ATTESA, PUBBLICATO, SOSPESO, ANNULLATO)
titolo / descrizione
hashTag / classificazione
pubblico / sospeso / motivoSospensione
visibileDal / visibileAl  DATETIME
linkPubblico       VARCHAR
tags               JSON
layout             LONGTEXT          -- contenuto CKEditor
```

> Una **Serie** raggruppa più **Eventi** correlati (es. un festival, un corso, una stagione teatrale).

---

#### `mailtemplate` — Template Email

```sql
template  ENUM(
  PRENOTAZIONE_CONFERMATA,
  PRENOTAZIONE_ANNULLATA,
  PRENOTAZIONE_NOTIFICA,       -- notifica all'operatore
  RICHIESTA_CONTATTO,
  RICHIESTA_CONTATTO_NOTIFICA,
  REGISTRAZIONE_CONFERMATA
)
oggetto   VARCHAR
testo     TEXT                  -- corpo email (plain o HTML)
sistema   TINYINT               -- template di default del sistema
```

---

### 1.3 Relazioni principali Crono1

```
customers (1) ──────────────────────── (N) eventi
customers (1) ──────────────────────── (N) serie
customers (1) ──────────────────────── (N) luoghi
customers (1) ──────────────────────── (N) persone
customers (1) ──────────────────────── (N) sponsor
customers (1) ──────────────────────── (N) users

serie     (1) ──────────────────────── (N) eventi      [idRassegna]
eventi    (1) ──────────────────────── (N) eventi_date
eventi_date (1) ────────────────────── (N) prenotazioni [eventoDataID]

prenotazioni  → dati prenotante inline (no FK a users)
eventi_date   → luoghi, persone come JSON snapshot
prenotazioni  → luoghi, persone, posti come JSON snapshot
```

---

## 2. Osservazioni e Problemi di Crono1

| # | Problema | Impatto | Soluzione Crono2 |
|---|---|---|---|
| 1 | Schema fortemente **JSON-dipendente** (attributi, campiDinamici, posti, luoghi, persone) | Impossibile filtrare/ricercare/reportare | Normalizzare le relazioni chiave; JSON solo per dati extra non ricercabili |
| 2 | Dati evento **denormalizzati** nella prenotazione | Buono per storico, ma snapshot non strutturato | Mantenere snapshot ma come JSON tipizzato (`evento_snapshot`) |
| 3 | Nessun **FK esplicito** tra `prenotazioni` e `eventi_date` | Integrità dati affidata al codice applicativo | FK obbligatorie su tutte le relazioni critiche |
| 4 | `users` senza anagrafica — nome/cognome assenti | Profilo utente incompleto | `users` con `nome`, `cognome`, `telefono` nativi |
| 5 | **Prenotazione anonima** — nessun legame con account | Impossibile "le mie prenotazioni" | `user_id` nullable: supporto sia guest che registrato |
| 6 | `costoTotale` presente ma **pagamenti mai implementati** | Campo inutilizzato, nessun flusso di cassa | Progettare il campo fin dall'inizio; integrazioni pagamento come fase 2 |
| 7 | **Form prenotazione non personalizzabile** — `campiDinamici` JSON flat | Non si può configurare quali dati raccogliere per evento | Entità `campi_form` collegata all'evento: tipo, etichetta, obbligatorio, ordine |
| 8 | Nessun **locking temporale** dei posti durante la compilazione del form | Posti esauriti dal momento della scelta alla conferma | Sistema di **prenotazione temporanea** con scadenza (TBD: DB lock o Redis) |
| 9 | Nessuna **lista d'attesa** | Utente perde il posto senza alternativa | Tabella `lista_attesa` per sessione, configurabile per evento |
| 10 | `customerIDList` JSON in `users` | Utente multi-ente non gestibile in modo pulito | `ente_id` FK diretta in `users`; ruolo globale gestito da Keycloak |
| 11 | `model_templates` JSON-driven — schemi entità nel DB | Molto flessibile ma complessissimo da manutenere | Attributi extra come JSON semplice; template solo per form personalizzati |
| 12 | Nessuna **area utente** ("le mie prenotazioni") | L'utente non può consultare o cancellare le sue prenotazioni | SPA con sezione /profilo/prenotazioni |

---

## 3. Proposta Modello Dati Crono2

### 3.1 Principi

- **FK esplicite** per tutte le relazioni critiche.
- `customers` → **`enti`**, `customerID` → **`ente_id`** in tutte le FK.
- **Multi-tenancy nativa**: ogni entità dati porta `ente_id` per isolare i dati tra Enti.
- **JSON limitato** ai soli dati extra non ricercabili (es. snapshot evento, attributi liberi).
- **Prenotazione con `user_id` nullable**: supporto sia utenti registrati che guest.
- **Form prenotazione configurabile** per evento tramite entità `campi_form`.
- **Locking temporale** dei posti: prenotazione in stato `RISERVATA` con scadenza TTL.
- **Lista d'attesa** configurabile per sessione.
- **Tag normalizzati** per evento: tabella `tags` per Ente + pivot `evento_tag`.
- **Luoghi geolocalizzati**: campi `lat`/`lng` nella tabella `luoghi`.
- **Soft delete** + `created_at`/`updated_at` su tutte le entità principali.

### 3.2 Mappa entità Crono2

```
enti  (multi-tenant root)
  ├── users                  ← utenti con ente_id FK + ruolo
  ├── tags                   ← vocabolario tag dell'ente (normalizzato)
  ├── luoghi                 ← sedi geolocalizzate
  ├── persone                ← relatori, artisti
  ├── sponsor                ← partner
  ├── mail_templates         ← template email (per ente_id)  ★ ENTE-SCOPED
  ├── serie                  ← contenitore di eventi
  │     └── eventi           ← FK serie_id nullable
  └── eventi
        ├── evento_tag        (pivot)  ← tag associati
        ├── evento_luogo      (pivot)  ← luoghi associati (multi-luogo)
        ├── tipologie_posto            ← ★ NUOVO: tipi di posto per evento
        ├── campi_form                 ← definizione form prenotazione
        └── sessioni                   ← ex eventi_date (multi-data)
              ├── sessione_luogo        (pivot) ← luogo specifico per sessione
              ├── sessione_tipologie_posto (pivot) ← ★ NUOVO: disponibilità per tipologia
              ├── prenotazioni
              │     ├── prenotazione_posti  ← ★ NUOVO: dettaglio posti per tipologia
              │     └── risposte_form       ← risposte campi_form
              ├── lista_attesa
              └── prenotazioni_temporanee  ← lock temporale (con dettaglio tipologie)

notifiche_log   ← log email inviate
```

> **Multi-tenancy**: ogni tabella dati porta `ente_id` come prima FK.
> Tutte le query applicative filtrano sempre per `ente_id` tramite Global Scope Eloquent.

---

### 3.3 Draft tabella `enti` (ex `customers`)

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT PK | |
| `nome` | VARCHAR(255) | ragione sociale |
| `slug` | VARCHAR(255) UNIQUE | ex hashTag — URL vetrina `/ente/{slug}` |
| `shop_url` | VARCHAR(255) UNIQUE nullable | URL pubblico vetrina (es. `tennis-club-bologna`) → `crono.app/{shop_url}` |
| `codice_fiscale` | VARCHAR(16) UNIQUE | |
| `partita_iva` | VARCHAR(11) nullable | |
| `email` | VARCHAR UNIQUE | |
| `telefono` | VARCHAR nullable | |
| `indirizzo` / `citta` / `provincia` / `cap` | VARCHAR | |
| `lat` | DECIMAL(10,8) nullable | geolocalizzazione sede principale |
| `lng` | DECIMAL(11,8) nullable | geolocalizzazione sede principale |
| `descrizione` | TEXT nullable | testo breve (subtitle vetrina) |
| `logo` | VARCHAR nullable | path file |
| `copertina` | VARCHAR nullable | immagine header vetrina |
| `contenuto_vetrina` | LONGTEXT nullable | HTML/Markdown corpo pagina pubblica |
| `eventi_in_evidenza` | JSON nullable | array di `evento_id` da mostrare in evidenza |
| `stato` | ENUM | ATTIVO, SOSPESO, CANCELLATO |
| `licenza` | ENUM | GRATUITA, PREMIUM (TBD) |
| `config` | JSON nullable | configurazioni specifiche (es. colori, tema) |
| `attivo_dal` / `attivo_al` | DATETIME nullable | |
| `attivo` | BOOLEAN default true | |
| `deleted_at` | TIMESTAMP | soft delete |

> La vetrina è raggiungibile tramite `shop_url`. Vedi [04-enti-e-pagine-vetrina.md](./04-enti-e-pagine-vetrina.md).

---

### 3.4 Draft tabella `users`

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT PK | |
| `ente_id` | FK → enti nullable | Ente di appartenenza; NULL per utenti finali senza ente |
| `nome` | VARCHAR(255) | |
| `cognome` | VARCHAR(255) | |
| `email` | VARCHAR UNIQUE | |
| `keycloak_id` | VARCHAR UNIQUE nullable | ID utente in Keycloak |
| `password` | VARCHAR | hash (usato in modalità auth locale) |
| `telefono` | VARCHAR nullable | |
| `role` | ENUM | utente, operatore_ente, admin_ente, admin |
| `attivo` | BOOLEAN default true | |
| `primo_accesso_eseguito` | BOOLEAN default false | |
| `privacy_ok` | BOOLEAN default false | consenso GDPR |
| `newsletter_ok` | BOOLEAN default false | |
| `last_login_at` | DATETIME nullable | |
| `deleted_at` | TIMESTAMP | soft delete |

> L'`ente_id` identifica l'Ente a cui appartiene l'operatore/admin_ente.
> Gli utenti finali (ruolo `utente`) hanno `ente_id = NULL`.
> L'Admin di sistema (ruolo `admin`) ha accesso a tutti gli enti indipendentemente dall'`ente_id`.

---

### 3.5 Draft tabella `tags` (NUOVO — normalizzato)

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT PK | |
| `ente_id` | FK → enti | ogni ente ha il proprio vocabolario tag |
| `nome` | VARCHAR(100) | etichetta tag (es. "musica", "yoga", "bambini") |
| `colore` | VARCHAR(7) nullable | colore HEX per UI (es. `#3B82F6`) |
| `slug` | VARCHAR(100) | versione URL-safe del nome |
| `deleted_at` | TIMESTAMP | |

**Pivot `evento_tag`**:

| Campo | Tipo | Note |
|---|---|---|
| `evento_id` | FK → eventi | |
| `tag_id` | FK → tags | |

> Sostituisce il campo `tags` JSON di Crono1. I tag sono ricercabili, filtrabili
> e gestiti dall'Admin Ente nel pannello di configurazione.

---

### 3.6 Draft tabella `luoghi` (aggiornata con geolocalizzazione)

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT PK | |
| `ente_id` | FK → enti | |
| `nome` | VARCHAR(255) | |
| `descrizione` | TEXT nullable | |
| `slug` | VARCHAR(255) nullable | |
| `indirizzo` | VARCHAR(255) nullable | |
| `citta` | VARCHAR(100) nullable | |
| `provincia` | VARCHAR(2) nullable | |
| `cap` | VARCHAR(5) nullable | |
| `lat` | DECIMAL(10,8) nullable | latitudine (es. 44.49381) |
| `lng` | DECIMAL(11,8) nullable | longitudine (es. 11.33875) |
| `maps_url` | VARCHAR(512) nullable | link Google Maps / OpenStreetMap |
| `telefono` | VARCHAR nullable | |
| `email` | VARCHAR nullable | |
| `link_pubblico` | VARCHAR nullable | sito web luogo |
| `immagine` | VARCHAR nullable | path foto luogo |
| `stato` | ENUM | ATTIVO, SOSPESO |
| `deleted_at` | TIMESTAMP | |

**Pivot `evento_luogo`** (evento multi-luogo a livello generale):

| Campo | Tipo | Note |
|---|---|---|
| `evento_id` | FK → eventi | |
| `luogo_id` | FK → luoghi | |
| `principale` | BOOLEAN default false | luogo primario dell'evento |

**Pivot `sessione_luogo`** (override luogo per sessione specifica):

| Campo | Tipo | Note |
|---|---|---|
| `sessione_id` | FK → sessioni | |
| `luogo_id` | FK → luoghi | |

> Se una sessione ha un `sessione_luogo`, quel luogo sovrascrive quelli dell'evento.
> Permette eventi che si svolgono in più luoghi diversi in date diverse.

---

### 3.7 Draft tabella `serie` (contenitore di eventi)

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT PK | |
| `ente_id` | FK → enti | |
| `titolo` | VARCHAR(255) | |
| `descrizione` | TEXT nullable | |
| `slug` | VARCHAR(255) nullable | |
| `stato` | ENUM | BOZZA, PUBBLICATO, SOSPESO, ANNULLATO |
| `pubblico` | BOOLEAN | visibile in vetrina |
| `visibile_dal` / `visibile_al` | DATETIME nullable | finestra visibilità |
| `immagine` | VARCHAR nullable | copertina della serie |
| `contenuto` | LONGTEXT nullable | descrizione estesa |
| `link_pubblico` | VARCHAR nullable | |
| `deleted_at` | TIMESTAMP | |

> La **Serie** è un contenitore logico di eventi correlati: un festival, una stagione
> teatrale, un corso multi-sessione, una rassegna musicale. Gli **eventi** opzionalmente
> referenziano una serie tramite `serie_id` (FK nullable su `eventi`).

---

### 3.8 Draft tabella `sessioni` (ex `eventi_date`)

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT PK | |
| `evento_id` | FK → eventi | |
| `titolo` | VARCHAR(255) nullable | etichetta breve (es. "Mattina", "Turno A") |
| `descrizione` | TEXT nullable | descrizione dettagliata della singola data |
| `data_inizio` | DATETIME | |
| `data_fine` | DATETIME | |
| `posti_totali` | INT | contatore globale; 0 = illimitato |
| `posti_disponibili` | INT | decrementato a ogni conferma (contatore globale) |
| `posti_in_attesa` | INT | contatore lista d'attesa |
| `posti_riservati` | INT | contatore lock temporali attivi |
| `controlla_posti_globale` | BOOLEAN default false | **flag chiave**: se `true`, la disponibilità viene determinata solo da `posti_disponibili` globale; se `false`, viene controllata per singola tipologia tramite `sessione_tipologie_posto` |
| `prenotabile` | BOOLEAN default true | override manuale |
| `forza_non_disponibile` | BOOLEAN default false | chiusura forzata manuale |
| `soglia_chiusura_automatica` | INT nullable | chiudi a N posti globali rimasti |
| `attiva_lista_attesa` | BOOLEAN default false | |
| `durata_lock_minuti` | INT default 15 | TTL lock temporale posti |
| `note_pubbliche` | TEXT nullable | |
| `attributi` | JSON nullable | extra non ricercabili |
| `deleted_at` | TIMESTAMP | |

> **Logica `controlla_posti_globale`**:
> - `true` → il sistema ignora i contatori per-tipologia e usa solo `posti_disponibili`
>   della sessione. Utile per eventi senza distinzione di categoria.
> - `false` → il sistema verifica che ogni tipologia abbia disponibilità sufficiente
>   in `sessione_tipologie_posto.posti_disponibili`. Il `posti_disponibili` globale
>   resta comunque aggiornato come somma totale di tutti i posti confermati.

I luoghi della sessione sono assegnati tramite il pivot **`sessione_luogo`** (vedere § 3.6).

---

### 3.9 Draft tabella `tipologie_posto` (NUOVO)

Define le categorie di posto prenotabili per un **Evento**.
Sono condivise da tutte le sessioni dell'evento (con disponibilità propria per sessione).

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT PK | |
| `evento_id` | FK → eventi | |
| `ente_id` | FK → enti | denormalizzato per query multi-tenancy |
| `nome` | VARCHAR(255) | es. "Intero", "Ridotto", "Under 12", "VIP" |
| `descrizione` | TEXT nullable | dettagli mostrati all'utente nel form |
| `gratuita` | BOOLEAN default true | se `false`, è a pagamento |
| `costo` | DECIMAL(10,2) nullable | prezzo unitario; NULL o 0 se gratuita |
| `min_prenotabili` | INT default 1 | minimo posti di questa tipologia per prenotazione |
| `max_prenotabili` | INT nullable | massimo posti per prenotazione; NULL = illimitato |
| `ordinamento` | INT default 0 | ordine di visualizzazione nel form |
| `attiva` | BOOLEAN default true | |
| `deleted_at` | TIMESTAMP | |

> **Esempio**: evento "Spettacolo teatrale" con tipologie:
> - Intero — gratuita: no, costo: 15.00, min: 1, max: 4
> - Ridotto (under 14) — gratuita: no, costo: 8.00, min: 1, max: 2
> - Omaggio (ospiti) — gratuita: sì, min: 1, max: 1

---

### 3.10 Draft tabella `sessione_tipologie_posto` (NUOVO — disponibilità per tipologia)

Traccia la disponibilità di ogni tipologia di posto per ogni sessione.
Usata solo quando `sessioni.controlla_posti_globale = false`.

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT PK | |
| `sessione_id` | FK → sessioni | |
| `tipologia_posto_id` | FK → tipologie_posto | |
| `posti_totali` | INT | 0 = illimitato |
| `posti_disponibili` | INT | decrementato a ogni conferma |
| `posti_riservati` | INT | contatore lock temporali attivi per questa tipologia |
| `attiva` | BOOLEAN default true | disabilita questa tipologia per la sessione specifica |

> Vincoli applicativi:
> - `posti_disponibili >= 0` sempre.
> - La somma di `posti_disponibili` di tutte le tipologie attive deve essere
>   coerente con `sessioni.posti_disponibili` (aggiornata in sincronia).
> - Se `posti_totali = 0` (illimitato) non viene applicata una soglia superiore
>   sulla singola tipologia.

---

### 3.11 Draft tabella `campi_form` (form prenotazione configurabile)

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT PK | |
| `evento_id` | FK → eventi | ogni evento ha il suo form |
| `ordine` | INT | posizione nel form |
| `tipo` | ENUM | TEXT, TEXTAREA, SELECT, CHECKBOX, RADIO, DATE, EMAIL, PHONE, NUMBER |
| `etichetta` | VARCHAR(255) | label mostrata all'utente |
| `placeholder` | VARCHAR(255) nullable | |
| `obbligatorio` | BOOLEAN default false | |
| `opzioni` | JSON nullable | per SELECT/RADIO/CHECKBOX: lista valori |
| `validazione` | JSON nullable | regole (min, max, regex, ecc.) |
| `visibile_pubblico` | BOOLEAN | se visibile sul portale pubblico |
| `attivo` | BOOLEAN default true | |

> I campi **nome, cognome, email, telefono, posti_prenotati** sono sempre presenti
> (campi di sistema), non configurabili. I `campi_form` aggiungono campi extra.

---

### 3.12 Draft tabella `prenotazioni`

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT PK | |
| `sessione_id` | FK → sessioni | |
| `user_id` | FK → users nullable | NULL = prenotazione guest |
| `ente_id` | FK → enti | denormalizzato per query veloci |
| `stato` | ENUM | RISERVATA, CONFERMATA, DA_CONFERMARE, ANNULLATA, IN_LISTA_ATTESA |
| `codice` | VARCHAR(20) UNIQUE | codice human-readable (es. CRN-2026-00042) |
| `data_prenotazione` | DATETIME | |
| `scadenza_riserva` | DATETIME nullable | valorizzato se stato=RISERVATA |
| `posti_prenotati` | INT | totale posti (somma di `prenotazione_posti.quantita`) |
| `nome` / `cognome` | VARCHAR | |
| `email` | VARCHAR | |
| `telefono` | VARCHAR nullable | |
| `note` | TEXT nullable | |
| `costo_totale` | DECIMAL(10,2) nullable | somma di `prenotazione_posti.costo_riga` |
| `evento_snapshot` | JSON | snapshot titolo, data, luogo, tipologie al momento |
| `data_annullamento` | DATETIME nullable | |
| `motivo_annullamento` | TEXT nullable | |
| `annullata_da_user_id` | FK → users nullable | |
| `deleted_at` | TIMESTAMP | |

> Il dettaglio delle tipologie prenotate è in `prenotazione_posti` (§ 3.13).

---

### 3.13 Draft tabella `prenotazione_posti` (NUOVO — dettaglio per tipologia)

Una riga per ogni tipologia di posto inclusa nella prenotazione.

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT PK | |
| `prenotazione_id` | FK → prenotazioni | |
| `tipologia_posto_id` | FK → tipologie_posto | |
| `quantita` | INT | numero posti prenotati per questa tipologia |
| `costo_unitario` | DECIMAL(10,2) nullable | snapshot del costo al momento della prenotazione |
| `costo_riga` | DECIMAL(10,2) nullable | `quantita * costo_unitario` |

> **Esempio**: prenotazione con 2 Interi (15€) + 1 Ridotto (8€):
> - riga 1: tipologia=Intero, quantita=2, costo_unitario=15.00, costo_riga=30.00
> - riga 2: tipologia=Ridotto, quantita=1, costo_unitario=8.00, costo_riga=8.00
> - `prenotazioni.costo_totale` = 38.00 | `prenotazioni.posti_prenotati` = 3

---

### 3.14 Draft tabella `risposte_form` (normalizzato)

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT PK | |
| `prenotazione_id` | FK → prenotazioni | |
| `campo_form_id` | FK → campi_form | |
| `valore` | TEXT | risposta serializzata (stringa, numero, lista) |

> Alternativa: mantenere un JSON `risposte` nella prenotazione per semplicità.
> **Da decidere** in base al bisogno di filtrare/reportare per campo.

---

### 3.15 Draft tabella `prenotazioni_temporanee` (locking temporale)

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT PK | |
| `sessione_id` | FK → sessioni | |
| `posti_totali` | INT | totale posti bloccati (somma delle tipologie) |
| `dettaglio_tipologie` | JSON | array `[{tipologia_posto_id, quantita}]` per decrement per-tipologia al rilascio |
| `token` | VARCHAR(64) UNIQUE | token di sessione browser |
| `scadenza_at` | DATETIME | TTL: ora + `sessione.durata_lock_minuti` |
| `created_at` | TIMESTAMP | |

> Alla creazione del lock:
> - `sessioni.posti_riservati += posti_totali`
> - Se `controlla_posti_globale = false`: `sessione_tipologie_posto.posti_riservati` incrementato per ogni riga in `dettaglio_tipologie`.
>
> Al rilascio (scadenza o annullamento):
> - Decrement speculare dei contatori sopra.

---

### 3.16 Draft tabella `lista_attesa`

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT PK | |
| `sessione_id` | FK → sessioni | |
| `user_id` | FK → users nullable | |
| `nome` / `cognome` / `email` / `telefono` | VARCHAR | |
| `posti_richiesti` | INT | totale posti richiesti |
| `dettaglio_tipologie` | JSON nullable | array `[{tipologia_posto_id, quantita}]` se la lista attesa è per tipologia specifica |
| `posizione` | INT | ordine in lista |
| `stato` | ENUM | IN_ATTESA, NOTIFICATO, CONFERMATO, SCADUTO, RIMOSSO |
| `notificato_at` | DATETIME nullable | |
| `scadenza_conferma_at` | DATETIME nullable | |
| `created_at` | TIMESTAMP |

---

### 3.17 Draft tabella `mail_templates` (template email per Ente)

Ogni Ente può personalizzare i propri template email. Se per un Ente non esiste
un template personalizzato, il sistema usa il template di sistema (`ente_id = NULL`).

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT PK | |
| `ente_id` | FK → enti nullable | NULL = template di sistema (default piattaforma) |
| `tipo` | ENUM | vedi lista sotto |
| `oggetto` | VARCHAR(512) | oggetto email (supporta placeholder) |
| `corpo` | LONGTEXT | corpo email HTML (supporta placeholder) |
| `sistema` | BOOLEAN default false | `true` = template di default non eliminabile |
| `attivo` | BOOLEAN default true | |
| `created_at` / `updated_at` | TIMESTAMP | |

**Tipi di template** (enum `tipo`):

| Valore | Trigger |
|---|---|
| `PRENOTAZIONE_CONFERMATA` | Prenotazione confermata → utente |
| `PRENOTAZIONE_DA_CONFERMARE` | Prenotazione in attesa approvazione → utente |
| `PRENOTAZIONE_APPROVATA` | Approvazione manuale da operatore → utente |
| `PRENOTAZIONE_ANNULLATA_UTENTE` | Cancellazione da utente |
| `PRENOTAZIONE_ANNULLATA_OPERATORE` | Cancellazione da operatore → utente |
| `PRENOTAZIONE_NOTIFICA_STAFF` | Nuova prenotazione → operatori ente |
| `EVENTO_ANNULLATO` | Evento/sessione annullata → tutti i prenotati |
| `LISTA_ATTESA_ISCRIZIONE` | Conferma iscrizione lista d'attesa → utente |
| `LISTA_ATTESA_POSTO_DISPONIBILE` | Posto liberato → utente in lista |
| `LISTA_ATTESA_SCADENZA` | Finestra di conferma sta per scadere → utente |
| `REMINDER_EVENTO` | Promemoria prima dell'evento → utente (TBD) |
| `REGISTRAZIONE_CONFERMATA` | Account creato → utente |
| `RESET_PASSWORD` | Reset password → utente |

**Placeholder supportati** (sostituiti a runtime):

```
{{nome_utente}}, {{cognome_utente}}, {{email_utente}}
{{titolo_evento}}, {{data_sessione}}, {{ora_sessione}}
{{luogo_evento}}, {{codice_prenotazione}}
{{nome_ente}}, {{link_prenotazione}}, {{link_annullamento}}
{{dettaglio_posti}}  ← lista tipologie e quantità
```

> **Risoluzione template**: il sistema cerca prima un template con `ente_id = ente.id`
> e `tipo = X`; se non trovato, usa il template con `ente_id = NULL` e `tipo = X`.

---

### 3.18 Draft tabella `notifiche_log` (log email inviate)

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT PK | |
| `ente_id` | FK → enti | |
| `prenotazione_id` | FK → prenotazioni nullable | |
| `tipo` | ENUM | stesso enum di `mail_templates.tipo` |
| `destinatario_email` | VARCHAR(255) | |
| `oggetto` | VARCHAR(512) | oggetto effettivo inviato |
| `stato` | ENUM | IN_CODA, INVIATA, ERRORE |
| `errore` | TEXT nullable | messaggio di errore se `stato = ERRORE` |
| `tentativo` | INT default 1 | numero di tentativi |
| `inviata_at` | DATETIME nullable | |
| `created_at` | TIMESTAMP | |

---

## Aperto / Da decidere

- [ ] **Prenotazione guest**: quali campi obbligatori? (nome, cognome, email minimo?)
- [ ] **Lista d'attesa**: automatica con finestra di conferma o gestione manuale dall'operatore?
- [ ] **Locking temporale**: DB puro (tabella + scheduler) o Redis TTL?
- [ ] **Risposte form**: tabella `risposte_form` normalizzata o JSON `risposte` nella prenotazione? (impatta la reportistica)
- [ ] **Migrazione Crono1 → Crono2**: fresh start o script di migrazione dati?
- [ ] **Posti numerati** (es. platea con posti assegnati): da supportare o fuori scope per ora?
- [ ] **Pagamenti**: solo campo `costo_totale` per ora, integrazione (Stripe/PayPal) come fase successiva?
- [ ] **Persone e Sponsor**: entità proprie (come Crono1) o attributi JSON dell'evento?
- [ ] **`eventi_in_evidenza`** nella tabella `enti`: JSON di ID vs tabella pivot con ordinamento?
- [ ] **Tag globali di piattaforma** (condivisi tra enti) vs solo tag per Ente?
- [ ] **Geocoding automatico**: calcolare lat/lng dall'indirizzo tramite API (Google Maps / Nominatim)?
- [ ] **Tipologie di posto**: il `min_prenotabili = 1` significa "almeno 1 se scegli questa tipologia" oppure la tipologia è sempre obbligatoria in ogni prenotazione?
- [ ] **`controlla_posti_globale`**: quando è `true` e si usano tipologie a pagamento, come si calcola il costo (senza sapere quanti posti per tipologia)?
- [ ] **Template email**: editor visuale (WYSIWYG) per l'Admin Ente o solo testo con placeholder?

---

> Documento: `10-modello-dati.md` — Versione 0.2 — febbraio 2026  
> Fonte: dump schema DB `crono` (Crono1 produzione, febbraio 2026)
