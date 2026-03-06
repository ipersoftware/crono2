<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // URL informativa privacy/GDPR per ente (link "Maggiori informazioni")
        Schema::table('enti', function (Blueprint $table) {
            $table->string('privacy_url')->nullable()->after('contenuto_vetrina');
        });

        // Consenso GDPR registrato al momento della prenotazione
        Schema::table('prenotazioni', function (Blueprint $table) {
            $table->boolean('privacy_ok')->default(false)->after('note')
                ->comment('Consenso GDPR fornito dall\'utente in fase di prenotazione');
        });
    }

    public function down(): void
    {
        Schema::table('enti', function (Blueprint $table) {
            $table->dropColumn('privacy_url');
        });

        Schema::table('prenotazioni', function (Blueprint $table) {
            $table->dropColumn('privacy_ok');
        });
    }
};
