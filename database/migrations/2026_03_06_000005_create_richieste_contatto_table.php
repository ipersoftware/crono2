<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Abilita form contatti pubblico per ente
        Schema::table('enti', function (Blueprint $table) {
            $table->boolean('form_contatti_attivo')->default(false)->after('privacy_url');
        });

        // Richieste di contatto inviate dalla vetrina
        Schema::create('richieste_contatto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ente_id')->constrained('enti')->onDelete('cascade');
            $table->string('nome');
            $table->string('email');
            $table->string('telefono')->nullable();
            $table->text('messaggio');
            $table->boolean('letta')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('richieste_contatto');
        Schema::table('enti', function (Blueprint $table) {
            $table->dropColumn('form_contatti_attivo');
        });
    }
};
