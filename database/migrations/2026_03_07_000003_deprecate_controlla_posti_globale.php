<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessioni', function (Blueprint $table) {
            $table->dropColumn('controlla_posti_globale');
        });
    }

    public function down(): void
    {
        Schema::table('sessioni', function (Blueprint $table) {
            $table->boolean('controlla_posti_globale')->default(false)->after('posti_riservati');
        });
    }
};
