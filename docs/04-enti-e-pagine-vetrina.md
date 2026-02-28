# 04 — Enti e Pagina Vetrina

## Scopo

Descrivere la gestione degli **Enti** nel sistema Crono2, la configurazione della
**pagina vetrina pubblica** e le funzionalità esposte all'utente finale.

---

## 1. L'Ente in Crono2

Un **Ente** è l'unità tenant della piattaforma. Può essere:
- Un'associazione sportiva
- Uno studio professionale (medico, fisioterapico, legale…)
- Un teatro, un museo, una struttura culturale
- Un ente pubblico o privato di qualsiasi natura

Ogni Ente ha una propria **pagina vetrina pubblica** accessibile tramite il suo `shop_url`.
Ogni dato (eventi, sessioni, prenotazioni, utenti operativi) è isolato per Ente: **multi-tenancy nativa**.

---

## 2. shop_url — L'identità pubblica dell'Ente

Il campo `shop_url` sulla tabella `enti` identifica la vetrina dell'Ente sulla piattaforma.

```
https://crono.app/{shop_url}
```

Esempi:
```
https://crono.app/tennis-club-bologna
https://crono.app/studio-fisio-rossi
https://crono.app/teatro-delle-muse
```

### Regole
- **Univoco a livello di piattaforma** (constraint UNIQUE sul DB)
- Solo caratteri alfanumerici lowercase e trattini (`[a-z0-9-]`)
- **Gestito esclusivamente dall'Admin di sistema** (non modificabile dall'Admin Ente)
- Parametro delicato: impatta SEO, link esterni, integrazioni
- **Immutabile dopo la prima pubblicazione** per garantire stabilità dei link

---

## 3. URL degli Eventi — Link parlanti e SEO-friendly

Ogni evento ha un proprio **slug univoco** all'interno dell'Ente, che forma l'URL pubblico dell'evento.

### 3.1 Struttura URL evento

**Crono2 (nuova struttura):**
```
https://crono.app/{shop_url}/eventi/{event_slug}
```

Esempi:
```
https://crono.app/teatro-delle-muse/eventi/il-robot-selvaggio
https://crono.app/tennis-club-bologna/eventi/torneo-estivo-2026
https://crono.app/studio-fisio-rossi/eventi/corso-pilates-base
```

**Crono1 (vecchia struttura — da migliorare):**
```
https://crono.ipersoftware.it/search?metaclass=Evento&class=Evento&tag=ilrobotselvaggio
```

### 3.2 Generazione automatica dello slug

Lo slug viene generato automaticamente dal titolo dell'evento:

```
Titolo:  "Il Robot Selvaggio - Spettacolo Teatrale"
Slug:    "il-robot-selvaggio-spettacolo-teatrale"
```

**Regole di generazione:**
- Solo caratteri alfanumerici lowercase e trattini
- Rimozione caratteri speciali, apostrofi, punteggiatura
- Conversione spazi in trattini
- Univoco all'interno dell'Ente (se esiste già, aggiunge suffisso numerico)

**Modificabilità:**
- L'Admin Ente può modificare manualmente lo slug
- Se l'evento è già pubblicato, il vecchio slug viene mantenuto come redirect 301
- Lo storico dei vecchi slug è conservato per retrocompatibilità link esterni

### 3.3 Vantaggi SEO

✅ **URL parlanti**: il contenuto dell'evento è riconoscibile dall'URL  
✅ **Keyword optimization**: titolo evento indicizzato nell'URL  
✅ **Gerarchica**: struttura `{ente}/eventi/{slug}` logica e navigabile  
✅ **Breve e condivisibile**: facile da copiare e condividere  
✅ **Retrocompatibilità**: redirect automatici per slug modificati  

---

## 4. Struttura della pagina vetrina

La vetrina è una **SPA Vue.js** che carica i dati dell'Ente tramite API pubblica
(nessuna autenticazione richiesta per la lettura).

### 4.1 Layout pagina vetrina


```
┌──────────────────────────────────────────────────────┐
│  [Logo]  Nome Ente                    [CTA: Accedi]  │
├──────────────────────────────────────────────────────┤
│                                                      │
│         IMMAGINE DI COPERTINA                        │
│                                                      │
├──────────────────────────────────────────────────────┤
│  Descrizione breve dell'Ente                         │
│  [indirizzo / link mappa]                            │
├──────────────────────────────────────────────────────┤
│                                                      │
│  ★  EVENTI IN EVIDENZA  ★                            │
│  [card] [card] [card]                                │
│                                                      │
├──────────────────────────────────────────────────────┤
│                                                      │
│  PROSSIMI EVENTI                                     │
│  ┌─────────────────────────────────────────────┐    │
│  │ 🔍 Cerca eventi...  [Filtro tag ▾] [Data ▾] │    │
│  └─────────────────────────────────────────────┘    │
│  [card evento] [card evento] [card evento]           │
│  [card evento] [card evento] [card evento]           │
│  [Carica altri →]                                    │
│                                                      │
├──────────────────────────────────────────────────────┤
│  Contenuto libero (HTML)                             │
│  (es. contatti, orari, informazioni aggiuntive)      │
├──────────────────────────────────────────────────────┤
│  Footer                                              │
└──────────────────────────────────────────────────────┘
```

### 4.2 Sezioni della vetrina

| Sezione | Contenuto | Configurabile |
|---|---|---|
| **Header** | Logo, nome ente, copertina | ✅ |
| **Descrizione** | Testo breve + geolocalizzazione sede | ✅ |
| **In evidenza** | Fino a N eventi selezionati manualmente dall'Admin Ente | ✅ |
| **Prossimi eventi** | Lista eventi futuri pubblicati, ordinati per data | automatico |
| **Ricerca eventi** | Barra ricerca testo libero + filtri | automatico |
| **Contenuto libero** | HTML/Markdown editabile dall'Admin Ente | ✅ |

### 4.3 Design responsive

Tutta l'area utenti vetrina (visualizzazione pagina ente ed eventi), registrazione e area personale è **completamente responsive**, ottimizzata per:
- Desktop (≥1200px)
- Tablet (768-1199px)
- Mobile (320-767px)

Il layout si adatta automaticamente al dispositivo, garantendo un'esperienza utente ottimale su tutti i device.

### 4.4 Card evento in vetrina

Ogni card mostra:
- Titolo evento
- Data prossima sessione disponibile
- Luogo (nome + città)
- Immagine copertina evento
- Badge tag (es. "yoga", "musica")
- Indicatore disponibilità (posti liberi / esaurito / lista attesa)
- CTA: "Prenota" o "Scopri di più"

---

## 5. Ricerca e filtri eventi in vetrina

La vetrina espone una barra di ricerca con i seguenti filtri combinabili:

| Filtro | Tipo | Note |
|---|---|---|
| **Testo libero** | Input | ricerca su titolo + descrizione evento |
| **Tag** | Multi-select | vocabolario tag dell'Ente |
| **Data** | Date range | filtra per finestra temporale |
| **Luogo** | Select | sedi dell'Ente |
| **Serie** | Select | filtra per rassegna |
| **Disponibilità** | Toggle | mostra solo eventi con posti disponibili |

La ricerca è **client-side** per liste brevi o **API-driven** con paginazione per enti con molti eventi (TBD in base alla scala).

---

## 6. API pubblica vetrina

Le seguenti rotte API sono **pubbliche** (nessun token richiesto):

| Metodo | Rotta | Descrizione |
|---|---|---|
| `GET` | `/api/vetrina/{shop_url}` | Dati ente + configurazione vetrina (include tema/branding da `config`) |
| `GET` | `/api/vetrina/{shop_url}/eventi` | Lista eventi con filtri (qs params) |
| `GET` | `/api/vetrina/{shop_url}/eventi/{slug}` | Dettaglio singolo evento + sessioni |
| `GET` | `/api/vetrina/{shop_url}/serie` | Lista serie pubblicate |
| `GET` | `/api/vetrina/{shop_url}/serie/{slug}` | Dettaglio serie + eventi |

**Esempio risposta** `/api/vetrina/{shop_url}`:
```json
{
  "ente": {
    "nome": "Teatro delle Muse",
    "shop_url": "teatro-delle-muse",
    "descrizione": "Teatro storico nel cuore della città",
    "logo": "/storage/enti/teatro-muse-logo.png",
    "copertina": "/storage/enti/teatro-muse-cover.jpg",
    "email": "info@teatrodellemuse.it",
    "telefono": "+39 051 123456",
    "indirizzo": "Via del Teatro 12, Bologna",
    "config": {
      "tema": {
        "colore_primario": "#8B0000",
        "colore_secondario": "#D4AF37",
        "font_famiglia": "Playfair Display",
        "layout_eventi": "griglia"
      }
    }
  },
  "eventi_in_evidenza": [...]
}
```

---

## 7. Gestione vetrina dal pannello Admin Ente

L'Admin Ente configura la vetrina da un'apposita sezione del pannello:

### 7.1 Impostazioni generali
- Nome, descrizione breve, logo, immagine copertina
- Dati di contatto (telefono, email pubblica, indirizzo)
- Geolocalizzazione sede (inserimento manuale o geocoding da indirizzo)

**Nota:** Lo `shop_url` NON è modificabile dall'Admin Ente, è gestito esclusivamente dall'Admin di sistema.

### 7.2 Sezione "In evidenza"
- Selezione drag & drop degli eventi da mostrare in evidenza
- Ordinamento manuale
- Massimo N eventi (TBD)

### 7.3 Contenuto libero
- Editor HTML/Markdown per corpo pagina
- Sezioni configurabili (TBD: blocchi tipo page builder o editor libero?)

### 7.4 Tema e branding

L'Admin Ente può personalizzare l'aspetto della propria vetrina tramite un pannello dedicato "Tema e Branding".
Le impostazioni vengono salvate nel campo JSON `config` della tabella `enti`.

#### 7.4.1 Personalizzazione colori

**Variabili CSS personalizzabili**:

| Variabile | Default | Utilizzo |
|---|---|---|
| `colore_primario` | `#3B82F6` (blu) | Bottoni CTA, link, header |
| `colore_secondario` | `#64748B` (grigio) | Testi secondari, bordi |
| `colore_accento` | `#F59E0B` (arancione) | Badge disponibilità, highlight |
| `colore_sfondo` | `#FFFFFF` (bianco) | Sfondo principale |
| `colore_testo` | `#1E293B` (nero) | Testo principale |

**Picker colori nel pannello Admin**:
- Color picker visuale (hex)
- Anteprima live della vetrina
- Reset ai valori default

**Applicazione frontend**:
```css
/* Vue.js inietta variabili CSS dinamicamente */
:root {
  --color-primary: #3B82F6;
  --color-secondary: #64748B;
  --color-accent: #F59E0B;
  --color-bg: #FFFFFF;
  --color-text: #1E293B;
}
```

#### 7.4.2 Personalizzazione font

**Font preimpostati** (Google Fonts):

| Font | Categoria | Note |
|---|---|---|
| **Inter** | Sans-serif | Moderno, leggibile (default) |
| **Roboto** | Sans-serif | Neutrale, professionale |
| **Open Sans** | Sans-serif | Friendly, versatile |
| **Montserrat** | Sans-serif | Bold, impattante |
| **Lora** | Serif | Elegante, editoriale |
| **Playfair Display** | Serif | Lusso, cultura |
| **Poppins** | Sans-serif | Geometrico, giovane |

**Selezione**:
- Dropdown nel pannello Admin
- Separazione `font_titoli` e `font_testo` (opzionale)
- Anteprima con testo di esempio

**Caricamento dinamico**:
```javascript
// Carica font da Google Fonts on-demand
const fontFamily = ente.config.font_famiglia || 'Inter';
WebFont.load({ google: { families: [fontFamily] } });
```

#### 7.4.3 Layout eventi

**Template disponibili**:

| Template | Descrizione | Caso d'uso |
|---|---|---|
| **Griglia** | Card affiancate 3 colonne | Eventi con immagini forti (teatro, concerti) |
| **Lista** | Riga per evento, dettagli estesi | Molti eventi, focus su info (corsi, workshop) |
| **Calendario** | Vista mensile con badge | Eventi ricorrenti (palestre, centri sportivi) |

**Configurazione**: `config.layout_eventi` → `griglia` / `lista` / `calendario`

#### 7.4.4 CSS custom (opzionale - utenti avanzati)

Per enti con esigenze specifiche, l'Admin Ente può inserire **CSS personalizzato**:

**Campo**: `config.css_custom` (TEXT, max 5000 caratteri)

**Esempio**:
```css
/* Override bordi card */
.evento-card {
  border-radius: 16px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Stile custom per tag */
.tag-badge {
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
```

**Sicurezza**:
- Sanitizzazione CSS server-side (rimozione `<script>`, `javascript:`, etc.)
- Scope: solo classi della vetrina (namespace `.vetrina-{shop_url}`)
- Validazione: parser CSS per evitare sintassi malevola

**Toggle on/off**: l'Admin Ente può disabilitare il CSS custom senza perderlo.

#### 7.4.5 Struttura campo `config` nel DB

```json
{
  "tema": {
    "colore_primario": "#3B82F6",
    "colore_secondario": "#64748B",
    "colore_accento": "#F59E0B",
    "colore_sfondo": "#FFFFFF",
    "colore_testo": "#1E293B",
    "font_famiglia": "Inter",
    "font_titoli": "Montserrat",
    "layout_eventi": "griglia"
  },
  "css_custom": "/* CSS personalizzato */",
  "css_custom_attivo": true,
  "notifica_cancellazioni": true
}
```

> Il campo `config` nella tabella `enti` è di tipo JSON. Vedi [10-modello-dati.md §3.3](./10-modello-dati.md#33-draft-tabella-enti-ex-customers).

#### 7.4.6 Preview e pubblicazione

**Workflow**:
1. Admin Ente modifica tema/branding nel pannello
2. **Anteprima live** aggiornata in tempo reale (iframe della vetrina)
3. Pulsante "Salva" → aggiorna `enti.config`
4. Frontend vetrina legge `config` via API `/api/vetrina/{shop_url}`
5. Vue.js applica dinamicamente variabili CSS e font

**Cache frontend**:
- LocalStorage con TTL 1 ora
- Invalidazione automatica al cambio config (versioning)

---

## 8. Multi-tenancy e isolamento dati

Ogni vetrina mostra **esclusivamente** i dati dell'Ente corrispondente allo `shop_url`:
- Solo eventi con `ente_id = ente.id` e `stato = PUBBLICATO`
- Solo sessioni future (o con disponibilità)
- Solo serie pubblicate
- Solo tag dell'Ente

Le query API sono sempre scoped sull'`ente_id`; non è possibile accedere ai dati di un altro Ente tramite la vetrina pubblica.

---

## Aperto / Da decidere

- [ ] **Event slug**: lunghezza massima? Limite caratteri per evitare URL troppo lunghi?
- [ ] **Redirect 301**: mantenere storico slug per quanto tempo? (1 anno, permanente?)
- [ ] **Mappa enti**: pagina pubblica di piattaforma che mostra tutti gli enti attivi?
- [ ] **Ricerca globale**: cercare eventi su tutta la piattaforma (cross-ente)?
- [ ] **SEO**: pagine vetrina renderizzate server-side (SSR/SSG) o solo SPA?
- [ ] **Dominio custom per ente**: es. `prenotazioni.tennisclubbo.it` → redirect a `crono.app/tennis-club-bologna`?
- [ ] **Sezioni "In evidenza"**: massimo quanti eventi? Gestione scadenza automatica (es. evento passato rimosso da evidenza)?
- [ ] Editor del contenuto libero: blocchi strutturati (page builder) o HTML/Markdown libero?
- [ ] **Limite CSS custom**: 5000 caratteri sufficienti? Modalità "Pro" con limite maggiore?

---

> Documento: `04-enti-e-pagine-vetrina.md` — Versione 0.1 (brainstorming) — febbraio 2026
