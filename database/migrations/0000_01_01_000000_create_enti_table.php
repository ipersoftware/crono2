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
            $table->string('codice_fiscale')->unique();
            $table->string('partita_iva')->nullable();
            $table->string('email')->unique();
            $table->string('telefono')->nullable();
            $table->text('indirizzo')->nullable();
            $table->string('citta')->nullable();
            $table->string('provincia', 2)->nullable();
            $table->string('cap', 5)->nullable();
            $table->text('descrizione')->nullable();
            $table->string('logo')->nullable();
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
