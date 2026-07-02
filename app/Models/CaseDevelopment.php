<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseDevelopment extends Model
{
    protected $fillable = [
        'incident_type',
        'incident_id',
        'title',
        'description',
        'source_url',
        'reported_at',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
    ];
}
