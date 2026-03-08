<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessioni', function (Blueprint $table) {
            $table->integer('soglia_chiusura_prenotazioni')
                ->nullable()
                ->after('soglia_chiusura_automatica')
                ->comment('Chiudi automaticamente (forza_non_disponibile) quando le prenotazioni attive raggiungono N');
        });
    }

    public function down(): void
    {
        Schema::table('sessioni', function (Blueprint $table) {
            $table->dropColumn('soglia_chiusura_prenotazioni');
        });
    }
};
