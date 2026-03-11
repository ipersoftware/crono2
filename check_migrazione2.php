<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== STRUTTURA TABELLE CRONO1 PER GABBIANELLA ===\n\n";

// Evento ID 82 in Crono1
$evento = DB::connection('crono1')->table('eventi')->find(82);
echo "--- eventi (ID=82) colonne e valori ---\n";
foreach ((array)$evento as $k => $v) {
    if (strlen((string)$v) > 200) $v = substr($v, 0, 200) . '...';
    echo "  $k = $v\n";
}
echo "\n";

// eventi_date ID=136
$ed = DB::connection('crono1')->table('eventi_date')->find(136);
echo "--- eventi_date (ID=136) colonne e valori ---\n";
foreach ((array)$ed as $k => $v) {
    if (strlen((string)$v) > 300) $v = substr($v, 0, 300) . '...';
    echo "  $k = $v\n";
}
echo "\n";

// Prenotazioni per questo evento - guarda le colonne
$cols = DB::connection('crono1')->select("SHOW COLUMNS FROM prenotazioni");
echo "--- prenotazioni colonne ---\n";
foreach ($cols as $c) {
    echo "  {$c->Field} ({$c->Type})\n";
}
echo "\n";

// Una prenotazione campione per customerID=1, eventoDataID=136
$prenCamp = DB::connection('crono1')->table('prenotazioni')
    ->where('eventoDataID', 136)
    ->first();
if ($prenCamp) {
    echo "--- Prenotazione campione (eventoDataID=136) ---\n";
    foreach ((array)$prenCamp as $k => $v) {
        if (strlen((string)$v) > 300) $v = substr($v, 0, 300) . '...';
        echo "  $k = $v\n";
    }
}
echo "\n";

// Colonne moduli
$colsMod = DB::connection('crono1')->select("SHOW COLUMNS FROM moduli");
echo "--- moduli colonne ---\n";
foreach ($colsMod as $c) {
    echo "  {$c->Field} ({$c->Type})\n";
}
echo "\n";

// Moduli per evento 82
$moduli = DB::connection('crono1')->table('moduli')
    ->where('idEvento', 82)
    ->orWhere('idEvento', 82)
    ->get();
if ($moduli->isEmpty()) {
    // prova con event_id o eventoID
    $moduli = DB::connection('crono1')->table('moduli')
        ->where('customerID', 1)
        ->get();
}
echo "--- moduli per evento 82 o customer 1 (" . $moduli->count() . " righe) ---\n";
foreach ($moduli as $m) {
    foreach ((array)$m as $k => $v) {
        if (strlen((string)$v) > 300) $v = substr($v, 0, 300) . '...';
        echo "  $k = $v\n";
    }
    echo "  ---\n";
}
echo "\n";

// colonne di eventi_date
$colsEd = DB::connection('crono1')->select("SHOW COLUMNS FROM eventi_date");
echo "--- eventi_date colonne ---\n";
foreach ($colsEd as $c) {
    echo "  {$c->Field} ({$c->Type})\n";
}
