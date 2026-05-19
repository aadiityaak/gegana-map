<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WanTerorIncident extends Model
{
    protected $fillable = [
        'incident_type',
        'finding_type',
        'description',
        'photos',
        'province_id',
        'regency_id',
        'district_id',
        'village_id',
    ];

    protected $casts = [
        'photos' => 'array',
    ];
}

