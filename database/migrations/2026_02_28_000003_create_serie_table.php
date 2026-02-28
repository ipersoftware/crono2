<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serie', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ente_id')->constrained('enti')->onDelete('cascade');
            $table->string('titolo');
            $table->text('descrizione')->nullable();
            $table->string('slug')->nullable();
            $table->enum('stato', ['BOZZA', 'PUBBLICATO', 'SOSPESO', 'ANNULLATO'])->default('BOZZA');
            $table->boolean('pubblico')->default(false)->comment('Visibile in vetrina');
            $table->dateTime('visibile_dal')->nullable();
            $table->dateTime('visibile_al')->nullable();
            $table->string('immagine')->nullable()->comment('Copertina della serie');
            $table->longText('contenuto')->nullable()->comment('Descrizione estesa HTML/Markdown');
            $table->string('link_pubblico')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serie');
    }
};
