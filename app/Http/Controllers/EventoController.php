<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Evento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventoController extends Controller
{
    /** GET /api/enti/{ente}/eventi */
    public function index(Request $request, Ente $ente): JsonResponse
    {
        $eventi = Evento::where('ente_id', $ente->id)
            ->when($request->stato, fn ($q, $s) => $q->where('stato', $s))
            ->when($request->serie_id, fn ($q, $id) => $q->where('serie_id', $id))
            ->when($request->q, fn ($q, $search) => $q->where('titolo', 'like', "%{$search}%"))
            ->with(['serie', 'tags', 'luoghi'])
            ->withCount('sessioni')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($eventi);
    }

    /** POST /api/enti/{ente}/eventi */
    public function store(Request $request, Ente $ente): JsonResponse
    {
        $data = $this->validaEvento($request);
        $data['ente_id'] = $ente->id;
        $data['slug'] = $this->generaSlug($data['titolo'], $ente->id);

        $evento = Evento::create($data);

        // Sync tags e luoghi
        if ($request->has('tag_ids')) {
            $evento->tags()->sync($request->tag_ids);
        }

        if ($request->has('luogo_ids')) {
            $luoghiPivot = collect($request->luogo_ids)->mapWithKeys(
                fn ($id, $index) => [$id => ['principale' => $index === 0]]
            );
            $evento->luoghi()->sync($luoghiPivot);
        }

        return response()->json($evento->load(['serie', 'tags', 'luoghi']), 201);
    }

    /** GET /api/enti/{ente}/eventi/{evento} */
    public function show(Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizza($ente, $evento);

        return response()->json(
            $evento->load([
                'serie',
                'tags',
                'luoghi',
                'sessioni',
                'tipologiePosto',
                'campiForm',
            ])
        );
    }

    /** PUT /api/enti/{ente}/eventi/{evento} */
    public function update(Request $request, Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizza($ente, $evento);

        $data = $this->validaEvento($request, partial: true);

        // Aggiorna slug con storico se il titolo cambia
        if (isset($data['titolo']) && $data['titolo'] !== $evento->titolo) {
            $nuovoSlug = $this->generaSlug($data['titolo'], $ente->id, $evento->id);
            if ($nuovoSlug !== $evento->slug) {
                $history = $evento->slug_history ?? [];
                $history[] = $evento->slug;
                $data['slug'] = $nuovoSlug;
                $data['slug_history'] = array_unique($history);
            }
        }

        $evento->update($data);

        if ($request->has('tag_ids')) {
            $evento->tags()->sync($request->tag_ids);
        }

        if ($request->has('luogo_ids')) {
            $luoghiPivot = collect($request->luogo_ids)->mapWithKeys(
                fn ($id, $index) => [$id => ['principale' => $index === 0]]
            );
            $evento->luoghi()->sync($luoghiPivot);
        }

        return response()->json($evento->fresh(['serie', 'tags', 'luoghi']));
    }

    /** DELETE /api/enti/{ente}/eventi/{evento} */
    public function destroy(Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizza($ente, $evento);
        $evento->delete();

        return response()->json(['message' => 'Evento eliminato.']);
    }

    /** PATCH /api/enti/{ente}/eventi/{evento}/pubblica */
    public function pubblica(Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizza($ente, $evento);
        $evento->update(['stato' => 'PUBBLICATO', 'pubblico' => true]);

        return response()->json($evento);
    }

    /** PATCH /api/enti/{ente}/eventi/{evento}/sospendi */
    public function sospendi(Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizza($ente, $evento);
        $evento->update(['stato' => 'SOSPESO', 'pubblico' => false]);

        return response()->json($evento);
    }

    /** PATCH /api/enti/{ente}/eventi/{evento}/annulla */
    public function annulla(Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizza($ente, $evento);
        $evento->update(['stato' => 'ANNULLATO', 'pubblico' => false]);

        // TODO: inviare notifica EVENTO_ANNULLATO a tutti i prenotati

        return response()->json($evento);
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function validaEvento(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'serie_id'                    => 'nullable|exists:serie,id',
            'titolo'                      => "{$req}|string|max:255",
            'descrizione_breve'           => 'nullable|string|max:512',
            'descrizione'                 => 'nullable|string',
            'immagine'                    => 'nullable|string|max:255',
            'stato'                       => 'nullable|in:BOZZA,PUBBLICATO,SOSPESO,ANNULLATO',
            'pubblico'                    => 'nullable|boolean',
            'in_evidenza'                 => 'nullable|boolean',
            'ordinamento'                 => 'nullable|integer',
            'visibile_dal'                => 'nullable|date',
            'visibile_al'                 => 'nullable|date',
            'prenotabile_dal'             => 'nullable|date',
            'prenotabile_al'              => 'nullable|date',
            'posti_max_per_prenotazione'  => 'nullable|integer|min:1',
            'richiede_approvazione'       => 'nullable|boolean',
            'consenti_multi_sessione'     => 'nullable|boolean',
            'consenti_prenotazione_guest' => 'nullable|boolean',
            'cancellazione_consentita_ore' => 'nullable|integer|min:-1',
            'mostra_disponibilita'        => 'nullable|boolean',
            'attiva_note'                 => 'nullable|boolean',
            'nota_etichetta'              => 'nullable|string|max:255',
            'costo'                       => 'nullable|numeric|min:0',
            'attributi'                   => 'nullable|array',
        ]);
    }

    private function generaSlug(string $titolo, int $enteId, ?int $excludeId = null): string
    {
        $base = Str::slug($titolo);
        $slug = $base;
        $i    = 1;

        while (
            Evento::where('ente_id', $enteId)
                ->where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function autorizza(Ente $ente, Evento $evento): void
    {
        abort_if((int) $evento->ente_id !== (int) $ente->id, 403, 'Evento non appartiene a questo Ente.');
    }
}
