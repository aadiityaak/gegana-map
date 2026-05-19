<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WilayahController extends Controller
{
    public function provinces()
    {
        return DB::table('reg_provinces')
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
    }

    public function regencies(Request $request)
    {
        $provinceId = (string) $request->query('province_id', '');

        return DB::table('reg_regencies')
            ->select(['id', 'name'])
            ->where('province_id', $provinceId)
            ->orderBy('name')
            ->get();
    }

    public function districts(Request $request)
    {
        $regencyId = (string) $request->query('regency_id', '');

        return DB::table('reg_districts')
            ->select(['id', 'name'])
            ->where('regency_id', $regencyId)
            ->orderBy('name')
            ->get();
    }

    public function villages(Request $request)
    {
        $districtId = (string) $request->query('district_id', '');

        return DB::table('reg_villages')
            ->select(['id', 'name'])
            ->where('district_id', $districtId)
            ->orderBy('name')
            ->limit(500)
            ->get();
    }
}

