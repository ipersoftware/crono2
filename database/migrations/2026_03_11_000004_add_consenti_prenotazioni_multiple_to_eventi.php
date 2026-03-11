<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventi', function (Blueprint $table) {
            $table->boolean('consenti_prenotazioni_multiple')
                ->default(false)
                ->after('consenti_multi_sessione')
                ->comment('Con consenti_multi_sessione=true, permette di prenotare la stessa sessione più volte con la stessa email/utente');
        });
    }

    public function down(): void
    {
        Schema::table('eventi', function (Blueprint $table) {
            $table->dropColumn('consenti_prenotazioni_multiple');
        });
    }
};
