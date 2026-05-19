<?php

namespace App\Http\Controllers\Jibom;

use App\Http\Controllers\Controller;
use App\Models\JibomIncident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class JibomIncidentController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->query('type');
        $allowedTypes = ['ancaman', 'temuan', 'ledakan'];
        if (is_string($type) && $type !== '' && ! in_array($type, $allowedTypes, true)) {
            $type = null;
        }

        $provinceId = $request->query('province_id');
        if (is_string($provinceId)) {
            $provinceId = trim($provinceId);
        }
        if (! is_string($provinceId) || $provinceId === '' || strlen($provinceId) !== 2) {
            $provinceId = null;
        } else {
            $exists = DB::table('reg_provinces')->where('id', $provinceId)->exists();
            if (! $exists) {
                $provinceId = null;
            }
        }

        $query = DB::table('jibom_incidents as ji')
            ->leftJoin('reg_provinces as p', 'p.id', '=', 'ji.province_id')
            ->leftJoin('reg_regencies as r', 'r.id', '=', 'ji.regency_id')
            ->leftJoin('reg_districts as d', 'd.id', '=', 'ji.district_id')
            ->leftJoin('reg_villages as v', 'v.id', '=', 'ji.village_id')
            ->select([
                'ji.id',
                'ji.incident_type',
                'ji.finding_type',
                'ji.province_id',
                'ji.regency_id',
                'ji.district_id',
                'ji.village_id',
                'ji.created_at',
                'p.name as province_name',
                'r.name as regency_name',
                'd.name as district_name',
                'v.name as village_name',
            ]);

        if (is_string($type) && $type !== '') {
            $query->where('ji.incident_type', $type);
        }

        if (is_string($provinceId) && $provinceId !== '') {
            $query->where('ji.province_id', $provinceId);
        }

        $items = $query->orderByDesc('ji.id')->paginate(20)->withQueryString();

        return Inertia::render('jibom/Index', [
            'items' => $items,
            'filters' => [
                'type' => $type,
                'province_id' => $provinceId,
            ],
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('jibom/Form', [
            'mode' => 'create',
            'item' => null,
            'filters' => [
                'type' => $request->query('type'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);
        JibomIncident::create($data);

        return redirect('/jibom');
    }

    public function edit(JibomIncident $incident)
    {
        return Inertia::render('jibom/Form', [
            'mode' => 'edit',
            'item' => $incident,
            'filters' => [
                'type' => $incident->incident_type,
            ],
        ]);
    }

    public function update(Request $request, JibomIncident $incident)
    {
        $data = $this->validatePayload($request);
        $incident->update($data);

        return redirect('/jibom');
    }

    public function destroy(JibomIncident $incident)
    {
        $incident->delete();

        return redirect('/jibom');
    }

    private function validatePayload(Request $request): array
    {
        $findingTypes = ['bom-militer', 'bom-rakitan', 'bom-ikan', 'petasan', 'lainnya'];

        return $request->validate([
            'incident_type' => ['required', 'string', Rule::in(['ancaman', 'temuan', 'ledakan'])],
            'finding_type' => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => $request->input('incident_type') === 'temuan'),
                Rule::in($findingTypes),
            ],
            'province_id' => ['required', 'string', 'size:2', 'exists:reg_provinces,id'],
            'regency_id' => ['required', 'string', 'size:4', 'exists:reg_regencies,id'],
            'district_id' => ['required', 'string', 'size:6', 'exists:reg_districts,id'],
            'village_id' => ['required', 'string', 'size:10', 'exists:reg_villages,id'],
        ]);
    }
}
