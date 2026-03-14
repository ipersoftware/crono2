<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Prenotazione;
use App\Models\Sessione;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NewsletterController extends Controller
{
    /**
     * POST /api/enti/{ente}/newsletter/snapshot
     *
     * Crea uno snapshot dei filtri correnti e restituisce un token breve (15 min).
     * Il frontend lo usa per aprire Ermes con il deep-link:
     *   {ERMES_URL}/newsletter/new?crono2_token={token}&crono2_url={app_url}
     */
    public function creaSnapshot(Request $request, Ente $ente): JsonResponse
    {
        $request->validate([
            'filtri' => 'required|array',
        ]);

        $token = Str::random(48);

        Cache::put("newsletter_snapshot:{$token}", [
            'ente_id' => $ente->id,
            'filtri'  => $request->filtri,
        ], now()->addMinutes(15));

        return response()->json([
            'token'   => $token,
            'expires' => now()->addMinutes(15)->toISOString(),
        ]);
    }

    /**
     * GET /api/v1/newsletter/{token}/subscribers
     *
     * Endpoint pubblico (autenticato con ERMES_API_TOKEN) consumato da Ermes.
     * Restituisce la lista dei prenotati corrispondente ai filtri dello snapshot.
     */
    public function subscribers(Request $request, string $token): JsonResponse
    {
        // Autenticazione bearer token Ermes
        $ermesToken = config('services.ermes.api_token');
        if ($ermesToken && $request->bearerToken() !== $ermesToken) {
            abort(401, 'Token non valido.');
        }

        $snapshot = Cache::get("newsletter_snapshot:{$token}");
        if (!$snapshot) {
            abort(404, 'Token non trovato o scaduto.');
        }

        $enteId = $snapshot['ente_id'];
        $filtri = $snapshot['filtri'];

        $ente = Ente::findOrFail($enteId);

        $q = Prenotazione::where('ente_id', $enteId)
            ->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE'])
            ->with(['sessione.evento']);

        if (!empty($filtri['evento_id'])) {
            $sessioniIds = Sessione::where('evento_id', (int) $filtri['evento_id'])->pluck('id');
            $q->whereIn('sessione_id', $sessioniIds);
        }
        if (!empty($filtri['sessione_id'])) {
            $q->where('sessione_id', $filtri['sessione_id']);
        }
        if (!empty($filtri['stato'])) {
            $q->where('stato', $filtri['stato']);
        }
        if (!empty($filtri['cerca'])) {
            $cerca = $filtri['cerca'];
            $q->where(fn($query) => $query
                ->where('codice', 'like', "%{$cerca}%")
                ->orWhere('email', 'like', "%{$cerca}%")
                ->orWhere('nome', 'like', "%{$cerca}%")
                ->orWhere('cognome', 'like', "%{$cerca}%")
            );
        }
        if (!empty($filtri['data_dal'])) {
            $q->whereDate('data_prenotazione', '>=', $filtri['data_dal']);
        }
        if (!empty($filtri['data_al'])) {
            $q->whereDate('data_prenotazione', '<=', $filtri['data_al']);
        }

        $prenotazioni = $q->get();

        // Deduplica per email — un utente che ha prenotato più sessioni appare una volta sola
        $subscribers = $prenotazioni
            ->unique('email')
            ->map(fn($p) => [
                'email'        => $p->email,
                'nome'         => $p->nome,
                'cognome'      => $p->cognome,
                'nome_completo'=> trim("{$p->cognome} {$p->nome}"),
                'telefono'     => $p->telefono,
                'codice'       => $p->codice,
                'sessione'     => $p->sessione ? [
                    'id'          => $p->sessione->id,
                    'data_inizio' => $p->sessione->data_inizio,
                ] : null,
                'evento'       => $p->sessione?->evento ? [
                    'id'     => $p->sessione->evento->id,
                    'titolo' => $p->sessione->evento->titolo,
                ] : null,
            ])
            ->values();

        return response()->json([
            'ente' => [
                'id'   => $ente->id,
                'nome' => $ente->nome,
            ],
            'filtri'      => $filtri,
            'subscribers' => $subscribers,
            'total'       => $subscribers->count(),
        ]);
    }

    /**
     * GET /api/enti/{ente}/newsletter/ermes-attivo
     *
     * Verifica se l'ente ha il servizio Ermes attivo nel governance DB.
     */
    public function ermesAttivo(Ente $ente): JsonResponse
    {
        if (!$ente->governance_id) {
            return response()->json(['attivo' => false]);
        }

        $attivo = DB::connection('governance')
            ->table('enti_servizi')
            ->where('ente_id', $ente->governance_id)
            ->where('servizio_id', 'ermes')
            ->where('attivo', true)
            ->exists();

        $ermesUrl = config('services.ermes.url');

        return response()->json([
            'attivo'    => $attivo,
            'ermes_url' => $attivo ? $ermesUrl : null,
        ]);
    }
}
