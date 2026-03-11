<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CONTROLLO COERENZA MIGRAZIONE: LA GABBIANELLA ===\n\n";

// 1. Trovare l'evento in Crono1
$eventiC1 = DB::connection('crono1')->table('eventi')
    ->where('titolo', 'like', '%gabbianella%')
    ->get(['id', 'titolo', 'customerID']);

if ($eventiC1->isEmpty()) {
    echo "⚠  Nessun evento trovato in Crono1 con titolo '%gabbianella%'\n";
    exit(1);
}

foreach ($eventiC1 as $eC1) {
    echo "--- Crono1 evento: ID={$eC1->id} | titolo={$eC1->titolo} | customerID={$eC1->customerID}\n\n";

    // 2. Quante sessioni (eventi_date) in Crono1?
    $dateC1 = DB::connection('crono1')->table('eventi_date')
        ->where('idEvento', $eC1->id)
        ->get(['id', 'dataInizio', 'postiTotali', 'postiDisponibili']);
    echo "  Sessioni Crono1 (" . $dateC1->count() . "):\n";
    foreach ($dateC1 as $d) {
        echo "    - eventi_date.id={$d->id} | dataInizio={$d->dataInizio} | postiTotali={$d->postiTotali} | postiDisponibili={$d->postiDisponibili}\n";
    }
    echo "\n";

    // 3. Tipologie di posto in Crono1 (tabella tipologie_posto se esiste, altrimenti cercarne traccia)
    // Prima vediamo quali tabelle esistono in crono1 con 'tipolog' nel nome
    $tables = DB::connection('crono1')->select("SHOW TABLES LIKE '%tipolog%'");
    if (!empty($tables)) {
        echo "  Tabelle Crono1 con 'tipolog':\n";
        foreach ($tables as $t) {
            $vals = (array)$t;
            echo "    - " . reset($vals) . "\n";
        }
        echo "\n";
    }

    // Cerca anche tabelle con 'posto'
    $tables2 = DB::connection('crono1')->select("SHOW TABLES LIKE '%posto%'");
    if (!empty($tables2)) {
        echo "  Tabelle Crono1 con 'posto':\n";
        foreach ($tables2 as $t) {
            $vals = (array)$t;
            echo "    - " . reset($vals) . "\n";
        }
        echo "\n";
    }

    // Cerca tabelle con 'ticket' o 'categoria' (possibili nomi per le tipologie)
    $tables3 = DB::connection('crono1')->select("SHOW TABLES LIKE '%ticket%'");
    if (!empty($tables3)) {
        echo "  Tabelle Crono1 con 'ticket':\n";
        foreach ($tables3 as $t) {
            $vals = (array)$t;
            echo "    - " . reset($vals) . "\n";
        }
        echo "\n";
    }

    $tables4 = DB::connection('crono1')->select("SHOW TABLES LIKE '%categor%'");
    if (!empty($tables4)) {
        echo "  Tabelle Crono1 con 'categor':\n";
        foreach ($tables4 as $t) {
            $vals = (array)$t;
            echo "    - " . reset($vals) . "\n";
        }
        echo "\n";
    }

    // 4. Elenco tutte le tabelle Crono1 per capire la struttura
    $allTables = DB::connection('crono1')->select("SHOW TABLES");
    echo "  Tutte le tabelle in Crono1 DB:\n";
    foreach ($allTables as $t) {
        $vals = (array)$t;
        echo "    - " . reset($vals) . "\n";
    }
    echo "\n";

    // 5. Verificare lo stesso evento in Crono2
    $eventoC2 = DB::table('eventi')
        ->where('titolo', 'like', '%gabbianella%')
        ->whereNull('deleted_at')
        ->first(['id', 'titolo', 'slug']);

    if (!$eventoC2) {
        echo "⚠  Evento NON trovato in Crono2!\n";
    } else {
        echo "  Crono2 evento: ID={$eventoC2->id} | titolo={$eventoC2->titolo}\n\n";

        // Tipologie posto in Crono2
        $tipologieC2 = DB::table('tipologie_posto')
            ->where('evento_id', $eventoC2->id)
            ->whereNull('deleted_at')
            ->get(['id', 'nome', 'gratuita', 'costo', 'max_prenotabili', 'ordinamento']);
        echo "  Tipologie posto Crono2 (" . $tipologieC2->count() . "):\n";
        foreach ($tipologieC2 as $tp) {
            echo "    - id={$tp->id} | nome={$tp->nome} | gratuita={$tp->gratuita} | costo={$tp->costo} | max={$tp->max_prenotabili}\n";
        }
        echo "\n";

        // Sessioni in Crono2
        $sessioniC2 = DB::table('sessioni')
            ->where('evento_id', $eventoC2->id)
            ->whereNull('deleted_at')
            ->get(['id', 'data_inizio', 'posti_totali', 'posti_disponibili']);
        echo "  Sessioni Crono2 (" . $sessioniC2->count() . "):\n";
        foreach ($sessioniC2 as $s) {
            echo "    - id={$s->id} | data_inizio={$s->data_inizio} | posti_totali={$s->posti_totali} | posti_disponibili={$s->posti_disponibili}\n";
            
            // STP per questa sessione
            $stps = DB::table('sessione_tipologie_posto')
                ->where('sessione_id', $s->id)
                ->join('tipologie_posto', 'tipologie_posto.id', '=', 'sessione_tipologie_posto.tipologia_posto_id')
                ->get(['tipologie_posto.nome', 'sessione_tipologie_posto.posti_totali as stp_totali', 'sessione_tipologie_posto.posti_disponibili as stp_disp']);
            foreach ($stps as $stp) {
                echo "        └ tipologia={$stp->nome} | stp_totali={$stp->stp_totali} | stp_disp={$stp->stp_disp}\n";
            }
        }
    }
    echo "\n";
}
