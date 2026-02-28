<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('enti', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique()->nullable();
            $table->string('shop_url')->unique()->nullable()->comment('URL pubblico vetrina crono.app/{shop_url} — gestito solo da Admin');
            $table->string('codice_fiscale')->unique()->nullable();
            $table->string('partita_iva', 11)->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('telefono')->nullable();
            $table->string('indirizzo')->nullable();
            $table->string('citta')->nullable();
            $table->string('provincia', 2)->nullable();
            $table->string('cap', 5)->nullable();
            $table->decimal('lat', 10, 8)->nullable()->comment('Latitudine sede principale');
            $table->decimal('lng', 11, 8)->nullable()->comment('Longitudine sede principale');
            $table->text('descrizione')->nullable()->comment('Testo breve per vetrina');
            $table->string('logo')->nullable();
            $table->string('copertina')->nullable()->comment('Immagine header vetrina');
            $table->longText('contenuto_vetrina')->nullable()->comment('HTML/Markdown corpo pagina pubblica');
            $table->json('eventi_in_evidenza')->nullable()->comment('Array di evento_id da mostrare in evidenza');
            $table->enum('stato', ['ATTIVO', 'SOSPESO', 'CANCELLATO'])->default('ATTIVO');
            $table->enum('licenza', ['GRATUITA', 'PREMIUM'])->default('GRATUITA');
            $table->json('config')->nullable()->comment('Configurazioni specifiche: tema, branding, notifiche');
            $table->dateTime('attivo_dal')->nullable();
            $table->dateTime('attivo_al')->nullable();
            $table->boolean('attivo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enti');
    }
};
