<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrenotazioneTemporanea extends Model
{
    public $timestamps = false;

    protected $table = 'prenotazioni_temporanee';

    protected $fillable = [
        'sessione_id',
        'posti_totali',
        'dettaglio_tipologie',
        'token',
        'scadenza_at',
    ];

    protected $casts = [
        'scadenza_at'          => 'datetime',
        'dettaglio_tipologie'  => 'array',
        'created_at'           => 'datetime',
    ];

    // ─── Relazioni ────────────────────────────────────────────────────────────

    public function sessione(): BelongsTo
    {
        return $this->belongsTo(Sessione::class);
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    /** Lock ancora validi (non scaduti) */
    public function scopeAttivi($query)
    {
        return $query->where('scadenza_at', '>', now());
    }

    /** Lock scaduti da rilasciare */
    public function scopeScaduti($query)
    {
        return $query->where('scadenza_at', '<=', now());
    }
}
