<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegRegency extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'reg_regencies';

    protected $fillable = ['id', 'province_id', 'name'];

    public $timestamps = false;
}

