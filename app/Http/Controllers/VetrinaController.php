<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Evento;
use App\Models\Serie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API pubblica — nessuna autenticazione richiesta.
 */
class VetrinaController extends Controller
{
    /**
     * GET /api/vetrina/{shop_url}
     * Info ente + eventi in evidenza.
     */
    public function index(string $shopUrl): JsonResponse
    {
        $ente = Ente::where('shop_url', $shopUrl)
            ->where('attivo', true)
            ->firstOrFail();

        $inEvidenza = [];
        if (!empty($ente->eventi_in_evidenza)) {
            $inEvidenza = Evento::whereIn('id', $ente->eventi_in_evidenza)
                ->where('stato', 'PUBBLICATO')
                ->with(['tags', 'sessioni' => fn($q) => $q->where('stato', 'APERTA')->orderBy('inizio_at')])
                ->get();
        }

        return response()->json([
            'ente' => [
                'nome'               => $ente->nome,
                'slug'               => $ente->slug,
                'shop_url'           => $ente->shop_url,
                'copertina'          => $ente->copertina,
                'contenuto_vetrina'  => $ente->contenuto_vetrina,
            ],
            'eventi_in_evidenza' => $inEvidenza,
        ]);
    }

    /**
     * GET /api/vetrina/{shop_url}/eventi
     * Lista eventi pubblicati con filtri.
     * Query: q, tag_id, luogo_id, serie_id, da, a, page
     */
    public function eventi(Request $request, string $shopUrl): JsonResponse
    {
        $ente = $this->enteAttivo($shopUrl);

        $q = Evento::where('ente_id', $ente->id)
            ->where('stato', 'PUBBLICATO')
            ->with(['tags', 'luoghi'])
            ->withCount('sessioni');

        if ($request->filled('q')) {
            $cerca = $request->q;
            $q->where(fn($query) => $query
                ->where('titolo', 'like', "%{$cerca}%")
                ->orWhere('descrizione', 'like', "%{$cerca}%"));
        }

        if ($request->filled('tag_id')) {
            $q->whereHas('tags', fn($query) => $query->where('tags.id', $request->tag_id));
        }

        if ($request->filled('luogo_id')) {
            $q->whereHas('luoghi', fn($query) => $query->where('luoghi.id', $request->luogo_id));
        }

        if ($request->filled('serie_id')) {
            $q->where('serie_id', $request->serie_id);
        }

        if ($request->filled('da')) {
            $q->whereHas('sessioni', fn($query) => $query->where('inizio_at', '>=', $request->da));
        }

        if ($request->filled('a')) {
            $q->whereHas('sessioni', fn($query) => $query->where('inizio_at', '<=', $request->a));
        }

        return response()->json($q->orderByDesc('created_at')->paginate(20));
    }

    /**
     * GET /api/vetrina/{shop_url}/eventi/{slug}
     * Dettaglio evento + sessioni disponibili.
     */
    public function evento(string $shopUrl, string $slug): JsonResponse
    {
        $ente = $this->enteAttivo($shopUrl);

        $evento = Evento::where('ente_id', $ente->id)
            ->where(fn($q) => $q->where('slug', $slug)->orWhereJsonContains('slug_history', $slug))
            ->where('stato', 'PUBBLICATO')
            ->with([
                'tags',
                'luoghi',
                'serie',
                'tipologiePosto' => fn($q) => $q->where('attiva', true),
                'campiForm'      => fn($q) => $q->where('attivo', true)->where('visibile_pubblico', true)->orderBy('ordine'),
                'sessioni'       => fn($q) => $q
                    ->where('stato', 'APERTA')
                    ->where('inizio_at', '>', now())
                    ->orderBy('inizio_at')
                    ->with('tipologieDisponibili'),
            ])
            ->firstOrFail();

        // Se lo slug è nella history, reindirizza logicamente con il nuovo slug
        $risposta = $evento->toArray();
        $risposta['redirect_slug'] = ($evento->slug !== $slug) ? $evento->slug : null;

        return response()->json($risposta);
    }

    /**
     * GET /api/vetrina/{shop_url}/serie
     * Serie pubblicate con i relativi eventi.
     */
    public function serie(string $shopUrl): JsonResponse
    {
        $ente = $this->enteAttivo($shopUrl);

        $serie = Serie::where('ente_id', $ente->id)
            ->withCount(['eventi' => fn($q) => $q->where('stato', 'PUBBLICATO')])
            ->having('eventi_count', '>', 0)
            ->orderBy('titolo')
            ->get();

        return response()->json($serie);
    }

    /**
     * GET /api/vetrina/{shop_url}/tags
     * Tag dell'ente con conteggio eventi pubblicati.
     */
    public function tags(string $shopUrl): JsonResponse
    {
        $ente = $this->enteAttivo($shopUrl);

        $tags = $ente->tags()
            ->withCount(['eventi' => fn($q) => $q->where('stato', 'PUBBLICATO')])
            ->orderBy('nome')
            ->get();

        return response()->json($tags);
    }

    private function enteAttivo(string $shopUrl): Ente
    {
        return Ente::where('shop_url', $shopUrl)
            ->where('attivo', true)
            ->firstOrFail();
    }
}
