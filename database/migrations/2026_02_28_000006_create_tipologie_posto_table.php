<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Categorie di posto prenotabili per Evento
        Schema::create('tipologie_posto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventi')->onDelete('cascade');
            $table->foreignId('ente_id')->constrained('enti')->onDelete('cascade')
                ->comment('Denormalizzato per query multi-tenancy');
            $table->string('nome')->comment('Es. "Intero", "Ridotto", "Under 12", "VIP"');
            $table->text('descrizione')->nullable()->comment('Dettagli mostrati nel form');
            $table->boolean('gratuita')->default(true)->comment('Se false, è a pagamento');
            $table->decimal('costo', 10, 2)->nullable()->comment('Prezzo unitario; NULL/0 se gratuita');
            $table->integer('min_prenotabili')->default(1)->comment('Minimo posti per prenotazione');
            $table->integer('max_prenotabili')->nullable()->comment('Massimo posti; NULL = illimitato');
            $table->integer('ordinamento')->default(0)->comment('Ordine nel form di prenotazione');
            $table->boolean('attiva')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Disponibilità per tipologia per sessione — usata quando controlla_posti_globale = false
        Schema::create('sessione_tipologie_posto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sessione_id')->constrained('sessioni')->onDelete('cascade');
            $table->foreignId('tipologia_posto_id')->constrained('tipologie_posto')->onDelete('cascade');
            $table->integer('posti_totali')->default(0)->comment('0 = illimitato per questa tipologia');
            $table->integer('posti_disponibili')->default(0);
            $table->integer('posti_riservati')->default(0)->comment('Lock temporali attivi per questa tipologia');
            $table->boolean('attiva')->default(true)->comment('Disabilita tipologia per sessione specifica');

            $table->unique(['sessione_id', 'tipologia_posto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessione_tipologie_posto');
        Schema::dropIfExists('tipologie_posto');
    }
};
