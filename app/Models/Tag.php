<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ente_id',
        'nome',
        'slug',
        'colore',
    ];

    // ─── Relazioni ────────────────────────────────────────────────────────────

    public function ente(): BelongsTo
    {
        return $this->belongsTo(Ente::class);
    }

    public function eventi(): BelongsToMany
    {
        return $this->belongsToMany(Evento::class, 'evento_tag');
    }
}
