<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegVillage extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'reg_villages';

    protected $fillable = ['id', 'district_id', 'name'];

    public $timestamps = false;
}

