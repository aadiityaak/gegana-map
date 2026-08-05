<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpoleksosbudkamItem extends Model
{
    protected $fillable = [
        'title',
        'description',
        'gallery',
        'incident_date',
        'severity_level',
        'status',
        'category',
        'sub_category',
        'latitude',
        'longitude',
        'provinsi',
        'kabupaten_kota',
        'kecamatan',
        'jumlah_terdampak',
        'source',
        'sumber_berita',
    ];

    protected $casts = [
        'gallery' => 'array',
        'incident_date' => 'date',
        'latitude' => 'float',
        'longitude' => 'float',
    ];
}
