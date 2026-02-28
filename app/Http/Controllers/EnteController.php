<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EnteController extends Controller
{
    /**
     * Lista enti
     */
    public function index(Request $request)
    {
        $query = Ente::query();

        if ($request->has('attivi')) {
            $query->where('attivo', true);
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('codice_fiscale', 'like', "%{$search}%");
            });
        }

        $enti = $query->orderBy('nome')->paginate(20);

        return response()->json($enti);
    }

    /**
     * Dettaglio ente
     */
    public function show(Ente $ente)
    {
        return response()->json($ente->load(['users']));
    }

    /**
     * Crea nuovo ente
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'codice_fiscale' => ['required', 'string', 'max:16', 'unique:enti'],
            'partita_iva' => ['nullable', 'string', 'max:11'],
            'email' => ['required', 'email', 'unique:enti'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'indirizzo' => ['nullable', 'string'],
            'citta' => ['nullable', 'string', 'max:255'],
            'provincia' => ['nullable', 'string', 'size:2'],
            'cap' => ['nullable', 'string', 'size:5'],
            'descrizione' => ['nullable', 'string'],
            'attivo' => ['boolean'],
        ]);

        $ente = Ente::create($validated);

        return response()->json([
            'message' => 'Ente creato con successo',
            'ente' => $ente,
        ], 201);
    }

    /**
     * Aggiorna ente
     */
    public function update(Request $request, Ente $ente)
    {
        $validated = $request->validate([
            'nome' => ['sometimes', 'string', 'max:255'],
            'codice_fiscale' => ['sometimes', 'string', 'max:16', Rule::unique('enti')->ignore($ente->id)],
            'partita_iva' => ['nullable', 'string', 'max:11'],
            'email' => ['sometimes', 'email', Rule::unique('enti')->ignore($ente->id)],
            'telefono' => ['nullable', 'string', 'max:20'],
            'indirizzo' => ['nullable', 'string'],
            'citta' => ['nullable', 'string', 'max:255'],
            'provincia' => ['nullable', 'string', 'size:2'],
            'cap' => ['nullable', 'string', 'size:5'],
            'descrizione' => ['nullable', 'string'],
            'attivo' => ['boolean'],
        ]);

        $ente->update($validated);

        return response()->json([
            'message' => 'Ente aggiornato con successo',
            'ente' => $ente,
        ]);
    }

    /**
     * Elimina ente (soft delete)
     */
    public function destroy(Ente $ente)
    {
        $ente->delete();

        return response()->json([
            'message' => 'Ente eliminato con successo',
        ]);
    }
}
