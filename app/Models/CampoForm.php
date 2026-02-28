<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampoForm extends Model
{
    use HasFactory;

    protected $table = 'campi_form';

    protected $fillable = [
        'evento_id',
        'ordine',
        'tipo',
        'etichetta',
        'placeholder',
        'obbligatorio',
        'opzioni',
        'validazione',
        'visibile_pubblico',
        'attivo',
    ];

    protected $casts = [
        'obbligatorio'     => 'boolean',
        'visibile_pubblico' => 'boolean',
        'attivo'           => 'boolean',
        'opzioni'          => 'array',
        'validazione'      => 'array',
    ];

    // ─── Relazioni ────────────────────────────────────────────────────────────

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function risposte(): HasMany
    {
        return $this->hasMany(RispostaForm::class);
    }
}
