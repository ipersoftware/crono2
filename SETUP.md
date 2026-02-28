# SETUP.md - Progetto Ermes Completato ✅

## 📋 Struttura Completa Creata

### Backend Laravel
```
✅ Composer.json con Laravel 11
✅ Configurazioni (app, auth, database, sanctum, cors, services)
✅ Migrations (users, enti, cache, jobs, tokens)
✅ Models (User, Ente)
✅ Controllers (AuthController, UserController, EnteController)
✅ Services (KeycloakAdminService, KeycloakSyncService)
✅ Routes API complete
✅ .env.example configurato
```

### Frontend Vue.js 3
```
✅ Package.json con Vue 3, Vite, Router, Pinia
✅ Vite config
✅ Router con protezione route
✅ Pinia store per auth
✅ API client con Axios
✅ Views: Login, Register, Home, Users, Enti
✅ App.vue con navigazione
```

### Documentazione
```
✅ README.md - Guida completa
✅ QUICKSTART.md - Avvio rapido
✅ KEYCLOAK.md - Configurazione Keycloak dettagliata
```

## 🚀 Prossimi Passi per Avviare

### 1. Installa Dipendenze Backend
```powershell
cd c:\workspaces\phpworkspace\ermes
composer install
```

### 2. Configura Environment
```powershell
copy .env.example .env
php artisan key:generate
```

### 3. Configura Database
Nel file `.env` modifica:
```env
DB_DATABASE=ermes
DB_USERNAME=root
DB_PASSWORD=your_password
```

Crea il database:
```sql
CREATE DATABASE ermes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Esegui migrations:
```powershell
php artisan migrate
```

### 4. Installa Dipendenze Frontend
```powershell
npm install
```

### 5. Avvia i Server

**Terminal 1 - Laravel:**
```powershell
php artisan serve
```

**Terminal 2 - Vite:**
```powershell
npm run dev
```

### 6. Crea Primo Utente Admin
```powershell
php artisan tinker
```

```php
App\Models\User::create([
    'nome' => 'Admin',
    'cognome' => 'Sistema',
    'email' => 'admin@ermes.local',
    'password' => bcrypt('password'),
    'role' => 'admin',
    'attivo' => true,
    'primo_accesso_eseguito' => true,
]);
```

## 🔐 Setup Keycloak (Opzionale)

### Quick Start con Docker
```powershell
docker run -d -p 8080:8080 `
  -e KEYCLOAK_ADMIN=admin `
  -e KEYCLOAK_ADMIN_PASSWORD=admin `
  --name keycloak `
  quay.io/keycloak/keycloak:latest start-dev
```

### Configurazione Minima
1. Accedi: http://localhost:8080 (admin/admin)
2. Crea Realm: `ermes`
3. Crea Client: `ermes-client` (confidential)
4. Copia Client Secret
5. Crea ruoli: utente, operatore_ente, admin_ente, admin
6. Aggiorna `.env`:
```env
AUTH_PROVIDER=keycloak
KEYCLOAK_BASE_URL=http://localhost:8080
KEYCLOAK_CLIENT_SECRET=your-secret
```

**Vedi KEYCLOAK.md per dettagli completi**

## 📱 Accesso Applicazione

- **Frontend**: http://localhost:5173
- **Backend API**: http://localhost:8000/api
- **Keycloak Admin**: http://localhost:8080 (se installato)

## 🎯 Funzionalità Implementate

### Autenticazione
- ✅ Login/Logout
- ✅ Registrazione utenti
- ✅ Token-based auth (Sanctum)
- ✅ Integrazione Keycloak opzionale
- ✅ Sincronizzazione automatica users

### Gestione Utenti
- ✅ CRUD completo
- ✅ Ruoli: utente, operatore_ente, admin_ente, admin
- ✅ Associazione a Enti
- ✅ Attivazione/Disattivazione

### Gestione Enti
- ✅ CRUD completo
- ✅ Dati completi (CF, PIVA, indirizzo, ecc.)
- ✅ Soft Delete

### Frontend Features
- ✅ Routing protetto
- ✅ State management (Pinia)
- ✅ Interfaccia responsive
- ✅ Gestione errori
- ✅ Auto-redirect su 401

## 🔧 Configurazione Avanzata

### Cambia Provider Auth
**Laravel puro:**
```env
AUTH_PROVIDER=laravel
```

**Con Keycloak:**
```env
AUTH_PROVIDER=keycloak
KEYCLOAK_SYNC_USERS=true
```

### Personalizzazione Ruoli
Modifica in:
- Migration: `0001_01_01_000000_create_users_table.php`
- Model: `app/Models/User.php`
- Controllers: `app/Http/Controllers/*`
- Keycloak: Crea i realm roles corrispondenti

## 📚 API Endpoints Disponibili

### Pubblici
```
POST /api/auth/register
POST /api/auth/login
GET  /api/auth/provider
GET  /api/health
```

### Autenticati (Bearer Token)
```
POST   /api/auth/logout
GET    /api/auth/me
GET    /api/users
POST   /api/users
GET    /api/users/{id}
PUT    /api/users/{id}
DELETE /api/users/{id}
GET    /api/enti
POST   /api/enti
GET    /api/enti/{id}
PUT    /api/enti/{id}
DELETE /api/enti/{id}
```

## 🧪 Test Rapido

### Test Login
```powershell
curl -X POST http://localhost:8000/api/auth/login `
  -H "Content-Type: application/json" `
  -d '{\"email\":\"admin@ermes.local\",\"password\":\"password\"}'
```

### Test API Protetta
```powershell
curl http://localhost:8000/api/users `
  -H "Authorization: Bearer YOUR_TOKEN"
```

## 🔍 Troubleshooting

### Errore: "Class not found"
```powershell
composer install
composer dump-autoload
```

### Errore: Storage/Cache non writable
```powershell
# Windows
icacls storage /grant Everyone:(OI)(CI)F /T
icacls bootstrap/cache /grant Everyone:(OI)(CI)F /T
```

### Errore CORS
Verifica `.env`:
```env
FRONTEND_URL=http://localhost:5173
```

### Frontend non connette al backend
Verifica proxy in `vite.config.js` e che Laravel sia su porta 8000

## 📝 TODO per Produzione

- [ ] Configura .env production
- [ ] Setup SSL/HTTPS
- [ ] Configura queue worker
- [ ] Setup log rotation
- [ ] Abilita cache
- [ ] Ottimizza config: `php artisan config:cache`
- [ ] Ottimizza routes: `php artisan route:cache`
- [ ] Build frontend: `npm run build`
- [ ] Setup deployment pipeline
- [ ] Backup database strategy
- [ ] Monitoring e alerting

## 🎯 Personalizzazioni Suggerite

1. **Logo/Brand**: Sostituisci favicon in `public/`
2. **Email**: Configura SMTP in `.env`
3. **Theme**: Personalizza colori in `App.vue`
4. **Traduzioni**: Aggiungi i18n se necessario
5. **Validazioni**: Estendi regole nei controllers
6. **Middleware**: Aggiungi per permission check
7. **Tests**: Setup PHPUnit e Vitest

## 📞 Supporto

Per problemi o domande:
1. Controlla QUICKSTART.md
2. Verifica log Laravel: `storage/logs/laravel.log`
3. Controlla console browser (F12)
4. Verifica Keycloak logs (se usato)

## 🎉 Progetto Pronto!

Il progetto è completamente configurato e pronto per:
- ✅ Sviluppo locale
- ✅ Estensione con nuove features
- ✅ Deploy in produzione (dopo configurazione)

**Buon lavoro con Ermes! 🚀**