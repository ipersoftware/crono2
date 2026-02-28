<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prenotazione extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'prenotazioni';

    protected $fillable = [
        'sessione_id',
        'user_id',
        'ente_id',
        'stato',
        'codice',
        'data_prenotazione',
        'scadenza_riserva',
        'posti_prenotati',
        'nome',
        'cognome',
        'email',
        'telefono',
        'note',
        'costo_totale',
        'evento_snapshot',
        'data_annullamento',
        'motivo_annullamento',
        'annullata_da_user_id',
    ];

    protected $casts = [
        'data_prenotazione'  => 'datetime',
        'scadenza_riserva'   => 'datetime',
        'data_annullamento'  => 'datetime',
        'costo_totale'       => 'decimal:2',
        'evento_snapshot'    => 'array',
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

    public function ente(): BelongsTo
    {
        return $this->belongsTo(Ente::class);
    }

    public function annullataDA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annullata_da_user_id');
    }

    public function posti(): HasMany
    {
        return $this->hasMany(PrenotazionePosto::class);
    }

    public function risposteForm(): HasMany
    {
        return $this->hasMany(RispostaForm::class);
    }

    public function notificheLog(): HasMany
    {
        return $this->hasMany(NotificaLog::class);
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeAttive($query)
    {
        return $query->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE', 'RISERVATA']);
    }

    public function scopeConfirmate($query)
    {
        return $query->where('stato', 'CONFERMATA');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isAnnullabile(): bool
    {
        if (!in_array($this->stato, ['CONFERMATA', 'DA_CONFERMARE'])) {
            return false;
        }

        // Carica la regola dall'evento della sessione
        $evento = $this->sessione?->evento;
        if (!$evento) {
            return false;
        }

        $ore = $evento->cancellazione_consentita_ore;

        if ($ore === null) {
            return true; // sempre consentita
        }

        if ($ore === -1) {
            return false; // mai consentita
        }

        $dataSessione = $this->sessione->data_inizio;

        return $dataSessione->diffInHours(now(), false) <= -$ore;
    }
}
