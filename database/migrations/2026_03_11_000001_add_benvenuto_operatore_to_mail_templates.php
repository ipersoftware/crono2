<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ENUM_VALUES = [
        'PRENOTAZIONE_CONFERMATA',
        'PRENOTAZIONE_DA_CONFERMARE',
        'PRENOTAZIONE_APPROVATA',
        'PRENOTAZIONE_ANNULLATA_UTENTE',
        'PRENOTAZIONE_ANNULLATA_OPERATORE',
        'PRENOTAZIONE_NOTIFICA_STAFF',
        'EVENTO_ANNULLATO',
        'LISTA_ATTESA_ISCRIZIONE',
        'LISTA_ATTESA_POSTO_DISPONIBILE',
        'LISTA_ATTESA_SCADENZA',
        'REMINDER_EVENTO',
        'REGISTRAZIONE_CONFERMATA',
        'RESET_PASSWORD',
        'BENVENUTO_OPERATORE',
    ];

    public function up(): void
    {
        $enumList = implode(',', array_map(fn ($v) => "'{$v}'", self::ENUM_VALUES));

        DB::statement("ALTER TABLE mail_templates MODIFY COLUMN tipo ENUM({$enumList}) NOT NULL");
        DB::statement("ALTER TABLE notifiche_log  MODIFY COLUMN tipo ENUM({$enumList}) NOT NULL");
    }

    public function down(): void
    {
        $withoutNew = array_filter(self::ENUM_VALUES, fn ($v) => $v !== 'BENVENUTO_OPERATORE');
        $enumList = implode(',', array_map(fn ($v) => "'{$v}'", $withoutNew));

        DB::statement("ALTER TABLE mail_templates MODIFY COLUMN tipo ENUM({$enumList}) NOT NULL");
        DB::statement("ALTER TABLE notifiche_log  MODIFY COLUMN tipo ENUM({$enumList}) NOT NULL");
    }
};
