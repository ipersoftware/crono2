<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ente extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'enti';

    protected $fillable = [
        'nome',
        'codice_fiscale',
        'partita_iva',
        'email',
        'telefono',
        'indirizzo',
        'citta',
        'provincia',
        'cap',
        'descrizione',
        'logo',
        'attivo',
    ];

    protected $casts = [
        'attivo' => 'boolean',
    ];

    // Relazioni
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // Scope
    public function scopeAttivi($query)
    {
        return $query->where('attivo', true);
    }
}
