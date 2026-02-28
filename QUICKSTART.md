# Quick Start - Ermes

## 1. Installazione Rapida

### Backend Laravel
```powershell
cd c:\workspaces\phpworkspace\ermes

# Installa dipendenze
composer install

# Configura .env
copy .env.example .env

# Genera key
php artisan key:generate

# Crea database 'ermes' in MySQL
# Poi esegui migrations
php artisan migrate

# Avvia server Laravel
php artisan serve
```

### Frontend Vue.js
```powershell
# In una nuova finestra terminal
cd c:\workspaces\phpworkspace\ermes

# Installa dipendenze
npm install

# Avvia dev server
npm run dev
```

## 2. Accesso

- **Frontend**: http://localhost:5173
- **Backend API**: http://localhost:8000/api
- **Health Check**: http://localhost:8000/api/health

## 3. Primo Utente

### Registrazione via Frontend
1. Vai su http://localhost:5173/register
2. Compila il form
3. Login con le credenziali create

### Creazione Admin via Tinker
```powershell
php artisan tinker
```

```php
$user = App\Models\User::create([
    'nome' => 'Admin',
    'cognome' => 'Sistema',
    'email' => 'admin@ermes.local',
    'password' => bcrypt('password'),
    'role' => 'admin',
    'attivo' => true,
    'primo_accesso_eseguito' => true,
]);
```

## 4. Configurazione Laravel + Keycloak (Opzionale)

Modifica `.env`:
```env
AUTH_PROVIDER=laravel
```

Per abilitare Keycloak:
```env
AUTH_PROVIDER=keycloak
KEYCLOAK_BASE_URL=http://localhost:8080
KEYCLOAK_REALM=ermes
KEYCLOAK_CLIENT_ID=ermes-client
KEYCLOAK_CLIENT_SECRET=your-secret-here
```

## 5. Test API

### Login
```powershell
curl -X POST http://localhost:8000/api/auth/login `
  -H "Content-Type: application/json" `
  -d '{"email":"admin@ermes.local","password":"password"}'
```

### Lista Utenti (con token)
```powershell
curl http://localhost:8000/api/users `
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## 6. Struttura Database

### Tabelle principali
- `users` - Utenti del sistema
- `enti` - Enti/Organizzazioni
- `personal_access_tokens` - Token Sanctum

### Ruoli disponibili
- `utente` - Utente base
- `operatore_ente` - Operatore di un ente
- `admin_ente` - Amministratore di un ente
- `admin` - Amministratore di sistema

## Troubleshooting

### Errore CORS
Verifica che in `.env` sia presente:
```env
FRONTEND_URL=http://localhost:5173
```

### Errore 500 Laravel
Controlla i permessi storage:
```powershell
# Windows
icacls storage /grant Everyone:(OI)(CI)F /T
icacls bootstrap/cache /grant Everyone:(OI)(CI)F /T
```

### Database non connette
Verifica credenziali in `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ermes
DB_USERNAME=root
DB_PASSWORD=
```
