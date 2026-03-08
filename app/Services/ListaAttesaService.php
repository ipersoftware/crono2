<?php

namespace App\Services;

use App\Models\Prenotazione;
use App\Models\Sessione;
use App\Models\SessioneTipologiaPosto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ListaAttesaService
{
    public function __construct(
        private readonly NotificaService  $notifiche,
        private readonly EventoLogService $log,
    ) {}

    /**
     * Chiamato dopo ogni annullamento di prenotazione.
     * Promuove il primo iscritto in lista in base alla policy tipo_conferma della sessione.
     */
    public function processaPromozione(Sessione $sessione): void
    {
        if (!$sessione->attiva_lista_attesa) {
            return;
        }

        $tipoConferma = $sessione->tipo_conferma ?? 'NESSUNA';

        if ($tipoConferma === 'NESSUNA') {
            return;
        }

        // Rilegge la sessione fresh per avere i contatori aggiornati
        $sessione = Sessione::find($sessione->id);

        // Posti effettivamente liberi (non riservati da lock attivi)
        $postiLiberi = $sessione->posti_totali > 0
            ? max(0, $sessione->posti_disponibili - $sessione->posti_riservati)
            : PHP_INT_MAX;

        if ($postiLiberi <= 0) {
            return;
        }

        match ($tipoConferma) {
            'PRENOTAZIONE_AUTOMATICA'    => $this->promuoviAutomaticamente($sessione, $postiLiberi),
            'PRENOTAZIONE_DA_CONFERMARE' => $this->notificaPrimoPosto($sessione),
            default                      => null,
        };
    }

    // ─── Promozione automatica ────────────────────────────────────────────────

    private function promuoviAutomaticamente(Sessione $sessione, int $postiLiberi): void
    {
        $candidati = Prenotazione::where('sessione_id', $sessione->id)
            ->where('stato', 'IN_LISTA_ATTESA')
            ->orderBy('posizione_lista_attesa')
            ->get();

        foreach ($candidati as $prenotazione) {
            if ($prenotazione->posti_prenotati > $postiLiberi) {
                continue; // non entra, prova il prossimo
            }

            try {
                DB::transaction(function () use ($prenotazione, $sessione, &$postiLiberi) {
                    // Ri-lock e ri-verifica
                    $sess = Sessione::lockForUpdate()->find($sessione->id);
                    $disponibili = $sess->posti_totali > 0
                        ? max(0, $sess->posti_disponibili - ($sess->posti_riservati ?? 0))
                        : PHP_INT_MAX;

                    if ($prenotazione->posti_prenotati > $disponibili) {
                        return; // qualcun altro ha preso i posti nel frattempo
                    }

                    // Promuovi: diventa prenotazione confermata
                    $totale = $prenotazione->posti_prenotati;
                    $prenotazione->update([
                        'stato'                  => 'CONFERMATA',
                        'posizione_lista_attesa' => null,
                        'data_prenotazione'      => now(),
                    ]);

                    // Aggiorna contatori sessione
                    $sess->decrement('posti_disponibili', $totale);
                    $sess->decrement('posti_in_attesa', min($totale, max(0, $sess->posti_in_attesa ?? 0)));

                    // Aggiorna contatori per tipologia
                    $prenotazione->load('posti');
                    foreach ($prenotazione->posti as $pp) {
                        SessioneTipologiaPosto::where('sessione_id', $sess->id)
                            ->where('tipologia_posto_id', $pp->tipologia_posto_id)
                            ->decrement('posti_disponibili', $pp->quantita);
                    }

                    $postiLiberi -= $totale;

                    // Notifica
                    $prenotazione->load(['sessione.evento.ente', 'sessione.luoghi', 'posti.tipologiaPosto']);
                    $this->notifiche->invia($prenotazione, 'PRENOTAZIONE_APPROVATA');
                    $this->notifiche->inviaNotificaStaff($prenotazione);

                    $this->log->log(
                        $sessione->evento_id,
                        'lista_attesa.promossa_automatica',
                        "Lista attesa: {$prenotazione->cognome} {$prenotazione->nome} promosso automaticamente (cod. {$prenotazione->codice})."
                    );
                });
            } catch (\Throwable $e) {
                Log::error("ListaAttesaService: errore promozione automatica prenotazione #{$prenotazione->id}: {$e->getMessage()}");
            }

            if ($postiLiberi <= 0) {
                break;
            }
        }
    }

    // ─── Notifica posto disponibile (conferma manuale) ────────────────────────

    private function notificaPrimoPosto(Sessione $sessione): void
    {
        // Se c'è già qualcuno in stato NOTIFICATO non ancora scaduto, non notificarne un altro
        $giaNotificato = Prenotazione::where('sessione_id', $sessione->id)
            ->where('stato', 'NOTIFICATO')
            ->where('scadenza_riserva', '>', now())
            ->exists();

        if ($giaNotificato) {
            return;
        }

        // Posti effettivamente disponibili (esclusi i riservati da lock attivi)
        $postiLiberi = $sessione->posti_totali > 0
            ? max(0, $sessione->posti_disponibili - ($sessione->posti_riservati ?? 0))
            : PHP_INT_MAX;

        if ($postiLiberi <= 0) {
            return;
        }

        // Cerca il primo candidato la cui richiesta rientra nei posti disponibili
        $prenotazione = Prenotazione::where('sessione_id', $sessione->id)
            ->where('stato', 'IN_LISTA_ATTESA')
            ->where(function ($q) use ($postiLiberi, $sessione) {
                // Se la sessione ha posti illimitati (posti_totali = 0) accetta tutti
                if ($sessione->posti_totali === 0) {
                    $q->whereRaw('1=1');
                } else {
                    $q->where('posti_prenotati', '<=', $postiLiberi);
                }
            })
            ->orderBy('posizione_lista_attesa')
            ->first();

        if (!$prenotazione) {
            // Nessun candidato compatibile: i posti liberati sono insufficienti
            // per chiunque in lista; si attende un ulteriore annullamento.
            return;
        }

        $ore      = $sessione->lista_attesa_finestra_conferma_ore ?? 24;
        $scadenza = now()->addHours($ore);

        $prenotazione->update([
            'stato'            => 'NOTIFICATO',
            'notificato_at'    => now(),
            'scadenza_riserva' => $scadenza,
        ]);

        $prenotazione->load(['sessione.evento.ente', 'sessione.luoghi']);
        $this->notifiche->inviaAListaAttesa($prenotazione, 'LISTA_ATTESA_POSTO_DISPONIBILE');

        $this->log->log(
            $sessione->evento_id,
            'lista_attesa.notificato',
            "Lista attesa: {$prenotazione->cognome} {$prenotazione->nome} notificato per posto disponibile (scadenza {$scadenza->format('d/m/Y H:i')})."
        );
    }
}
