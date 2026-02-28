<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Luogo extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ente_id',
        'nome',
        'descrizione',
        'slug',
        'indirizzo',
        'citta',
        'provincia',
        'cap',
        'lat',
        'lng',
        'maps_url',
        'telefono',
        'email',
        'link_pubblico',
        'immagine',
        'stato',
    ];

    protected $casts = [
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
    ];

    // ─── Relazioni ────────────────────────────────────────────────────────────

    public function ente(): BelongsTo
    {
        return $this->belongsTo(Ente::class);
    }

    public function eventi(): BelongsToMany
    {
        return $this->belongsToMany(Evento::class, 'evento_luogo')
            ->withPivot('principale');
    }

    public function sessioni(): BelongsToMany
    {
        return $this->belongsToMany(Sessione::class, 'sessione_luogo');
    }
}
