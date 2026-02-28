<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Luogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LuogoController extends Controller
{
    /** GET /api/enti/{ente}/luoghi */
    public function index(Request $request, Ente $ente): JsonResponse
    {
        $luoghi = Luogo::where('ente_id', $ente->id)
            ->when($request->q, fn ($q, $search) => $q->where('nome', 'like', "%{$search}%"))
            ->when($request->stato, fn ($q, $stato) => $q->where('stato', $stato))
            ->orderBy('nome')
            ->get();

        return response()->json($luoghi);
    }

    /** POST /api/enti/{ente}/luoghi */
    public function store(Request $request, Ente $ente): JsonResponse
    {
        $data = $request->validate([
            'nome'        => 'required|string|max:255',
            'descrizione' => 'nullable|string',
            'indirizzo'   => 'nullable|string|max:255',
            'citta'       => 'nullable|string|max:100',
            'provincia'   => 'nullable|string|size:2',
            'cap'         => 'nullable|string|size:5',
            'lat'         => 'nullable|numeric|between:-90,90',
            'lng'         => 'nullable|numeric|between:-180,180',
            'maps_url'    => 'nullable|url|max:512',
            'telefono'    => 'nullable|string|max:30',
            'email'       => 'nullable|email|max:255',
            'link_pubblico' => 'nullable|url|max:255',
            'immagine'    => 'nullable|string|max:255',
            'stato'       => 'nullable|in:ATTIVO,SOSPESO',
        ]);

        $data['ente_id'] = $ente->id;
        $data['slug']    = Str::slug($data['nome']);

        $luogo = Luogo::create($data);

        return response()->json($luogo, 201);
    }

    /** GET /api/enti/{ente}/luoghi/{luogo} */
    public function show(Ente $ente, Luogo $luogo): JsonResponse
    {
        $this->autorizza($ente, $luogo);

        return response()->json($luogo);
    }

    /** PUT /api/enti/{ente}/luoghi/{luogo} */
    public function update(Request $request, Ente $ente, Luogo $luogo): JsonResponse
    {
        $this->autorizza($ente, $luogo);

        $data = $request->validate([
            'nome'        => 'sometimes|string|max:255',
            'descrizione' => 'nullable|string',
            'indirizzo'   => 'nullable|string|max:255',
            'citta'       => 'nullable|string|max:100',
            'provincia'   => 'nullable|string|size:2',
            'cap'         => 'nullable|string|size:5',
            'lat'         => 'nullable|numeric|between:-90,90',
            'lng'         => 'nullable|numeric|between:-180,180',
            'maps_url'    => 'nullable|url|max:512',
            'telefono'    => 'nullable|string|max:30',
            'email'       => 'nullable|email|max:255',
            'link_pubblico' => 'nullable|url|max:255',
            'immagine'    => 'nullable|string|max:255',
            'stato'       => 'nullable|in:ATTIVO,SOSPESO',
        ]);

        if (isset($data['nome'])) {
            $data['slug'] = Str::slug($data['nome']);
        }

        $luogo->update($data);

        return response()->json($luogo);
    }

    /** DELETE /api/enti/{ente}/luoghi/{luogo} */
    public function destroy(Ente $ente, Luogo $luogo): JsonResponse
    {
        $this->autorizza($ente, $luogo);
        $luogo->delete();

        return response()->json(['message' => 'Luogo eliminato.']);
    }

    private function autorizza(Ente $ente, Luogo $luogo): void
    {
        abort_if((int) $luogo->ente_id !== (int) $ente->id, 403, 'Luogo non appartiene a questo Ente.');
    }
}
