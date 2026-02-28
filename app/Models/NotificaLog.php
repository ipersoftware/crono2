<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificaLog extends Model
{
    public $timestamps = false;

    protected $table = 'notifiche_log';

    protected $fillable = [
        'ente_id',
        'prenotazione_id',
        'tipo',
        'destinatario_email',
        'oggetto',
        'stato',
        'errore',
        'tentativo',
        'inviata_at',
    ];

    protected $casts = [
        'inviata_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    // ─── Relazioni ────────────────────────────────────────────────────────────

    public function ente(): BelongsTo
    {
        return $this->belongsTo(Ente::class);
    }

    public function prenotazione(): BelongsTo
    {
        return $this->belongsTo(Prenotazione::class);
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeInCoda($query)
    {
        return $query->where('stato', 'IN_CODA');
    }

    public function scopeErrori($query)
    {
        return $query->where('stato', 'ERRORE');
    }
}
