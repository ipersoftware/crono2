<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\KeycloakAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(private readonly KeycloakAdminService $keycloakAdminService)
    {
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
            $query->where('role', $request->input('role'));
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
            'cognome' => ['required', 'string', 'max:255'],
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', Password::min(8)],
            'role' => ['required', Rule::in(['utente', 'operatore_ente', 'admin_ente', 'admin'])],
            'ente_id' => ['nullable', 'exists:enti,id'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'attivo' => ['boolean'],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        if (config('auth_provider.driver') === 'keycloak') {
            try {
                $this->keycloakAdminService->syncUser($user, $request->input('password'), false);
            } catch (\Throwable $exception) {
                return response()->json([
                    'message' => 'Errore sincronizzazione con Keycloak: ' . $exception->getMessage(),
                ], 502);
            }
        }

        return response()->json([
            'message' => 'Utente creato con successo',
            'user' => $user->load(['ente']),
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

        $user->delete();

        return response()->json([
            'message' => 'Utente eliminato con successo',
        ]);
    }
}
