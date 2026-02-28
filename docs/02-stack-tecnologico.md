# 02 — Stack Tecnologico

## Scopo

Descrivere le tecnologie adottate in Crono2, le motivazioni di scelta e le
interazioni tra i componenti.

---

## 1. Architettura Generale

Crono2 segue un'architettura **SPA + API REST**:

```
┌─────────────────────────┐        ┌──────────────────────────┐
│   Frontend (Vue.js 3)   │◄──────►│   Backend (Laravel 11)   │
│   http://localhost:5173 │  REST  │   http://localhost:8000  │
└─────────────────────────┘        └────────────┬─────────────┘
                                                │
                         ┌──────────────────────┼───────────────────┐
                         │                      │                   │
                  ┌──────▼──────┐      ┌────────▼───────┐   ┌──────▼──────┐
                  │   MySQL /   │      │   Keycloak     │   │   Queue /   │
                  │   MariaDB   │      │   (SSO Auth)   │   │   Scheduler │
                  └─────────────┘      └────────────────┘   └─────────────┘
```

Il frontend non parla mai direttamente con il database né con Keycloak:
tutto transita attraverso le API Laravel.

---

## 2. Backend — Laravel 11

| Componente | Dettaglio |
|---|---|
| Framework | Laravel 11 |
| PHP | >= 8.2 |
| Autenticazione API | Laravel Sanctum (token bearer) |
| ORM | Eloquent |
| Migrations | Laravel Migrations |
| Queue | Laravel Queue (database driver, espandibile a Redis) |
| Scheduler | Laravel Task Scheduling (artisan schedule:run) |
| Test | PHPUnit / Pest |

### Struttura directory backend rilevante

```
app/
  Http/Controllers/      ← Controller REST
  Models/                ← Modelli Eloquent
  Services/              ← Logica di business (es. KeycloakAdminService)
config/
  auth.php               ← Configurazione guard e provider
  auth_provider.php      ← Switch Laravel / Keycloak
  services.php           ← Credenziali servizi esterni
routes/
  api.php                ← Tutte le route API
  auth.php               ← Route di autenticazione
```

---

## 3. Frontend — Vue.js 3

| Componente | Dettaglio |
|---|---|
| Framework | Vue.js 3 (Composition API) |
| Build tool | Vite |
| State management | Pinia |
| Routing | Vue Router 4 |
| HTTP client | Axios (via moduli in `resources/js/api/`) |
| Stile | TBD (Tailwind CSS o Bootstrap 5) |

### Struttura directory frontend rilevante

```
resources/js/
  App.vue                ← Root component
  app.js                 ← Entry point
  router/                ← Definizione route SPA
  stores/                ← Store Pinia
  views/                 ← Pagine (una per route)
  api/                   ← Moduli Axios per ogni risorsa
```

---

## 4. Autenticazione — Keycloak

L'autenticazione è gestita tramite **Keycloak** (Identity Provider esterno).
È supportata anche una modalità **locale** (Laravel + Sanctum) per ambienti
di sviluppo o installazioni senza Keycloak.

La scelta del provider è configurabile tramite la variabile d'ambiente:

```env
AUTH_PROVIDER=keycloak   # oppure: laravel
```

| Modalità | Flusso |
|---|---|
| **Keycloak** | Frontend ottiene token JWT da Keycloak → invia a Laravel → Laravel verifica e crea sessione Sanctum |
| **Locale** | Frontend invia credenziali a Laravel → Laravel verifica nel DB → restituisce token Sanctum |

I **ruoli** sono gestiti in Keycloak come Realm Roles e sincronizzati
nel campo `role` della tabella `users`.

### Ruoli previsti

| Ruolo | Descrizione |
|---|---|
| `utente` | Utente finale, può prenotare |
| `operatore_ente` | Gestisce prenotazioni dell'Ente di appartenenza |
| `admin_ente` | Gestisce eventi e operatori del proprio Ente |
| `admin` | Superutente di sistema |

> Dettaglio completo nel documento [09-autenticazione.md](./09-autenticazione.md) e [03-attori-e-ruoli.md](./03-attori-e-ruoli.md).

---

## 5. Database

| Parametro | Valore |
|---|---|
| DBMS | MySQL 8+ / MariaDB 10.5+ |
| ORM | Eloquent |
| Schema versioning | Laravel Migrations |
| Soft delete | Abilitato sulle entità principali |

Le tabelle già presenti nel template di partenza sono:
- `enti` — anagrafica degli Enti
- `users` — utenti con ruolo e associazione all'Ente
- `sessions`, `cache`, `jobs`, `personal_access_tokens` — infrastruttura Laravel

> Schema completo nel documento [10-modello-dati.md](./10-modello-dati.md).

---

## 6. Infrastruttura di Sviluppo

```
php artisan serve      ← Backend su :8000
npm run dev            ← Frontend (Vite) su :5173
docker (Keycloak)      ← Keycloak su :8080
MySQL                  ← Database su :3306
```

---

## Aperto / Da decidere

- [ ] Stile CSS: Tailwind CSS vs Bootstrap 5 vs altra libreria?
- [ ] Invio email: driver SMTP nativo vs Mailgun vs SES?
- [ ] Redis per queue e cache in produzione?
- [ ] Deploy: server VPS dedicato, Docker Compose, o hosting PHP classico?
- [ ] Versioning API (prefisso `/api/v1/`)?

---

> Documento: `02-stack-tecnologico.md` — Versione 0.1 (brainstorming) — febbraio 2026
