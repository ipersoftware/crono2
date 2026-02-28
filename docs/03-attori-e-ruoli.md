# 03 — Attori e Ruoli

## Scopo

Definire tutti gli attori del sistema Crono2, i loro ruoli, le responsabilità
e i livelli di accesso alle funzionalità.

---

## 1. Mappa degli attori

```
                        ┌─────────────────────────────────────────────┐
                        │               CRONO2                        │
                        │                                             │
   [Utente Anonimo] ────┼──► Vetrina pubblica, lista eventi           │
                        │                                             │
   [Utente Registrato] ─┼──► Prenotazioni, area personale            │
                        │                                             │
   [Operatore Ente] ────┼──► Gestione prenotazioni del proprio Ente  │
                        │                                             │
   [Admin Ente] ────────┼──► Configurazione Ente, eventi, operatori  │
                        │                                             │
   [Admin] ─────────────┼──► Tutto + impersonificazione Ente         │
                        └─────────────────────────────────────────────┘
```

---

## 2. Dettaglio ruoli

### 2.1 Utente Anonimo (non autenticato)

Visitatore non registrato. Può navigare la piattaforma in sola lettura.

**Può fare:**
- Visitare la pagina vetrina di un Ente
- Consultare il calendario e la lista eventi di un Ente
- Leggere la descrizione di un evento e le sessioni disponibili
- Effettuare una prenotazione come **guest** (se abilitato dall'Ente)
- Durante il checkout guest, scegliere volontariamente di creare un account

**Non può fare:**
- Accedere all'area personale (a meno che non crei un account durante la prenotazione)
- Vedere le proprie prenotazioni storiche senza account (solo via pagina pubblica con email + codice)

**Accesso alle prenotazioni guest:**
- Pagina pubblica "Visualizza le tue prenotazioni" (inserendo email + codice)
- Cancellazione prenotazione tramite link univoco nell'email di conferma
- Possibilità di attivare un account in qualsiasi momento dalla email di conferma

---

### 2.2 Utente (ruolo: `utente`)

Utente registrato e autenticato. Ha un'area personale.

**Può fare (in aggiunta all'anonimo):**
- Registrarsi e accedere tramite credenziali locali (email/password)
- Prenotare eventi con il proprio profilo precompilato
- Accedere all'**area personale** → "Le mie prenotazioni"
- Consultare lo storico delle prenotazioni effettuate
- Cancellare una prenotazione (entro i limiti configurati dall'Ente)
- Modificare i propri dati profilo (nome, cognome, telefono, ecc.)

**Non può fare:**
- Accedere al pannello gestionale degli Enti
- Visualizzare prenotazioni di altri utenti

---

### 2.3 Operatore Ente (ruolo: `operatore_ente`)

Membro dello staff di un Ente, assegnato da un Admin Ente.
Opera **esclusivamente nel contesto del proprio Ente**.
Accede tramite **Keycloak SSO**.

**Può fare:**
- Visualizzare tutte le prenotazioni del proprio Ente
- Filtrare e cercare prenotazioni per evento, sessione, stato
- Confermare o annullare prenotazioni (con motivo)
- Aggiungere manualmente una prenotazione (per telefono, sportello, ecc.)
- Esportare lista prenotazioni (CSV/Excel — TBD)
- Gestire la lista d'attesa di una sessione
- Visualizzare la dashboard operativa (posti disponibili, andamento prenotazioni)

**Non può fare:**
- Creare o modificare eventi
- Gestire altri operatori
- Accedere a dati di altri Enti

---

### 2.4 Admin Ente (ruolo: `admin_ente`)

Responsabile dell'Ente. Ha controllo completo sul proprio Ente.
Può avere più `admin_ente` per lo stesso Ente.
Accede tramite **Keycloak SSO**.

**Può fare (in aggiunta all'Operatore):**
- Configurare il profilo dell'Ente (anagrafica, logo, vetrina)
- Creare, modificare e pubblicare **eventi** e **sessioni**
- Configurare il **form di prenotazione** per ogni evento (campi custom)
- Gestire **serie/rassegne**
- Gestire **luoghi**, **persone**, **sponsor**
- Aggiungere e gestire gli Operatori del proprio Ente
- Configurare i **template email** dell'Ente
- Visualizzare la **reportistica** completa del proprio Ente
- Configurare le regole di prenotazione per sessione:
  - Posti totali
  - Finestra di prenotabilità (dal / al)
  - Durata lock temporale
  - Lista d'attesa (on/off)
  - Soglia chiusura automatica
  - Costo (TBD)

**Non può fare:**
- Accedere a dati di altri Enti
- Gestire altri Admin Ente (solo l'Admin di sistema può farlo — TBD)
- Modificare configurazioni globali della piattaforma

---

### 2.5 Admin di sistema (ruolo: `admin`)

Superutente della piattaforma. Ha visibilità e controllo su tutto il sistema.
Accede tramite **Keycloak SSO**.

**Può fare:**
- Tutto ciò che può fare l'Admin Ente, su qualsiasi Ente
- **Impersonificare un Ente**: navigare il pannello gestionale come se fosse
  un Admin Ente specifico, per supporto e troubleshooting
- Creare, attivare e sospendere Enti
- Gestire utenti globali (attivare, disattivare, cambiare ruolo)
- Assegnare un utente come Admin Ente
- Visualizzare statistiche e log globali della piattaforma
- Gestire configurazioni di sistema

> L'impersonificazione deve essere tracciata nei log (chi, quando, quale Ente).

---

## 3. Matrice permessi

| Funzione | Anonimo | Utente | Operatore | Admin Ente | Admin |
|---|:---:|:---:|:---:|:---:|:---:|
| Visualizza vetrina Ente | ✅ | ✅ | ✅ | ✅ | ✅ |
| Visualizza eventi pubblici | ✅ | ✅ | ✅ | ✅ | ✅ |
| Prenota (guest) | ✅ | ✅ | — | — | — |
| Prenota (registrato) | — | ✅ | — | — | — |
| "Le mie prenotazioni" | — | ✅ | — | — | — |
| Cancella propria prenotazione | — | ✅ | — | — | — |
| Visualizza prenotazioni Ente | — | — | ✅ | ✅ | ✅ |
| Gestisce prenotazioni (conferma/annulla) | — | — | ✅ | ✅ | ✅ |
| Inserisce prenotazione manuale | — | — | ✅ | ✅ | ✅ |
| Gestisce lista d'attesa | — | — | ✅ | ✅ | ✅ |
| Crea/modifica eventi | — | — | — | ✅ | ✅ |
| Configura form prenotazione | — | — | — | ✅ | ✅ |
| Configura Ente (profilo, logo) | — | — | — | ✅ | ✅ |
| Gestisce operatori | — | — | — | ✅ | ✅ |
| Dashboard reportistica | — | — | ✅ (parziale) | ✅ | ✅ |
| Crea/gestisce Enti | — | — | — | — | ✅ |
| Impersona Ente | — | — | — | — | ✅ |
| Gestione utenti globale | — | — | — | — | ✅ |

⚙️ = configurabile / TBD

---

## 4. Autenticazione e Keycloak

### Gestione autenticazione per ruolo

- **Utente (`utente`)**: autenticazione **locale** (email/password) gestita da Laravel.
  **Non usa Keycloak SSO** — autenticazione nativa Laravel (Sanctum).
- **Operatore Ente, Admin Ente, Admin**: autenticazione tramite **Keycloak SSO**.
  I ruoli sono definiti come **Realm Roles** in Keycloak e sincronizzati
  nel campo `role` della tabella `users`.

**Razionale separazione**:
- Gli utenti finali (`utente`) non necessitano di SSO enterprise
- Registrazione semplificata (email/password) riduce attrito
- I ruoli gestionali beneficiano di SSO per sicurezza e gestione centralizzata
- Keycloak gestisce solo utenti interni/staff, non la base utenti pubblica

Un utente con ruolo `admin` ha accesso globale a tutti gli Enti.
Un utente con ruolo `operatore_ente` o `admin_ente` è associato al proprio Ente
tramite il campo `ente_id` nella tabella `users`. Un utente con ruolo `utente`
ha `ente_id = NULL` (non appartiene a nessun Ente).

> Dettaglio tecnico nel documento [09-autenticazione.md](./09-autenticazione.md).

---

## 5. Area Personale Utente (nuovo in Crono2)

La SPA Vue.js espone una sezione `/profilo` accessibile agli utenti autenticati con le seguenti aree:

| Sezione | Contenuto |
|---|---|
| **Dati personali** | Nome, cognome, email, telefono, password |
| **Le mie prenotazioni** | Lista storica con stato, codice, evento, data |
| **Dettaglio prenotazione** | Dati completi + pulsante cancellazione (se entro termini) |

> L'area personale è assente in Crono1 — novità rilevante di Crono2.

---

## Aperto / Da decidere

- [ ] **Multi-Ente per Admin Ente**: un Admin Ente può gestire più di un Ente (scenario franchise)?
- [ ] **Notifica all'utente** quando viene promosso a Operatore/Admin di un Ente?
- [ ] **Registrazione pubblica**: chiunque può registrarsi, o solo su invito?
- [ ] **Profilo SSO Keycloak** (per ruoli gestionali): i dati nome/cognome si sincronizzano da Keycloak o sono gestiti localmente?

---

> Documento: `03-attori-e-ruoli.md` — Versione 0.1 (brainstorming) — febbraio 2026
