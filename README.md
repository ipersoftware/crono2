# Ermes - Gestionale Laravel + Vue.js + Keycloak

Progetto startup con Laravel 11, Vue.js 3 e integrazione Keycloak per l'autenticazione.

## 🚀 Caratteristiche

- **Backend**: Laravel 11 (ultima versione)
- **Frontend**: Vue.js 3 + Vite
- **Autenticazione**: Laravel Sanctum + Keycloak (opzionale)
- **State Management**: Pinia
- **Routing**: Vue Router
- **Gestione Utenti**: CRUD completo con ruoli
- **Gestione Enti**: CRUD completo
- **Sincronizzazione Keycloak**: Automatica per utenti e ruoli

## 📋 Prerequisiti

- PHP >= 8.2
- Composer
- Node.js >= 18
- MySQL/MariaDB
- Keycloak (opzionale, per autenticazione SSO)

## 🛠️ Installazione

### 1. Clona il repository

```bash
cd c:\workspaces\phpworkspace\ermes
```

### 2. Backend Laravel

```bash
# Installa dipendenze PHP
composer install

# Copia il file .env
copy .env.example .env

# Genera application key
php artisan key:generate

# Configura il database in .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=ermes
# DB_USERNAME=root
# DB_PASSWORD=

# Esegui le migrations
php artisan migrate

# Avvia il server
php artisan serve
```

### 3. Frontend Vue.js

```bash
# Installa dipendenze Node
npm install

# Avvia il dev server
npm run dev
```

Il frontend sarà disponibile su `http://localhost:5173`
Il backend API su `http://localhost:8000`

## 🔐 Configurazione Keycloak

### 1. Installa e avvia Keycloak

```bash
# Con Docker
docker run -p 8080:8080 -e KEYCLOAK_ADMIN=admin -e KEYCLOAK_ADMIN_PASSWORD=admin quay.io/keycloak/keycloak:latest start-dev
```

### 2. Configura Realm e Client

1. Accedi a Keycloak Admin Console: `http://localhost:8080`
2. Crea un nuovo Realm chiamato `ermes`
3. Crea un Client:
   - Client ID: `ermes-client`
   - Client Protocol: `openid-connect`
   - Access Type: `confidential`
   - Valid Redirect URIs: `http://localhost:8000/auth/keycloak/callback`
4. Copia il Client Secret dalla tab Credentials

### 3. Crea i ruoli Keycloak

Vai in Realm Roles e crea:
- `utente`
- `operatore_ente`
- `admin_ente`
- `admin`
- `staff_mfa_required`

### 4. Configura .env

```env
AUTH_PROVIDER=keycloak

KEYCLOAK_BASE_URL=http://localhost:8080
KEYCLOAK_REALM=ermes
KEYCLOAK_CLIENT_ID=ermes-client
KEYCLOAK_CLIENT_SECRET=your-client-secret-here
KEYCLOAK_REDIRECT_URI=http://localhost:8000/auth/keycloak/callback
KEYCLOAK_SYNC_USERS=true
KEYCLOAK_ADMIN_REALM=ermes
KEYCLOAK_ADMIN_CLIENT_ID=admin-cli
KEYCLOAK_ADMIN_CLIENT_SECRET=your-admin-secret
```

## 📁 Struttura Progetto

```
ermes/
├── app/
│   ├── Http/Controllers/
│   │   ├── AuthController.php
│   │   ├── UserController.php
│   │   └── EnteController.php
│   ├── Models/
│   │   ├── User.php
│   │   └── Ente.php
│   └── Services/
│       ├── KeycloakAdminService.php
│       └── KeycloakSyncService.php
├── database/migrations/
├── resources/js/
│   ├── api/
│   ├── components/
│   ├── router/
│   ├── stores/
│   ├── views/
│   ├── App.vue
│   └── main.js
├── routes/
│   └── api.php
└── config/
    ├── auth_provider.php
    └── services.php
```

## 🎯 Ruoli Utente

- **utente**: Utente base
- **operatore_ente**: Operatore di un ente
- **admin_ente**: Amministratore di un ente
- **admin**: Amministratore di sistema

## 📡 API Endpoints

### Autenticazione
- `POST /api/auth/register` - Registrazione
- `POST /api/auth/login` - Login
- `POST /api/auth/logout` - Logout
- `GET /api/auth/me` - Utente corrente
- `GET /api/auth/provider` - Info provider autenticazione

### Utenti (richiede autenticazione)
- `GET /api/users` - Lista utenti
- `GET /api/users/{id}` - Dettaglio utente
- `POST /api/users` - Crea utente
- `PUT /api/users/{id}` - Aggiorna utente
- `DELETE /api/users/{id}` - Elimina utente

### Enti (richiede autenticazione)
- `GET /api/enti` - Lista enti
- `GET /api/enti/{id}` - Dettaglio ente
- `POST /api/enti` - Crea ente
- `PUT /api/enti/{id}` - Aggiorna ente
- `DELETE /api/enti/{id}` - Elimina ente

## 🔧 Sviluppo

```bash
# Backend
php artisan serve

# Frontend
npm run dev

# Build production
npm run build
```

## 📝 Note

- Il progetto è basato sul progetto **smartpass** per la configurazione Keycloak
- La sincronizzazione con Keycloak avviene automaticamente quando `AUTH_PROVIDER=keycloak`
- Gli utenti possono essere gestiti sia tramite Laravel che tramite Keycloak
- Per autenticazione locale impostare `AUTH_PROVIDER=laravel`

## 🤝 Contribuire

Questo è un progetto startup. Personalizza secondo le tue esigenze!

## 📄 Licenza

MIT