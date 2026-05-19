<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegDistrict extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'reg_districts';

    protected $fillable = ['id', 'regency_id', 'name'];

    public $timestamps = false;
}

