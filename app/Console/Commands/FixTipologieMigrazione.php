<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corregge le tipologie posto per eventi migrati da Crono1 con la sola tipologia "Ordinario".
 *
 * Uso:
 *   php artisan crono1:fix-tipologie --ente-id=5
 *   php artisan crono1:fix-tipologie --ente-id=5 --evento-id=42
 *   php artisan crono1:fix-tipologie --ente-id=5 --dry-run
 */
class FixTipologieMigrazione extends Command
{
    protected $signature = 'crono1:fix-tipologie
                            {--ente-id=      : ID ente Crono2 da processare}
                            {--evento-id=    : Limita la correzione a un singolo evento Crono2}
                            {--dry-run       : Simula senza scrivere}';

    protected $description = 'Corregge le tipologie posto di eventi migrati da Crono1 (sostituisce "Ordinario" con le tipologie reali dal JSON posti)';

    private bool $dryRun = false;

    public function handle(): int
    {
        $enteId   = (int) $this->option('ente-id');
        $eventoId = $this->option('evento-id') ? (int) $this->option('evento-id') : null;
        $this->dryRun = (bool) $this->option('dry-run');

        if (! $enteId) {
            $this->error('--ente-id è obbligatorio');
            return 1;
        }

        if ($this->dryRun) {
            $this->warn('=== DRY-RUN: nessuna modifica verrà salvata ===');
        }

        // Carica gli eventi da processare
        $query = DB::table('eventi')->where('ente_id', $enteId)->whereNull('deleted_at');
        if ($eventoId) {
            $query->where('id', $eventoId);
        }
        $eventi = $query->get(['id', 'titolo']);

        $this->info("Trovati {$eventi->count()} eventi per ente {$enteId}");

        $totCreati    = 0;
        $totRicollegati = 0;
        $totSkip      = 0;

        foreach ($eventi as $ev) {
            // Trova il record crono1 corrispondente tramite titolo (cerca nella sorgente)
            $evC1 = DB::connection('crono1')->table('eventi')
                ->where('titolo', $ev->titolo)
                ->first(['id']);

            if (! $evC1) {
                $this->line("  ↩ [SKIP] \"{$ev->titolo}\" — non trovato in Crono1");
                $totSkip++;
                continue;
            }

            // Raccoglie tipologie uniche dal campo posti JSON delle eventi_date
            $tipoMap = $this->raccogliTipologieDaC1($evC1->id);

            if (empty($tipoMap)) {
                $this->line("  — \"{$ev->titolo}\": nessuna tipologia nel JSON, già a Ordinario → ok");
                $totSkip++;
                continue;
            }

            // Verifica se l'evento ha solo "Ordinario"
            $tipologieC2 = DB::table('tipologie_posto')
                ->where('evento_id', $ev->id)
                ->whereNull('deleted_at')
                ->get(['id', 'nome']);

            $nomiC2 = $tipologieC2->pluck('nome')->toArray();
            $nomeFatti = array_keys($tipoMap);

            // Se le tipologie corrette sono già tutte presenti, salta
            if (empty(array_diff($nomeFatti, $nomiC2))) {
                $this->line("  ✓ \"{$ev->titolo}\": tipologie già corrette (" . implode(', ', $nomiC2) . ")");
                $totSkip++;
                continue;
            }

            $this->info("  ▶ Processo \"{$ev->titolo}\" (ID {$ev->id})");
            $this->line("    Tipologie C1: " . implode(', ', $nomeFatti));
            $this->line("    Tipologie C2 esistenti: " . implode(', ', $nomiC2));

            // Crea le tipologie mancanti
            $nuovaTipologieMap = []; // [nome => id]
            foreach ($tipologieC2 as $tp) {
                $nuovaTipologieMap[$tp->nome] = $tp->id;
            }

            $ord = count($nuovaTipologieMap);
            foreach ($tipoMap as $nome => $p) {
                if (isset($nuovaTipologieMap[$nome])) {
                    continue;
                }

                $costo = isset($p['costoUnitario']) && $p['costoUnitario'] > 0 ? (float) $p['costoUnitario'] : null;
                $max   = isset($p['massimo'])      && $p['massimo']      > 0 ? (int)   $p['massimo']      : null;
                $min   = isset($p['minimo'])       && $p['minimo']       > 0 ? (int)   $p['minimo']       : 1;

                $this->line("    + Creo tipologia: \"{$nome}\"" . ($max ? " (max {$max})" : ''));
                if (! $this->dryRun) {
                    $nuovaTipologieMap[$nome] = DB::table('tipologie_posto')->insertGetId([
                        'evento_id'       => $ev->id,
                        'ente_id'         => $enteId,
                        'nome'            => $nome,
                        'descrizione'     => $p['descrizione'] ?? null,
                        'gratuita'        => ($costo === null),
                        'costo'           => $costo,
                        'min_prenotabili' => $min,
                        'max_prenotabili' => $max,
                        'ordinamento'     => $ord,
                        'attiva'          => true,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                } else {
                    $nuovaTipologieMap[$nome] = 0; // placeholder
                }
                $totCreati++;
                $ord++;
            }

            // Per ogni sessione dell'evento, ricostruisce sessione_tipologie_posto e prenotazione_posti
            $sessioni = DB::table('sessioni')
                ->where('evento_id', $ev->id)
                ->whereNull('deleted_at')
                ->get(['id', 'posti_totali', 'posti_disponibili']);

            foreach ($sessioni as $s) {
                // Recupera il record eventi_date corrispondente
                $edC1 = DB::connection('crono1')->table('eventi_date')
                    ->where('idEvento', $evC1->id)
                    ->where('dataInizio', DB::table('sessioni')->where('id', $s->id)->value('data_inizio'))
                    ->first(['id', 'posti']);

                $postiSessioneJson = ($edC1 && !empty($edC1->posti))
                    ? json_decode($edC1->posti, true)
                    : null;

                if (! $postiSessioneJson || ! is_array($postiSessioneJson)) {
                    continue;
                }

                // Recupera l'id della "Ordinario" per questa sessione (da sostituire)
                $ordinarioTp = DB::table('tipologie_posto')
                    ->where('evento_id', $ev->id)
                    ->where('nome', 'Ordinario')
                    ->whereNull('deleted_at')
                    ->first(['id']);

                foreach ($postiSessioneJson as $p) {
                    $nomeT = trim($p['tipologia'] ?? $p['id'] ?? '');
                    $tpId  = $nuovaTipologieMap[$nomeT] ?? null;
                    if (! $tpId) {
                        continue;
                    }

                    $postiTp = isset($p['postiTotali']) && $p['postiTotali'] > 0 ? (int) $p['postiTotali'] : 0;

                    // Aggiunge/aggiorna sessione_tipologie_posto per la tipologia reale
                    $stpExist = DB::table('sessione_tipologie_posto')
                        ->where('sessione_id', $s->id)
                        ->where('tipologia_posto_id', $tpId)
                        ->first();

                    if (! $stpExist) {
                        $this->line("      + STP sessione {$s->id} → tipologia \"{$nomeT}\" (posti_totali={$postiTp})");
                        if (! $this->dryRun) {
                            DB::table('sessione_tipologie_posto')->insert([
                                'sessione_id'        => $s->id,
                                'tipologia_posto_id' => $tpId,
                                'posti_totali'       => $postiTp,
                                'posti_disponibili'  => $postiTp,
                                'posti_riservati'    => 0,
                                'attiva'             => true,
                            ]);
                        }
                        $totRicollegati++;
                    }
                }

                // Ricollegare le prenotazione_posti dall'Ordinario alle tipologie reali
                if ($ordinarioTp) {
                    $this->ricollegaPrenotazioniPosti($s->id, $ordinarioTp->id, $nuovaTipologieMap);
                }

                // Elimina la riga "Ordinario" da sessione_tipologie_posto
                if ($ordinarioTp) {
                    $this->line("      - Rimuovo STP Ordinario (sessione {$s->id})");
                    if (! $this->dryRun) {
                        DB::table('sessione_tipologie_posto')
                            ->where('sessione_id', $s->id)
                            ->where('tipologia_posto_id', $ordinarioTp->id)
                            ->delete();
                    }
                }
            }

            // Soft-delete (o delete fisico) della tipologia "Ordinario" a livello evento
            // solo se non ci sono più prenotazione_posti che la referenziano
            $ordTp = DB::table('tipologie_posto')
                ->where('evento_id', $ev->id)
                ->where('nome', 'Ordinario')
                ->whereNull('deleted_at')
                ->first(['id']);

            if ($ordTp) {
                $refCount = DB::table('prenotazione_posti')
                    ->where('tipologia_posto_id', $ordTp->id)
                    ->count();

                if ($refCount === 0) {
                    $this->line("    - Elimino tipologia \"Ordinario\" (ID {$ordTp->id}) — nessuna prenotazione referenzia");
                    if (! $this->dryRun) {
                        DB::table('tipologie_posto')
                            ->where('id', $ordTp->id)
                            ->update(['deleted_at' => now()]);
                    }
                } else {
                    $this->warn("    ⚠ Tipologia \"Ordinario\" (ID {$ordTp->id}) ha ancora {$refCount} prenotazione_posti — mantenuta, controllare manualmente");
                }
            }

            // Ricalcola posti_disponibili per sessione_tipologie_posto
            if (! $this->dryRun) {
                $this->ricalcolaPosti($ev->id);
            }
        }

        $this->info("");
        $this->info("=== Riepilogo ===");
        $this->info("  Tipologie create : {$totCreati}");
        $this->info("  STP ricollegate  : {$totRicollegati}");
        $this->info("  Skip             : {$totSkip}");

        return 0;
    }

    /** Legge le tipologie uniche dal campo posti JSON di tutti gli eventi_date per un evento Crono1. */
    private function raccogliTipologieDaC1(int $eventoIdC1): array
    {
        $tipoMap = [];
        $rows = DB::connection('crono1')->table('eventi_date')
            ->where('idEvento', $eventoIdC1)
            ->whereNull('deleted_at')
            ->get(['posti']);

        foreach ($rows as $row) {
            if (empty($row->posti)) {
                continue;
            }
            $arr = json_decode($row->posti, true);
            if (! is_array($arr)) {
                continue;
            }
            foreach ($arr as $p) {
                $nome = trim($p['tipologia'] ?? $p['id'] ?? '');
                if ($nome !== '' && ! isset($tipoMap[$nome])) {
                    $tipoMap[$nome] = $p;
                }
            }
        }

        return $tipoMap;
    }

    /**
     * Ricollegare i record prenotazione_posti associati a $ordinarioTpId verso
     * le tipologie reali usando il campo posti JSON delle prenotazioni Crono1.
     */
    private function ricollegaPrenotazioniPosti(int $sessioneId, int $ordinarioTpId, array $nuovaTipologieMap): void
    {
        // Ottieni tutte le prenotazioni della sessione
        $prenotazioni = DB::table('prenotazioni')
            ->where('sessione_id', $sessioneId)
            ->whereNull('deleted_at')
            ->get(['id', 'codice']);

        foreach ($prenotazioni as $p) {
            // Leggi il posti JSON da Crono1
            $pC1 = DB::connection('crono1')->table('prenotazioni')
                ->where('codice', $p->codice)
                ->first(['posti', 'postiPrenotati']);

            if (! $pC1 || empty($pC1->posti)) {
                continue;
            }

            $postiArr = json_decode($pC1->posti, true);
            if (! is_array($postiArr)) {
                continue;
            }

            // Rimuovi la riga "Ordinario" per questa prenotazione
            $haOrdinario = DB::table('prenotazione_posti')
                ->where('prenotazione_id', $p->id)
                ->where('tipologia_posto_id', $ordinarioTpId)
                ->exists();

            if (! $haOrdinario) {
                continue; // Già corretto o non presente
            }

            // Crea le righe per le tipologie reali
            foreach ($postiArr as $pb) {
                $nomeT = trim($pb['tipologia'] ?? '');
                $tpId  = $nuovaTipologieMap[$nomeT] ?? null;
                if (! $tpId) {
                    continue;
                }

                $qty       = max(1, (int) ($pb['quantitaRichiesta'] ?? 0));
                $costoUnit = isset($pb['costoUnitario']) && $pb['costoUnitario'] > 0
                                ? (float) $pb['costoUnitario'] : null;

                $exists = DB::table('prenotazione_posti')
                    ->where('prenotazione_id', $p->id)
                    ->where('tipologia_posto_id', $tpId)
                    ->exists();

                if (! $exists) {
                    if (! $this->dryRun) {
                        DB::table('prenotazione_posti')->insert([
                            'prenotazione_id'    => $p->id,
                            'tipologia_posto_id' => $tpId,
                            'quantita'           => $qty,
                            'costo_unitario'     => $costoUnit,
                            'costo_riga'         => $costoUnit ? $qty * $costoUnit : null,
                        ]);
                    }
                    $this->line("        + PP prenotazione {$p->codice}: \"{$nomeT}\" x{$qty}");
                }
            }

            // Rimuovi la riga Ordinario solo se sono state create le righe reali
            $nuoveRighe = DB::table('prenotazione_posti')
                ->where('prenotazione_id', $p->id)
                ->whereIn('tipologia_posto_id', array_values($nuovaTipologieMap))
                ->count();

            if ($nuoveRighe > 0) {
                $this->line("        - PP prenotazione {$p->codice}: rimuovo Ordinario");
                if (! $this->dryRun) {
                    DB::table('prenotazione_posti')
                        ->where('prenotazione_id', $p->id)
                        ->where('tipologia_posto_id', $ordinarioTpId)
                        ->delete();
                }
            }
        }
    }

    /** Ricalcola posti_disponibili per tutte le sessioni dell'evento. */
    private function ricalcolaPosti(int $eventoIdC2): void
    {
        $sessioni = DB::table('sessioni')
            ->where('evento_id', $eventoIdC2)
            ->whereNull('deleted_at')
            ->get(['id', 'posti_totali']);

        foreach ($sessioni as $s) {
            if ($s->posti_totali === 0) {
                continue;
            }

            $occupati = DB::table('prenotazioni')
                ->where('sessione_id', $s->id)
                ->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE'])
                ->whereNull('deleted_at')
                ->sum('posti_prenotati');

            DB::table('sessioni')->where('id', $s->id)
                ->update(['posti_disponibili' => max(0, $s->posti_totali - $occupati)]);

            // Aggiorna sessione_tipologie_posto con pool proprio
            $stps = DB::table('sessione_tipologie_posto')->where('sessione_id', $s->id)->get();
            foreach ($stps as $stp) {
                if ($stp->posti_totali === 0) {
                    continue;
                }
                $prenotIds = DB::table('prenotazioni')
                    ->where('sessione_id', $s->id)
                    ->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE'])
                    ->whereNull('deleted_at')
                    ->pluck('id');

                $occTp = DB::table('prenotazione_posti')
                    ->whereIn('prenotazione_id', $prenotIds)
                    ->where('tipologia_posto_id', $stp->tipologia_posto_id)
                    ->sum('quantita');

                DB::table('sessione_tipologie_posto')
                    ->where('id', $stp->id)
                    ->update(['posti_disponibili' => max(0, $stp->posti_totali - $occTp)]);
            }
        }
    }
}
