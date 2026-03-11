<?php

namespace App\Http\Controllers;

use App\Models\NotificaLog;
use App\Models\User;
use App\Services\KeycloakAdminService;
use App\Services\NotificaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(
        private readonly KeycloakAdminService $keycloakAdminService,
        private readonly NotificaService      $notificaService,
    ) {
    }

    /**
     * Lista utenti
     */
    public function index(Request $request)
    {
        $query = User::query()->with(['ente']);

        if ($request->has('ente_id')) {
            $query->where('ente_id', $request->input('ente_id'));
        }

        if ($request->has('role')) {
            $roles = is_array($request->input('role'))
                ? $request->input('role')
                : array_map('trim', explode(',', $request->input('role')));
            $query->whereIn('role', $roles);
        }

        if ($request->has('attivi')) {
            $query->where('attivo', true);
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                  ->orWhere('cognome', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('cognome')->orderBy('nome')->paginate(20);

        return response()->json($users);
    }

    /**
     * Dettaglio utente
     */
    public function show(User $user)
    {
        return response()->json($user->load(['ente']));
    }

    /**
     * Crea nuovo utente
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cognome'  => ['required', 'string', 'max:255'],
            'nome'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', Rule::unique('users')->whereNull('deleted_at')],
            'role'     => ['required', Rule::in(['utente', 'operatore_ente', 'admin_ente', 'admin'])],
            'ente_id'  => ['nullable', 'exists:enti,id'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'attivo'   => ['boolean'],
        ]);

        // Password auto-generata — lettere+numeri, no simboli per evitare problemi di rendering HTML nelle email
        $plainPassword          = Str::password(14, letters: true, numbers: true, symbols: false, spaces: false);
        $validated['password']  = Hash::make($plainPassword);

        try {
            $user = DB::transaction(function () use ($validated, $plainPassword) {
                $user = User::create($validated);

                if (config('auth_provider.driver') === 'keycloak') {
                    // temporary = true: Keycloak forza il cambio password al primo login
                    $this->keycloakAdminService->syncUser($user, $plainPassword, true);
                }

                return $user;
            });
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Errore creazione utente: ' . $exception->getMessage(),
            ], 502);
        }

        // Invio benvenuto fuori dalla transazione: un errore email non rollbacka la creazione
        $this->notificaService->inviaBenvenutoUtente($user->load('ente'), $plainPassword);

        return response()->json([
            'message' => 'Utente creato con successo',
            'user'    => $user->load(['ente']),
        ], 201);
    }

    /**
     * Aggiorna utente
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'cognome' => ['sometimes', 'string', 'max:255'],
            'nome' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'role' => ['sometimes', Rule::in(['utente', 'operatore_ente', 'admin_ente', 'admin'])],
            'ente_id' => ['nullable', 'exists:enti,id'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'attivo' => ['boolean'],
        ]);

        $user->update($validated);

        if (config('auth_provider.driver') === 'keycloak') {
            try {
                $this->keycloakAdminService->syncUser($user);
            } catch (\Throwable $exception) {
                return response()->json([
                    'message' => 'Errore sincronizzazione con Keycloak: ' . $exception->getMessage(),
                ], 502);
            }
        }

        return response()->json([
            'message' => 'Utente aggiornato con successo',
            'user' => $user->load(['ente']),
        ]);
    }

    /**
     * Elimina utente
     */
    public function destroy(User $user)
    {
        if (config('auth_provider.driver') === 'keycloak') {
            try {
                $this->keycloakAdminService->deleteUser($user);
            } catch (\Throwable $exception) {
                return response()->json([
                    'message' => 'Errore eliminazione su Keycloak: ' . $exception->getMessage(),
                ], 502);
            }
        }

        $user->forceDelete();

        return response()->json([
            'message' => 'Utente eliminato con successo',
        ]);
    }

    /**
     * POST /api/users/{user}/reset-password
     * Invia email di reset credenziali tramite Keycloak (link sicuro, nessuna password in chiaro).
     */
    public function resetPassword(User $user)
    {
        if (config('auth_provider.driver') === 'keycloak') {
            try {
                $this->keycloakAdminService->sendPasswordResetEmail($user);
            } catch (\Throwable $exception) {
                return response()->json([
                    'message' => 'Errore reset credenziali: ' . $exception->getMessage(),
                ], 502);
            }
        }

        // Log su notifiche_log se l'utente appartiene a un ente
        if ($user->ente_id !== null) {
            NotificaLog::create([
                'ente_id'            => $user->ente_id,
                'prenotazione_id'    => null,
                'tipo'               => 'RESET_PASSWORD',
                'destinatario_email' => $user->email,
                'oggetto'            => 'Reset credenziali inviato da Keycloak',
                'stato'              => 'INVIATA',
                'inviata_at'         => now(),
            ]);
        }

        Log::info('Reset credenziali inviato', [
            'user_id' => $user->id,
            'email'   => $user->email,
            'ente_id' => $user->ente_id,
        ]);

        return response()->json(['message' => 'Email di reset credenziali inviata.']);
    }

    /**
     * PATCH /api/users/{user}/toggle-attivo
     * Sospende o riattiva l'accesso dell'utente.
     */
    public function toggleAttivo(User $user)
    {
        $user->update(['attivo' => !$user->attivo]);

        if (config('auth_provider.driver') === 'keycloak') {
            try {
                $this->keycloakAdminService->syncUser($user);
            } catch (\Throwable $exception) {
                return response()->json([
                    'message' => 'Errore sincronizzazione stato su Keycloak: ' . $exception->getMessage(),
                ], 502);
            }
        }

        return response()->json([
            'message' => $user->attivo ? 'Utente riattivato.' : 'Utente sospeso.',
            'user'    => $user->load(['ente']),
        ]);
    }
}
