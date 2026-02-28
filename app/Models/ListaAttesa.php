<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListaAttesa extends Model
{
    public $timestamps = false;

    protected $table = 'lista_attesa';

    protected $fillable = [
        'sessione_id',
        'user_id',
        'nome',
        'cognome',
        'email',
        'telefono',
        'posti_richiesti',
        'dettaglio_tipologie',
        'posizione',
        'stato',
        'notificato_at',
        'scadenza_conferma_at',
    ];

    protected $casts = [
        'notificato_at'       => 'datetime',
        'scadenza_conferma_at' => 'datetime',
        'created_at'          => 'datetime',
        'dettaglio_tipologie' => 'array',
    ];

    // ─── Relazioni ────────────────────────────────────────────────────────────

    public function sessione(): BelongsTo
    {
        return $this->belongsTo(Sessione::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeInAttesa($query)
    {
        return $query->where('stato', 'IN_ATTESA');
    }

    public function scopeNotificati($query)
    {
        return $query->where('stato', 'NOTIFICATO');
    }

    public function scopeScadutiConferma($query)
    {
        return $query->where('stato', 'NOTIFICATO')
            ->where('scadenza_conferma_at', '<=', now());
    }
}
