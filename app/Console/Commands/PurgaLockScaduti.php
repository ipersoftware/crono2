<?php

namespace App\Console\Commands;

use App\Models\PrenotazionePosto;
use App\Models\PrenotazioneTemporanea;
use App\Models\Sessione;
use App\Models\SessioneTipologiaPosto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgaLockScaduti extends Command
{
    protected $signature   = 'prenotazioni:purga-lock {--forza : Rimuove tutti i lock, anche quelli non ancora scaduti}';
    protected $description = 'Elimina i lock temporanei scaduti, ripristina i posti_riservati e ricalibra posti_disponibili';

    public function handle(): int
    {
        $query = PrenotazioneTemporanea::query();
        if (!$this->option('forza')) {
            $query->where('scadenza_at', '<=', now());
        }
        $lockScaduti = $query->get();

        if ($lockScaduti->isEmpty()) {
            $this->line('Nessun lock scaduto da rimuovere.');
            // Ricalibra comunque per correggere eventuali inconsistenze pregresse
            $this->ricalibra(collect());
            return self::SUCCESS;
        }

        // Raccoglie gli id sessione coinvolti prima di eliminare i lock
        $sessionIds = $lockScaduti->pluck('sessione_id')->unique();

        $rimossi = 0;
        foreach ($lockScaduti as $lock) {
            DB::transaction(function () use ($lock) {
                $sessione = Sessione::lockForUpdate()->find($lock->sessione_id);
                if ($sessione) {
                    $posti  = collect($lock->dettaglio_tipologie);
                    $totale = $posti->sum('quantita');
                    $sessione->decrement('posti_riservati', max(0, min($totale, $sessione->posti_riservati)));

                    foreach ($posti as $posto) {
                        SessioneTipologiaPosto::where('sessione_id', $sessione->id)
                            ->where('tipologia_posto_id', $posto['tipologia_id'])
                            ->each(fn($st) => $st->decrement(
                                'posti_riservati',
                                max(0, min($posto['quantita'], $st->posti_riservati))
                            ));
                    }
                }
                $lock->delete();
            });
            $rimossi++;
        }

        $this->info("Rimossi {$rimossi} lock scaduti.");

        // Ricalibra posti_disponibili per le sessioni interessate
        $this->ricalibra($sessionIds);

        return self::SUCCESS;
    }

    /**
     * Ricalcola posti_disponibili e posti_riservati per le sessioni indicate
     * (o per tutte le sessioni attive se $sessionIds è vuoto).
     * Usa le prenotazioni reali come fonte di verità.
     */
    private function ricalibra(\Illuminate\Support\Collection $sessionIds): void
    {
        $statiAttivi = ['CONFERMATA', 'DA_CONFERMARE', 'RISERVATA'];

        $query = Sessione::where('posti_totali', '>', 0);
        if ($sessionIds->isNotEmpty()) {
            $query->whereIn('id', $sessionIds);
        }
        $sessioni = $query->get();

        foreach ($sessioni as $sessione) {
            DB::transaction(function () use ($sessione, $statiAttivi) {
                $sessione = Sessione::lockForUpdate()->find($sessione->id);

                // Posti confermati reali
                $confermati = $sessione->prenotazioni()
                    ->whereIn('stato', $statiAttivi)
                    ->sum('posti_prenotati');

                // Lock ancora attivi (non scaduti) per questa sessione
                $lockAttivi = PrenotazioneTemporanea::where('sessione_id', $sessione->id)
                    ->where('scadenza_at', '>', now())
                    ->sum('posti_totali');

                $nuovoDisp = max(0, $sessione->posti_totali - $confermati);

                if ($sessione->posti_disponibili !== $nuovoDisp || $sessione->posti_riservati !== $lockAttivi) {
                    $this->line(sprintf(
                        '  Sessione %d: disp %d→%d, riservati %d→%d',
                        $sessione->id,
                        $sessione->posti_disponibili, $nuovoDisp,
                        $sessione->posti_riservati, $lockAttivi
                    ));
                    $sessione->update(['posti_disponibili' => $nuovoDisp, 'posti_riservati' => $lockAttivi]);
                }

                // Ricalibra per tipologia
                $stps = SessioneTipologiaPosto::where('sessione_id', $sessione->id)
                    ->where('posti_totali', '>', 0)
                    ->get();

                foreach ($stps as $stp) {
                    $confermatiTip = PrenotazionePosto::where('tipologia_posto_id', $stp->tipologia_posto_id)
                        ->whereHas('prenotazione', fn($q) => $q
                            ->where('sessione_id', $sessione->id)
                            ->whereIn('stato', $statiAttivi)
                        )
                        ->sum('quantita');

                    // Lock attivi per questa tipologia
                    $lockAttiviTip = PrenotazioneTemporanea::where('sessione_id', $sessione->id)
                        ->where('scadenza_at', '>', now())
                        ->get()
                        ->sum(function ($lr) use ($stp) {
                            $det = collect($lr->dettaglio_tipologie)
                                ->firstWhere('tipologia_id', $stp->tipologia_posto_id);
                            return $det ? (int) $det['quantita'] : 0;
                        });

                    $nuovoDispTip = max(0, $stp->posti_totali - $confermatiTip);

                    if ($stp->posti_disponibili !== $nuovoDispTip || $stp->posti_riservati !== $lockAttiviTip) {
                        $this->line(sprintf(
                            '    Tipologia %d (sessione %d): disp %d→%d, riservati %d→%d',
                            $stp->tipologia_posto_id, $sessione->id,
                            $stp->posti_disponibili, $nuovoDispTip,
                            $stp->posti_riservati, $lockAttiviTip
                        ));
                        $stp->update(['posti_disponibili' => $nuovoDispTip, 'posti_riservati' => $lockAttiviTip]);
                    }
                }
            });
        }

        $this->info('Ricalibrazione completata.');
    }
}
