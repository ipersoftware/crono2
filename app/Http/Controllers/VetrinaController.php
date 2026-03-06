<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Evento;
use App\Models\RichiestaContatto;
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
        $ente = $this->enteAttivo($shopUrl);

        $inEvidenza = [];
        if (!empty($ente->eventi_in_evidenza)) {
            $inEvidenza = Evento::whereIn('id', $ente->eventi_in_evidenza)
                ->where('stato', 'PUBBLICATO')
                ->with(['tags', 'sessioni' => fn($q) => $q->where('prenotabile', true)->where('forza_non_disponibile', false)->where('data_fine', '>', now())->orderBy('data_inizio')])
                ->get();
        }

        return response()->json([
            'ente' => [
                'nome'               => $ente->nome,
                'slug'               => $ente->slug,
                'shop_url'           => $ente->shop_url,
                'copertina'          => $ente->copertina,
                'contenuto_vetrina'  => $ente->contenuto_vetrina,
                'indirizzo'          => $ente->indirizzo,
                'citta'              => $ente->citta,
                'provincia'          => $ente->provincia,
                'email'              => $ente->email,
                'privacy_url'        => $ente->privacy_url,
                'form_contatti_attivo' => (bool) $ente->form_contatti_attivo,
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
            $q->whereHas('sessioni', fn($query) => $query->where('data_inizio', '>=', $request->da));
        }

        if ($request->filled('a')) {
            $q->whereHas('sessioni', fn($query) => $query->where('data_inizio', '<=', $request->a));
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
                    ->where('prenotabile', true)
                    ->where('forza_non_disponibile', false)
                    ->where('data_fine', '>', now())
                    ->orderBy('data_inizio')
                    ->with(['tipologiePosto.tipologiaPosto', 'luoghi']),
            ])
            ->firstOrFail();

        // Se lo slug è nella history, reindirizza logicamente con il nuovo slug
        $risposta = $evento->toArray();
        $risposta['redirect_slug'] = ($evento->slug !== $slug) ? $evento->slug : null;
        $risposta['ente_privacy_url'] = $ente->privacy_url;
        $risposta['ente_info'] = [
            'nome'        => $ente->nome,
            'shop_url'    => $ente->shop_url,
            'privacy_url' => $ente->privacy_url,
            'indirizzo'   => $ente->indirizzo,
            'citta'       => $ente->citta,
            'provincia'   => $ente->provincia,
            'email'       => $ente->email,
        ];

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

    /**
     * POST /api/vetrina/{shop_url}/contatto
     * Invio form contatti pubblico.
     */
    public function contatto(Request $request, string $shopUrl): JsonResponse
    {
        $ente = $this->enteAttivo($shopUrl);

        abort_if(!$ente->form_contatti_attivo, 403, 'Form contatti non attivo.');

        $data = $request->validate([
            'nome'     => 'required|string|max:150',
            'email'    => 'required|email|max:255',
            'telefono' => 'nullable|string|max:50',
            'messaggio'=> 'required|string|max:3000',
        ]);

        RichiestaContatto::create([
            'ente_id'   => $ente->id,
            'nome'      => $data['nome'],
            'email'     => $data['email'],
            'telefono'  => $data['telefono'] ?? null,
            'messaggio' => $data['messaggio'],
        ]);

        return response()->json(['message' => 'Richiesta inviata con successo.'], 201);
    }

    private function enteAttivo(string $shopUrl): Ente
    {
        return Ente::where(function ($q) use ($shopUrl) {
                $q->where('shop_url', $shopUrl)
                  ->orWhere('slug', $shopUrl)
                  ->orWhere('id', is_numeric($shopUrl) ? (int) $shopUrl : 0);
            })
            ->where('attivo', true)
            ->firstOrFail();
    }
}
