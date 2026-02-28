<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class KeycloakSyncService
{
    public function syncFromKeycloakUser(object $keycloakUser): User
    {
        $raw = (array) ($keycloakUser->user ?? []);

        $keycloakId = $keycloakUser->id ?? Arr::get($raw, 'sub');
        $email = strtolower((string) ($keycloakUser->email ?? Arr::get($raw, 'email', '')));

        $nome = (string) ($keycloakUser->user['given_name'] ?? Arr::get($raw, 'given_name', $keycloakUser->name ?? ''));
        $cognome = (string) ($keycloakUser->user['family_name'] ?? Arr::get($raw, 'family_name', ''));

        if ($nome === '' && $keycloakUser->name) {
            $parts = explode(' ', trim((string) $keycloakUser->name), 2);
            $nome = $parts[0] ?? '';
            $cognome = $parts[1] ?? '';
        }

        $role = $this->extractMappedRole($raw);
        $enteId = $this->extractNullableInt($raw, 'ente_id');

        $user = User::query()
            ->where('email', $email)
            ->orWhere('keycloak_id', $keycloakId)
            ->first();

        $attributes = [
            'nome' => $nome !== '' ? $nome : 'Utente',
            'cognome' => $cognome,
            'email' => $email,
            'keycloak_id' => $keycloakId,
            'last_login_provider' => 'keycloak',
            'attivo' => true,
            'primo_accesso_eseguito' => true,
        ];

        if ($role !== null) {
            $attributes['role'] = $role;
        }

        if ($enteId !== null) {
            $attributes['ente_id'] = $enteId;
        }

        if ($this->isEmailVerified($raw)) {
            $attributes['email_verified_at'] = now();
        }

        if ($user) {
            $user->fill($attributes);
            $user->save();
            return $user;
        }

        $attributes['password'] = Hash::make(bin2hex(random_bytes(16)));
        return User::create($attributes);
    }

    private function extractMappedRole(array $raw): ?string
    {
        $roles = [];

        $realmRoles = Arr::get($raw, 'realm_access.roles', []);
        if (is_array($realmRoles)) {
            $roles = array_merge($roles, $realmRoles);
        }

        $resourceAccess = Arr::get($raw, 'resource_access', []);
        if (is_array($resourceAccess)) {
            foreach ($resourceAccess as $clientAccess) {
                if (is_array($clientAccess) && isset($clientAccess['roles']) && is_array($clientAccess['roles'])) {
                    $roles = array_merge($roles, $clientAccess['roles']);
                }
            }
        }

        $roles = array_values(array_unique(array_map('strval', $roles)));

        $priority = [
            'admin',
            'admin_ente',
            'operatore_ente',
            'utente',
            'manager',
            'user',
        ];

        $selected = null;
        foreach ($priority as $candidate) {
            if (in_array($candidate, $roles, true)) {
                $selected = $candidate;
                break;
            }
        }

        return match ($selected) {
            'manager' => 'operatore_ente',
            'user' => 'utente',
            default => $selected,
        };
    }

    private function isEmailVerified(array $raw): bool
    {
        return (bool) Arr::get($raw, 'email_verified', false);
    }

    private function extractNullableInt(array $raw, string $key): ?int
    {
        $value = Arr::get($raw, "attributes.$key");

        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if ($value === null || $value === '') {
            $value = Arr::get($raw, $key);
        }

        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
