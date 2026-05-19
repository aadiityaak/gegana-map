<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JibomIncident extends Model
{
    protected $fillable = [
        'incident_type',
        'finding_type',
        'province_id',
        'regency_id',
        'district_id',
        'village_id',
    ];
}

