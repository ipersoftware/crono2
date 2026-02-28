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
- Univoco a livello di piattaforma
- Solo caratteri alfanumerici lowercase e trattini (`[a-z0-9-]`)
- Configurabile dall'Admin Ente, approvato dall'Admin di sistema (TBD)
- Immutabile dopo la prima pubblicazione (per stabilità dei link esterni) — TBD

---

## 3. Struttura della pagina vetrina

La vetrina è una **SPA Vue.js** che carica i dati dell'Ente tramite API pubblica
(nessuna autenticazione richiesta per la lettura).

### 3.1 Layout pagina vetrina

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

### 3.2 Sezioni della vetrina

| Sezione | Contenuto | Configurabile |
|---|---|---|
| **Header** | Logo, nome ente, copertina | ✅ |
| **Descrizione** | Testo breve + geolocalizzazione sede | ✅ |
| **In evidenza** | Fino a N eventi selezionati manualmente dall'Admin Ente | ✅ |
| **Prossimi eventi** | Lista eventi futuri pubblicati, ordinati per data | automatico |
| **Ricerca eventi** | Barra ricerca testo libero + filtri | automatico |
| **Contenuto libero** | HTML/Markdown editabile dall'Admin Ente | ✅ |

### 3.3 Card evento in vetrina

Ogni card mostra:
- Titolo evento
- Data prossima sessione disponibile
- Luogo (nome + città)
- Immagine copertina evento
- Badge tag (es. "yoga", "musica")
- Indicatore disponibilità (posti liberi / esaurito / lista attesa)
- CTA: "Prenota" o "Scopri di più"

---

## 4. Ricerca e filtri eventi in vetrina

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

## 5. API pubblica vetrina

Le seguenti rotte API sono **pubbliche** (nessun token richiesto):

| Metodo | Rotta | Descrizione |
|---|---|---|
| `GET` | `/api/vetrina/{shop_url}` | Dati ente + configurazione vetrina |
| `GET` | `/api/vetrina/{shop_url}/eventi` | Lista eventi con filtri (qs params) |
| `GET` | `/api/vetrina/{shop_url}/eventi/{slug}` | Dettaglio singolo evento + sessioni |
| `GET` | `/api/vetrina/{shop_url}/serie` | Lista serie pubblicate |
| `GET` | `/api/vetrina/{shop_url}/serie/{slug}` | Dettaglio serie + eventi |

---

## 6. Gestione vetrina dal pannello Admin Ente

L'Admin Ente configura la vetrina da un'apposita sezione del pannello:

### 6.1 Impostazioni generali
- Nome, descrizione breve, logo, immagine copertina
- `shop_url` (richiede approvazione Admin sistema — TBD)
- Dati di contatto (telefono, email pubblica, indirizzo)
- Geolocalizzazione sede (inserimento manuale o geocoding da indirizzo)

### 6.2 Sezione "In evidenza"
- Selezione drag & drop degli eventi da mostrare in evidenza
- Ordinamento manuale
- Massimo N eventi (TBD)

### 6.3 Contenuto libero
- Editor HTML/Markdown per corpo pagina
- Sezioni configurabili (TBD: blocchi tipo page builder o editor libero?)

### 6.4 Tema e branding (TBD)
- Colore primario dell'ente
- Font personalizzato
- Layout alternativo (lista vs griglia eventi)

---

## 7. Multi-tenancy e isolamento dati

Ogni vetrina mostra **esclusivamente** i dati dell'Ente corrispondente allo `shop_url`:
- Solo eventi con `ente_id = ente.id` e `stato = PUBBLICATO`
- Solo sessioni future (o con disponibilità)
- Solo serie pubblicate
- Solo tag dell'Ente

Le query API sono sempre scoped sull'`ente_id`; non è possibile accedere ai dati di un altro Ente tramite la vetrina pubblica.

---

## Aperto / Da decidere

- [ ] `shop_url` libero o approvato dall'Admin di sistema prima della pubblicazione?
- [ ] `shop_url` modificabile dopo la pubblicazione? (impatto SEO e link salvati)
- [ ] **Tema/branding**: personalizzazione colori e font per ente, o tema unico?
- [ ] **Mappa enti**: pagina pubblica di piattaforma che mostra tutti gli enti attivi?
- [ ] **Ricerca globale**: cercare eventi su tutta la piattaforma (cross-ente)?
- [ ] **SEO**: pagine vetrina renderizzate server-side (SSR/SSG) o solo SPA?
- [ ] **Dominio custom per ente**: es. `prenotazioni.tennisclubbo.it` → redirect a `crono.app/tennis-club-bologna`?
- [ ] **Sezioni "In evidenza"**: massimo quanti eventi? Gestione scadenza automatica (es. evento passato rimosso da evidenza)?
- [ ] Editor del contenuto libero: blocchi strutturati (page builder) o HTML/Markdown libero?

---

> Documento: `04-enti-e-pagine-vetrina.md` — Versione 0.1 (brainstorming) — febbraio 2026
