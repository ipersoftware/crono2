<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoLog extends Model
{
    public $timestamps = false;

    protected $table = 'evento_log';

    protected $fillable = [
        'evento_id',
        'user_id',
        'azione',
        'descrizione',
        'dettagli',
    ];

    protected $casts = [
        'dettagli'   => 'array',
        'created_at' => 'datetime',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
