<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HermesAgentLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'type',
        'title',
        'message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
}
