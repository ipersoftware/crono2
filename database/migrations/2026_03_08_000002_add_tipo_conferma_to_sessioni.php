<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge tipo_conferma alla tabella sessioni.
 * Determina il comportamento della lista d'attesa quando si libera un posto:
 *  - PRENOTAZIONE_AUTOMATICA : promuove automaticamente il primo in lista
 *  - PRENOTAZIONE_DA_CONFERMARE : invia notifica con link temporaneo di conferma
 *  - NESSUNA : raccoglie le iscrizioni ma non gestisce la promozione
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessioni', function (Blueprint $table) {
            $table->enum('tipo_conferma', [
                'PRENOTAZIONE_AUTOMATICA',
                'PRENOTAZIONE_DA_CONFERMARE',
                'NESSUNA',
            ])
            ->default('NESSUNA')
            ->after('attiva_lista_attesa')
            ->comment('Comportamento promozione lista d\'attesa');
        });
    }

    public function down(): void
    {
        Schema::table('sessioni', function (Blueprint $table) {
            $table->dropColumn('tipo_conferma');
        });
    }
};
