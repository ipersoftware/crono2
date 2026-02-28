<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('impersonated_ente_id')
                ->nullable()
                ->after('abilities')
                ->comment('Se valorizzato, questo token è un token di impersonificazione per l\'ente indicato');

            $table->foreign('impersonated_ente_id')
                ->references('id')
                ->on('enti')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropForeign(['impersonated_ente_id']);
            $table->dropColumn('impersonated_ente_id');
        });
    }
};
