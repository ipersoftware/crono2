<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Svuota tutte le tabelle dati di Crono2 (eventio, sessioni, prenotazioni, luoghi…)
 * mantenendo intatta la struttura e gli enti/utenti.
 *
 * Uso:
 *   php artisan crono2:truncate
 *   php artisan crono2:truncate --yes   (salta la conferma)
 */
class TruncateCrono2 extends Command
{
    protected $signature = 'crono2:truncate {--yes : Salta la richiesta di conferma}';

    protected $description = 'Svuota le tabelle dati di Crono2 (per reset ambienti di test)';

    // Ordine di truncate: prima i figli, poi i padri.
    // Wrappato con FOREIGN_KEY_CHECKS=0 per sicurezza.
    private const TABELLE = [
        'risposte_form',
        'campi_form',
        'prenotazione_posti',
        'notifiche_log',
        'prenotazioni',
        'sessione_tipologie_posto',
        'sessione_luogo',
        'sessioni',
        'tipologie_posto',
        'evento_log',
        'evento_luogo',
        'evento_tag',
        'eventi',
        'tags',
        'luoghi',
    ];

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('yes')) {
            $this->error('Questo comando NON può essere eseguito in ambiente production.');
            return self::FAILURE;
        }

        if (! $this->option('yes')) {
            if (! $this->confirm('Stai per svuotare tutte le tabelle dati di Crono2. Sei sicuro?')) {
                $this->info('Operazione annullata.');
                return self::SUCCESS;
            }
        }

        $this->info('Disabilito FK checks e svuoto le tabelle…');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (self::TABELLE as $tabella) {
            DB::table($tabella)->truncate();
            $this->line("  ✓ {$tabella}");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('✅ Tutte le tabelle svuotate.');
        return self::SUCCESS;
    }
}
