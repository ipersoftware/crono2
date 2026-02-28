<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailTemplate extends Model
{
    use HasFactory;

    protected $table = 'mail_templates';

    protected $fillable = [
        'ente_id',
        'tipo',
        'oggetto',
        'corpo',
        'sistema',
        'attivo',
    ];

    protected $casts = [
        'sistema' => 'boolean',
        'attivo'  => 'boolean',
    ];

    // ─── Relazioni ────────────────────────────────────────────────────────────

    public function ente(): BelongsTo
    {
        return $this->belongsTo(Ente::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Risolve il template per un Ente e tipo:
     * cerca prima il template personalizzato dell'Ente,
     * poi il template di sistema (ente_id = NULL).
     */
    public static function risolvi(int $enteId, string $tipo): ?self
    {
        return static::where('tipo', $tipo)->where('attivo', true)
            ->where(function ($q) use ($enteId) {
                $q->where('ente_id', $enteId)
                    ->orWhereNull('ente_id');
            })
            ->orderByRaw('ente_id IS NULL ASC') // prima quelli con ente_id valorizzato
            ->first();
    }

    /**
     * Sostituisce i placeholder nel corpo e nell'oggetto.
     *
     * @param  array<string, string>  $dati  es. ['{{nome_utente}}' => 'Mario']
     */
    public function renderizza(array $dati): array
    {
        return [
            'oggetto' => strtr($this->oggetto, $dati),
            'corpo'   => strtr($this->corpo, $dati),
        ];
    }
}
