<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Estende il modello Sanctum per supportare l'impersonificazione degli enti.
 *
 * @property int|null $impersonated_ente_id
 * @property-read Ente|null $impersonatedEnte
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'impersonated_ente_id',
    ];

    protected $casts = [
        'abilities'            => 'json',
        'last_used_at'         => 'datetime',
        'expires_at'           => 'datetime',
        'impersonated_ente_id' => 'integer',
    ];

    /**
     * Ente attualmente impersonificato dall'admin tramite questo token.
     */
    public function impersonatedEnte(): BelongsTo
    {
        return $this->belongsTo(Ente::class, 'impersonated_ente_id');
    }

    /**
     * Indica se il token è un token di impersonificazione.
     */
    public function isImpersonating(): bool
    {
        return $this->impersonated_ente_id !== null;
    }
}
