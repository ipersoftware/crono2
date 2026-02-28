<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Serie extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'serie';

    protected $fillable = [
        'ente_id',
        'titolo',
        'descrizione',
        'slug',
        'stato',
        'pubblico',
        'visibile_dal',
        'visibile_al',
        'immagine',
        'contenuto',
        'link_pubblico',
    ];

    protected $casts = [
        'pubblico'     => 'boolean',
        'visibile_dal' => 'datetime',
        'visibile_al'  => 'datetime',
    ];

    // ─── Relazioni ────────────────────────────────────────────────────────────

    public function ente(): BelongsTo
    {
        return $this->belongsTo(Ente::class);
    }

    public function eventi(): HasMany
    {
        return $this->hasMany(Evento::class);
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopePubblicati($query)
    {
        return $query->where('stato', 'PUBBLICATO')->where('pubblico', true);
    }
}
