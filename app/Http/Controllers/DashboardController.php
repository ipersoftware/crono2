<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Evento;
use App\Models\Prenotazione;
use App\Models\RichiestaContatto;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * GET /api/enti/{ente}/dashboard
     * Statistiche riepilogative per la dashboard operatore/admin ente.
     */
    public function index(Ente $ente): JsonResponse
    {
        $anno = now()->year;
        $oggi = now()->toDateString();
        $inizioSettimana = now()->startOfWeek()->toDateString();

        // ── 1. Eventi pubblicati nell'anno corrente ───────────────────────
        $eventiAnno = Evento::where('ente_id', $ente->id)
            ->where('stato', 'PUBBLICATO')
            ->whereHas('sessioni', fn ($q) => $q->whereYear('data_inizio', $anno))
            ->with(['tags', 'luoghi' => fn ($q) => $q->wherePivot('principale', true)])
            ->withCount('sessioni')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($e) => [
                'id'                 => $e->id,
                'titolo'             => $e->titolo,
                'slug'               => $e->slug,
                'stato'              => $e->stato,
                'sessioni_count'     => $e->sessioni_count,
                'prenotazioni_count' => Prenotazione::where('ente_id', $ente->id)
                    ->whereHas('sessione', fn ($q) => $q->where('evento_id', $e->id))
                    ->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE'])
                    ->count(),
                'luogo'              => $e->luoghi->first()?->nome,
                'tags'               => $e->tags->pluck('nome'),
            ]);

        $totaleEventiAnno = Evento::where('ente_id', $ente->id)
            ->where('stato', 'PUBBLICATO')
            ->whereHas('sessioni', fn ($q) => $q->whereYear('data_inizio', $anno))
            ->count();

        // ── 2. Statistiche prenotazioni ───────────────────────────────────
        $basePrenotazioni = Prenotazione::where('ente_id', $ente->id);

        $prenotazioniOggi = (clone $basePrenotazioni)
            ->whereDate('created_at', $oggi)
            ->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE'])
            ->count();

        $prenotazioniSettimana = (clone $basePrenotazioni)
            ->whereDate('created_at', '>=', $inizioSettimana)
            ->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE'])
            ->count();

        $prenotazioniAnno = (clone $basePrenotazioni)
            ->whereYear('created_at', $anno)
            ->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE', 'ANNULLATA_UTENTE', 'ANNULLATA_OPERATORE'])
            ->count();

        $annullateSettimana = (clone $basePrenotazioni)
            ->whereDate('created_at', '>=', $inizioSettimana)
            ->whereIn('stato', ['ANNULLATA_UTENTE', 'ANNULLATA_OPERATORE'])
            ->count();

        // Ultime 5 prenotazioni del giorno
        $ultimeDiOggi = (clone $basePrenotazioni)
            ->whereDate('created_at', $oggi)
            ->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE'])
            ->with(['sessione.evento'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'codice'         => $p->codice,
                'nome'           => $p->nome . ' ' . $p->cognome,
                'evento'         => $p->sessione?->evento?->titolo,
                'stato'          => $p->stato,
                'posti'          => $p->posti_prenotati,
                'created_at'     => $p->created_at?->format('H:i'),
            ]);

        // ── 3. Richieste di contatto non lette ────────────────────────────
        $richiesteNonLette = RichiestaContatto::where('ente_id', $ente->id)
            ->where('letta', false)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id'         => $r->id,
                'nome'       => $r->nome,
                'email'      => $r->email,
                'messaggio'  => \Str::limit($r->messaggio, 80),
                'created_at' => $r->created_at?->diffForHumans(),
            ]);

        $totaleRichiesteNonLette = RichiestaContatto::where('ente_id', $ente->id)
            ->where('letta', false)
            ->count();

        return response()->json([
            'anno'                       => $anno,
            'eventi_pubblicati_anno'     => $eventiAnno,
            'totale_eventi_anno'         => $totaleEventiAnno,
            'stats' => [
                'prenotazioni_oggi'       => $prenotazioniOggi,
                'prenotazioni_settimana'  => $prenotazioniSettimana,
                'annullate_settimana'     => $annullateSettimana,
                'prenotazioni_anno'       => $prenotazioniAnno,
            ],
            'ultime_prenotazioni_oggi'   => $ultimeDiOggi,
            'richieste_non_lette'        => $richiesteNonLette,
            'totale_richieste_non_lette' => $totaleRichiesteNonLette,
        ]);
    }
}
