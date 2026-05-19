<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegProvince extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'reg_provinces';

    protected $fillable = ['id', 'name'];

    public $timestamps = false;
}

