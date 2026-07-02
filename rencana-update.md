# Rencana Update: Hermes AI Agent Integration

## Ringkasan

Menambahkan integrasi dengan **Hermes Agent** — AI agent yang secara otomatis mencari berita terkait **JIBOM**, **KBRN**, dan **WAN Teror** melalui cron per jam, lalu menginput/mengupdate data insiden ke gegana-map via API.

---

## 1. Sumber Berita Baru: `ai_agent`

### 1.1 Enum `news_source` diperluas

Saat ini `news_source` hanya punya nilai `offline`. Tambahkan:

| Nilai      | Label                                    |
| ---------- | ---------------------------------------- |
| `offline`  | Offline (input manual)                   |
| `online`   | Online (input manual dari sumber daring) |
| `ai_agent` | AI Agent (Hermes)                        |

### 1.2 Yang perlu diubah

- **Migration**: Ubah kolom `news_source` dari `string('news_source', 10)` jadi cukup `string('news_source')` (atau `varchar(20)`) untuk menampung nilai yang lebih panjang.
- **Model** (`JibomIncident`, `KBRNIncident`, `WanTerorIncident`): Tambahkan validasi `Rule::in(['offline', 'online', 'ai_agent'])` di controller.
- **Frontend form** (`Form.vue` di tiap insiden): Dropdown news_source tambahkan opsi "AI Agent".

---

## 2. Case Development — Pelacakan Perkembangan Kasus

### 2.1 Kebutuhan

Saat Hermes menemukan berita yang **sama** (dideteksi dari `news_url`), jangan buat insiden baru. Sebaliknya, tambahkan **entri perkembangan** ke insiden yang sudah ada.

### 2.2 Tabel Baru: `case_developments`

| Kolom           | Tipe                   | Keterangan                     |
| --------------- | ---------------------- | ------------------------------ |
| `id`            | bigint PK              |                                |
| `incident_type` | varchar(20)            | `jibom` / `kbrn` / `wan_teror` |
| `incident_id`   | bigint FK              | ID insiden terkait             |
| `title`         | varchar(255)           | Judul perkembangan             |
| `description`   | text                   | Isi perkembangan               |
| `source_url`    | varchar(2048) nullable | URL berita sumber              |
| `reported_at`   | timestamp              | Tanggal kejadian dilaporkan    |
| `created_at`    | timestamp              |                                |
| `updated_at`    | timestamp              |                                |

### 2.3 Migration

```php
// database/migrations/xxxx_xx_xx_000008_create_case_developments_table.php
Schema::create('case_developments', function (Blueprint $table) {
    $table->id();
    $table->string('incident_type', 20); // 'jibom', 'kbrn', 'wan_teror'
    $table->unsignedBigInteger('incident_id');
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('source_url', 2048)->nullable();
    $table->timestamp('reported_at')->nullable();
    $table->timestamps();

    $table->index(['incident_type', 'incident_id']);
});
```

### 2.4 Model: `CaseDevelopment`

```php
// app/Models/CaseDevelopment.php
class CaseDevelopment extends Model
{
    protected $fillable = [
        'incident_type', 'incident_id',
        'title', 'description', 'source_url', 'reported_at',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
    ];
}
```

### 2.5 Relasi di Model Insiden

Tambahkan relasi polimorfik atau manual ke masing-masing model (`JibomIncident`, `KBRNIncident`, `WanTerorIncident`):

```php
// Di JibomIncident.php (dan serupa di KBRNIncident, WanTerorIncident)
public function developments(): HasMany
{
    return $this->hasMany(CaseDevelopment::class, 'incident_id')
        ->where('incident_type', 'jibom')
        ->orderByDesc('reported_at');
}
```

---

## 3. API untuk Hermes Agent

### 3.1 Endpoint

| Method | Endpoint                                  | Fungsi                                |
| ------ | ----------------------------------------- | ------------------------------------- |
| `POST` | `/api/hermes/incidents`                   | Cari/cek & input insiden              |
| `POST` | `/api/hermes/incidents/{id}/developments` | Tambah perkembangan kasus             |
| `GET`  | `/api/hermes/incidents/check`             | Cek apakah artikel sudah ada (by URL) |

### 3.2 Authentication

Gunakan **API token** sederhana via header:

```
Authorization: Bearer {HERMES_API_TOKEN}
```

Token disimpan di `.env`:

```
HERMES_API_TOKEN=xxx-secret-token-xxx
```

Middleware verifikasi token dibuat custom: `app/Http/Middleware/AuthenticateHermesAgent.php`

### 3.3 Route (di `routes/api.php`)

Buat file route baru `routes/api.php` (register di `bootstrap/app.php` jika belum ada):

```php
Route::prefix('hermes')->middleware('hermes.auth')->group(function () {
    Route::post('/incidents', [HermesIncidentController::class, 'upsert']);
    Route::get('/incidents/check', [HermesIncidentController::class, 'check']);
    Route::post('/incidents/{id}/developments', [HermesCaseDevelopmentController::class, 'store']);
});
```

### 3.4 Controller: `HermesIncidentController`

#### `upsert` — Buat atau Update Insiden

**Request body:**

```json
{
    "incident_type": "jibom",
    "title": "Ledakan di Jakarta Pusat",
    "description": "Terjadi ledakan di kawasan...",
    "news_url": "https://example.com/berita/123",
    "news_source": "ai_agent",
    "latitude": -6.2088,
    "longitude": 106.8456,
    "province_id": "31",
    "regency_id": "3171",
    "district_id": "317101",
    "village_id": "3171011001",
    "incident_category": "ledakan",
    "finding_type": "temuan",
    "reported_at": "2026-07-02 10:00:00"
}
```

**Logika:**

1. Cek `news_url` — jika sudah ada di database insiden terkait → **skip** buat baru, langsung tambahkan ke `case_developments`.
2. Jika `news_url` belum ada → buat insiden baru.
3. Jika `news_url` sudah ada dan ada `development_title` / `development_description` → tambahkan ke `case_developments`.

**Response:**

```json
{
    "status": "created",
    "incident_id": 42,
    "message": "Insiden baru dibuat."
}
```

atau:

```json
{
    "status": "updated",
    "incident_id": 42,
    "development_id": 7,
    "message": "Perkembangan ditambahkan ke insiden yang sudah ada."
}
```

#### `check` — Cek Keberadaan

**Query params:** `?news_url=https://...`

**Response:**

```json
{
    "exists": true,
    "incident_id": 42,
    "incident_type": "jibom",
    "development_count": 3
}
```

### 3.5 Controller: `HermesCaseDevelopmentController`

#### `store` — Tambah Perkembangan

**Request body:**

```json
{
    "incident_type": "jibom",
    "title": "Update: Korban bertambah",
    "description": "Jumlah korban kini menjadi 5 orang...",
    "source_url": "https://example.com/berita/123-update",
    "reported_at": "2026-07-03 08:00:00"
}
```

**Response:**

```json
{
    "status": "created",
    "development_id": 8
}
```

---

## 4. Deduplikasi Artikel

### 4.1 Strategi

- **Primary key matching**: `news_url` — jika URL sama, dianggap artikel yang sama.
- **Fallback**: Bisa ditambahkan hash dari judul + tanggal untuk deteksi berita yang sama dari sumber berbeda (future).

### 4.2 Update Migration

Kolom `news_url` sudah ada di ketiga tabel insiden. Perlu **unique index** untuk mempercepat lookup:

```php
// Migration: add unique index on news_url (nullable, where not null)
$table->unique('news_url', 'jibom_incidents_news_url_unique');
```

Karena kolom nullable, gunakan partial unique index (MySQL 8.0+ via virtual column atau cukup index biasa untuk lookup cepat).

---

## 5. Cron Job di Hermes Agent

### 5.1 Spesifikasi

- **Frekuensi**: Setiap 1 jam
- **Job**: Cari berita terkait keyword JIBOM, KBRN, WAN Teror dari sumber berita / news API
- **Flow tiap cycle**:
    1. Query news API / scrape sumber berita dengan keyword: `bom`, `ledakan`, `radiasi`, `nuklir`, `bahan kimia`, `teror`, `teroris`
    2. Untuk tiap artikel yang ditemukan:
        - Panggil `GET /api/hermes/incidents/check?news_url=...`
        - Jika `exists: false` → panggil `POST /api/hermes/incidents` untuk buat insiden baru
        - Jika `exists: true` dan artikel berisi info baru → panggil `POST /api/hermes/incidents/{id}/developments`
    3. Log hasil ke file / monitoring

### 5.2 Konfigurasi Cron (Hermes Agent)

```cron
0 * * * * /path/to/hermes-agent/bin/hermes scan-news --categories=jibom,kbrn,wan-teror >> /var/log/hermes-news.log 2>&1
```

---

## 6. Checklist Pekerjaan

### Fase 1: Database & Model (Backend)

- [ ] Migration: ubah `news_source` column size (dari 10 ke 30)
- [ ] Migration: buat tabel `case_developments`
- [ ] Migration: tambah index pada `news_url` di ketiga tabel insiden
- [ ] Model: `CaseDevelopment`
- [ ] Relasi `developments()` di `JibomIncident`, `KBRNIncident`, `WanTerorIncident`

### Fase 2: API

- [ ] Middleware `AuthenticateHermesAgent`
- [ ] Controller `HermesIncidentController` (`upsert`, `check`)
- [ ] Controller `HermesCaseDevelopmentController` (`store`)
- [ ] Route file `routes/api.php`
- [ ] Register `api.php` di `bootstrap/app.php`
- [ ] `.env` + config: `HERMES_API_TOKEN`

### Fase 3: Frontend

- [ ] Tambah opsi "AI Agent" di dropdown `news_source` pada form JIBOM/KBRN/WanTeror
- [ ] Tambah section "Perkembangan Kasus" di halaman detail/view insiden (timeline)
- [ ] Tampilkan `news_source` label di tabel index

### Fase 4: Hermes Agent (di repo terpisah)

- [ ] Setup cron scheduler (per jam)
- [ ] Integrasi news API / scraping
- [ ] Logic: deduplikasi → upsert
- [ ] Logic: deteksi berita baru → tambah development
- [ ] Logging & monitoring

---

## 7. Catatan

- API token disimpan di `.env`, tidak di-commit ke repository.
- Rate limiting bisa ditambahkan nanti jika diperlukan.
- `case_developments` bersifat append-only (tidak ada edit/delete via API Hermes, hanya via admin panel).
