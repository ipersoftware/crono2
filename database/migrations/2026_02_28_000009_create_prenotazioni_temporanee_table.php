<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lock temporale posti durante la compilazione del form di prenotazione
        Schema::create('prenotazioni_temporanee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sessione_id')->constrained('sessioni')->onDelete('cascade');
            $table->integer('posti_totali')->comment('Totale posti bloccati (somma tipologie)');
            $table->json('dettaglio_tipologie')->nullable()
                ->comment('Array [{tipologia_posto_id, quantita}] per decrement per-tipologia al rilascio');
            $table->string('token', 64)->unique()->comment('Token di sessione browser');
            $table->dateTime('scadenza_at')->comment('TTL: ora + sessione.durata_lock_minuti');
            $table->timestamp('created_at')->useCurrent();

            $table->index('scadenza_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prenotazioni_temporanee');
    }
};
