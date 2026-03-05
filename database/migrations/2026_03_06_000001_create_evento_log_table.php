<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evento_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventi')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('azione', 80)->comment('es. evento.pubblicato, sessione.posti_modificati, prenotazione.annullata');
            $table->string('descrizione')->comment('Testo leggibile del cambiamento');
            $table->json('dettagli')->nullable()->comment('Dati strutturati opzionali (before/after, etc.)');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_log');
    }
};
