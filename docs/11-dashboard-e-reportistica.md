# 11 — Dashboard e Reportistica

## Scopo

Descrivere le funzionalità di **analisi statistica e reportistica** disponibili nel pannello
operativo di **Crono2**, con particolare riferimento alla sezione **Statistiche Prenotazioni**
accessibile agli operatori e agli amministratori di ogni Ente.

---

## 1. Contesto e Obiettivi

La sezione Statistiche nasce dall'esigenza di fornire agli Enti uno sguardo quantitativo
sull'andamento delle proprie attività. Le metriche esposte consentono di:

- monitorare l'andamento delle prenotazioni nel tempo;
- identificare eventi ad alta domanda (sold-out, liste d'attesa);
- valutare il tasso di abbandono/annullamento;
- analizzare il comportamento degli utenti (fasce orarie, giorni della settimana);
- avere una visione dei ricavi per eventi a pagamento.

---

## 2. Accesso e Permessi

| Ruolo            | Accesso Statistiche              |
|------------------|----------------------------------|
| `utente`         | ✖ non accessibile                |
| `operatore_ente` | ✔ accesso in sola lettura        |
| `admin_ente`     | ✔ accesso + filtri avanzati      |
| `admin`          | ✔ accesso a tutti gli enti       |

Il pannello Statistiche è accessibile tramite la voce **"📊 Statistiche"** nella barra
di navigazione principale, visibile per i ruoli `operatore_ente`, `admin_ente` e `admin`.

---

## 3. Filtri Globali della Pagina

Tutti i widget della pagina rispettano i seguenti filtri globali:

| Filtro     | Valori                                     | Default           |
|------------|--------------------------------------------|-------------------|
| `dal`      | Data inizio periodo (date input)           | 1° gennaio anno corrente |
| `al`       | Data fine periodo (date input)             | Oggi              |
| `evento_id`| Selezione opzionale evento specifico       | (tutti)           |

---

## 4. Struttura della Pagina

### 4.1 KPI Overview (cards sintetiche)

Riga di card riepilogative in cima alla pagina. Valori calcolati sul periodo selezionato.

| Card                       | Fonte dati                                                    | Colore  |
|----------------------------|---------------------------------------------------------------|---------|
| Prenotazioni confermate    | `prenotazioni.stato IN ('CONFERMATA','DA_CONFERMARE')`        | Blu     |
| Totale posti prenotati     | `SUM(prenotazioni.posti_prenotati)` (stato attivo)            | Verde   |
| Tasso di annullamento      | `ANNULLATA* / totale * 100` (%)                               | Arancio |
| Ricavi totali              | `SUM(costo_totale)` dove `costo_totale > 0`                   | Verde scuro |
| Prenotazioni in lista attesa | `stato IN ('IN_LISTA_ATTESA','NOTIFICATO')`                 | Viola   |

> **Nota:** il tasso di annullamento include `ANNULLATA`, `ANNULLATA_UTENTE`, `ANNULLATA_ADMIN`.

---

### 4.2 Andamento Prenotazioni nel Tempo (line chart)

**Cosa mostra:** prenotazioni mensili nell'arco degli ultimi 12 mesi (o del periodo filtrato),
suddivise per stato aggregato: *Confermate* e *Annullate*.

**Fonte dati:**
```sql
SELECT
  DATE_FORMAT(data_prenotazione, '%Y-%m') AS mese,
  SUM(CASE WHEN stato IN ('CONFERMATA','DA_CONFERMARE') THEN 1 ELSE 0 END) AS confermate,
  SUM(CASE WHEN stato LIKE 'ANNULLATA%' THEN 1 ELSE 0 END) AS annullate
FROM prenotazioni
WHERE ente_id = ?
  AND data_prenotazione BETWEEN ? AND ?
GROUP BY mese
ORDER BY mese
```

**Utilità:** rilevare stagionalità, picchi di prenotazione, impatto di comunicazioni/campagne.

---

### 4.3 Distribuzione per Stato (donut chart)

**Cosa mostra:** spaccato percentuale di tutte le prenotazioni del periodo per stato.

**Stati visualizzati:**
- `CONFERMATA`
- `DA_CONFERMARE`
- `ANNULLATA` / `ANNULLATA_UTENTE` / `ANNULLATA_ADMIN` (raggruppabili)
- `IN_LISTA_ATTESA`
- `NOTIFICATO`
- `SCADUTA` / `SCADUTO`
- `RISERVATA` (prenotazioni in bozza non ancora confermate)

**Utilità:** capire quante prenotazioni non arrivano a buon fine e per quale motivo.

---

### 4.4 Top 10 Eventi per Prenotazioni (bar chart orizzontale)

**Cosa mostra:** classifica dei 10 eventi con il maggior numero di prenotazioni attive
nel periodo. Per ciascun evento viene mostrato anche il **tasso medio di occupazione**
(`posti_prenotati / posti_totali`).

**Fonte dati:**
```sql
SELECT
  e.titolo,
  COUNT(p.id) AS n_prenotazioni,
  SUM(p.posti_prenotati) AS posti_prenotati,
  AVG(s.posti_totali) AS posti_totali_medi,
  ROUND(SUM(p.posti_prenotati) / NULLIF(SUM(s.posti_totali), 0) * 100, 1) AS tasso_occupazione
FROM prenotazioni p
JOIN sessioni s ON p.sessione_id = s.id
JOIN eventi e ON s.evento_id = e.id
WHERE p.ente_id = ?
  AND p.stato IN ('CONFERMATA','DA_CONFERMARE')
  AND p.data_prenotazione BETWEEN ? AND ?
GROUP BY e.id, e.titolo
ORDER BY n_prenotazioni DESC
LIMIT 10
```

**Utilità:** identificare gli eventi di punta e ottimizzare l'offerta.

---

### 4.5 Tasso di Occupazione Sessioni (bar chart)

**Cosa mostra:** per ciascun evento una barra che rappresenta il tasso di riempimento
medio delle sessioni (`posti_prenotati_totali / posti_totali`), con distinzione visiva
per: < 50% (grigio), 50–80% (giallo), > 80% (verde), 100% sold-out (rosso).

**Utilità:** capire quali eventi/sessioni sono sottoutilizzati e quali vanno sempre esauriti.

---

### 4.6 Prenotazioni per Giorno della Settimana (bar chart)

**Cosa mostra:** in quali giorni della settimana (Lun→Dom) vengono effettuate più
prenotazioni nel periodo selezionato.

**Fonte dati:**
```sql
SELECT DAYOFWEEK(data_prenotazione) AS giorno, COUNT(*) AS n
FROM prenotazioni
WHERE ente_id = ? AND data_prenotazione BETWEEN ? AND ?
GROUP BY giorno
```

**Utilità:** schedulare comunicazioni e campagne nei giorni di maggiore attività.

---

### 4.7 Prenotazioni per Fascia Oraria (bar chart)

**Cosa mostra:** a che ora del giorno vengono effettuate le prenotazioni (raggruppate
per ora intera, 00–23).

**Fonte dati:**
```sql
SELECT HOUR(data_prenotazione) AS ora, COUNT(*) AS n
FROM prenotazioni
WHERE ente_id = ? AND data_prenotazione BETWEEN ? AND ?
GROUP BY ora
```

**Utilità:** individuare i picchi di traffico sull'applicazione; utile per eventuale
ottimizzazione infrastrutturale.

---

### 4.8 Lista d'Attesa — Dettaglio (tabella)

**Cosa mostra:** elenco degli eventi che hanno prenotazioni correntemente in lista
d'attesa, con:
- N° persone in attesa
- N° persone notificate (stato `NOTIFICATO`)
- Tasso di conversione lista attesa → confermata (storico del periodo)

**Utilità:** capire dove la domanda supera l'offerta e decidere se ampliare la capienza.

---

### 4.9 Distribuzione Tipologie Posto (pie chart)

**Cosa mostra:** quanti posti sono stati prenotati per ciascuna tipologia posto
(es. "Adulto", "Ridotto", "Gratuito"), in termini di quantità e ricavo generato.

**Fonte dati:** tabella `prenotazione_posti` → `tipologia_posto_id` → `tipologie_posto.nome`

**Utilità:** capire la composizione del pubblico e l'efficacia delle tipologie tariffarie.

---

### 4.10 Ricavi per Mese (line/bar chart)

**Cosa mostra:** andamento mensile dei ricavi (`SUM(costo_totale)`) nel periodo,
solo per eventi con `costo > 0`. Il widget compare automaticamente solo se l'ente
ha almeno una prenotazione con `costo_totale > 0`.

**Fonte dati:**
```sql
SELECT DATE_FORMAT(data_prenotazione, '%Y-%m') AS mese,
       SUM(costo_totale) AS ricavi
FROM prenotazioni
WHERE ente_id = ? AND costo_totale > 0
  AND stato IN ('CONFERMATA','DA_CONFERMARE')
  AND data_prenotazione BETWEEN ? AND ?
GROUP BY mese
```

**Utilità:** monitorare l'andamento economico dell'ente.

---

## 5. API Backend

| Endpoint                                          | Controller             | Permesso          |
|---------------------------------------------------|------------------------|-------------------|
| `GET /api/enti/{ente}/statistiche/overview`       | `StatisticheController@overview`    | `operatore_ente`  |
| `GET /api/enti/{ente}/statistiche/trend`          | `StatisticheController@trend`       | `operatore_ente`  |
| `GET /api/enti/{ente}/statistiche/stati`          | `StatisticheController@stati`       | `operatore_ente`  |
| `GET /api/enti/{ente}/statistiche/top-eventi`     | `StatisticheController@topEventi`   | `operatore_ente`  |
| `GET /api/enti/{ente}/statistiche/occupazione`    | `StatisticheController@occupazione` | `operatore_ente`  |
| `GET /api/enti/{ente}/statistiche/giorni`         | `StatisticheController@giorni`      | `operatore_ente`  |
| `GET /api/enti/{ente}/statistiche/ore`            | `StatisticheController@ore`         | `operatore_ente`  |
| `GET /api/enti/{ente}/statistiche/lista-attesa`   | `StatisticheController@listaAttesa` | `operatore_ente`  |
| `GET /api/enti/{ente}/statistiche/tipologie`      | `StatisticheController@tipologie`   | `operatore_ente`  |
| `GET /api/enti/{ente}/statistiche/ricavi`         | `StatisticheController@ricavi`      | `operatore_ente`  |

Tutti i parametri `dal`, `al`, `evento_id` vengono passati come query string.

> **Alternativa semplificata:** un singolo endpoint `GET /statistiche` che restituisce
> tutti i dati in una sola risposta, per ridurre il numero di chiamate HTTP dalla SPA.
> Soluzione consigliata per la v1 dell'implementazione.

---

## 6. Frontend Vue

**Componente principale:** `resources/js/views/admin/Statistiche.vue`

**Libreria grafici consigliata:** [Chart.js](https://www.chartjs.org/) tramite
[vue-chartjs](https://vue-chartjs.org/) (già compatibile con Vue 3).

**Layout:**
```
┌─────────────────────────────────────────────────────────┐
│  Filtri:  DAL [____] AL [____]  Evento: [tutti ▼]  [Aggiorna] │
├──────────┬──────────┬──────────┬──────────┬─────────────┤
│ Prenot.  │  Posti   │  Tasso   │  Ricavi  │  In attesa  │
│ confirmate│ prenotati│ annullam.│ totali   │             │
├──────────┴──────────┴──────────┴──────────┴─────────────┤
│  Andamento nel tempo (line)    │  Distribuzione stati (donut) │
├────────────────────────────────┴──────────────────────────┤
│  Top 10 eventi (bar orizzontale)                          │
├────────────────────┬──────────────────────────────────────┤
│  Giorni settimana  │  Fasce orarie                        │
├────────────────────┴──────────────────────────────────────┤
│  Lista d'attesa (tabella)  │  Tipologie posto (pie)        │
└────────────────────────────────────────────────────────────┘
```

---

## 7. Aperto / Da Decidere

- [ ] **Confronto anno su anno**: aggiungere linea "anno precedente" al trend mensile?
- [ ] **Export PDF/XLS della pagina statistiche**: utile per report periodici?
- [ ] **Endpoint unificato vs multipli**: v1 suggerita = endpoint unico `/statistiche` con tutti i dati  
- [ ] **vue-chartjs vs alternativa**: considerare anche ApexCharts se si vuole più interattività
- [ ] **Widget ricavi condizionale**: nascondere automaticamente se l'ente non ha mai avuto eventi a pagamento
- [ ] **Granularità confronto stagionalità**: settimane vs mesi vs trimestri

---

> Versione documento: 0.1 — 11 marzo 2026
