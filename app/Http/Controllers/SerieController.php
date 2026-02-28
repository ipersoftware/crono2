<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Serie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SerieController extends Controller
{
    /** GET /api/enti/{ente}/serie */
    public function index(Request $request, Ente $ente): JsonResponse
    {
        $serie = Serie::where('ente_id', $ente->id)
            ->when($request->stato, fn ($q, $s) => $q->where('stato', $s))
            ->withCount('eventi')
            ->orderBy('titolo')
            ->get();

        return response()->json($serie);
    }

    /** POST /api/enti/{ente}/serie */
    public function store(Request $request, Ente $ente): JsonResponse
    {
        $data = $request->validate([
            'titolo'      => 'required|string|max:255',
            'descrizione' => 'nullable|string',
            'slug'        => 'nullable|string|max:255',
            'stato'       => 'nullable|in:BOZZA,PUBBLICATO,SOSPESO,ANNULLATO',
            'pubblico'    => 'nullable|boolean',
            'visibile_dal' => 'nullable|date',
            'visibile_al'  => 'nullable|date|after:visibile_dal',
            'immagine'    => 'nullable|string|max:255',
            'contenuto'   => 'nullable|string',
            'link_pubblico' => 'nullable|url',
        ]);

        $data['ente_id'] = $ente->id;
        $data['slug']    = $data['slug'] ?? Str::slug($data['titolo']);

        $serie = Serie::create($data);

        return response()->json($serie->load('eventi'), 201);
    }

    /** GET /api/enti/{ente}/serie/{serie} */
    public function show(Ente $ente, Serie $serie): JsonResponse
    {
        $this->autorizza($ente, $serie);

        return response()->json($serie->load(['eventi' => fn ($q) => $q->withCount('sessioni')]));
    }

    /** PUT /api/enti/{ente}/serie/{serie} */
    public function update(Request $request, Ente $ente, Serie $serie): JsonResponse
    {
        $this->autorizza($ente, $serie);

        $data = $request->validate([
            'titolo'      => 'sometimes|string|max:255',
            'descrizione' => 'nullable|string',
            'slug'        => 'nullable|string|max:255',
            'stato'       => 'nullable|in:BOZZA,PUBBLICATO,SOSPESO,ANNULLATO',
            'pubblico'    => 'nullable|boolean',
            'visibile_dal' => 'nullable|date',
            'visibile_al'  => 'nullable|date',
            'immagine'    => 'nullable|string|max:255',
            'contenuto'   => 'nullable|string',
            'link_pubblico' => 'nullable|url',
        ]);

        $serie->update($data);

        return response()->json($serie);
    }

    /** DELETE /api/enti/{ente}/serie/{serie} */
    public function destroy(Ente $ente, Serie $serie): JsonResponse
    {
        $this->autorizza($ente, $serie);
        $serie->delete();

        return response()->json(['message' => 'Serie eliminata.']);
    }

    private function autorizza(Ente $ente, Serie $serie): void
    {
        abort_if((int) $serie->ente_id !== (int) $ente->id, 403, 'Serie non appartiene a questo Ente.');
    }
}
