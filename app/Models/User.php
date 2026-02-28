<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cognome',
        'nome',
        'email',
        'keycloak_id',
        'last_login_provider',
        'password',
        'primo_accesso_eseguito',
        'role',
        'ente_id',
        'telefono',
        'attivo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'attivo' => 'boolean',
            'primo_accesso_eseguito' => 'boolean',
        ];
    }

    // Relazioni
    public function ente(): BelongsTo
    {
        return $this->belongsTo(Ente::class);
    }

    // Helper per verificare ruoli
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
}
