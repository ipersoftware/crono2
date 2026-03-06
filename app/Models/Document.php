<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $fillable = [
        'ente_id',
        'user_id',
        'file_name',
        'name',
        'relative_path',
        'mime_type',
        'file_size',
        'description',
    ];

    public function ente(): BelongsTo
    {
        return $this->belongsTo(Ente::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
