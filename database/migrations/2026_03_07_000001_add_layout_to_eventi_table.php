<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventi', function (Blueprint $table) {
            $table->string('colore_primario', 20)->nullable()->after('immagine')->comment('Hex colore primario gradiente (#rrggbb)');
            $table->string('colore_secondario', 20)->nullable()->after('colore_primario')->comment('Hex colore secondario gradiente (#rrggbb)');
        });
    }

    public function down(): void
    {
        Schema::table('eventi', function (Blueprint $table) {
            $table->dropColumn(['colore_primario', 'colore_secondario']);
        });
    }
};
