<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Descrizione in Crono1
$ev1 = DB::connection('crono1')->table('eventi')->where('id', 82)->first();
echo "=== CRONO1 descrizione ===\n";
echo $ev1->descrizione . "\n\n";

// Colonne evento Crono1 (per capire se c'è altro campo oltre descrizione)
$cols = DB::connection('crono1')->select('SHOW COLUMNS FROM eventi');
echo "=== Colonne eventi Crono1 ===\n";
foreach ($cols as $c) echo "  {$c->Field} ({$c->Type})\n";

echo "\n=== CRONO2 descrizione ===\n";
$ev2 = DB::table('eventi')->where('id', 15)->first();
echo $ev2->descrizione . "\n";

// Colonne evento Crono2
$cols2 = DB::connection()->select('SHOW COLUMNS FROM eventi');
echo "\n=== Colonne eventi Crono2 ===\n";
foreach ($cols2 as $c) echo "  {$c->Field} ({$c->Type})\n";
