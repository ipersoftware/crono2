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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ente_id')->nullable()->constrained('enti')->onDelete('set null');
            $table->string('cognome');
            $table->string('nome');
            $table->string('email')->unique();
            $table->string('keycloak_id')->nullable()->unique();
            $table->string('last_login_provider', 32)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable()->comment('NULL in modalità Keycloak');
            $table->boolean('primo_accesso_eseguito')->default(false);
            $table->enum('role', [
                'utente',
                'operatore_ente',
                'admin_ente',
                'admin',
            ])->default('utente');
            $table->string('telefono')->nullable();
            $table->boolean('attivo')->default(true);
            $table->boolean('privacy_ok')->default(false)->comment('Consenso GDPR');
            $table->boolean('newsletter_ok')->default(false);
            $table->dateTime('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
