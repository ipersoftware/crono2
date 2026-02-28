<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('luoghi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ente_id')->constrained('enti')->onDelete('cascade');
            $table->string('nome');
            $table->text('descrizione')->nullable();
            $table->string('slug')->nullable();
            $table->string('indirizzo')->nullable();
            $table->string('citta', 100)->nullable();
            $table->string('provincia', 2)->nullable();
            $table->string('cap', 5)->nullable();
            $table->decimal('lat', 10, 8)->nullable()->comment('Latitudine');
            $table->decimal('lng', 11, 8)->nullable()->comment('Longitudine');
            $table->string('maps_url', 512)->nullable()->comment('Link Google Maps / OpenStreetMap');
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->string('link_pubblico')->nullable()->comment('Sito web del luogo');
            $table->string('immagine')->nullable();
            $table->enum('stato', ['ATTIVO', 'SOSPESO'])->default('ATTIVO');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('luoghi');
    }
};
