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

    /**
     * Match wilayah name to ID via LIKE search.
     * Used by Hermes Agent cron to resolve Nominatim place names
     * into numeric wilayah IDs (province_id, regency_id, etc.).
     *
     * Query params:
     *   type  = province | regency | district | village
     *   name  = partial name (e.g. "Jawa Barat", "Bogor")
     *   parent_id = optional — scope search within parent
     *               (province_id for regency, regency_id for district, etc.)
     */
    public function match(Request $request)
    {
        $type = $request->query('type', '');
        $name = trim($request->query('name', ''));
        $parentId = trim($request->query('parent_id', ''));

        if ($name === '') {
            return response()->json(['matches' => []]);
        }

        $table = match ($type) {
            'province' => 'reg_provinces',
            'regency'  => 'reg_regencies',
            'district' => 'reg_districts',
            'village'  => 'reg_villages',
            default    => null,
        };

        if ($table === null) {
            return response()->json(['matches' => []], 422);
        }

        $query = DB::table($table)
            ->select(['id', 'name'])
            ->where('name', 'like', "%{$name}%");

        // Scope to parent if provided
        if ($parentId !== '') {
            $fk = match ($type) {
                'regency'  => 'province_id',
                'district' => 'regency_id',
                'village'  => 'district_id',
                default    => null,
            };
            if ($fk !== null) {
                $query->where($fk, $parentId);
            }
        }

        // Prefer exact match first, then partial
        $exact = (clone $query)->where('name', $name)->first();
        if ($exact) {
            return response()->json(['matches' => [$exact]]);
        }

        $results = $query->orderBy('name')->limit(10)->get();

        return response()->json(['matches' => $results]);
    }
}

