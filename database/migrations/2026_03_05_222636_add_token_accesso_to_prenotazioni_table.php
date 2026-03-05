<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('prenotazioni', function (Blueprint $table) {
            $table->string('token_accesso', 64)->nullable()->unique()
                ->after('codice')
                ->comment('Token per accesso guest alla prenotazione senza autenticazione');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prenotazioni', function (Blueprint $table) {
            $table->dropColumn('token_accesso');
        });
    }
};
