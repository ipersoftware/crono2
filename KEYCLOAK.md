# Guida Keycloak - Ermes

## Installazione Keycloak

### Opzione 1: Docker (Raccomandato)

```powershell
docker run -d `
  -p 8080:8080 `
  -e KEYCLOAK_ADMIN=admin `
  -e KEYCLOAK_ADMIN_PASSWORD=admin `
  --name keycloak `
  quay.io/keycloak/keycloak:latest start-dev
```

### Opzione 2: Download Standalone

1. Scarica da: https://www.keycloak.org/downloads
2. Estrai l'archivio
3. Avvia:
```powershell
cd keycloak-XX.X.X\bin
.\kc.bat start-dev
```

## Configurazione Realm "ermes"

### 1. Accedi alla Admin Console
- URL: http://localhost:8080
- Username: `admin`
- Password: `admin`

### 2. Crea Realm
1. Click su dropdown "master" in alto a sinistra
2. Click "Create Realm"
3. Nome: `ermes`
4. Click "Create"

### 3. Configura Client per Backend

1. Vai in **Clients** → **Create client**
2. **General Settings**:
   - Client type: `OpenID Connect`
   - Client ID: `ermes-client`
3. **Capability config**:
   - Client authentication: `ON`
   - Authorization: `OFF`
   - Standard flow: `ON`
   - Direct access grants: `ON`
4. **Login settings**:
   - Valid redirect URIs: `http://localhost:8000/*`
   - Valid post logout redirect URIs: `http://localhost:8000/*`
   - Web origins: `http://localhost:8000`

5. Vai in **Credentials** tab
6. Copia il **Client Secret**
7. Aggiornalo nel `.env`:
```env
KEYCLOAK_CLIENT_SECRET=xxx-your-secret-xxx
```

### 4. Configura Client Admin (per API amministrative)

1. **Clients** → **Create client**
2. Client ID: `admin-cli`
3. Client authentication: `ON`
4. Service accounts roles: `ON`
5. Credentials → Copia il secret
6. Service Account Roles:
   - Assegna ruolo `manage-users`
   - Assegna ruolo `manage-clients`

Aggiorna `.env`:
```env
KEYCLOAK_ADMIN_CLIENT_ID=admin-cli
KEYCLOAK_ADMIN_CLIENT_SECRET=xxx-admin-secret-xxx
```

### 5. Crea Realm Roles

Vai in **Realm roles** → **Create role**

Crea questi ruoli:
- `utente`
- `operatore_ente`
- `admin_ente`
- `admin`
- `staff_mfa_required`

### 6. Configura Default Roles

1. Vai in **Realm roles**
2. Click su **Default roles**
3. Aggiungi `utente` ai default roles

## Creazione Utenti in Keycloak

### Via Admin Console

1. **Users** → **Add user**
2. Compila:
   - Username: email dell'utente
   - Email: stesso email
   - First name: nome
   - Last name: cognome
   - Email verified: ON
3. **Credentials** tab:
   - Set password
   - Temporary: OFF
4. **Role mapping** tab:
   - Assegna il ruolo appropriato

### Via Laravel (Automatico)

Quando crei un utente tramite API Laravel, viene automaticamente sincronizzato con Keycloak se `AUTH_PROVIDER=keycloak`:

```php
POST /api/users
{
  "nome": "Mario",
  "cognome": "Rossi",
  "email": "mario.rossi@example.com",
  "password": "SecurePass123!",
  "role": "utente"
}
```

## Attributi Custom (Opzionale)

Per salvare `ente_id` e `struttura_id` in Keycloak:

1. **Users** → seleziona utente
2. **Attributes** tab
3. Aggiungi:
   - Key: `ente_id`, Value: `1`
   - Key: `struttura_id`, Value: `2`

Questi vengono sincronizzati automaticamente dal servizio Laravel.

## Testing Keycloak

### Test Token

```powershell
# Ottieni token
curl -X POST http://localhost:8080/realms/ermes/protocol/openid-connect/token `
  -H "Content-Type: application/x-www-form-urlencoded" `
  -d "client_id=ermes-client" `
  -d "client_secret=YOUR_CLIENT_SECRET" `
  -d "grant_type=password" `
  -d "username=test@example.com" `
  -d "password=password"
```

### Verifica Token

```powershell
curl http://localhost:8080/realms/ermes/protocol/openid-connect/userinfo `
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

## Integrazione Frontend

Il frontend può utilizzare Keycloak direttamente:

### Opzione 1: Via Laravel (Consigliato per questo progetto)
Il frontend fa login tramite API Laravel che gestisce Keycloak internamente.

### Opzione 2: Direct Keycloak (Avanzato)
Installare `keycloak-js`:
```bash
npm install keycloak-js
```

## Troubleshooting

### Errore "Invalid redirect uri"
Verifica che gli URI in Keycloak Client matchino esattamente quelli configurati.

### Errore "Client authentication failed"
Verifica che il Client Secret in `.env` sia corretto.

### Errore "Role not found"
Assicurati che tutti i ruoli siano stati creati nel Realm.

### Utenti non sincronizzano
Verifica:
```env
KEYCLOAK_SYNC_USERS=true
AUTH_PROVIDER=keycloak
```

## Best Practices

1. **Produzione**: Cambia password admin di Keycloak
2. **SSL**: Abilita HTTPS in produzione
3. **Realm**: Usa realm separati per dev/staging/prod
4. **Backup**: Esporta configurazione realm regolarmente
5. **MFA**: Abilita per utenti admin/staff

## Export/Import Configurazione

### Export Realm
```powershell
docker exec -it keycloak /opt/keycloak/bin/kc.sh export --dir /tmp/export --realm ermes
```

### Import Realm
```powershell
# Copia export file in container
docker cp realm-export.json keycloak:/tmp/

# Import
docker exec -it keycloak /opt/keycloak/bin/kc.sh import --file /tmp/realm-export.json
```

## Link Utili

- Admin Console: http://localhost:8080/admin
- Realm ermes: http://localhost:8080/realms/ermes
- Documentazione: https://www.keycloak.org/documentation
