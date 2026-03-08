<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor lista d'attesa: rimuove la tabella dedicata e gestisce
 * tutto tramite lo stato della prenotazione + 2 nuovi campi.
 *
 * - posizione_lista_attesa : ordine in coda, NULL quando non in lista
 * - notificato_at          : quando è stata inviata la notifica "posto disponibile"
 * - scadenza_riserva       : già esistente, riusato come deadline di conferma
 * - token_accesso          : già esistente, usato come link di conferma lista attesa
 *
 * Gli stati usati per la lista attesa erano già nell'enum prenotazioni:
 *   IN_LISTA_ATTESA, NOTIFICATO, SCADUTA
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prenotazioni', function (Blueprint $table) {
            $table->integer('posizione_lista_attesa')->nullable()
                ->after('posti_prenotati')
                ->comment('Posizione in coda lista attesa; NULL se non in lista');
            $table->dateTime('notificato_at')->nullable()
                ->after('posizione_lista_attesa')
                ->comment('Timestamp invio notifica "posto disponibile"');
        });

        Schema::dropIfExists('lista_attesa');
    }

    public function down(): void
    {
        Schema::table('prenotazioni', function (Blueprint $table) {
            $table->dropColumn(['posizione_lista_attesa', 'notificato_at']);
        });

        // Ricrea la tabella lista_attesa per il rollback
        Schema::create('lista_attesa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sessione_id')->constrained('sessioni')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('nome');
            $table->string('cognome');
            $table->string('email');
            $table->string('telefono')->nullable();
            $table->string('token', 64)->nullable()->unique();
            $table->integer('posti_richiesti');
            $table->json('dettaglio_tipologie')->nullable();
            $table->integer('posizione');
            $table->enum('stato', ['IN_ATTESA', 'NOTIFICATO', 'CONFERMATO', 'SCADUTO', 'RIMOSSO'])->default('IN_ATTESA');
            $table->dateTime('notificato_at')->nullable();
            $table->dateTime('scadenza_conferma_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['sessione_id', 'stato']);
            $table->index(['sessione_id', 'posizione']);
        });
    }
};
