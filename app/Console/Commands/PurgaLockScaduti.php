<?php

namespace App\Console\Commands;

use App\Models\PrenotazioneTemporanea;
use App\Models\Sessione;
use App\Models\SessioneTipologiaPosto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgaLockScaduti extends Command
{
    protected $signature   = 'prenotazioni:purga-lock {--forza : Rimuove tutti i lock, anche quelli non ancora scaduti}';
    protected $description = 'Elimina i lock temporanei scaduti e ripristina i posti_riservati';

    public function handle(): int
    {
        $query = PrenotazioneTemporanea::query();
        if (!$this->option('forza')) {
            $query->where('scadenza_at', '<=', now());
        }
        $lockScaduti = $query->get();

        if ($lockScaduti->isEmpty()) {
            $this->line('Nessun lock scaduto da rimuovere.');
            return self::SUCCESS;
        }

        $rimossi = 0;

        foreach ($lockScaduti as $lock) {
            /** @var \App\Models\PrenotazioneTemporanea $lock */
            DB::transaction(function () use ($lock) {
                $sessione = Sessione::lockForUpdate()->find($lock->sessione_id);
                if ($sessione) {
                    $posti  = collect($lock->dettaglio_tipologie);
                    $totale = $posti->sum('quantita');
                    $sessione->decrement('posti_riservati', max(0, min($totale, $sessione->posti_riservati)));

                    foreach ($posti as $posto) {
                        SessioneTipologiaPosto::where('sessione_id', $sessione->id)
                            ->where('tipologia_posto_id', $posto['tipologia_id'])
                            ->each(function ($st) use ($posto) {
                                $st->decrement('posti_riservati', max(0, min($posto['quantita'], $st->posti_riservati)));
                            });
                    }
                }
                $lock->delete();
            });
            $rimossi++;
        }

        $this->info("Rimossi {$rimossi} lock scaduti.");
        return self::SUCCESS;
    }
}
