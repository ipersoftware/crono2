<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RichiestaContatto extends Model
{
    public $timestamps = false;

    protected $table = 'richieste_contatto';

    protected $fillable = [
        'ente_id',
        'nome',
        'email',
        'telefono',
        'messaggio',
        'letta',
    ];

    protected $casts = [
        'letta'      => 'boolean',
        'created_at' => 'datetime',
    ];

    public function ente(): BelongsTo
    {
        return $this->belongsTo(Ente::class);
    }
}
