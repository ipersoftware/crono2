<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrenotazionePosto extends Model
{
    public $timestamps = false;

    protected $table = 'prenotazione_posti';

    protected $fillable = [
        'prenotazione_id',
        'tipologia_posto_id',
        'quantita',
        'costo_unitario',
        'costo_riga',
    ];

    protected $casts = [
        'costo_unitario' => 'decimal:2',
        'costo_riga'     => 'decimal:2',
    ];

    // ─── Relazioni ────────────────────────────────────────────────────────────

    public function prenotazione(): BelongsTo
    {
        return $this->belongsTo(Prenotazione::class);
    }

    public function tipologiaPosto(): BelongsTo
    {
        return $this->belongsTo(TipologiaPosto::class);
    }
}
