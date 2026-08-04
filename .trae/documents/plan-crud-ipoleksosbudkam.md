# Plan: CRUD Data IPOLEKSOSBUDKAM Lokal + Gabungan API

## Summary

Menambahkan fitur CRUD untuk data ipoleksosbudkam lokal di aplikasi, lalu menggabungkan data lokal dengan data dari API eksternal (crime-map) dalam satu tampilan list/peta yang sama. User dengan role `superadmin`/`admin`/`adminvip` dapat create, edit, delete data lokal langsung dari halaman ipoleksosbudkam.

---

## Current State

### Arsitektur Saat Ini
```
Vue (ipoleksosbudkam/Index.vue)
  | fetch() ke /api/ipoleksosbudkam/monitoring-data
  v
Laravel Route (web.php, closure)
  | Http::withToken(DATA_TOKEN) → proxy ke external crime-map API
  v
External Service (crime-map)
```

- **Tidak ada** tabel database, model, atau controller untuk ipoleksosbudkam
- Semua data dari external API, Laravel hanya proxy pass-through
- Halaman Vue `ipoleksosbudkam/Index.vue` fetch data via `fetch()` ke `/api/ipoleksosbudkam/monitoring-data`
- Pola CRUD sudah ada untuk 3 modul lain: JIBOM, KBRN, WAN TEROR (Controller + Model + Form.vue + Index.vue)
- CRUD routes dilindungi middleware `role:superadmin,admin,adminvip`

### Struktur Data MonitoringItem (dari API)
```ts
{
  id: number | string;
  title: string;
  description: string | null;
  incident_date: string | null;
  severity_level: 'low' | 'medium' | 'high' | 'critical' | string;
  status: 'active' | 'monitoring' | 'resolved' | string;
  provinsi?: { id: number; nama: string } | null;
  kabupaten_kota?: { id: number; nama: string } | null;
  kecamatan?: { id: number; nama: string } | null;
  category?: { id: number; name: string; slug: string } | null;
  sub_category?: { id: number; name: string; slug: string } | null;
  latitude?: number | string | null;
  longitude?: number | string | null;
  jumlah_terdampak?: number | null;
  source?: string | null;
  gallery?: Array<{ path: string; url: string }>;
  video_url?: string | null;
  sumber_berita?: string | null;
  data_source?: string | null;
}
```

---

## Proposed Changes

### 1. Database Migration
**File baru:** `database/migrations/YYYY_MM_DD_HHMMSS_create_ipoleksosbudkam_items_table.php`

Buat tabel `ipoleksosbudkam_items` dengan struktur mengikuti MonitoringItem API:

| Column | Type | Notes |
|---|---|---|
| id | bigIncrements | PK |
| title | string(255) | required |
| description | longText | nullable |
| incident_date | date | nullable |
| severity_level | string(20) | default 'low' (low/medium/high/critical) |
| status | string(20) | default 'active' (active/monitoring/resolved) |
| category | string(100) | nullable (e.g. 'ekonomi', 'politik') |
| sub_category | string(100) | nullable (e.g. 'ekonomi-korupsi') |
| latitude | decimal(10,7) | nullable |
| longitude | decimal(10,7) | nullable |
| provinsi | string(100) | nullable (nama provinsi, bukan FK) |
| kabupaten_kota | string(100) | nullable |
| kecamatan | string(100) | nullable |
| jumlah_terdampak | integer | nullable |
| source | string(255) | nullable |
| sumber_berita | string(2048) | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

**Mengapa field nama wilayah pakai string, bukan FK ke tabel wilayah:**
- Data API external pakai nama string (`provinsi.nama`, `kabupaten_kota.nama`), bukan ID
- Menjaga kompatibilitas saat merge data, query dan filter tetap sederhana
- Menghindari dependency ke seeder wilayah yg besar (83k+ villages)

### 2. Model
**File baru:** `app/Models/IpoleksosbudkamItem.php`

```php
class IpoleksosbudkamItem extends Model
{
    protected $fillable = [
        'title', 'description', 'incident_date',
        'severity_level', 'status',
        'category', 'sub_category',
        'latitude', 'longitude',
        'provinsi', 'kabupaten_kota', 'kecamatan',
        'jumlah_terdampak', 'source', 'sumber_berita',
    ];
}
```

### 3. Controller
**File baru:** `app/Http/Controllers/IpoleksosbudkamController.php`

Mengikuti pola minimal dari `JibomIncidentController`:
- `index()` — list dengan paginasi + filter (category, sub_category)
- `create()` — Inertia render form (mode create)
- `store()` — validasi + simpan + redirect
- `show($item)` — Inertia render form (mode view)
- `edit($item)` — Inertia render form (mode edit)
- `update($item)` — validasi + update + redirect
- `destroy($item)` — hapus + redirect

Validasi store/update mengacu pada field MonitoringItem.
**Tidak perlu:** foto upload, rich text editor, wilayah cascade — karena ipoleksosbudkam lebih sederhana.

### 4. Routes (web.php)

**a) CRUD routes** — tambahkan di dalam group `role:superadmin,admin,adminvip` (baris 590):

```php
// Inside Route::middleware(['role:superadmin,admin,adminvip'])->group(function () {
Route::get('ipoleksosbudkam-local', [IpoleksosbudkamController::class, 'index'])->name('ipoleksosbudkam-local.index');
Route::get('ipoleksosbudkam-local/create', [IpoleksosbudkamController::class, 'create'])->name('ipoleksosbudkam-local.create');
Route::post('ipoleksosbudkam-local', [IpoleksosbudkamController::class, 'store'])->name('ipoleksosbudkam-local.store');
Route::get('ipoleksosbudkam-local/{item}', [IpoleksosbudkamController::class, 'show'])->name('ipoleksosbudkam-local.show');
Route::get('ipoleksosbudkam-local/{item}/edit', [IpoleksosbudkamController::class, 'edit'])->name('ipoleksosbudkam-local.edit');
Route::put('ipoleksosbudkam-local/{item}', [IpoleksosbudkamController::class, 'update'])->name('ipoleksosbudkam-local.update');
Route::delete('ipoleksosbudkam-local/{item}', [IpoleksosbudkamController::class, 'destroy'])->name('ipoleksosbudkam-local.destroy');
```

**b) API merge route** — modifikasi existing `/api/ipoleksosbudkam/monitoring-data` (baris 66-118):

Route ini akan:
1. Query data lokal dari `ipoleksosbudkam_items` dengan filter yg sama
2. Fetch data dari API external (seperti sekarang)
3. Normalize local data agar match format API response
4. Merge kedua dataset, sort by `incident_date DESC`
5. Paginate manual hasil merge
6. Return response dengan format yg sama (`{ data, meta }`)

Response akan punya field `data_source`:
- `"api"` — dari external crime-map
- `"lokal"` — dari database lokal

### 5. Vue — ipoleksosbudkam/Index.vue (update)

**File:** `resources/js/pages/ipoleksosbudkam/Index.vue`

Perubahan pada halaman list (existing, ~900 baris):

1. **MonitoringItem type** — tambah field `data_source: 'api' | 'lokal'` + `_local_id?: number`
2. **Indikator sumber data** — badge kecil di setiap row: "API" (biru) / "Lokal" (hijau)
3. **Tombol "Tambah Data"** — tampil hanya untuk role `superadmin`/`admin`/`adminvip`, navigasi ke Form create
4. **Tombol Edit/Hapus** — tampil di setiap row data lokal (icon button kecil)
5. **Konfirmasi hapus** — dialog konfirmasi sebelum delete
6. **Handle delete** — `fetch DELETE /ipoleksosbudkam-local/{_local_id}` lalu refresh list
7. **Map marker** — warna berbeda untuk data lokal (hijau) vs API (merah default)

### 6. Vue — ipoleksosbudkam/Form.vue (baru)

**File baru:** `resources/js/pages/ipoleksosbudkam/Form.vue`

Form sederhana untuk create/edit/view data lokal. Mirip `jibom/Form.vue` tapi tanpa:
- Rich text editor (pakai textarea untuk description)
- Upload foto
- Cascade wilayah (pakai text input bebas untuk provinsi/kab/kota/kec)
- Peta Leaflet (pakai input angka lat/lng)

Field form:
- `title` (text input, required)
- `description` (textarea)
- `incident_date` (date input)
- `severity_level` (select: rendah/sedang/tinggi/kritis)
- `status` (select: aktif/monitoring/selesai)
- `category` (select: ideologi/politik/ekonomi/sosial-budaya/keamanan)
- `sub_category` (text input bebas)
- `provinsi` (text input)
- `kabupaten_kota` (text input)
- `kecamatan` (text input)
- `latitude`, `longitude` (number input)
- `jumlah_terdampak` (number input)
- `source` (text input)
- `sumber_berita` (text input atau URL)

Mode: `create` | `edit` | `view`

---

## Assumptions & Decisions

| # | Decision | Rationale |
|---|---|---|
| 1 | Nama wilayah pakai `string` bukan FK | Cocokkan dengan format API, hindari dependency wilayah seeder besar |
| 2 | `data_source` field tidak disimpan di DB | Ditambahkan saat merge di API route, cukup sebagai marker |
| 3 | CRUD routes di-prefix `ipoleksosbudkam-local` | Hindari konflik dengan Inertia routes `/ipoleksosbudkam/{category}` yg sudah ada |
| 4 | Form tanpa rich text & foto upload | Data ipoleksosbudkam sifatnya monitoring teks, bukan insiden dengan bukti visual |
| 5 | Pagination merge manual di PHP | Karena data gabungan dari 2 sumber, paginasi tidak bisa pakai Eloquent paginate langsung |
| 6 | Role yang sama dengan modul lain: `superadmin`, `admin`, `adminvip` | Konsisten dengan JIBOM/KBRN/WAN TEROR |

---

## Files to Create/Modify

### Create
1. `database/migrations/YYYY_MM_DD_HHMMSS_create_ipoleksosbudkam_items_table.php`
2. `app/Models/IpoleksosbudkamItem.php`
3. `app/Http/Controllers/IpoleksosbudkamController.php`
4. `resources/js/pages/ipoleksosbudkam/Form.vue`

### Modify
5. `routes/web.php` — tambah CRUD routes + modifikasi merge API route
6. `resources/js/pages/ipoleksosbudkam/Index.vue` — tombol CRUD, indikator sumber, map marker warna

---

## Verification

1. Jalankan `php artisan migrate` — tabel `ipoleksosbudkam_items` terbuat
2. Login sebagai `superadmin@example.com` / password `password`
3. Buka `/ipoleksosbudkam` — lihat data dari API (dengan badge "API")
4. Klik "Tambah Data" — isi form, simpan
5. Data lokal muncul di list dengan badge "Lokal" dan tombol edit/hapus
6. Map menampilkan marker hijau untuk data lokal
7. Edit data lokal — perubahan tersimpan dan list refresh
8. Hapus data lokal — muncul konfirmasi, data hilang
9. Login sebagai user biasa (role `user`) — tombol CRUD tidak tampil, data tetap bisa dilihat
