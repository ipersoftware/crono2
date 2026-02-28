<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lista_attesa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sessione_id')->constrained('sessioni')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('nome');
            $table->string('cognome');
            $table->string('email');
            $table->string('telefono')->nullable();
            $table->integer('posti_richiesti')->comment('Totale posti richiesti');
            $table->json('dettaglio_tipologie')->nullable()
                ->comment('Array [{tipologia_posto_id, quantita}] se lista attesa per tipologia');
            $table->integer('posizione')->comment('Ordine in lista');
            $table->enum('stato', [
                'IN_ATTESA',
                'NOTIFICATO',
                'CONFERMATO',
                'SCADUTO',
                'RIMOSSO',
            ])->default('IN_ATTESA');
            $table->dateTime('notificato_at')->nullable();
            $table->dateTime('scadenza_conferma_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sessione_id', 'stato']);
            $table->index(['sessione_id', 'posizione']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_attesa');
    }
};
