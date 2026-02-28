<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\KeycloakAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly KeycloakAdminService $keycloakAdminService)
    {
    }

    public function provider()
    {
        $driver = config('auth_provider.driver', 'laravel');

        return response()->json([
            'driver' => $driver,
            'keycloak_login_url' => url('/auth/keycloak'),
        ]);
    }

    /**
     * Registrazione nuovo utente
     */
    public function register(Request $request)
    {
        $isKeycloakAuth = config('auth_provider.driver') === 'keycloak';

        $validated = $request->validate([
            'cognome' => ['required', 'string', 'max:255'],
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'telefono' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'cognome' => $validated['cognome'],
            'nome' => $validated['nome'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'telefono' => $validated['telefono'] ?? null,
            'role' => 'utente',
            'attivo' => true,
            'primo_accesso_eseguito' => true,
        ]);

        if ($isKeycloakAuth) {
            try {
                $this->keycloakAdminService->syncUser($user, $validated['password'], false);
            } catch (\Throwable $exception) {
                $user->delete();

                return response()->json([
                    'message' => 'Errore sincronizzazione registrazione con Keycloak: ' . $exception->getMessage(),
                ], 502);
            }
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Registrazione completata con successo.',
            'user' => $user->load(['ente']),
            'token' => $token,
        ], 201);
    }

    /**
     * Login utente
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Le credenziali fornite non sono corrette.'],
            ]);
        }

        if (!$user->attivo) {
            throw ValidationException::withMessages([
                'email' => ['Il tuo account è stato disattivato.'],
            ]);
        }

        if (config('auth_provider.driver') === 'keycloak' && in_array($user->role, [
            'operatore_ente',
            'admin_ente',
            'admin',
        ], true)) {
            throw ValidationException::withMessages([
                'email' => ['Per questo account devi accedere tramite Keycloak.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login effettuato con successo',
            'user' => $user->load(['ente']),
            'token' => $token,
            'role' => $user->role,
            'primo_accesso' => !$user->primo_accesso_eseguito,
        ]);
    }

    /**
     * Logout utente
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        $response = [
            'message' => 'Logout effettuato con successo',
        ];

        // Se l'autenticazione è via Keycloak, aggiungi l'URL di logout SSO
        if (config('auth_provider.driver') === 'keycloak') {
            $baseUrl = config('services.keycloak.base_url');
            $realm = config('services.keycloak.realm');
            $clientId = config('services.keycloak.client_id');
            $postLogoutRedirectUri = config('app.url') . '/login';

            $keycloakLogoutUrl = sprintf(
                '%s/realms/%s/protocol/openid-connect/logout?post_logout_redirect_uri=%s&client_id=%s',
                $baseUrl,
                $realm,
                urlencode($postLogoutRedirectUri),
                urlencode($clientId)
            );

            $response['keycloak_logout_url'] = $keycloakLogoutUrl;
        }

        return response()->json($response);
    }

    /**
     * Utente corrente
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load(['ente']),
        ]);
    }

    /**
     * Redirect to Keycloak login
     */
    public function redirectToKeycloak()
    {
        $baseUrl = config('services.keycloak.base_url');
        $realm = config('services.keycloak.realm');
        $clientId = config('services.keycloak.client_id');
        $redirectUri = config('services.keycloak.redirect');

        $authUrl = sprintf(
            '%s/realms/%s/protocol/openid-connect/auth?client_id=%s&redirect_uri=%s&response_type=code&scope=openid&prompt=none',
            $baseUrl,
            $realm,
            urlencode($clientId),
            urlencode($redirectUri)
        );

        return redirect($authUrl);
    }

    /**
     * Handle Keycloak callback
     */
    public function handleKeycloakCallback(Request $request)
    {
        $code = $request->input('code');
        $error = $request->input('error');

        // Handle prompt=none errors (no SSO session)
        if ($error === 'login_required' || $error === 'interaction_required') {
            // No SSO session exists, redirect to normal login
            $baseUrl = config('services.keycloak.base_url');
            $realm = config('services.keycloak.realm');
            $clientId = config('services.keycloak.client_id');
            $redirectUri = config('services.keycloak.redirect');

            $authUrl = sprintf(
                '%s/realms/%s/protocol/openid-connect/auth?client_id=%s&redirect_uri=%s&response_type=code&scope=openid',
                $baseUrl,
                $realm,
                urlencode($clientId),
                urlencode($redirectUri)
            );

            return redirect($authUrl);
        }

        if (!$code) {
            return redirect(config('app.url') . '/login?error=no_code');
        }

        try {
            // Exchange code for token
            $baseUrl = config('services.keycloak.base_url');
            $realm = config('services.keycloak.realm');
            $clientId = config('services.keycloak.client_id');
            $clientSecret = config('services.keycloak.client_secret');
            $redirectUri = config('services.keycloak.redirect');

            $client = new \GuzzleHttp\Client(['verify' => config('services.keycloak.guzzle.verify', true)]);
            
            $response = $client->post(sprintf('%s/realms/%s/protocol/openid-connect/token', $baseUrl, $realm), [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                ],
            ]);

            $tokens = json_decode($response->getBody(), true);
            $accessToken = $tokens['access_token'];

            // Decode JWT to get roles (without verification for simplicity)
            $tokenParts = explode('.', $accessToken);
            $tokenPayload = json_decode(base64_decode($tokenParts[1]), true);
            
            // Extract realm roles from token
            $realmRoles = $tokenPayload['realm_access']['roles'] ?? [];
            
            // Check if user has allowed roles
            $allowedRoles = ['admin', 'admin_ente', 'operatore_ente'];
            $hasAllowedRole = !empty(array_intersect($allowedRoles, $realmRoles));
            
            if (!$hasAllowedRole) {
                return redirect(config('app.url') . '/login?error=unauthorized_role');
            }

            // Get user info from Keycloak
            $userInfoResponse = $client->get(sprintf('%s/realms/%s/protocol/openid-connect/userinfo', $baseUrl, $realm), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);

            $keycloakUser = json_decode($userInfoResponse->getBody(), true);
            
            // Determine user role based on Keycloak roles (priority: admin > admin_ente > operatore_ente)
            $userRole = 'utente';
            if (in_array('admin', $realmRoles)) {
                $userRole = 'admin';
            } elseif (in_array('admin_ente', $realmRoles)) {
                $userRole = 'admin_ente';
            } elseif (in_array('operatore_ente', $realmRoles)) {
                $userRole = 'operatore_ente';
            }

            // Find or sync user in local database
            $user = User::where('email', $keycloakUser['email'])->first();

            if (!$user) {
                // Create user from Keycloak data
                $user = User::create([
                    'email' => $keycloakUser['email'],
                    'nome' => $keycloakUser['given_name'] ?? '',
                    'cognome' => $keycloakUser['family_name'] ?? '',
                    'password' => Hash::make(\Str::random(32)), // Random password for Keycloak users
                    'role' => $userRole,
                    'attivo' => true,
                    'primo_accesso_eseguito' => true,
                ]);
            } else {
                // Update user role from Keycloak
                $user->update(['role' => $userRole]);
            }

            if (!$user->attivo) {
                return redirect(config('app.url') . '/login?error=account_disabled');
            }

            // Create Sanctum token
            $token = $user->createToken('keycloak-auth')->plainTextToken;

            // Redirect to Vue SPA with token
            return redirect(config('app.url') . '/auth/callback?token=' . $token);

        } catch (\Throwable $e) {
            \Log::error('Keycloak callback error: ' . $e->getMessage());
            return redirect(config('app.url') . '/login?error=auth_failed');
        }
    }
}
