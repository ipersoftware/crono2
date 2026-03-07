<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Evento;
use App\Models\Prenotazione;
use Illuminate\Http\JsonResponse;

class MonitoraggioController extends Controller
{
    /**
     * GET /api/enti/{ente}/eventi/{evento}/monitoraggio
     * Restituisce i dati di monitoraggio prenotazioni per un evento.
     */
    public function evento(Ente $ente, Evento $evento): JsonResponse
    {
        abort_if($evento->ente_id !== $ente->id, 403);

        // Carica sessioni con tipologie e conteggio prenotazioni
        $sessioni = $evento->sessioni()
            ->with(['tipologiePosto.tipologiaPosto', 'luoghi'])
            ->withCount([
                'prenotazioni as prenotazioni_totali',
                'prenotazioni as prenotazioni_confermate' => fn ($q) => $q->where('stato', 'CONFERMATA'),
                'prenotazioni as prenotazioni_da_confermare' => fn ($q) => $q->where('stato', 'DA_CONFERMARE'),
                'prenotazioni as prenotazioni_annullate' => fn ($q) => $q->whereIn('stato', ['ANNULLATA_UTENTE', 'ANNULLATA_OPERATORE']),
            ])
            ->orderBy('data_inizio')
            ->get();

        // Totali globali evento
        $sessioniIds = $sessioni->pluck('id');

        $totalePrenotazioni    = Prenotazione::whereIn('sessione_id', $sessioniIds)->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE', 'RISERVATA'])->count();
        $totaleConfermate      = Prenotazione::whereIn('sessione_id', $sessioniIds)->where('stato', 'CONFERMATA')->count();
        $totaleDaConfermare    = Prenotazione::whereIn('sessione_id', $sessioniIds)->where('stato', 'DA_CONFERMARE')->count();
        $totaleAnnullate       = Prenotazione::whereIn('sessione_id', $sessioniIds)->whereIn('stato', ['ANNULLATA_UTENTE', 'ANNULLATA_OPERATORE'])->count();

        $totalePostiTotali     = $sessioni->sum('posti_totali');
        $totalePostiDisponibili = $sessioni->sum('posti_disponibili');

        $sessioni->transform(function ($s) {
            return [
                'id'                      => $s->id,
                'data_inizio'             => $s->data_inizio,
                'data_fine'               => $s->data_fine,
                'luoghi'                  => $s->luoghi->pluck('nome'),
                'posti_totali'            => $s->posti_totali,
                'posti_disponibili'       => $s->posti_disponibili,
                'posti_in_attesa'         => $s->posti_in_attesa ?? 0,
                'prenotazioni_attive'     => $s->prenotazioni_confermate + $s->prenotazioni_da_confermare,
                'prenotazioni_confermate' => $s->prenotazioni_confermate,
                'prenotazioni_da_confermare' => $s->prenotazioni_da_confermare,
                'prenotazioni_annullate'  => $s->prenotazioni_annullate,
                'tipologie'               => $s->tipologiePosto->map(fn ($tp) => [
                    'nome'             => $tp->tipologiaPosto->nome ?? '–',
                    'posti_totali'     => $tp->posti_totali,
                    'posti_disponibili'=> $tp->posti_disponibili,
                    'posti_riservati'  => $tp->posti_riservati,
                ]),
            ];
        });

        return response()->json([
            'evento' => [
                'id'     => $evento->id,
                'titolo' => $evento->titolo,
                'stato'  => $evento->stato,
            ],
            'riepilogo' => [
                'prenotazioni_attive'        => $totalePrenotazioni,
                'prenotazioni_confermate'    => $totaleConfermate,
                'prenotazioni_da_confermare' => $totaleDaConfermare,
                'prenotazioni_annullate'     => $totaleAnnullate,
                'posti_totali'               => $totalePostiTotali,
                'posti_disponibili'          => $totalePostiDisponibili,
            ],
            'sessioni' => $sessioni,
        ]);
    }
}
