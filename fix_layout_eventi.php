<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Leggi tutti gli eventi Crono1 del customer 1 che hanno il campo layout popolato
$eventiC1 = DB::connection('crono1')
    ->table('eventi')
    ->where('customerID', 1)
    ->whereNotNull('layout')
    ->where('layout', '!=', '')
    ->get(['id', 'titolo', 'descrizione', 'layout']);

$updated = 0;
$skipped = 0;

foreach ($eventiC1 as $e1) {
    // Trova corrispondente in Crono2 tramite titolo (come fa la migrazione via slug)
    // Usiamo il titolo per semplicità, ma verifichiamo per ente_id=1
    $e2 = DB::table('eventi')
        ->where('ente_id', 1)
        ->where('titolo', $e1->titolo)
        ->whereNull('deleted_at')
        ->first(['id', 'titolo', 'descrizione', 'descrizione_breve']);

    if (!$e2) {
        echo "SKIP (non trovato in Crono2): {$e1->titolo}\n";
        $skipped++;
        continue;
    }

    DB::table('eventi')->where('id', $e2->id)->update([
        'descrizione_breve' => mb_substr(strip_tags($e1->descrizione ?? ''), 0, 512) ?: null,
        'descrizione'       => $e1->layout,
        'updated_at'        => now(),
    ]);

    echo "OK  [{$e2->id}] {$e1->titolo}\n";
    $updated++;
}

echo "\nAggiornati: {$updated} eventi, saltati: {$skipped}\n";
