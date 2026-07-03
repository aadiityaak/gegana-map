---
name: gegana-hermes-integration
description: |
  Integrasi Hermes Agent ↔ gegana-map — cron job tiap 4 jam: fetch Google News RSS, crawl artikel asli via browser, analisis AI (lokasi + klasifikasi), geocode via maps skill, POST ke REST API (pusdatagegana.my.id).
  Use when user mentions gegana, pusdatagegana, JIBOM, KBRN, WAN Teror, insiden teror, or article-to-incident pipeline.
tags: [gegana, pusdatagegana, jibom, kbrn, terorisme, insiden, news-api, integration]
---

# gegana-map Integration

Integrasi Hermes Agent ke **gegana-map** — sistem monitoring insiden JIBOM, KBRN, dan WAN Teror milik Pusdata Gegana Korps Brimob Polri.

## Environment Variables

```env
GEGANA_API_BASE=https://dev.pusdatagegana.my.id/api/hermes
GEGANA_API_TOKEN=token-rahasia-anda
```

## Authentication

Semua request wajib:

```
Authorization: Bearer {GEGANA_API_TOKEN}
Content-Type: application/json
```

## API Endpoints

### Cek Keberadaan Artikel

```
GET {GEGANA_API_BASE}/incidents/check?news_url=URL&incident_type=jibom|kbrn|wan_teror
```

Response: `{"exists": false}` atau `{"exists": true, "incident_id": 42, "development_count": 3}`

### Buat Insiden Baru

```
POST {GEGANA_API_BASE}/incidents
```

Body fields: `incident_type` (*wajib), `news_url`, `title`, `description`, `latitude`, `longitude`, `province_id` (2 digit), `regency_id` (4 digit), `district_id` (6 digit), `village_id` (10 digit), `incident_category`, `finding_type`, `reported_at` (ISO 8601).

Semua field lokasi opsional — backend menerima NULL (sudah diperbaiki per 2 Jul 2026).

Response: `{"status": "created", "incident_id": N}`, `{"status": "updated", ...}` (news_url sudah ada → otomatis jadi development), atau `{"status": "skipped"}` (tidak ada info baru).

### Tambah Perkembangan

```
POST {GEGANA_API_BASE}/incidents/{id}/developments
```

Body: `incident_type`, `title` (*wajib), `description`, `source_url`, `reported_at`.

### Kirim Log Aktivitas

```
POST {GEGANA_API_BASE}/logs
```

Body: `type` (*wajib: scan_start|scan_done|search_start|search_done|summarizing|summary_done|insert|update|skip|error|info), `title` (*wajib), `message`, `metadata` (object).

## Kategori Insiden

### JIBOM

| incident_category | finding_type |
|---|---|
| `ancaman` | — |
| `temuan` | `bom-militer` / `bom-rakitan` / `bom-ikan` / `petasan` / `lainnya` |
| `ledakan` | — |

### KBRN

| incident_category | finding_type |
|---|---|
| `ancaman` | — |
| `temuan` | `kimia` / `biologi` / `radioaktif` / `nuklir` / `amoniak` / `gas-beracun` / `klorin` / `asam-sulfat` / `asam-nitrat` / `racun-tikus` / `senyawa-organik` / `sianida` / `logam-berat` / `bahan-radiasi` / `lainnya` |
| `ledakan` | — |

### WAN Teror

| incident_category |
|---|
| `napiter` / `ex-napiter` / `jaringan-terorisme` / `bullying-perundungan` / `aksi-teror` |

Default jika ragu: JIBOM=`ancaman`, KBRN=`ancaman`, WAN_TEROR=`aksi-teror`.

## Sumber Berita

**Primary: Google News RSS** — gratis, tanpa API key, coverage media Indonesia luas.

| Kategori | RSS URL |
|---|---|
| JIBOM | `https://news.google.com/rss/search?q=bom+ledakan&hl=id&gl=ID&ceid=ID:id` |
| KBRN | `https://news.google.com/rss/search?q=nuklir+radioaktif&hl=id&gl=ID&ceid=ID:id` |
| WAN Teror | `https://news.google.com/rss/search?q=teroris&hl=id&gl=ID&ceid=ID:id` |

Format: RSS 2.0 XML. Parse `<item>` → `<title>`, `<link>`, `<pubDate>`, `<source>`, `<description>`.

**Alternatif (butuh API key):**

| API | Status | Note |
|---|---|---|
| GDELT 2.0 | ❌ Timeout dari sumopod | Unreachable |
| GNews.io | ✅ Reachable | 100 req/day free |
| NewsAPI.org | ✅ Reachable | 100 req/day free |

## Batasan Area

**HANYA Indonesia.** Artikel luar negeri diabaikan. Filter dari teks artikel asli (bukan snippet):
- Ada nama kota/provinsi Indonesia → proses
- Luar negeri (Yunani, Ekuador, AS, dll) → **SKIP**
- Ambigu → tetap proses dengan koordinat Jakarta sebagai fallback

## Flow Kerja Lengkap

### Stage 1: Fetch (Python script)
Script `scripts/gegana-news-fetch.py` (bundled dalam skill):
1. Fetch RSS Google News per kategori
2. Parse XML, filter artikel >4 jam terakhir via `<pubDate>`
3. Cek dedup: `GET /incidents/check?news_url=...`
4. Output JSON — artikel `"status": "new"` siap dianalisis

### Stage 2: Crawl Artikel Asli (Browser — WAJIB)
Google News RSS `<link>` selalu ke `news.google.com/rss/articles/...`. Halaman ini JavaScript-redirect ke artikel asli. **Browser tools wajib** — curl/urllib tidak bisa follow JS redirect.

Untuk setiap artikel baru:
```
browser_navigate(google_url)  # auto-redirect ke artikel asli
browser_console("document.body.innerText.substring(0, 4000)")  # ekstrak teks
```

Hasil: teks 4000 karakter dari artikel CNBC/Detik/Kompas/dll asli.

### Stage 3: Analisis AI
Dari teks artikel asli:
1. **Ekstrak lokasi** — cari nama kota/kabupaten/provinsi Indonesia. Prioritas: nama spesifik > kabupaten > provinsi > Jakarta.
2. **Filter Indonesia** — jika lokasi luar negeri → SKIP.
3. **Klasifikasi** — tentukan `incident_category` dan `finding_type` (jika `temuan`).

### Stage 4: Geocoding (Koordinat)
```
python3 /home/ubuntu/.hermes/skills/productivity/maps/scripts/maps_client.py search "Nama Lokasi, Indonesia"
```
Ambil `lat`, `lon` dari hasil pertama. Fallback: kota → kabupaten → provinsi → Jakarta.

### Stage 4b: Geocoding (Wilayah ID) — WAJIB
Setelah dapat `lat`/`lon`, cari `province_id` + `regency_id` via reverse geocode + match API:

1. **Reverse geocode** koordinat via Nominatim:
   ```bash
   python3 -c "
   import json, urllib.request, time
   url = 'https://nominatim.openstreetmap.org/reverse?lat={LAT}&lon={LON}&format=json&addressdetails=1'
   time.sleep(1)
   req = urllib.request.Request(url, headers={'User-Agent': 'HermesAgent/1.0'})
   data = json.loads(urllib.request.urlopen(req).read())
   addr = data.get('address', {})
   print(json.dumps({'state': addr.get('state',''), 'county': addr.get('county',''), 'city': addr.get('city',''), 'district': addr.get('district','')}))
   "
   ```
   Key dari `address`: `state` (provinsi), `county`/`city` (kabupaten/kota), `district` (kecamatan).

2. **Match province**: `curl -s 'https://dev.pusdatagegana.my.id/api/wilayah/match?type=province&name=Jawa+Barat'`
3. **Match regency** (jika data wilayah lengkap — lihat pitfall "WilayahIndonesiaSeeder fallback dummy"): `curl -s 'https://dev.pusdatagegana.my.id/api/wilayah/match?type=regency&name=Kab+Bogor&parent_id=32'`
4. Sertakan `province_id` (+ `regency_id` jika tersedia) di POST body.

Endpoint match publik (no auth), tersedia sejak commit `0df3e21` di repo gegana-map. Detail lengkap: `references/wilayah-match-api.md`.

### Stage 5: POST ke gegana
Gunakan **curl** (bukan Python requests — lebih simpel, no dependency):
```bash
curl -s -X POST https://dev.pusdatagegana.my.id/api/hermes/incidents \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"incident_type":"wan_teror","news_url":"GOOGLE_URL","title":"...","description":"...","latitude":-6.3904,"longitude":106.6921,"province_id":"32","regency_id":"3201","incident_category":"napiter","reported_at":"..."}'
```
Field opsional (`finding_type`, `latitude`/`longitude`, `province_id`, `regency_id`, `district_id`, `village_id`) boleh dihapus dari JSON jika null. Tapi **selalu sertakan `province_id` + `regency_id` jika tersedia** — tanpa ini, alamat di gegana-map kosong meskipun deskripsi artikel jelas.

### Stage 6: Log & Laporan
- Kirim log ke `POST /logs` (type: `insert`, `skip`, `scan_start`, `scan_done`)
- Laporkan ringkasan ke Telegram: jumlah baru/total per kategori, skip LN

## Pitfalls & Lessons

- **NOT a bug: `incident_type` overwrite di HermesIncidentController line 77** — `'incident_type' => $validated['incident_category']` adalah BY DESIGN. DB gegana-map menyimpan kategori (`napiter`/`ledakan`/`ancaman`) di kolom `incident_type`, bukan broad type (`wan_teror`/`jibom`/`kbrn`). Jangan "perbaiki" ini.
- **Alamat/Wilayah kosong meskipun deskripsi jelas**: `province_id`/`regency_id` tidak dikirim oleh cron → `viewLocationLabel` di detail page kosong. Solusi: Stage 4b (reverse geocode → match wilayah) wajib dijalankan.
- **Koordinat tidak tampil di list view**: `WanTerorIncidentController::index` TIDAK me-SELECT `latitude`/`longitude`. Hanya detail page (`/wan-teror/{id}`) yang menampilkan koordinat + mini-map. Jangan panik kalau koordinat gak kelihatan di tabel — cek detail page dulu.
- **JS redirect**: Google News RSS URL tidak bisa di-crawl dengan curl/urllib. Redirect terjadi via JavaScript → **WAJIB pakai browser_navigate**. Tanpa browser, hanya dapat title + snippet RSS — tidak cukup untuk ekstrak lokasi akurat.
- **Source name tidak cukup**: User menolak nebak lokasi dari nama portal (Serambinews.com=Aceh) karena tidak selalu akurat. Wajib ambil dari teks artikel asli.
- **Maps client path**: `/home/ubuntu/.hermes/skills/productivity/maps/scripts/maps_client.py` (bukan `maps/scripts/`).
- **FK constraint bug (fixed)**: Awalnya province_id/regency_id gagal FK saat dikosongkan. Sudah diperbaiki — semua field lokasi nullable.
- **Route placement: `web.php` vs `api.php`**: Semua route di `routes/web.php` kena middleware `auth:verified` → redirect ke login jika diakses tanpa session cookie. Endpoint publik (seperti `/api/wilayah/match`) harus ditaruh di `routes/api.php` yang menggunakan stateless `api` middleware group. Jangan tambahkan route publik di `web.php` meskipun di luar `Route::middleware(['auth'])` block — tetap kena global middleware.
- **WilayahIndonesiaSeeder fallback dummy**: Seeder punya fallback `seedMinimalWilayah()` yang membuat data dummy ("Kabupaten Contoh XXX") jika file `database/wilayah/wilayah_indonesia.sql` tidak ditemukan atau path-nya salah. Seeder tidak error — hanya silent fallback. Akibat: `/api/wilayah/match` hanya bisa match **province** (38 provinsi real), tapi **regency/district/village** kosong. Verifikasi: test query `match?type=regency&name=Bogor` — harusnya tidak return "Contoh". Fix: pastikan file SQL ada di server (`ls database/wilayah/wilayah_indonesia.sql`) atau import langsung via `mysql < file.sql`. Untuk sementara, cron hanya kirim `province_id` saja.
- **Deduplication key**: `news_url` = Google RSS `<link>` URL. Gunakan konsisten untuk dedup.
- **Filter 4 jam**: Script hanya ambil artikel `<pubDate>` dalam 4 jam terakhir — hindari duplikat antar cycle.
- **Browser sequential**: browser_navigate harus sequential — tidak bisa paralel. Untuk 10 artikel ≈ 1-2 menit.

## Script & Referensi

| File | Path |
|---|---|
| Fetch script | `scripts/gegana-news-fetch.py` (bundled) |
| Maps client | `maps` skill (`productivity/maps`) — dependency eksternal |
| Cron prompt | `references/cron-prompt.md` |
| Wilayah Match API | `references/wilayah-match-api.md` |

## Error Codes

| Status | Arti |
|---|---|
| 201 | Berhasil dibuat |
| 401 | Token invalid/missing |
| 422 | Validasi gagal |
| 500 | Server misconfigured |

## Portability

Skill ini **self-contained** — bisa di-copy ke server/Hermes Agent lain. Tinggal:

```bash
cp -r gegana-hermes-integration ~/.hermes/skills/custom/
```

### Dependensi Eksternal
| Dependensi | Cara Install |
|---|---|
| `maps` skill | `hermes skills install maps` (untuk geocoding) |
| `GEGANA_API_TOKEN` | Set environment variable |
| Browser tools | Hermes Agent harus punya browser tools enabled |

### Struktur Skill
```
gegana-hermes-integration/
├── SKILL.md                          # Dokumentasi utama
├── scripts/
│   └── gegana-news-fetch.py          # Stage 1: fetch RSS + dedup
└── references/
    ├── cron-prompt.md                # Prompt untuk cron job
    └── wilayah-match-api.md          # Dokumentasi API match wilayah
```

Tanpa `maps` skill: geocoding gagal, tapi fetch + crawl + klasifikasi + POST tetap jalan (koordinat null).

### Setup di Server Baru
1. Copy skill folder
2. Install `maps` skill
3. Set `GEGANA_API_TOKEN` env var
4. Load skill + create cron job dengan prompt dari `references/cron-prompt.md`
