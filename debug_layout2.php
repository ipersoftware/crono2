<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$ev1 = DB::connection('crono1')->table('eventi')->where('id', 82)->first();
echo "=== layout (Crono1) ===\n";
echo $ev1->layout . "\n\n";
echo "=== descrizione (Crono1) ===\n";
echo $ev1->descrizione . "\n";
