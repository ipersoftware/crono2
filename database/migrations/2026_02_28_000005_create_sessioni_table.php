<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventi')->onDelete('cascade');
            $table->string('titolo')->nullable()->comment('Etichetta breve es. "Mattina", "Turno A"');
            $table->text('descrizione')->nullable()->comment('Descrizione dettagliata della singola data');
            $table->dateTime('data_inizio');
            $table->dateTime('data_fine');
            // Contatori posti (globale)
            $table->integer('posti_totali')->default(0)->comment('0 = illimitato');
            $table->integer('posti_disponibili')->default(0);
            $table->integer('posti_in_attesa')->default(0)->comment('Contatore lista d\'attesa');
            $table->integer('posti_riservati')->default(0)->comment('Contatore lock temporali attivi');
            // Flag disponibilità
            $table->boolean('controlla_posti_globale')->default(false)
                ->comment('true = disponibilità solo dal contatore globale; false = per singola tipologia');
            $table->boolean('prenotabile')->default(true)->comment('Override manuale');
            $table->boolean('forza_non_disponibile')->default(false)->comment('Chiusura forzata manuale');
            $table->integer('soglia_chiusura_automatica')->nullable()->comment('Chiudi a N posti globali rimasti');
            // Overbooking
            $table->integer('soglia_overbooking_percentuale')->nullable()->comment('Es. 10 = +10% posti extra');
            $table->integer('soglia_overbooking_assoluta')->nullable()->comment('Es. 5 = +5 posti extra');
            // Lista d'attesa e lock
            $table->boolean('attiva_lista_attesa')->default(false);
            $table->integer('lista_attesa_finestra_conferma_ore')->default(24)
                ->comment('Ore per confermare dopo notifica lista attesa');
            $table->integer('durata_lock_minuti')->default(15)->comment('TTL lock temporale posti');
            // Extra
            $table->text('note_pubbliche')->nullable();
            $table->json('attributi')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Pivot sessione ↔ luogo (override luogo per sessione specifica)
        Schema::create('sessione_luogo', function (Blueprint $table) {
            $table->foreignId('sessione_id')->constrained('sessioni')->onDelete('cascade');
            $table->foreignId('luogo_id')->constrained('luoghi')->onDelete('cascade');
            $table->primary(['sessione_id', 'luogo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessione_luogo');
        Schema::dropIfExists('sessioni');
    }
};
