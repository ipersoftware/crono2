<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessioni', function (Blueprint $table) {
            $table->boolean('visualizza_disponibili')
                ->default(false)
                ->after('note_pubbliche')
                ->comment('Se true, mostra i posti disponibili della sessione in fase di booking');
        });

        Schema::table('tipologie_posto', function (Blueprint $table) {
            $table->boolean('visualizza_disponibili')
                ->default(false)
                ->after('attiva')
                ->comment('Se true, mostra i posti disponibili per questa tipologia in fase di booking');
        });
    }

    public function down(): void
    {
        Schema::table('sessioni', function (Blueprint $table) {
            $table->dropColumn('visualizza_disponibili');
        });

        Schema::table('tipologie_posto', function (Blueprint $table) {
            $table->dropColumn('visualizza_disponibili');
        });
    }
};
