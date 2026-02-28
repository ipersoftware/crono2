<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipologiaPosto extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tipologie_posto';

    protected $fillable = [
        'evento_id',
        'ente_id',
        'nome',
        'descrizione',
        'gratuita',
        'costo',
        'min_prenotabili',
        'max_prenotabili',
        'ordinamento',
        'attiva',
    ];

    protected $casts = [
        'gratuita'        => 'boolean',
        'attiva'          => 'boolean',
        'costo'           => 'decimal:2',
    ];

    // ─── Relazioni ────────────────────────────────────────────────────────────

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function ente(): BelongsTo
    {
        return $this->belongsTo(Ente::class);
    }

    public function sessioniDisponibilita(): HasMany
    {
        return $this->hasMany(SessioneTipologiaPosto::class);
    }

    public function prenotazionePosti(): HasMany
    {
        return $this->hasMany(PrenotazionePosto::class);
    }
}
