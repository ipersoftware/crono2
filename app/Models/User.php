<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'ente_id',
        'cognome',
        'nome',
        'email',
        'keycloak_id',
        'last_login_provider',
        'password',
        'primo_accesso_eseguito',
        'role',
        'telefono',
        'attivo',
        'privacy_ok',
        'newsletter_ok',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'password'               => 'hashed',
            'attivo'                 => 'boolean',
            'primo_accesso_eseguito' => 'boolean',
            'privacy_ok'             => 'boolean',
            'newsletter_ok'          => 'boolean',
            'last_login_at'          => 'datetime',
        ];
    }

    // ─── Relazioni ────────────────────────────────────────────────────────────

    public function ente(): BelongsTo
    {
        return $this->belongsTo(Ente::class);
    }

    public function prenotazioni(): HasMany
    {
        return $this->hasMany(Prenotazione::class);
    }

    // ─── Helper ruoli ────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isAdminEnte(): bool
    {
        return $this->role === 'admin_ente';
    }

    public function isOperatoreEnte(): bool
    {
        return $this->role === 'operatore_ente';
    }

    public function isUtente(): bool
    {
        return $this->role === 'utente';
    }

    /** Può gestire dati dell'ente (operatore o admin_ente o admin) */
    public function gestisceEnte(?int $enteId = null): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (in_array($this->role, ['admin_ente', 'operatore_ente'])) {
            return $enteId === null || $this->ente_id === $enteId;
        }

        return false;
    }
}


    public function isUtente(): bool
    {
        return $this->role === 'utente';
    }
}
