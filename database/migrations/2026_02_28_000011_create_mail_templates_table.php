<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Template email per Ente (ente_id NULL = template di sistema/fallback)
        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ente_id')->nullable()->constrained('enti')->onDelete('cascade')
                ->comment('NULL = template di sistema (default piattaforma)');
            $table->enum('tipo', [
                'PRENOTAZIONE_CONFERMATA',
                'PRENOTAZIONE_DA_CONFERMARE',
                'PRENOTAZIONE_APPROVATA',
                'PRENOTAZIONE_ANNULLATA_UTENTE',
                'PRENOTAZIONE_ANNULLATA_OPERATORE',
                'PRENOTAZIONE_NOTIFICA_STAFF',
                'EVENTO_ANNULLATO',
                'LISTA_ATTESA_ISCRIZIONE',
                'LISTA_ATTESA_POSTO_DISPONIBILE',
                'LISTA_ATTESA_SCADENZA',
                'REMINDER_EVENTO',
                'REGISTRAZIONE_CONFERMATA',
                'RESET_PASSWORD',
            ]);
            $table->string('oggetto', 512)->comment('Oggetto email — supporta placeholder {{...}}');
            $table->longText('corpo')->comment('Corpo email HTML — supporta placeholder {{...}}');
            $table->boolean('sistema')->default(false)
                ->comment('true = template di default non eliminabile');
            $table->boolean('attivo')->default(true);
            $table->timestamps();

            // Risoluzione: prima cerca ente_id = X AND tipo = T, poi ente_id = NULL AND tipo = T
            $table->unique(['ente_id', 'tipo']);
        });

        // Log di ogni email inviata
        Schema::create('notifiche_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ente_id')->constrained('enti')->onDelete('cascade');
            $table->foreignId('prenotazione_id')->nullable()->constrained('prenotazioni')->onDelete('set null');
            $table->enum('tipo', [
                'PRENOTAZIONE_CONFERMATA',
                'PRENOTAZIONE_DA_CONFERMARE',
                'PRENOTAZIONE_APPROVATA',
                'PRENOTAZIONE_ANNULLATA_UTENTE',
                'PRENOTAZIONE_ANNULLATA_OPERATORE',
                'PRENOTAZIONE_NOTIFICA_STAFF',
                'EVENTO_ANNULLATO',
                'LISTA_ATTESA_ISCRIZIONE',
                'LISTA_ATTESA_POSTO_DISPONIBILE',
                'LISTA_ATTESA_SCADENZA',
                'REMINDER_EVENTO',
                'REGISTRAZIONE_CONFERMATA',
                'RESET_PASSWORD',
            ]);
            $table->string('destinatario_email');
            $table->string('oggetto', 512)->comment('Oggetto effettivo inviato');
            $table->enum('stato', ['IN_CODA', 'INVIATA', 'ERRORE'])->default('IN_CODA');
            $table->text('errore')->nullable()->comment('Messaggio di errore se stato = ERRORE');
            $table->integer('tentativo')->default(1);
            $table->dateTime('inviata_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ente_id', 'stato']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifiche_log');
        Schema::dropIfExists('mail_templates');
    }
};
