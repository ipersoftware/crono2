<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge token alla tabella lista_attesa.
 * Il token è generato all'iscrizione e usato come link one-time
 * per la conferma quando tipo_conferma = PRENOTAZIONE_DA_CONFERMARE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lista_attesa', function (Blueprint $table) {
            $table->string('token', 64)->nullable()->unique()
                ->after('email')
                ->comment('Token one-time per conferma posto disponibile');
        });
    }

    public function down(): void
    {
        Schema::table('lista_attesa', function (Blueprint $table) {
            $table->dropColumn('token');
        });
    }
};
