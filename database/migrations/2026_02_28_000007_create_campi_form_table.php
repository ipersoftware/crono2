<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Definizione del form di prenotazione personalizzabile per evento
        Schema::create('campi_form', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventi')->onDelete('cascade');
            $table->integer('ordine')->default(0)->comment('Posizione nel form');
            $table->enum('tipo', [
                'TEXT',
                'TEXTAREA',
                'SELECT',
                'CHECKBOX',
                'RADIO',
                'DATE',
                'EMAIL',
                'PHONE',
                'NUMBER',
            ]);
            $table->string('etichetta')->comment('Label mostrata all\'utente');
            $table->string('placeholder')->nullable();
            $table->boolean('obbligatorio')->default(false);
            $table->json('opzioni')->nullable()->comment('Per SELECT/RADIO/CHECKBOX: lista valori');
            $table->json('validazione')->nullable()->comment('Regole es. min, max, regex');
            $table->boolean('visibile_pubblico')->default(true);
            $table->boolean('attivo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campi_form');
    }
};
