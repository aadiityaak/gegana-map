<?php

namespace App\Http\Controllers\KBRN;

use App\Http\Controllers\Controller;
use App\Models\KBRNIncident;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class KBRNIncidentController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->query('type');
        $allowedTypes = ['ancaman', 'temuan', 'ledakan'];
        if (is_string($type) && $type !== '' && ! in_array($type, $allowedTypes, true)) {
            $type = null;
        }

        $findingType = $request->query('finding_type');
        $allowedFindingTypes = [
            'kimia',
            'biologi',
            'radioaktif',
            'nuklir',
            'amoniak',
            'gas-beracun',
            'klorin',
            'asam-sulfat',
            'asam-nitrat',
            'racun-tikus',
            'senyawa-organik',
            'sianida',
            'logam-berat',
            'bahan-radiasi',
            'lainnya'
        ];
        if (is_string($findingType) && $findingType !== '' && ! in_array($findingType, $allowedFindingTypes, true)) {
            $findingType = null;
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

        $query = DB::table('kbrn_incidents as ki')
            ->leftJoin('reg_provinces as p', 'p.id', '=', 'ki.province_id')
            ->leftJoin('reg_regencies as r', 'r.id', '=', 'ki.regency_id')
            ->leftJoin('reg_districts as d', 'd.id', '=', 'ki.district_id')
            ->leftJoin('reg_villages as v', 'v.id', '=', 'ki.village_id')
            ->select([
                'ki.id',
                'ki.incident_type',
                'ki.finding_type',
                'ki.description',
                'ki.photos',
                'ki.news_source',
                'ki.province_id',
                'ki.regency_id',
                'ki.district_id',
                'ki.village_id',
                'ki.created_at',
                'p.name as province_name',
                'r.name as regency_name',
                'd.name as district_name',
                'v.name as village_name',
            ]);

        if (is_string($type) && $type !== '') {
            $query->where('ki.incident_type', $type);
        }

        if (is_string($findingType) && $findingType !== '') {
            $query->where('ki.finding_type', $findingType);
        }

        if (is_string($provinceId) && $provinceId !== '') {
            $query->where('ki.province_id', $provinceId);
        }

        $items = $query->orderByDesc('ki.id')->paginate(20)->withQueryString();

        return Inertia::render('kbrn/Index', [
            'items' => $items,
            'filters' => [
                'type' => $type,
                'finding_type' => $findingType,
                'province_id' => $provinceId,
            ],
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('kbrn/Form', [
            'mode' => 'create',
            'item' => null,
            'filters' => [
                'type' => $request->query('type'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $data = Arr::only($validated, [
            'incident_type',
            'finding_type',
            'latitude',
            'longitude',
            'news_source',
            'news_url',
            'province_id',
            'regency_id',
            'district_id',
            'village_id',
        ]);
        $data['description'] = $this->sanitizeDescription($validated['description'] ?? null);
        $data['photos'] = $this->storePhotos($request, []);

        KBRNIncident::create($data);

        return redirect('/kbrn');
    }

    public function show(KBRNIncident $incident)
    {
        $row = DB::table('kbrn_incidents as ki')
            ->leftJoin('reg_provinces as p', 'p.id', '=', 'ki.province_id')
            ->leftJoin('reg_regencies as r', 'r.id', '=', 'ki.regency_id')
            ->leftJoin('reg_districts as d', 'd.id', '=', 'ki.district_id')
            ->leftJoin('reg_villages as v', 'v.id', '=', 'ki.village_id')
            ->where('ki.id', $incident->id)
            ->select([
                'ki.id',
                'ki.incident_type',
                'ki.finding_type',
                'ki.description',
                'ki.photos',
                'ki.latitude',
                'ki.longitude',
                'ki.news_source',
                'ki.news_url',
                'ki.province_id',
                'ki.regency_id',
                'ki.district_id',
                'ki.village_id',
                'ki.created_at',
                'p.name as province_name',
                'r.name as regency_name',
                'd.name as district_name',
                'v.name as village_name',
            ])
            ->first();

        $photos = $row?->photos;
        if (is_string($photos)) {
            $decoded = json_decode($photos, true);
            $photos = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($photos)) {
            $photos = [];
        }

        $item = $row
            ? [
                'id' => $row->id,
                'incident_type' => $row->incident_type,
                'finding_type' => $row->finding_type,
                'description' => $row->description,
                'photos' => $photos,
                'latitude' => $row->latitude,
                'longitude' => $row->longitude,
                'news_source' => $row->news_source,
                'news_url' => $row->news_url,
                'province_id' => $row->province_id,
                'regency_id' => $row->regency_id,
                'district_id' => $row->district_id,
                'village_id' => $row->village_id,
                'created_at' => $row->created_at,
                'province_name' => $row->province_name,
                'regency_name' => $row->regency_name,
                'district_name' => $row->district_name,
                'village_name' => $row->village_name,
            ]
            : $incident;

        return Inertia::render('kbrn/Form', [
            'mode' => 'view',
            'item' => $item,
            'filters' => [
                'type' => $incident->incident_type,
            ],
        ]);
    }

    public function edit(KBRNIncident $incident)
    {
        return Inertia::render('kbrn/Form', [
            'mode' => 'edit',
            'item' => $incident,
            'filters' => [
                'type' => $incident->incident_type,
            ],
        ]);
    }

    public function update(Request $request, KBRNIncident $incident)
    {
        $validated = $this->validatePayload($request);
        $data = Arr::only($validated, [
            'incident_type',
            'finding_type',
            'latitude',
            'longitude',
            'news_source',
            'news_url',
            'province_id',
            'regency_id',
            'district_id',
            'village_id',
        ]);
        $data['description'] = $this->sanitizeDescription($validated['description'] ?? null);

        $existing = $incident->photos;
        if (! is_array($existing)) {
            $existing = [];
        }
        $keep = $validated['existing_photos'] ?? null;
        if (is_array($keep)) {
            $keep = array_values(array_intersect($existing, $keep));
        } else {
            $keep = $existing;
        }
        $data['photos'] = $this->storePhotos($request, $keep);

        $incident->update($data);

        return redirect('/kbrn');
    }

    public function destroy(KBRNIncident $incident)
    {
        $incident->delete();

        return redirect('/kbrn');
    }

    private function validatePayload(Request $request): array
    {
        $findingTypes = [
            'kimia',
            'biologi',
            'radioaktif',
            'nuklir',
            'amoniak',
            'gas-beracun',
            'klorin',
            'asam-sulfat',
            'asam-nitrat',
            'racun-tikus',
            'senyawa-organik',
            'sianida',
            'logam-berat',
            'bahan-radiasi',
            'lainnya'
        ];

        return $request->validate([
            'incident_type' => ['required', 'string', Rule::in(['ancaman', 'temuan', 'ledakan'])],
            'finding_type' => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => $request->input('incident_type') === 'temuan'),
                Rule::in($findingTypes),
            ],
            'description' => ['nullable', 'string', 'max:50000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'news_source' => ['required', 'string', Rule::in(['offline', 'online'])],
            'news_url' => [
                'nullable',
                'string',
                'max:2048',
                Rule::requiredIf(fn () => $request->input('news_source') === 'online'),
                'url',
            ],
            'existing_photos' => ['nullable', 'array', 'max:20'],
            'existing_photos.*' => ['string', 'max:255'],
            'photos' => ['nullable', 'array', 'max:20'],
            'photos.*' => ['file', 'image', 'max:4096'],
            'province_id' => ['required', 'string', 'size:2', 'exists:reg_provinces,id'],
            'regency_id' => ['required', 'string', 'size:4', 'exists:reg_regencies,id'],
            'district_id' => ['required', 'string', 'size:6', 'exists:reg_districts,id'],
            'village_id' => ['required', 'string', 'size:10', 'exists:reg_villages,id'],
        ]);
    }

    private function storePhotos(Request $request, array $keep): array
    {
        $files = $request->file('photos', []);
        if (! is_array($files)) {
            $files = [];
        }

        $uploaded = [];
        foreach ($files as $file) {
            if (! $file) {
                continue;
            }
            $uploaded[] = $file->store('kbrn', 'public');
        }

        return array_values(array_filter(array_merge($keep, $uploaded)));
    }

    private function sanitizeDescription(?string $html): ?string
    {
        if (! is_string($html)) {
            return null;
        }
        $html = trim($html);
        if ($html === '') {
            return null;
        }

        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new \DOMXPath($doc);
        foreach (['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta'] as $tag) {
            $nodes = $xpath->query('//' . $tag);
            if (! $nodes) {
                continue;
            }
            foreach ($nodes as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $all = $xpath->query('//*');
        if ($all) {
            foreach ($all as $node) {
                if (! ($node instanceof \DOMElement)) {
                    continue;
                }
                $attrNames = [];
                foreach (iterator_to_array($node->attributes ?? []) as $attr) {
                    if ($attr instanceof \DOMAttr) {
                        $attrNames[] = $attr->name;
                    }
                }
                foreach ($attrNames as $name) {
                    if ($node->tagName === 'a' && $name === 'href') {
                        continue;
                    }
                    $node->removeAttribute($name);
                }

                if ($node->tagName === 'a' && $node->hasAttribute('href')) {
                    $href = trim($node->getAttribute('href'));
                    if ($href === '' || preg_match('/^\\s*javascript:/i', $href)) {
                        $node->removeAttribute('href');
                        continue;
                    }
                    $isAbsoluteHttp = preg_match('/^https?:\\/\\//i', $href) === 1;
                    $isRootRelative = str_starts_with($href, '/');
                    if (! $isAbsoluteHttp && ! $isRootRelative) {
                        $node->removeAttribute('href');
                    }
                }
            }
        }

        $out = trim($doc->saveHTML() ?? '');
        return $out === '' ? null : $out;
    }
}
