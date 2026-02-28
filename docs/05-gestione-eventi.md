# 05 — Gestione Eventi

## Scopo

Descrivere il modello di un **Evento** in Crono2, la sua struttura multi-data
e multi-luogo, il ciclo di vita, le serie come contenitori e la gestione dei tag.

---

## 1. Cos'è un Evento

Un **Evento** è l'unità prenotabile della piattaforma.
Rappresenta qualsiasi attività organizzata da un Ente per la quale
un utente può effettuare una prenotazione.

Esempi:
- Una lezione di yoga settimanale (evento ricorrente, multi-sessione)
- Un concerto (evento singolo con una sola data)
- Un corso di formazione di 5 giornate (multi-data, stesso luogo)
- Una rassegna teatrale (più eventi raggruppati in una Serie)
- Una visita medica specialistica (multi-slot giornalieri)

Un Evento:
- Appartiene a un **Ente** (`ente_id`)
- Ha zero o più **Sessioni** (date/orari prenotabili)
- Può appartenere a una **Serie** (`serie_id` nullable)
- Ha zero o più **Tag** (via pivot `evento_tag`)
- Ha zero o più **Luoghi** associati (via pivot `evento_luogo`)
- Ha un **Form di prenotazione** configurabile (`campi_form`)

---

## 2. Struttura dati dell'Evento

### Draft tabella `eventi`

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT PK | |
| `ente_id` | FK → enti | tenant owner |
| `serie_id` | FK → serie nullable | raggruppamento in serie |
| `titolo` | VARCHAR(255) | |
| `slug` | VARCHAR(255) UNIQUE (ente_id, slug) | URL-friendly, univoco per ente, genera URL pubblico `/{shop_url}/eventi/{slug}` |
| `slug_history` | JSON nullable | storico slug precedenti per redirect 301 |
| `descrizione_breve` | VARCHAR(512) nullable | abstract per card in vetrina |
| `descrizione` | LONGTEXT nullable | descrizione completa (HTML/Markdown) |
| `immagine` | VARCHAR nullable | path copertina |
| `stato` | ENUM | BOZZA, PUBBLICATO, SOSPESO, ANNULLATO |
| `pubblico` | BOOLEAN default false | visibile in vetrina |
| `in_evidenza` | BOOLEAN default false | mostrato in evidenza sulla vetrina |
| `ordinamento` | INT default 0 | ordinamento manuale (usato in serie/vetrina) |
| `visibile_dal` | DATETIME nullable | inizio finestra visibilità |
| `visibile_al` | DATETIME nullable | fine finestra visibilità |
| `prenotabile_dal` | DATETIME nullable | apertura prenotazioni |
| `prenotabile_al` | DATETIME nullable | chiusura prenotazioni |
| `posti_max_per_prenotazione` | INT default 1 | max posti acquistabili in una singola prenotazione |
| `richiede_approvazione` | BOOLEAN default false | prenotazione → stato DA_CONFERMARE |
| `consenti_multi_sessione` | BOOLEAN default false | permetti a un utente di prenotare più sessioni dello stesso evento. Vedi [§11 Multi-sessione](./06-prenotazioni.md#11-prenotazione-multi-sessione) |
| `consenti_prenotazione_guest` | BOOLEAN default true | abilita prenotazioni senza registrazione per questo evento |
| `cancellazione_consentita_ore` | INT nullable | finestra cancellazione: NULL=sempre; -1=mai; N=fino a N ore prima. Vedi [§8 Cancellazione](./06-prenotazioni.md#8-cancellazione-prenotazione-da-parte-dellutente) |
| `mostra_disponibilita` | BOOLEAN default true | mostra posti rimasti in vetrina |
| `attiva_note` | BOOLEAN default false | abilita campo note libere nel form |
| `nota_etichetta` | VARCHAR(255) nullable | etichetta campo note |
| `costo` | DECIMAL(10,2) nullable | prezzo base (0 = gratuito) |
| `attributi` | JSON nullable | attributi extra non ricercabili |
| `deleted_at` | TIMESTAMP | |

---

## 3. Multi-data: le Sessioni

Un Evento può avere **N Sessioni** (`sessioni`), ciascuna con data/ora propria.
Le prenotazioni si fanno sempre sulla **Sessione**, non sull'Evento direttamente.

```
Evento: "Corso di yoga"
  ├── Sessione: Martedì 3 marzo, 09:00-10:00  [20 posti]
  ├── Sessione: Martedì 3 marzo, 18:00-19:00  [20 posti]
  ├── Sessione: Martedì 10 marzo, 09:00-10:00 [20 posti]
  └── Sessione: Martedì 10 marzo, 18:00-19:00 [20 posti]
```

L'utente in vetrina vede l'evento e sceglie la sessione desiderata tramite
un selettore calendario o una lista.

### Regole per sessione (override dell'evento)

Ogni sessione può sovrascrivere alcune configurazioni dell'evento padre:
- Luogo specifico (via `sessione_luogo`)
- Posti totali diversi dall'evento
- Durata lock temporale
- Prenotabilità on/off manuale
- Finestre di disponibilità proprie

---

## 4. Multi-luogo: i Luoghi dell'Evento

Un Evento può svolgersi in **più luoghi** (es. tour su più città).
I luoghi sono gestiti tramite due pivot:

### `evento_luogo` — luoghi generali dell'evento
Associa uno o più luoghi all'evento in modo generico.
Il luogo con `principale = true` è quello mostrato in vetrina come sede principale.

### `sessione_luogo` — luogo specifico per sessione
Quando una sessione si svolge in un luogo diverso dagli altri,
il luogo viene indicato a livello di sessione e sovrascrive quelli dell'evento.

```
Evento: "Rassegna Jazz"
  ├── Sessione 1 (15 marzo) → Luogo: Teatro A [override]
  ├── Sessione 2 (22 marzo) → Luogo: Piazza B [override]
  └── Sessione 3 (29 marzo) → Luogo: Teatro A [override]
```

---

## 5. Le Serie — Contenitore di eventi

Una **Serie** è un contenitore logico che raggruppa eventi correlati.

Esempi:
- "Stagione teatrale 2026" → contiene 12 spettacoli
- "Corso di inglese B2 — Primavera 2026" → contiene 20 lezioni
- "Festival del Cinema 2026" → contiene 8 proiezioni + 3 talk

### Relazione Serie → Evento

```
serie  (1) ──────────────────── (N) eventi   [FK serie_id in eventi]
```

Un evento può appartenere a **una sola serie** (o a nessuna).
Una serie può avere pagina pubblica propria in vetrina
(es. `/ente/teatro-delle-muse/serie/stagione-2026`).

### Visibilità della Serie in vetrina

- La serie ha la propria sezione in vetrina con la lista degli eventi che la compongono.
- Gli eventi di una serie compaiono anche nella lista generale degli eventi dell'ente.
- La serie può avere immagine di copertina e descrizione proprie.

---

## 6. I Tag degli eventi

I **Tag** sono etichette categoriali associate agli eventi per consentire
la filtrazione in vetrina.

### Caratteristiche

- Ogni Ente ha il proprio **vocabolario tag** (tabella `tags` con `ente_id`).
- I tag sono gestiti dall'Admin Ente nel pannello di configurazione.
- Un evento può avere **N tag** (pivot `evento_tag`).
- In vetrina i tag sono mostrati come badge colorati (colore configurabile per tag).
- I tag sono usati come filtro di ricerca in vetrina.

### Esempi di vocabolario tag

| Ente | Tag possibili |
|---|---|
| Associazione sportiva | yoga, pilates, spinning, nuoto, bambini, adulti, outdoor |
| Teatro | prosa, danza, cabaret, musica, famiglia, anteprima |
| Studio medico | fisioterapia, osteopatia, nutrizione, check-up |

---

## 7. Ciclo di vita dell'Evento

```
[BOZZA] ──────editabile──────► [PUBBLICATO]
                                     │
                              ┌──────┴──────┐
                              │             │
                         [SOSPESO]    [ANNULLATO]
                              │
                         [PUBBLICATO]  ← riattivabile
```

| Stato | Visibile in vetrina | Prenotabile |
|---|:---:|:---:|
| `BOZZA` | ❌ | ❌ |
| `PUBBLICATO` | ✅ (se `pubblico = true`) | ✅ (se nella finestra) |
| `SOSPESO` | ❌ | ❌ |
| `ANNULLATO` | ❌ | ❌ |

> Quando un evento viene **ANNULLATO**, le prenotazioni confermate devono essere
> notificate (email automatica). Vedi [07-notifiche.md](./07-notifiche.md).

---

## 8. Creazione evento — Flusso Admin Ente

Il pannello di gestione guida l'Admin Ente nella creazione di un evento:

```
[Step 1] Informazioni generali
  - Titolo, descrizione breve, descrizione completa
  - Immagine copertina
  - Tag (selezione dal vocabolario ente)
  - Serie di appartenenza (opzionale)
  - Costo (0 = gratuito)

[Step 2] Luoghi
  - Selezione luoghi dall'anagrafica ente (o creazione al volo)
  - Indicazione luogo principale

[Step 2b] Tipologie di posto
  - Aggiunta tipologie: nome, costo (o gratuita), min/max prenotabili, ordine
  - Se nessuna tipologia: il form usa solo il campo "numero di posti" generico

[Step 3] Sessioni / Date
  - Aggiunta manuale: data, ora inizio/fine, descrizione, luogo (override)
  - Per ogni sessione:
    - se tipologie definite: posti_totali per tipologia + flag controlla_posti_globale
    - se no tipologie: posti_totali globale
  - Creazione ricorrente: es. "ogni martedì per 8 settimane"  ← TBD
  - Configurazione disponibilità per sessione

[Step 4] Form di prenotazione
  - Campi di sistema (sempre presenti)
  - Aggiunta campi custom (tipo, etichetta, obbligatorietà)
  - Anteprima form

[Step 5] Configurazione prenotazione
  - Finestra prenotabilità (dal/al)
  - Richiede approvazione? (on/off)
  - Cancellazione consentita fino a N ore prima
  - Lista d'attesa (on/off per sessione)
  - Durata lock temporale

[Step 6] Revisione e pubblicazione
  - Anteprima pagina evento
  - Salva come bozza  →  Pubblica
```

---

## 9. Tipologie di posto

### 9.1 Cosa sono

Le **Tipologie di posto** sono categorie prenotabili definite per un Evento.
Permettono di differenziare la prenotazione per categoria (es. interi, ridotti, VIP)
e/o per prezzo. Ogni tipologia ha disponibilità propria per sessione.

### 9.2 Attributi di una Tipologia

| Attributo | Descrizione |
|---|---|
| **Nome** | Es. "Intero", "Ridotto Under 14", "VIP", "Omaggio" |
| **Descrizione** | Testo opzionale mostrato nel form di prenotazione |
| **Gratuita** | Se `true`, nessun costo; se `false`, c'è un `costo` unitario |
| **Costo** | Prezzo unitario (€); usato per calcolare `costo_totale` della prenotazione |
| **Min prenotabili** | Minimo posti di questa tipologia per una singola prenotazione (≥ 1) |
| **Max prenotabili** | Massimo posti per prenotazione; `NULL` = nessun limite |
| **Ordinamento** | Ordine di visualizzazione nel form di prenotazione |
| **Attiva** | On/off per abilitare o disabilitare la tipologia |

### 9.3 Disponibilità per sessione (`sessione_tipologie_posto`)

Per ogni sessione, ogni tipologia attiva ha i propri contatori:
- `posti_totali` — capienza massima per questa tipologia in questa sessione (0 = illimitato)
- `posti_disponibili` — decrementato ad ogni prenotazione confermata
- `posti_riservati` — incrementato ad ogni lock temporale attivo

### 9.4 Flag `controlla_posti_globale`

La sessione ha un flag chiave che determina come viene verificata la disponibilità:

```
controlla_posti_globale = FALSE  (default)
┌─────────────────────────────────────────────────────────────────┐
│  Il sistema verifica la disponibilità PER SINGOLA TIPOLOGIA     │
│  usando sessione_tipologie_posto.posti_disponibili              │
│  → Utile quando ogni categoria ha una capienza propria          │
│    (es. 10 posti VIP, 50 posti Intero, 20 posti Ridotto)       │
└─────────────────────────────────────────────────────────────────┘

controlla_posti_globale = TRUE
┌─────────────────────────────────────────────────────────────────┐
│  Il sistema usa SOLO sessioni.posti_disponibili (globale)       │
│  Le tipologie non hanno una capienza individuale                │
│  → Utile per eventi con più categorie di prezzo ma un unico     │
│    contatore di posti (es. 100 posti totali, qualunque tariffa) │
└─────────────────────────────────────────────────────────────────┘
```

### 9.5 Esempio pratico

**Evento: "Spettacolo Teatrale — 15 marzo"**

Tipologie definite sull'evento:
| Tipologia | Costo | Min | Max |
|---|---|---|---|
| Intero | 15.00 € | 1 | 4 |
| Ridotto (under 14) | 8.00 € | 1 | 2 |
| Omaggio | gratuito | 1 | 1 |

Sessione "15 marzo ore 21:00" — `controlla_posti_globale = FALSE`:
| Tipologia | Posti totali | Posti disponibili |
|---|---|---|
| Intero | 80 | 80 |
| Ridotto | 20 | 20 |
| Omaggio | 5 | 5 |

Sessione "15 marzo ore 21:00" — `controlla_posti_globale = TRUE`:
- `sessioni.posti_totali = 100`, `sessioni.posti_disponibili = 100`
- Le tipologie esistono ma i loro contatori individuali non sono verificati

### 9.6 Configurazione nel pannello Admin Ente

Le tipologie si configurano nella scheda **"Tipologie di posto"** della pagina di creazione/modifica evento (Step 2b del wizard):

1. Aggiungere tipologie con nome, costo, min/max
2. Per ogni sessione: indicare i posti totali per tipologia (o 0 = illimitato)
3. Scegliere se usare il controllo globale o per tipologia
4. Possibilità di disabilitare una tipologia specifica per una sessione

---

## 10. Pagina pubblica dell'Evento

La rotta pubblica dell'evento (in SPA):
```
/ente/{shop_url}/evento/{slug}
```

### Contenuto della pagina

```
┌─────────────────────────────────────────────────────┐
│  Immagine copertina                                  │
├─────────────────────────────────────────────────────┤
│  Titolo evento                                       │
│  [Tag: yoga] [Tag: adulti]                           │
│  Serie: Corso Yoga Primavera 2026                    │
├─────────────────────────────────────────────────────┤
│  Descrizione completa                                │
├─────────────────────────────────────────────────────┤
│  SCEGLI UNA SESSIONE                                 │
│  ┌──────────────────────────────────────────────┐   │
│  │ Mar 3 mar · 09:00-10:00 · Sala A  [18 posti] │   │
│  │ Mar 3 mar · 18:00-19:00 · Sala A  [ESAURITO] │   │
│  │ Mar 10 mar · 09:00-10:00 · Sala B [20 posti] │   │
│  └──────────────────────────────────────────────┘   │
│                                                      │
│  [PRENOTA →]                                         │
├─────────────────────────────────────────────────────┤
│  DOVE                                                │
│  Via Roma 10, Bologna                                │
│  [Mappa]                                             │
└─────────────────────────────────────────────────────┘
```

---

## Aperto / Da decidere

- [ ] **Sessioni ricorrenti**: generazione automatica (es. "ogni martedì per 8 settimane") o solo inserimento manuale?
- [ ] **Evento a sessione unica**: se l'evento non ha sessioni, si prenota direttamente l'evento (senza scelta)?
- [ ] **Costo per sessione**: il prezzo può variare per sessione o solo a livello di evento?
- [ ] **Immagini multiple** per evento (galleria) o solo copertina?
- [ ] **Allegati** scaricabili per evento (es. programma PDF)?
- [ ] **Persone** (relatori/artisti): entità propria con profilo, o solo testo nel campo descrizione?
- [ ] **Sponsor** per evento: da mantenere come in Crono1?
- [ ] **Annullamento evento**: notifica automatica a tutti i prenotati? Rimborso (se a pagamento)?
- [ ] **Clona evento**: funzione per duplicare un evento con le sue sessioni?

---

> Documento: `05-gestione-eventi.md` — Versione 0.1 (brainstorming) — febbraio 2026
