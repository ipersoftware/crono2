<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE prenotazioni MODIFY COLUMN stato ENUM(
            'RISERVATA',
            'CONFERMATA',
            'DA_CONFERMARE',
            'ANNULLATA',
            'ANNULLATA_UTENTE',
            'ANNULLATA_ADMIN',
            'IN_LISTA_ATTESA',
            'NOTIFICATO',
            'SCADUTA',
            'SCADUTO'
        ) NOT NULL DEFAULT 'RISERVATA'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE prenotazioni MODIFY COLUMN stato ENUM(
            'RISERVATA',
            'CONFERMATA',
            'DA_CONFERMARE',
            'ANNULLATA',
            'IN_LISTA_ATTESA',
            'NOTIFICATO',
            'SCADUTO'
        ) NOT NULL DEFAULT 'RISERVATA'");
    }
};
