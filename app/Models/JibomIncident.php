<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JibomIncident extends Model
{
    protected $fillable = [
        'incident_type',
        'finding_type',
        'description',
        'photos',
        'latitude',
        'longitude',
        'news_source',
        'news_url',
        'province_id',
        'regency_id',
        'district_id',
        'village_id',
    ];

    protected $casts = [
        'photos' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function developments(): HasMany
    {
        return $this->hasMany(CaseDevelopment::class, 'incident_id')
            ->where('incident_type', 'jibom')
            ->orderByDesc('reported_at');
    }
}
