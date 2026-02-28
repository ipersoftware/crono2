<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RispostaForm extends Model
{
    public $timestamps = false;

    protected $table = 'risposte_form';

    protected $fillable = [
        'prenotazione_id',
        'campo_form_id',
        'valore',
    ];

    // ─── Relazioni ────────────────────────────────────────────────────────────

    public function prenotazione(): BelongsTo
    {
        return $this->belongsTo(Prenotazione::class);
    }

    public function campoForm(): BelongsTo
    {
        return $this->belongsTo(CampoForm::class);
    }
}
