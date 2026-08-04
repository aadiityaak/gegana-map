<?php

namespace App\Http\Controllers;

use App\Models\IpoleksosbudkamItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class IpoleksosbudkamController extends Controller
{
    private const SEVERITY_LEVELS = ['low', 'medium', 'high', 'critical'];
    private const STATUSES = ['active', 'monitoring', 'resolved'];
    private const CATEGORIES = ['ideologi', 'politik', 'ekonomi', 'sosial-budaya', 'keamanan'];

    public function index(Request $request)
    {
        $category = $request->query('category');
        if (is_string($category) && $category !== '' && ! in_array($category, self::CATEGORIES, true)) {
            $category = null;
        }

        $query = IpoleksosbudkamItem::query();

        if ($category) {
            $query->where('category', $category);
        }

        $items = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return Inertia::render('ipoleksosbudkam/Form', [
            'mode' => 'index',
            'items' => $items,
            'filters' => [
                'category' => $category,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('ipoleksosbudkam/Form', [
            'mode' => 'create',
            'item' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        IpoleksosbudkamItem::create($validated);

        return redirect()->route('ipoleksosbudkam.index');
    }

    public function show(IpoleksosbudkamItem $item)
    {
        return Inertia::render('ipoleksosbudkam/Form', [
            'mode' => 'view',
            'item' => $item,
        ]);
    }

    public function edit(IpoleksosbudkamItem $item)
    {
        return Inertia::render('ipoleksosbudkam/Form', [
            'mode' => 'edit',
            'item' => $item,
        ]);
    }

    public function update(Request $request, IpoleksosbudkamItem $item)
    {
        $validated = $this->validatePayload($request);

        $item->update($validated);

        return redirect()->route('ipoleksosbudkam.index');
    }

    public function destroy(IpoleksosbudkamItem $item)
    {
        $item->delete();

        return redirect()->route('ipoleksosbudkam.index');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:50000'],
            'incident_date' => ['nullable', 'date'],
            'severity_level' => ['required', 'string', Rule::in(self::SEVERITY_LEVELS)],
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
            'category' => ['nullable', 'string', Rule::in(self::CATEGORIES)],
            'sub_category' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kabupaten_kota' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'jumlah_terdampak' => ['nullable', 'integer', 'min:0'],
            'source' => ['nullable', 'string', 'max:255'],
            'sumber_berita' => ['nullable', 'string', 'max:2048'],
        ]);
    }
}
