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
        'governance_id',
        'nome',
        'slug',
        'shop_url',
        'codice_fiscale',
        'partita_iva',
        'email',
        'telefono',
        'indirizzo',
        'citta',
        'provincia',
        'cap',
        'lat',
        'lng',
        'descrizione',
        'logo',
        'copertina',
        'contenuto_vetrina',
        'eventi_in_evidenza',
        'stato',
        'licenza',
        'config',
        'attivo_dal',
        'attivo_al',
        'attivo',
    ];

    protected $casts = [
        'attivo'              => 'boolean',
        'eventi_in_evidenza'  => 'array',
        'config'              => 'array',
        'attivo_dal'          => 'datetime',
        'attivo_al'           => 'datetime',
        'lat'                 => 'decimal:8',
        'lng'                 => 'decimal:8',
    ];

    // ─── Relazioni ────────────────────────────────────────────────────────────

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function luoghi(): HasMany
    {
        return $this->hasMany(Luogo::class);
    }

    public function serie(): HasMany
    {
        return $this->hasMany(Serie::class);
    }

    public function eventi(): HasMany
    {
        return $this->hasMany(Evento::class);
    }

    public function mailTemplates(): HasMany
    {
        return $this->hasMany(MailTemplate::class);
    }

    public function notificheLog(): HasMany
    {
        return $this->hasMany(NotificaLog::class);
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeAttivi($query)
    {
        return $query->where('attivo', true)->where('stato', 'ATTIVO');
    }
}

