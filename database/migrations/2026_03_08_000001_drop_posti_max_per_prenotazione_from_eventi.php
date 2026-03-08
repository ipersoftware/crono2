<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventi', function (Blueprint $table) {
            $table->dropColumn('posti_max_per_prenotazione');
        });
    }

    public function down(): void
    {
        Schema::table('eventi', function (Blueprint $table) {
            $table->integer('posti_max_per_prenotazione')->default(1)
                ->after('prenotabile_al')
                ->comment('Max posti acquistabili in una singola prenotazione');
        });
    }
};
