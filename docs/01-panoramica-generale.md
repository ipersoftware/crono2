# 01 — Panoramica Generale

## Scopo

Questo documento descrive il contesto, gli obiettivi e i principi guida di **Crono2**,
sistema di prenotazione eventi di nuova generazione.

---

## 1. Contesto

**Crono** è una piattaforma software che consente a **Enti** (associazioni, studi professionali,
strutture pubbliche o private) di pubblicare una propria **pagina vetrina** sulla quale
gli **utenti finali** possono consultare gli **eventi** disponibili ed effettuare **prenotazioni**.

**Crono1** è la versione attualmente in produzione, sviluppata circa due anni fa.
Pur funzionante, presenta limiti sia sul piano tecnologico che funzionale che ne hanno
motivato la completa riscrittura.

---

## 2. Obiettivi di Crono2

### 2.1 Obiettivi funzionali

- Consentire a qualsiasi tipo di Ente di configurare la propria offerta di eventi
  in modo autonomo e flessibile.
- Offrire all'utente finale un'esperienza di prenotazione semplice e immediata,
  accessibile anche senza registrazione (TBD).
- Supportare eventi di natura eterogenea: sportivi, culturali, sanitari, formativi, ecc.
- Gestire regole avanzate di prenotazione: capienza massima, slot temporali,
  prenotazione multipla, liste d'attesa, ecc.
- Fornire a ogni Ente un pannello operativo per la gestione delle prenotazioni ricevute.

### 2.2 Obiettivi tecnici

- Architettura pulita, manutenibile e scalabile, basata sullo stack **Laravel + Vue.js**.
- Autenticazione basata su **Keycloak** (SSO), con fallback su provider locale.
- API REST ben definita, documentata e testabile.
- Frontend SPA (Single Page Application) reattiva e mobile-friendly.
- Base dati relazionale robusta, progettata per supportare l'evoluzione del dominio.

### 2.3 Cosa migliora rispetto a Crono1

| Aspetto | Crono1 | Crono2 |
|---|---|---|
| Stack frontend | (legacy) | Vue.js 3 + Pinia + Vue Router |
| Stack backend | (legacy) | Laravel 11 + Sanctum |
| Autenticazione | Locale | Keycloak SSO + locale |
| Ruoli e permessi | Limitati | RBAC strutturato per Ente |
| Tipi di evento | Fissi | Configurabili |
| Pagina vetrina | Statica | Dinamica e personalizzabile |
| Notifiche | Assenti / limitate | Email + reminder automatici |
| Reportistica | Assente | Dashboard con statistiche |
| Multi-ente | Assente / parziale | Nativa |

---

## 3. Principali Attori

| Attore | Descrizione |
|--------|-------------|
| **Utente finale** | Persona che consulta la vetrina e prenota un evento |
| **Operatore Ente** | Membro dello staff di un Ente, gestisce le prenotazioni |
| **Admin Ente** | Responsabile dell'Ente, configura eventi e operatori |
| **Admin di sistema** | Superutente della piattaforma, gestisce tutti gli Enti |

> Dettaglio completo nel documento [03-attori-e-ruoli.md](./03-attori-e-ruoli.md).

---

## 4. Macro-funzioni del Sistema

Il sistema si articola nelle seguenti macro-funzioni (capitoli della documentazione):

1. **Gestione Enti e pagine vetrina** — registrazione, profilo, branding dell'Ente
2. **Gestione Eventi** — creazione, configurazione, disponibilità, calendario
3. **Prenotazioni** — flusso utente, stati, regole, lista d'attesa
4. **Notifiche** — conferme, reminder, cancellazioni
5. **Dashboard e reportistica** — viste operative e statistiche
6. **Autenticazione e profilo utente** — Keycloak, login locale, primo accesso
7. **Integrazioni** — connessione con sistemi esterni (Ermes, Smartpass)

---

## 5. Integrazioni con Sistemi Esterni

Crono2 si integra con sistemi esterni per estendere le funzionalità:

| Sistema | Funzione | Modalità |
|---------|----------|----------|
| **Keycloak** | Autenticazione SSO per ruoli gestionali | OAuth 2.0 / OpenID Connect |
| **Ermes** | Invio newsletter massive ai prenotati | API REST esposta da Crono2 |
| **Smartpass** | Generazione pass digitali per eventi | API REST bidirezionale (futuro) |

**Caso d'uso principale (Ermes):**
Un operatore vuole inviare una newsletter a tutti i prenotati di un evento.
Ermes interroga l'API di Crono2 per ottenere la lista dei destinatari,
l'operatore compone il messaggio su Ermes e invia.

> Dettagli completi nel documento [08-integrazioni.md](./08-integrazioni.md).

---

## 6. Note di Dominio

- Un **Ente** può avere più **Eventi** attivi contemporaneamente.
- Un **Evento** ha una o più **Sessioni** (slot temporali prenotabili) — TBD.
- Una **Prenotazione** appartiene a un Utente e a una Sessione (o a un Evento diretto).
- Le regole di prenotazione (capienza, anticipo minimo, finestra di cancellazione, ecc.)
  sono configurabili per ogni Evento.

---

## Aperto / Da decidere

- [ ] Prenotazione possibile anche per utenti anonimi (senza account)?
- [ ] Un utente può prenotare per conto terzi (es. prenotazione familiare)?
- [ ] Pagamenti integrati (es. prenotazione a pagamento)?
- [ ] App mobile dedicata o solo PWA?
- [ ] Multi-lingua?
- [ ] Un utente può essere associato a più Enti?
- [ ] Analisi del DB di Crono1 per allineamento / migrazione dati.

---

> Documento: `01-panoramica-generale.md` — Versione 0.1 (brainstorming) — febbraio 2026
