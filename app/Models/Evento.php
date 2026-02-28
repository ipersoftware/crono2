<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evento extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'eventi';

    protected $fillable = [
        'ente_id',
        'serie_id',
        'titolo',
        'slug',
        'slug_history',
        'descrizione_breve',
        'descrizione',
        'immagine',
        'stato',
        'pubblico',
        'in_evidenza',
        'ordinamento',
        'visibile_dal',
        'visibile_al',
        'prenotabile_dal',
        'prenotabile_al',
        'posti_max_per_prenotazione',
        'richiede_approvazione',
        'consenti_multi_sessione',
        'consenti_prenotazione_guest',
        'cancellazione_consentita_ore',
        'mostra_disponibilita',
        'attiva_note',
        'nota_etichetta',
        'costo',
        'attributi',
    ];

    protected $casts = [
        'pubblico'                    => 'boolean',
        'in_evidenza'                 => 'boolean',
        'richiede_approvazione'       => 'boolean',
        'consenti_multi_sessione'     => 'boolean',
        'consenti_prenotazione_guest' => 'boolean',
        'mostra_disponibilita'        => 'boolean',
        'attiva_note'                 => 'boolean',
        'visibile_dal'                => 'datetime',
        'visibile_al'                 => 'datetime',
        'prenotabile_dal'             => 'datetime',
        'prenotabile_al'              => 'datetime',
        'costo'                       => 'decimal:2',
        'slug_history'                => 'array',
        'attributi'                   => 'array',
    ];

    // ─── Relazioni ────────────────────────────────────────────────────────────

    public function ente(): BelongsTo
    {
        return $this->belongsTo(Ente::class);
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function sessioni(): HasMany
    {
        return $this->hasMany(Sessione::class);
    }

    public function tipologiePosto(): HasMany
    {
        return $this->hasMany(TipologiaPosto::class);
    }

    public function campiForm(): HasMany
    {
        return $this->hasMany(CampoForm::class)->orderBy('ordine');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'evento_tag');
    }

    public function luoghi(): BelongsToMany
    {
        return $this->belongsToMany(Luogo::class, 'evento_luogo')
            ->withPivot('principale');
    }

    public function luogoPrincipale(): BelongsTo
    {
        return $this->luoghi()->wherePivot('principale', true)->first()
            ? $this->belongsToMany(Luogo::class, 'evento_luogo')->wherePivot('principale', true)
            : $this->belongsToMany(Luogo::class, 'evento_luogo');
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopePubblicati($query)
    {
        return $query->where('stato', 'PUBBLICATO')->where('pubblico', true);
    }

    public function scopeInEvidenza($query)
    {
        return $query->where('in_evidenza', true)->scopePubblicati($query);
    }
}
