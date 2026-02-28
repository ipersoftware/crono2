<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enti', function (Blueprint $table) {
            $table->unsignedBigInteger('governance_id')
                ->nullable()
                ->after('id')
                ->comment('ID dell\'ente nel DB Governance (se importato da lì)');

            $table->unique('governance_id');
        });
    }

    public function down(): void
    {
        Schema::table('enti', function (Blueprint $table) {
            $table->dropUnique(['governance_id']);
            $table->dropColumn('governance_id');
        });
    }
};
