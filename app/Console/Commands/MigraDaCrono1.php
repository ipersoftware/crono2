<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Comando di migrazione dati da Crono1 a Crono2.
 *
 * Uso:
 *   php artisan crono1:migra --customer-id=1 --ente-id=5
 *   php artisan crono1:migra --customer-id=1 --ente-id=5 --dry-run
 *   php artisan crono1:migra --customer-id=1 --ente-id=5 --skip-prenotazioni
 *   php artisan crono1:migra --customer-id=1 --ente-id=5 --force
 *
 * Cosa migra (in ordine):
 *   1. Luoghi
 *   2. Eventi + tipologia posto "Ordinario" per ciascuno
 *   3. Sessioni (eventi_date) + sessione_luogo + sessione_tipologie_posto
 *   4. Prenotazioni + prenotazione_posti (opzionale con --skip-prenotazioni)
 *
 * Idempotente: rieseguendo senza --force salta i record già presenti
 * (matching per nome su luoghi, per slug su eventi, per codice su prenotazioni).
 */
class MigraDaCrono1 extends Command
{
    protected $signature = 'crono1:migra
                            {--customer-id= : ID del customer Crono1 da migrare}
                            {--ente-id=     : ID dell\'ente Crono2 destinazione}
                            {--dry-run      : Simula senza scrivere dati}
                            {--skip-prenotazioni : Salta la migrazione delle prenotazioni}
                            {--force        : Aggiorna i record già esistenti invece di saltarli}';

    protected $description = 'Migra i dati da un customerID Crono1 a un ente Crono2';

    // Mappe idCrono1 → idCrono2 costruite durante la migrazione
    private array $luoghiMap    = [];  // luogo.id       → Luogo.id
    private array $eventiMap    = [];  // eventi.id       → Evento.id
    private array $tipologieMap = [];  // eventi.id       → [tipologiaNome => TipologiaPosto.id, ...]
    private array $sessioniMap  = [];  // eventi_date.id  → Sessione.id
    private array $campiFormMap = [];  // eventoIdC2      → campo_form.id (campo "Ente/Organizzazione")

    private bool $dryRun = false;
    private bool $force  = false;

    // Contatori per il riepilogo finale
    private array $contatori = [
        'luoghi'        => ['skip' => 0, 'insert' => 0, 'update' => 0],
        'eventi'        => ['skip' => 0, 'insert' => 0, 'update' => 0],
        'sessioni'      => ['skip' => 0, 'insert' => 0, 'update' => 0],
        'prenotazioni'  => ['skip' => 0, 'insert' => 0, 'update' => 0],
        'risposte_form' => ['skip' => 0, 'insert' => 0, 'update' => 0],
    ];

    // -------------------------------------------------------------------------
    // Entry point
    // -------------------------------------------------------------------------

    public function handle(): int
    {
        $customerId = (int) $this->option('customer-id');
        $enteId     = (int) $this->option('ente-id');
        $this->dryRun = (bool) $this->option('dry-run');
        $this->force  = (bool) $this->option('force');

        if (! $customerId || ! $enteId) {
            $this->error('Specificare --customer-id e --ente-id');
            return self::FAILURE;
        }

        // Verifica ente destinazione in Crono2
        $ente = DB::table('enti')->find($enteId);
        if (! $ente) {
            $this->error("Ente ID {$enteId} non trovato in Crono2.");
            return self::FAILURE;
        }

        // Verifica customer sorgente in Crono1
        $customer = DB::connection('crono1')->table('customers')->find($customerId);
        if (! $customer) {
            $this->error("Customer ID {$customerId} non trovato in Crono1.");
            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('⚠  DRY-RUN attivo — nessun dato verrà scritto nel DB.');
        }

        $this->info("╔══════════════════════════════════════════════════════════════╗");
        $this->info("  Migrazione Crono1 → Crono2");
        $this->info("  Sorgente : customer «{$customer->name}» (ID {$customerId})");
        $this->info("  Dest.    : ente «{$ente->nome}» (ID {$enteId})");
        $this->info("╚══════════════════════════════════════════════════════════════╝");
        $this->newLine();

        DB::transaction(function () use ($customerId, $enteId) {
            $this->migraLuoghi($customerId, $enteId);
            $this->migraEventi($customerId, $enteId);
            $this->migraSessioni($customerId, $enteId);

            if (! $this->option('skip-prenotazioni')) {
                $this->migraPrenotazioni($customerId, $enteId);
            }
        });

        $this->newLine();
        $this->riepilogo();

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // 1. Luoghi
    // -------------------------------------------------------------------------

    private function migraLuoghi(int $customerId, int $enteId): void
    {
        $rows = DB::connection('crono1')->table('luoghi')
            ->where('customerID', $customerId)
            ->get();

        $this->info("▶ Luoghi ({$rows->count()})");

        foreach ($rows as $r) {
            $existing = DB::table('luoghi')
                ->where('ente_id', $enteId)
                ->where('nome', $r->nome)
                ->whereNull('deleted_at')
                ->first();

            if ($existing && ! $this->force) {
                $this->line("    ↩ [SKIP] {$r->nome}");
                $this->luoghiMap[$r->id] = $existing->id;
                $this->contatori['luoghi']['skip']++;
                continue;
            }

            $data = [
                'ente_id'     => $enteId,
                'nome'        => $r->nome,
                'descrizione' => $r->descrizione ?? null,
                'slug'        => $this->slugUnico('luoghi', 'ente_id', $enteId, $r->nome),
                'indirizzo'   => $r->indirizzo ?? null,
                'citta'       => $r->citta ?? null,
                'provincia'   => isset($r->provincia) ? mb_substr($r->provincia, 0, 2) : null,
                'cap'         => $r->cap ?? null,
                'lat'         => isset($r->lat) && $r->lat ? (float) $r->lat : null,
                'lng'         => isset($r->lng) && $r->lng ? (float) $r->lng : null,
                'maps_url'    => $r->maps_url ?? $r->link ?? null,
                'telefono'    => $r->telefono ?? null,
                'email'       => $r->email ?? null,
                'stato'       => 'ATTIVO',
                'updated_at'  => now(),
            ];

            if (! $this->dryRun) {
                if ($existing) {
                    DB::table('luoghi')->where('id', $existing->id)->update($data);
                    $this->luoghiMap[$r->id] = $existing->id;
                    $this->contatori['luoghi']['update']++;
                } else {
                    $data['created_at'] = now();
                    $id = DB::table('luoghi')->insertGetId($data);
                    $this->luoghiMap[$r->id] = $id;
                    $this->contatori['luoghi']['insert']++;
                }
                $this->line("    ✓ {$r->nome} → ID {$this->luoghiMap[$r->id]}");
            } else {
                $this->line("    [DRY] {$r->nome}");
                $this->luoghiMap[$r->id] = -$r->id;
                $this->contatori['luoghi']['insert']++;
            }
        }
    }

    // -------------------------------------------------------------------------
    // 2. Eventi + TipologiaPosto "Ordinario"
    // -------------------------------------------------------------------------

    private function migraEventi(int $customerId, int $enteId): void
    {
        $rows = DB::connection('crono1')->table('eventi')
            ->where('customerID', $customerId)
            ->get();

        $this->info("▶ Eventi ({$rows->count()})");

        foreach ($rows as $r) {
            $slug = $this->slugUnico('eventi', 'ente_id', $enteId, $r->titolo, $r->hashTag ?? null);

            $existing = DB::table('eventi')
                ->where('ente_id', $enteId)
                ->where('slug', $slug)
                ->whereNull('deleted_at')
                ->first();

            if ($existing && ! $this->force) {
                $this->line("    ↩ [SKIP] {$r->titolo}");
                $this->eventiMap[$r->id] = $existing->id;
                $this->recuperaTipologiaDefault($r->id, $existing->id, $enteId);
                $this->contatori['eventi']['skip']++;
                continue;
            }

            $stato = $this->mappaStatoEvento($r->stato ?? 'IN_ATTESA');

            $data = [
                'ente_id'                      => $enteId,
                'serie_id'                     => null,
                'titolo'                        => $r->titolo,
                'slug'                          => $slug,
                // Crono1: `descrizione` = testo breve, `layout` = HTML ricco dell'editor
                // Crono2: `descrizione_breve` = testo breve, `descrizione` = HTML ricco
                'descrizione_breve'             => isset($r->descrizione) ? mb_substr(strip_tags($r->descrizione), 0, 512) : null,
                'descrizione'                   => (isset($r->layout) && trim($r->layout) !== '') ? $r->layout : ($r->descrizione ?? null),
                'stato'                         => $stato,
                'pubblico'                      => (bool) ($r->pubblico ?? false),
                'in_evidenza'                   => false,
                'ordinamento'                   => (int) ($r->ordinamento ?? 0),
                'visibile_dal'                  => $this->dataSicura($r->visibileDal ?? null),
                'visibile_al'                   => $this->dataSicura($r->visibileAl ?? null),
                'prenotabile_dal'               => $this->dataSicura($r->prenotabileDal ?? null),
                'prenotabile_al'                => $this->dataSicura($r->prenotabileAl ?? null),
                'richiede_approvazione'         => false,
                'consenti_multi_sessione'       => (bool) ($r->consenti_multi_sessione ?? false),
                'consenti_prenotazione_guest'   => (bool) ($r->consenti_prenotazione_guest ?? true),
                'cancellazione_consentita_ore'  => null,
                'mostra_disponibilita'          => (bool) ($r->mostraDisponibilita ?? true),
                'attiva_note'                   => (bool) ($r->abilitaNote ?? false),
                'nota_etichetta'                => $r->noteEtichetta ?? null,
                'costo'                         => null,
                'attributi'                     => null,
                'updated_at'                    => now(),
            ];

            if (! $this->dryRun) {
                if ($existing) {
                    DB::table('eventi')->where('id', $existing->id)->update($data);
                    $eventoId = $existing->id;
                    $this->contatori['eventi']['update']++;
                } else {
                    $data['created_at'] = now();
                    $eventoId = DB::table('eventi')->insertGetId($data);
                    $this->contatori['eventi']['insert']++;
                }
                $this->eventiMap[$r->id] = $eventoId;
                $this->tipologieMap[$r->id] = $this->creaTipologieDaPostiJson($r->id, $eventoId, $enteId);
                $this->line("    ✓ {$r->titolo} → ID {$eventoId}");
            } else {
                $this->line("    [DRY] {$r->titolo}");
                $this->eventiMap[$r->id]    = -$r->id;
                $this->tipologieMap[$r->id] = ['_dry' => -$r->id];
                $this->contatori['eventi']['insert']++;
            }
        }
    }

    /**
     * Legge le tipologie di posto dal campo JSON `posti` delle eventi_date di Crono1
     * e crea (o recupera) i corrispondenti record in tipologie_posto di Crono2.
     * Restituisce una mappa [tipologiaNome => tipologiaC2_id].
     * Fallback a 'Ordinario' se non ci sono tipologie nel JSON.
     */
    private function creaTipologieDaPostiJson(int $eventoIdC1, int $eventoId, int $enteId): array
    {
        // Raccoglie le tipologie uniche da tutte le sessioni dell'evento
        $tipoMap = [];
        $sessioniC1 = DB::connection('crono1')->table('eventi_date')
            ->where('idEvento', $eventoIdC1)
            ->whereNull('deleted_at')
            ->get(['posti']);

        foreach ($sessioniC1 as $s) {
            if (empty($s->posti)) {
                continue;
            }
            $postiArr = json_decode($s->posti, true);
            if (! is_array($postiArr)) {
                continue;
            }
            foreach ($postiArr as $p) {
                $nome = trim($p['tipologia'] ?? $p['id'] ?? '');
                if ($nome === '' || isset($tipoMap[$nome])) {
                    continue;
                }
                $tipoMap[$nome] = $p;
            }
        }

        if (empty($tipoMap)) {
            // Nessuna tipologia nel JSON: fallback a 'Ordinario'
            return ['Ordinario' => $this->creaTipologiaOrdinariaFallback($eventoId, $enteId)];
        }

        $result = [];
        $ord = 0;
        foreach ($tipoMap as $nome => $p) {
            $costo = isset($p['costoUnitario']) && $p['costoUnitario'] > 0 ? (float) $p['costoUnitario'] : null;
            $max   = isset($p['massimo'])      && $p['massimo']      > 0 ? (int)   $p['massimo']      : null;
            $min   = isset($p['minimo'])       && $p['minimo']       > 0 ? (int)   $p['minimo']       : 1;

            $existing = DB::table('tipologie_posto')
                ->where('evento_id', $eventoId)
                ->where('nome', $nome)
                ->whereNull('deleted_at')
                ->first();

            if ($existing) {
                $result[$nome] = $existing->id;
            } else {
                $result[$nome] = DB::table('tipologie_posto')->insertGetId([
                    'evento_id'       => $eventoId,
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
            }
            $ord++;
        }

        return $result;
    }

    /** Crea (o recupera) la tipologia posto "Ordinario" come fallback. */
    private function creaTipologiaOrdinariaFallback(int $eventoId, int $enteId): int
    {
        $existing = DB::table('tipologie_posto')
            ->where('evento_id', $eventoId)
            ->where('nome', 'Ordinario')
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return $existing->id;
        }

        return DB::table('tipologie_posto')->insertGetId([
            'evento_id'       => $eventoId,
            'ente_id'         => $enteId,
            'nome'            => 'Ordinario',
            'gratuita'        => true,
            'costo'           => null,
            'min_prenotabili' => 1,
            'max_prenotabili' => null,
            'ordinamento'     => 0,
            'attiva'          => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    /** Recupera (o crea) la mappa tipologie per un evento già esistente in Crono2. */
    private function recuperaTipologiaDefault(int $eventoIdC1, int $eventoId, int $enteId): void
    {
        if (isset($this->tipologieMap[$eventoIdC1])) {
            return;
        }

        $this->tipologieMap[$eventoIdC1] = $this->creaTipologieDaPostiJson($eventoIdC1, $eventoId, $enteId);
    }

    // -------------------------------------------------------------------------
    // 5. Sessioni (eventi_date)
    // -------------------------------------------------------------------------

    private function migraSessioni(int $customerId, int $enteId): void
    {
        $rows = DB::connection('crono1')->table('eventi_date')
            ->whereIn('idEvento', function ($q) use ($customerId) {
                $q->select('id')->from('eventi')->where('customerID', $customerId);
            })
            ->get();

        $this->info("▶ Sessioni / Date ({$rows->count()})");

        foreach ($rows as $r) {
            $eventoIdC2 = $this->eventiMap[$r->idEvento] ?? null;
            if (! $eventoIdC2) {
                $this->warn("    ⚠ Sessione ID {$r->id} — evento Crono1 ID {$r->idEvento} non trovato nella mappa, skip");
                continue;
            }

            // Matching per evitare duplicati: stesso evento + stessa data_inizio
            $dataInizio = $this->dataSicura($r->dataInizio ?? $r->dataEvento ?? null);
            $existing = DB::table('sessioni')
                ->where('evento_id', $eventoIdC2)
                ->where('data_inizio', $dataInizio)
                ->whereNull('deleted_at')
                ->first();

            if ($existing && ! $this->force) {
                $this->line("    ↩ [SKIP] sessione {$r->id} ({$dataInizio})");
                $this->sessioniMap[$r->id] = $existing->id;
                $this->contatori['sessioni']['skip']++;
                continue;
            }

            $postiTotali   = (int) ($r->postiTotali ?? 0);
            $rendiNonDisp  = (bool) ($r->rendiNonDisponibile ?? false);
            $soglia        = isset($r->rendiNonDisponibileNumeroPrenotazioni) && $r->rendiNonDisponibileNumeroPrenotazioni > 0
                             ? (int) $r->rendiNonDisponibileNumeroPrenotazioni
                             : null;

            $dataFine = $this->dataSicura($r->dataFine ?? null);
            if (! $dataFine && $dataInizio) {
                // Crono1 non ha sempre data_fine: usa data_inizio + 1 ora come fallback
                $dataFine = Carbon::parse($dataInizio)->addHour()->format('Y-m-d H:i:s');
            }

            $data = [
                'evento_id'                          => $eventoIdC2,
                'titolo'                             => $r->sessione ?? null,
                'descrizione'                        => $r->descrizione ?? null,
                'data_inizio'                        => $dataInizio,
                'data_fine'                          => $dataFine,
                'posti_totali'                       => $postiTotali,
                'posti_disponibili'                  => (int) ($r->postiDisponibili ?? $postiTotali),
                'posti_in_attesa'                    => 0,
                'posti_riservati'                    => 0,
                'prenotabile'                        => true,
                'forza_non_disponibile'              => $rendiNonDisp,
                'soglia_chiusura_prenotazioni'       => $soglia,
                'attiva_lista_attesa'                => false,
                'lista_attesa_finestra_conferma_ore' => 24,
                'durata_lock_minuti'                 => 15,
                'visualizza_disponibili'             => false,
                'note_pubbliche'                     => null,
                'updated_at'                         => now(),
            ];

            if (! $this->dryRun) {
                if ($existing) {
                    DB::table('sessioni')->where('id', $existing->id)->update($data);
                    $sessioneId = $existing->id;
                    $this->contatori['sessioni']['update']++;
                } else {
                    $data['created_at'] = now();
                    $sessioneId = DB::table('sessioni')->insertGetId($data);
                    $this->contatori['sessioni']['insert']++;
                }

                $this->sessioniMap[$r->id] = $sessioneId;

                // sessione_tipologie_posto: una riga per ciascuna tipologia dal JSON posti
                $postiSessioneJson = !empty($r->posti) ? json_decode($r->posti, true) : null;
                $tipologieEvento   = $this->tipologieMap[$r->idEvento] ?? [];

                if ($postiSessioneJson && is_array($postiSessioneJson) && !empty($tipologieEvento)) {
                    foreach ($postiSessioneJson as $p) {
                        $nomeT = trim($p['tipologia'] ?? $p['id'] ?? '');
                        $tpId  = $tipologieEvento[$nomeT] ?? null;
                        if (! $tpId) {
                            continue;
                        }
                        // Se la tipologia ha posti propri li usa, altrimenti 0 (condivide il pool globale)
                        $postiTp = isset($p['postiTotali']) && $p['postiTotali'] > 0 ? (int) $p['postiTotali'] : 0;

                        $stpExist = DB::table('sessione_tipologie_posto')
                            ->where('sessione_id', $sessioneId)
                            ->where('tipologia_posto_id', $tpId)
                            ->first();

                        if (! $stpExist) {
                            DB::table('sessione_tipologie_posto')->insert([
                                'sessione_id'        => $sessioneId,
                                'tipologia_posto_id' => $tpId,
                                'posti_totali'       => $postiTp,
                                'posti_disponibili'  => $postiTp,
                                'posti_riservati'    => 0,
                                'attiva'             => true,
                            ]);
                        } elseif ($this->force) {
                            DB::table('sessione_tipologie_posto')
                                ->where('id', $stpExist->id)
                                ->update([
                                    'posti_totali'      => $postiTp,
                                    'posti_disponibili' => $postiTp,
                                ]);
                        }
                    }
                } else {
                    // Fallback: unica tipologia (Ordinario o prima disponibile)
                    $tipologiaId = ! empty($tipologieEvento) ? reset($tipologieEvento) : null;
                    if ($tipologiaId) {
                        $stpExist = DB::table('sessione_tipologie_posto')
                            ->where('sessione_id', $sessioneId)
                            ->where('tipologia_posto_id', $tipologiaId)
                            ->first();

                        if (! $stpExist) {
                            DB::table('sessione_tipologie_posto')->insert([
                                'sessione_id'        => $sessioneId,
                                'tipologia_posto_id' => $tipologiaId,
                                'posti_totali'       => $postiTotali,
                                'posti_disponibili'  => (int) ($r->postiDisponibili ?? $postiTotali),
                                'posti_riservati'    => 0,
                                'attiva'             => true,
                            ]);
                        } elseif ($this->force) {
                            DB::table('sessione_tipologie_posto')
                                ->where('id', $stpExist->id)
                                ->update([
                                    'posti_totali'      => $postiTotali,
                                    'posti_disponibili' => (int) ($r->postiDisponibili ?? $postiTotali),
                                ]);
                        }
                    }
                }

                // sessione_luogo dai luoghi JSON di Crono1
                $this->collegaLuoghiSessione($r, $sessioneId);

                $this->line("    ✓ {$dataInizio} → ID {$sessioneId}");
            } else {
                $this->line("    [DRY] {$dataInizio}");
                $this->sessioniMap[$r->id] = -$r->id;
                $this->contatori['sessioni']['insert']++;
            }
        }
    }

    /** Collega i luoghi dalla colonna JSON `luoghi` di eventi_date alla pivot sessione_luogo. */
    private function collegaLuoghiSessione(object $eventData, int $sessioneId): void
    {
        if (empty($eventData->luoghi)) {
            return;
        }

        try {
            $luoghiJson = is_string($eventData->luoghi)
                ? json_decode($eventData->luoghi, true)
                : (array) $eventData->luoghi;
        } catch (\Exception $e) {
            return;
        }

        if (! is_array($luoghiJson)) {
            return;
        }

        foreach ($luoghiJson as $item) {
            // Il campo può contenere l'id direttamente, un oggetto con {id, nome}
            // oppure un oggetto Crono1 con {referenceID, label, tag, metaClass}
            $luogoIdC1 = is_array($item)
                ? ($item['id'] ?? $item['referenceID'] ?? null)
                : (is_numeric($item) ? (int) $item : null);
            if (! $luogoIdC1 || ! isset($this->luoghiMap[$luogoIdC1])) {
                continue;
            }

            $luogoIdC2 = $this->luoghiMap[$luogoIdC1];
            $exists = DB::table('sessione_luogo')
                ->where('sessione_id', $sessioneId)
                ->where('luogo_id', $luogoIdC2)
                ->exists();

            if (! $exists) {
                DB::table('sessione_luogo')->insert([
                    'sessione_id' => $sessioneId,
                    'luogo_id'    => $luogoIdC2,
                ]);
            }
        }
    }

    // -------------------------------------------------------------------------
    // 6. Prenotazioni
    // -------------------------------------------------------------------------

    // Mapping stato Crono1 → stato Crono2
    private const STATI_PRENOTAZIONE_MAP = [
        'CONFERMATA'     => 'CONFERMATA',
        'ANNULLATA'      => 'ANNULLATA',
        'DA_CONFERMARE'  => 'DA_CONFERMARE',
    ];

    private function migraPrenotazioni(int $customerId, int $enteId): void
    {
        $rows = DB::connection('crono1')->table('prenotazioni')
            ->where('customerID', $customerId)
            ->get();

        $this->info("▶ Prenotazioni ({$rows->count()})");

        foreach ($rows as $r) {
            $sessioneIdC2 = isset($r->eventoDataID) ? ($this->sessioniMap[$r->eventoDataID] ?? null) : null;

            if (! $sessioneIdC2) {
                $this->warn("    ⚠ Prenotazione «{$r->codice}» — sessione Crono1 ID {$r->eventoDataID} non trovata, skip");
                $this->contatori['prenotazioni']['skip']++;
                continue;
            }

            $existing = DB::table('prenotazioni')
                ->where('codice', $r->codice)
                ->whereNull('deleted_at')
                ->first();

            if ($existing && ! $this->force) {
                $this->line("    ↩ [SKIP] {$r->codice}");
                $this->contatori['prenotazioni']['skip']++;
                continue;
            }

            $stato = self::STATI_PRENOTAZIONE_MAP[$r->stato ?? ''] ?? 'CONFERMATA';
            $posti = max(1, (int) ($r->postiPrenotati ?? 1));

            // Mappa tipologie evento: [nomeTipologia => tipologiaC2_id]
            $tipologieEvento = isset($r->eventoID) ? ($this->tipologieMap[$r->eventoID] ?? []) : [];
            // Parsa il breakdown tipologico della prenotazione
            $postiPrenotJsonRaw = !empty($r->posti) ? json_decode($r->posti, true) : null;
            $postiBreakdown     = (is_array($postiPrenotJsonRaw) && !empty($tipologieEvento))
                                    ? $postiPrenotJsonRaw
                                    : null;

            // Fallback tipologia singola: prima nella mappa o prima nella sessione
            $tipologiaId = null;
            if (empty($tipologieEvento)) {
                $stp = DB::table('sessione_tipologie_posto')
                    ->where('sessione_id', $sessioneIdC2)
                    ->first();
                $tipologiaId = $stp ? $stp->tipologia_posto_id : null;
            } elseif (! $postiBreakdown) {
                $tipologiaId = reset($tipologieEvento);
            }

            // Snapshot evento
            $eventoSnapshot = [
                'titolo'    => $r->eventoTitolo ?? null,
                'slug'      => $r->eventoHashTag ?? null,
                'data'      => $r->dataEvento ?? null,
                'luoghi'    => $r->luoghi ?? null,
                'sorgente'  => 'crono1',
            ];

            $dataPrenotazione = $this->dataSicura($r->dataPrenotazione ?? null) ?? now();

            $prenotazioneData = [
                'sessione_id'           => $sessioneIdC2,
                'user_id'               => null,
                'ente_id'               => $enteId,
                'stato'                 => $stato,
                'codice'                => $r->codice,
                'data_prenotazione'     => $dataPrenotazione,
                'scadenza_riserva'      => null,
                'posti_prenotati'       => $posti,
                'nome'                  => $r->nome ?? '-',
                'cognome'               => $r->cognome ?? '-',
                'email'                 => $r->email ?? 'noreply@migrazione.local',
                'telefono'              => $r->telefono ?? null,
                'note'                  => $r->note ?? null,
                'costo_totale'          => isset($r->costoTotale) ? (float) $r->costoTotale : null,
                'evento_snapshot'       => json_encode($eventoSnapshot),
                'data_annullamento'     => $this->dataSicura($r->dataAnnullamento ?? null),
                'motivo_annullamento'   => $r->motivoAnnullamento ?? null,
                'annullata_da_user_id'  => null,
                'updated_at'            => now(),
            ];

            if (! $this->dryRun) {
                if ($existing) {
                    DB::table('prenotazioni')->where('id', $existing->id)->update($prenotazioneData);
                    $prenotazioneId = $existing->id;
                    $this->contatori['prenotazioni']['update']++;
                } else {
                    $prenotazioneData['created_at'] = $dataPrenotazione;
                    $prenotazioneId = DB::table('prenotazioni')->insertGetId($prenotazioneData);
                    $this->contatori['prenotazioni']['insert']++;
                }

                // prenotazione_posti: uno per tipologia se disponibile il breakdown, altrimenti unico record
                if ($postiBreakdown) {
                    foreach ($postiBreakdown as $pb) {
                        $nomeT = trim($pb['tipologia'] ?? '');
                        $tpId  = $tipologieEvento[$nomeT] ?? null;
                        if (! $tpId) {
                            continue;
                        }
                        $qty       = max(1, (int) ($pb['quantitaRichiesta'] ?? 0));
                        $costoUnit = isset($pb['costoUnitario']) && $pb['costoUnitario'] > 0
                                        ? (float) $pb['costoUnitario'] : null;

                        $ppExists = DB::table('prenotazione_posti')
                            ->where('prenotazione_id', $prenotazioneId)
                            ->where('tipologia_posto_id', $tpId)
                            ->exists();

                        if (! $ppExists) {
                            DB::table('prenotazione_posti')->insert([
                                'prenotazione_id'    => $prenotazioneId,
                                'tipologia_posto_id' => $tpId,
                                'quantita'           => $qty,
                                'costo_unitario'     => $costoUnit,
                                'costo_riga'         => $costoUnit ? $qty * $costoUnit : null,
                            ]);
                        }
                    }
                } elseif ($tipologiaId) {
                    $ppExists = DB::table('prenotazione_posti')
                        ->where('prenotazione_id', $prenotazioneId)
                        ->where('tipologia_posto_id', $tipologiaId)
                        ->exists();

                    if (! $ppExists) {
                        DB::table('prenotazione_posti')->insert([
                            'prenotazione_id'    => $prenotazioneId,
                            'tipologia_posto_id' => $tipologiaId,
                            'quantita'           => $posti,
                            'costo_unitario'     => null,
                            'costo_riga'         => null,
                        ]);
                    }
                }

                // risposte_form: salva il campo "organizzazione" se valorizzato
                $organizzazione = isset($r->organizzazione) ? trim((string) $r->organizzazione) : '';
                if ($organizzazione !== '' && isset($r->eventoID) && isset($this->eventiMap[$r->eventoID])) {
                    $eventoIdC2   = $this->eventiMap[$r->eventoID];
                    $campoFormId  = $this->getCampoOrganizzazione($eventoIdC2);

                    $rfExists = DB::table('risposte_form')
                        ->where('prenotazione_id', $prenotazioneId)
                        ->where('campo_form_id', $campoFormId)
                        ->exists();

                    if (! $rfExists) {
                        DB::table('risposte_form')->insert([
                            'prenotazione_id' => $prenotazioneId,
                            'campo_form_id'   => $campoFormId,
                            'valore'          => $organizzazione,
                        ]);
                        $this->contatori['risposte_form']['insert']++;
                    } else {
                        $this->contatori['risposte_form']['skip']++;
                    }
                }

                $this->line("    ✓ {$r->codice} ({$stato})");
            } else {
                $this->line("    [DRY] {$r->codice} ({$stato})");
                $this->contatori['prenotazioni']['insert']++;
            }
        }

        // Ricalcola posti_disponibili nelle sessioni migrate (solo se non dry-run)
        if (! $this->dryRun) {
            $this->ricalcolaPosti($enteId);
        }
    }

    // -------------------------------------------------------------------------
    // Ricalcolo posti disponibili
    // -------------------------------------------------------------------------

    /**
     * Dopo la migrazione delle prenotazioni, ricalcola posti_disponibili su sessioni
     * e sessione_tipologie_posto basandosi sulle prenotazioni CONFERMATA / DA_CONFERMARE.
     */
    private function ricalcolaPosti(int $enteId): void
    {
        $this->info("▶ Ricalcolo posti disponibili...");

        foreach ($this->sessioniMap as $sessioneId) {
            if ($sessioneId < 0) {
                continue; // dry-run placeholder
            }

            $sessione = DB::table('sessioni')->find($sessioneId);
            if (! $sessione || $sessione->posti_totali === 0) {
                continue; // illimitati
            }

            $occupati = DB::table('prenotazioni')
                ->where('sessione_id', $sessioneId)
                ->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE'])
                ->whereNull('deleted_at')
                ->sum('posti_prenotati');

            $disponibili = max(0, $sessione->posti_totali - $occupati);
            DB::table('sessioni')->where('id', $sessioneId)->update(['posti_disponibili' => $disponibili]);

            // Aggiorna anche sessione_tipologie_posto
            $stps = DB::table('sessione_tipologie_posto')->where('sessione_id', $sessioneId)->get();
            foreach ($stps as $stp) {
                if ($stp->posti_totali === 0) {
                    continue;
                }

                $prenotazioniIds = DB::table('prenotazioni')
                    ->where('sessione_id', $stp->sessione_id)
                    ->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE'])
                    ->whereNull('deleted_at')
                    ->pluck('id');

                $occTp = DB::table('prenotazione_posti')
                    ->whereIn('prenotazione_id', $prenotazioniIds)
                    ->where('tipologia_posto_id', $stp->tipologia_posto_id)
                    ->sum('quantita');

                $dispTp = max(0, $stp->posti_totali - $occTp);
                DB::table('sessione_tipologie_posto')
                    ->where('id', $stp->id)
                    ->update(['posti_disponibili' => $dispTp]);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Helper: campo_form "Ente/Organizzazione"
    // -------------------------------------------------------------------------

    /**
     * Restituisce l'id del campo_form "Ente / Organizzazione" per l'evento Crono2 dato.
     * Lo crea la prima volta che viene richiesto (creazione lazy).
     */
    private function getCampoOrganizzazione(int $eventoIdC2): int
    {
        if (isset($this->campiFormMap[$eventoIdC2])) {
            return $this->campiFormMap[$eventoIdC2];
        }

        $existing = DB::table('campi_form')
            ->where('evento_id', $eventoIdC2)
            ->where('etichetta', 'Ente / Organizzazione')
            ->first();

        if ($existing) {
            $this->campiFormMap[$eventoIdC2] = $existing->id;
            return $existing->id;
        }

        // Calcola ordine: mettilo per ultimo tra i campi già esistenti
        $ordine = DB::table('campi_form')->where('evento_id', $eventoIdC2)->max('ordine') ?? -1;

        $id = DB::table('campi_form')->insertGetId([
            'evento_id'       => $eventoIdC2,
            'ordine'          => $ordine + 1,
            'tipo'            => 'TEXT',
            'etichetta'       => 'Ente / Organizzazione',
            'placeholder'     => 'Es. Comune di Roma, Associazione XY…',
            'obbligatorio'    => false,
            'opzioni'         => null,
            'validazione'     => null,
            'visibile_pubblico' => true,
            'attivo'          => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->campiFormMap[$eventoIdC2] = $id;
        return $id;
    }

    // -------------------------------------------------------------------------
    // Utilità
    // -------------------------------------------------------------------------

    /** Genera uno slug univoco per la tabella+scope dato, aggiungendo suffisso numerico se necessario. */
    private function slugUnico(string $table, string $scopeCol, int $scopeVal, string $titolo, ?string $slugDefault = null): string
    {
        $base = $slugDefault ? Str::slug($slugDefault) : Str::slug($titolo);
        if (! $base) {
            $base = 'item-' . Str::random(6);
        }

        $slug = $base;
        $i = 1;
        while (DB::table($table)->where($scopeCol, $scopeVal)->where('slug', $slug)->whereNull('deleted_at')->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    /** Converte una data Crono1 in formato MySQL o null se non valida. */
    private function dataSicura(mixed $valore): ?string
    {
        if (! $valore) {
            return null;
        }
        try {
            $dt = Carbon::parse($valore);
            if ($dt->year < 2000 || $dt->year > 2100) {
                return null;
            }
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /** Mappa lo stato Crono1 (IN_ATTESA, PUBBLICATO...) allo stato Crono2. */
    private function mappaStatoEvento(string $stato): string
    {
        return match (strtoupper($stato)) {
            'PUBBLICATO'  => 'PUBBLICATO',
            'SOSPESO'     => 'SOSPESO',
            'ANNULLATO'   => 'ANNULLATO',
            default       => 'BOZZA',
        };
    }

    /** Stampa il riepilogo finale. */
    private function riepilogo(): void
    {
        $this->info("══════════════════════════════════════════");
        $this->info("  RIEPILOGO MIGRAZIONE");
        $this->info("══════════════════════════════════════════");
        $headers = ['Entità', 'Inseriti', 'Aggiornati', 'Saltati'];
        $rows = [];
        foreach ($this->contatori as $entita => $c) {
            $rows[] = [$entita, $c['insert'], $c['update'], $c['skip']];
        }
        $this->table($headers, $rows);

        if ($this->dryRun) {
            $this->warn('⚠  DRY-RUN: nessun dato è stato effettivamente scritto.');
        }
    }
}
