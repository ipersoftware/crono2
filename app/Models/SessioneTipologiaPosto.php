<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessioneTipologiaPosto extends Model
{
    public $timestamps = false;

    protected $table = 'sessione_tipologie_posto';

    protected $fillable = [
        'sessione_id',
        'tipologia_posto_id',
        'posti_totali',
        'posti_disponibili',
        'posti_riservati',
        'attiva',
    ];

    protected $casts = [
        'attiva' => 'boolean',
    ];

    // ─── Relazioni ────────────────────────────────────────────────────────────

    public function sessione(): BelongsTo
    {
        return $this->belongsTo(Sessione::class);
    }

    public function tipologiaPosto(): BelongsTo
    {
        return $this->belongsTo(TipologiaPosto::class);
    }
}
