<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ente_id')->constrained('enti')->onDelete('cascade');
            $table->foreignId('serie_id')->nullable()->constrained('serie')->onDelete('set null');
            $table->string('titolo');
            $table->string('slug', 255)->comment('Univoco per ente — genera URL pubblico /{shop_url}/eventi/{slug}');
            $table->json('slug_history')->nullable()->comment('Storico slug precedenti per redirect 301');
            $table->string('descrizione_breve', 512)->nullable()->comment('Abstract per card in vetrina');
            $table->longText('descrizione')->nullable()->comment('Descrizione completa HTML/Markdown');
            $table->string('immagine')->nullable()->comment('Path copertina');
            $table->enum('stato', ['BOZZA', 'PUBBLICATO', 'SOSPESO', 'ANNULLATO'])->default('BOZZA');
            $table->boolean('pubblico')->default(false)->comment('Visibile in vetrina');
            $table->boolean('in_evidenza')->default(false)->comment('Mostrato in evidenza sulla vetrina');
            $table->integer('ordinamento')->default(0)->comment('Ordinamento manuale');
            $table->dateTime('visibile_dal')->nullable();
            $table->dateTime('visibile_al')->nullable();
            $table->dateTime('prenotabile_dal')->nullable()->comment('Apertura prenotazioni');
            $table->dateTime('prenotabile_al')->nullable()->comment('Chiusura prenotazioni');
            $table->integer('posti_max_per_prenotazione')->default(1)->comment('Max posti in una singola prenotazione');
            $table->boolean('richiede_approvazione')->default(false)->comment('Prenotazione → stato DA_CONFERMARE');
            $table->boolean('consenti_multi_sessione')->default(false)->comment('Permetti prenotazione di più sessioni dello stesso evento');
            $table->boolean('consenti_prenotazione_guest')->default(true)->comment('Abilita prenotazioni senza registrazione');
            $table->integer('cancellazione_consentita_ore')->nullable()->comment('NULL=sempre; -1=mai; N=fino a N ore prima');
            $table->boolean('mostra_disponibilita')->default(true)->comment('Mostra posti rimasti in vetrina');
            $table->boolean('attiva_note')->default(false)->comment('Abilita campo note libere nel form');
            $table->string('nota_etichetta')->nullable()->comment('Etichetta campo note');
            $table->decimal('costo', 10, 2)->nullable()->comment('Prezzo base (0 = gratuito)');
            $table->json('attributi')->nullable()->comment('Attributi extra non ricercabili');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['ente_id', 'slug']);
        });

        // Pivot evento ↔ tag
        Schema::create('evento_tag', function (Blueprint $table) {
            $table->foreignId('evento_id')->constrained('eventi')->onDelete('cascade');
            $table->foreignId('tag_id')->constrained('tags')->onDelete('cascade');
            $table->primary(['evento_id', 'tag_id']);
        });

        // Pivot evento ↔ luogo (multi-luogo a livello evento)
        Schema::create('evento_luogo', function (Blueprint $table) {
            $table->foreignId('evento_id')->constrained('eventi')->onDelete('cascade');
            $table->foreignId('luogo_id')->constrained('luoghi')->onDelete('cascade');
            $table->boolean('principale')->default(false)->comment('Luogo primario dell\'evento');
            $table->primary(['evento_id', 'luogo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_luogo');
        Schema::dropIfExists('evento_tag');
        Schema::dropIfExists('eventi');
    }
};
