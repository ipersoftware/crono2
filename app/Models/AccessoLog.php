<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessoLog extends Model
{
    public $timestamps = false;

    protected $table = 'accessi_log';

    protected $fillable = [
        'user_id',
        'ente_id',
        'role',
        'ip',
        'user_agent',
        'client_id',
        'esito',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function ente(): BelongsTo
    {
        return $this->belongsTo(Ente::class);
    }
}
