<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vocabolario tag per Ente (normalizzato — sostituisce il campo JSON tags di Crono1)
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ente_id')->constrained('enti')->onDelete('cascade');
            $table->string('nome', 100);
            $table->string('slug', 100);
            $table->string('colore', 7)->nullable()->comment('Colore HEX per UI es. #3B82F6');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['ente_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
