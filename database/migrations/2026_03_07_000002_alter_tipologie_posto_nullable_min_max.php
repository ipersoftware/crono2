<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipologie_posto', function (Blueprint $table) {
            $table->integer('min_prenotabili')->nullable()->default(null)->change();
            $table->integer('max_prenotabili')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tipologie_posto', function (Blueprint $table) {
            $table->integer('min_prenotabili')->default(1)->change();
            $table->integer('max_prenotabili')->nullable()->default(null)->change();
        });
    }
};
