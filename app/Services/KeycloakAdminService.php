<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class KeycloakAdminService
{
    private const MANAGED_ROLES = [
        'utente',
        'operatore_ente',
        'admin_ente',
        'admin',
        'staff_mfa_required',
    ];

    private ?string $accessToken = null;

    public function syncUser(User $user, ?string $plainPassword = null, bool $temporaryPassword = false): void
    {
        if (!$this->isSyncEnabled()) {
            return;
        }

        $keycloakUser = $this->findUserByKeycloakIdOrEmail($user->keycloak_id, $user->email);

        $payload = [
            'username' => $user->email,
            'email' => $user->email,
            'firstName' => $user->nome,
            'lastName' => $user->cognome,
            'enabled' => (bool) $user->attivo,
            'emailVerified' => $user->email_verified_at !== null,
            'attributes' => $this->buildAttributes($user),
        ];

        if ($keycloakUser === null) {
            $keycloakId = $this->createUser($payload, $user->email);
            $user->forceFill(['keycloak_id' => $keycloakId])->save();
        } else {
            $keycloakId = (string) ($keycloakUser['id'] ?? '');
            if ($keycloakId === '') {
                throw new RuntimeException('Utente Keycloak trovato senza id.');
            }

            $this->updateUser($keycloakId, $payload);
            if ($user->keycloak_id !== $keycloakId) {
                $user->forceFill(['keycloak_id' => $keycloakId])->save();
            }
        }

        if ($plainPassword !== null && $plainPassword !== '') {
            $this->setUserPassword($keycloakId, $plainPassword, $temporaryPassword);
        }

        $this->syncUserRoles($keycloakId, $user->role);
    }

    public function deleteUser(User $user): void
    {
        if (!$this->isSyncEnabled()) {
            return;
        }

        $keycloakUser = $this->findUserByKeycloakIdOrEmail($user->keycloak_id, $user->email);
        if ($keycloakUser === null) {
            return;
        }

        $keycloakId = (string) ($keycloakUser['id'] ?? '');
        if ($keycloakId === '') {
            return;
        }

        $response = $this->adminRequest()->delete($this->adminEndpoint("/users/{$keycloakId}"));
        if (!$response->successful() && $response->status() !== 404) {
            throw new RuntimeException('Errore eliminazione utente su Keycloak: ' . $response->body());
        }
    }

    private function isSyncEnabled(): bool
    {
        return config('auth_provider.driver') === 'keycloak'
            && (bool) config('services.keycloak.sync_users', false);
    }

    private function buildAttributes(User $user): array
    {
        $attributes = [];

        if ($user->ente_id !== null) {
            $attributes['ente_id'] = [(string) $user->ente_id];
        }

        return $attributes;
    }

    private function findUserByKeycloakIdOrEmail(?string $keycloakId, string $email): ?array
    {
        if ($keycloakId) {
            $response = $this->adminRequest()->get($this->adminEndpoint("/users/{$keycloakId}"));
            if ($response->successful()) {
                return $response->json();
            }
        }

        $response = $this->adminRequest()->get($this->adminEndpoint('/users'), [
            'email' => $email,
            'exact' => 'true',
            'max' => 2,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Errore ricerca utente su Keycloak: ' . $response->body());
        }

        $users = $response->json();
        if (!is_array($users) || empty($users)) {
            return null;
        }

        return $users[0];
    }

    private function createUser(array $payload, string $email): string
    {
        $response = $this->adminRequest()->post($this->adminEndpoint('/users'), $payload);

        if (!$response->successful() && $response->status() !== 201) {
            throw new RuntimeException('Errore creazione utente su Keycloak: ' . $response->body());
        }

        $location = $response->header('Location');
        if (is_string($location) && $location !== '') {
            $parts = explode('/', rtrim($location, '/'));
            $id = end($parts);
            if (is_string($id) && $id !== '') {
                return $id;
            }
        }

        $created = $this->findUserByKeycloakIdOrEmail(null, $email);
        if ($created && !empty($created['id'])) {
            return (string) $created['id'];
        }

        throw new RuntimeException('Impossibile recuperare ID utente creato su Keycloak.');
    }

    private function updateUser(string $keycloakId, array $payload): void
    {
        $response = $this->adminRequest()->put($this->adminEndpoint("/users/{$keycloakId}"), $payload);
        if (!$response->successful() && $response->status() !== 204) {
            throw new RuntimeException('Errore aggiornamento utente su Keycloak: ' . $response->body());
        }
    }

    private function setUserPassword(string $keycloakId, string $password, bool $temporary): void
    {
        $response = $this->adminRequest()->put($this->adminEndpoint("/users/{$keycloakId}/reset-password"), [
            'type' => 'password',
            'value' => $password,
            'temporary' => $temporary,
        ]);

        if (!$response->successful() && $response->status() !== 204) {
            throw new RuntimeException('Errore aggiornamento password su Keycloak: ' . $response->body());
        }
    }

    private function syncUserRoles(string $keycloakId, string $localRole): void
    {
        $assignedResponse = $this->adminRequest()->get($this->adminEndpoint("/users/{$keycloakId}/role-mappings/realm"));
        if (!$assignedResponse->successful()) {
            throw new RuntimeException('Errore lettura ruoli utente su Keycloak: ' . $assignedResponse->body());
        }

        $assignedRoles = $assignedResponse->json();
        if (!is_array($assignedRoles)) {
            $assignedRoles = [];
        }

        $toRemove = array_values(array_filter($assignedRoles, function ($role) {
            $name = (string) ($role['name'] ?? '');
            return in_array($name, self::MANAGED_ROLES, true);
        }));

        $toRemove = array_values(array_filter(array_map(function ($role) {
            $id = (string) ($role['id'] ?? '');
            $name = (string) ($role['name'] ?? '');

            if ($id === '' || $name === '') {
                return null;
            }

            return [
                'id' => $id,
                'name' => $name,
            ];
        }, $toRemove)));

        if (!empty($toRemove)) {
            $removeResponse = $this->adminRequest()
                ->withBody(json_encode($toRemove, JSON_UNESCAPED_UNICODE), 'application/json')
                ->send('DELETE', $this->adminEndpoint("/users/{$keycloakId}/role-mappings/realm"));

            if (!$removeResponse->successful() && $removeResponse->status() !== 204) {
                throw new RuntimeException('Errore rimozione ruoli su Keycloak: ' . $removeResponse->body());
            }
        }

        $targetRoleNames = [$localRole];
        if (in_array($localRole, ['admin', 'admin_ente', 'operatore_ente'], true)) {
            $targetRoleNames[] = 'staff_mfa_required';
        }

        $targetRoleNames = array_values(array_unique($targetRoleNames));

        $roleRepresentations = [];
        foreach ($targetRoleNames as $roleName) {
            $roleResponse = $this->adminRequest()->get($this->adminEndpoint('/roles/' . urlencode($roleName)));
            if ($roleResponse->status() === 404) {
                Log::warning('Ruolo Keycloak non trovato durante sync utente', ['role' => $roleName]);
                continue;
            }

            if (!$roleResponse->successful()) {
                throw new RuntimeException('Errore lettura ruolo Keycloak ' . $roleName . ': ' . $roleResponse->body());
            }

            $roleData = $roleResponse->json();
            $roleId = (string) ($roleData['id'] ?? '');
            $resolvedName = (string) ($roleData['name'] ?? '');

            if ($roleId === '' || $resolvedName === '') {
                continue;
            }

            $roleRepresentations[] = [
                'id' => $roleId,
                'name' => $resolvedName,
            ];
        }

        if (empty($roleRepresentations)) {
            return;
        }

        $addResponse = $this->adminRequest()
            ->withBody(json_encode($roleRepresentations, JSON_UNESCAPED_UNICODE), 'application/json')
            ->post($this->adminEndpoint("/users/{$keycloakId}/role-mappings/realm"));
        if (!$addResponse->successful() && $addResponse->status() !== 204) {
            throw new RuntimeException('Errore assegnazione ruoli su Keycloak: ' . $addResponse->body());
        }
    }

    private function adminRequest()
    {
        return Http::withToken($this->getAccessToken())
            ->withOptions([
                'verify' => (bool) config('services.keycloak.guzzle.verify', true),
            ])
            ->acceptJson();
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $baseUrl = rtrim((string) config('services.keycloak.base_url'), '/');
        $adminRealm = (string) config('services.keycloak.admin_realm', config('services.keycloak.realm'));
        $clientId = (string) config('services.keycloak.admin_client_id', config('services.keycloak.client_id'));
        $clientSecret = (string) config('services.keycloak.admin_client_secret', config('services.keycloak.client_secret'));

        if ($baseUrl === '' || $adminRealm === '' || $clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Configurazione Keycloak Admin incompleta (base_url/realm/client_id/client_secret).');
        }

        $response = Http::asForm()
            ->withOptions([
                'verify' => (bool) config('services.keycloak.guzzle.verify', true),
            ])
            ->post("{$baseUrl}/realms/{$adminRealm}/protocol/openid-connect/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Errore autenticazione admin su Keycloak: ' . $response->body());
        }

        $token = (string) ($response->json('access_token') ?? '');
        if ($token === '') {
            throw new RuntimeException('Token admin Keycloak non ricevuto.');
        }

        $this->accessToken = $token;
        return $this->accessToken;
    }

    private function adminEndpoint(string $path): string
    {
        $baseUrl = rtrim((string) config('services.keycloak.base_url'), '/');
        $realm = (string) config('services.keycloak.realm');

        if ($baseUrl === '' || $realm === '') {
            throw new RuntimeException('Configurazione Keycloak incompleta (base_url/realm).');
        }

        return "{$baseUrl}/admin/realms/{$realm}{$path}";
    }
}
