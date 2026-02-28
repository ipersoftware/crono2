<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prenotazioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sessione_id')->constrained('sessioni')->onDelete('restrict');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null')
                ->comment('NULL = prenotazione guest');
            $table->foreignId('ente_id')->constrained('enti')->onDelete('cascade')
                ->comment('Denormalizzato per query veloci');
            $table->enum('stato', [
                'RISERVATA',
                'CONFERMATA',
                'DA_CONFERMARE',
                'ANNULLATA',
                'IN_LISTA_ATTESA',
                'NOTIFICATO',
                'SCADUTO',
            ])->default('RISERVATA');
            $table->string('codice', 20)->unique()->comment('Codice human-readable es. CRN-2026-00042');
            $table->dateTime('data_prenotazione');
            $table->dateTime('scadenza_riserva')->nullable()->comment('Valorizzato se stato=RISERVATA');
            $table->integer('posti_prenotati')->comment('Totale posti (somma prenotazione_posti.quantita)');
            // Dati prenotante (snapshot — supporta guest senza account)
            $table->string('nome');
            $table->string('cognome');
            $table->string('email');
            $table->string('telefono')->nullable();
            $table->text('note')->nullable();
            $table->decimal('costo_totale', 10, 2)->nullable()
                ->comment('Somma di prenotazione_posti.costo_riga');
            // Snapshot evento al momento della prenotazione
            $table->json('evento_snapshot')->nullable()
                ->comment('Snapshot titolo, data, luogo, tipologie al momento della prenotazione');
            // Annullamento
            $table->dateTime('data_annullamento')->nullable();
            $table->text('motivo_annullamento')->nullable();
            $table->foreignId('annullata_da_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });

        // Dettaglio posti per tipologia (una riga per tipologia inclusa nella prenotazione)
        Schema::create('prenotazione_posti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prenotazione_id')->constrained('prenotazioni')->onDelete('cascade');
            $table->foreignId('tipologia_posto_id')->constrained('tipologie_posto')->onDelete('restrict');
            $table->integer('quantita')->comment('Numero posti prenotati per questa tipologia');
            $table->decimal('costo_unitario', 10, 2)->nullable()
                ->comment('Snapshot del costo al momento della prenotazione');
            $table->decimal('costo_riga', 10, 2)->nullable()
                ->comment('quantita * costo_unitario');
        });

        // Risposte ai campi form personalizzati
        Schema::create('risposte_form', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prenotazione_id')->constrained('prenotazioni')->onDelete('cascade');
            $table->foreignId('campo_form_id')->constrained('campi_form')->onDelete('cascade');
            $table->text('valore')->nullable()->comment('Risposta serializzata');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risposte_form');
        Schema::dropIfExists('prenotazione_posti');
        Schema::dropIfExists('prenotazioni');
    }
};
