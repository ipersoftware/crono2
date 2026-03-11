<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accessi_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ente_id')->nullable()->constrained('enti')->nullOnDelete();
            $table->string('role', 30);
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('client_id', 100)->nullable()->comment('Keycloak client_id (es. crono-web)');
            $table->enum('esito', ['ok', 'account_disabilitato'])->default('ok');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accessi_log');
    }
};
