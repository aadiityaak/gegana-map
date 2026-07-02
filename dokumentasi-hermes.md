# Dokumentasi: Integrasi Hermes AI Agent ↔ gegana-map

## Ringkasan

Hermes Agent adalah AI agent yang berjalan via cron setiap jam, mencari berita terkait JIBOM, KBRN, dan WAN Teror, lalu mengirim data ke gegana-map melalui REST API.

---

## 1. Konfigurasi

### 1.1 Environment Variables (gegana-map)

Di file `.env` server gegana-map:

```env
HERMES_API_TOKEN=token-rahasia-anda
```

### 1.2 Environment Variables (Hermes Agent)

```env
GEGANA_API_BASE=https://dev.pusdatagegana.my.id/api/hermes
GEGANA_API_TOKEN=token-rahasia-anda
```

---

## 2. Authentication

Semua request ke API harus menyertakan header:

```
Authorization: Bearer {HERMES_API_TOKEN}
Content-Type: application/json
```

---

## 3. API Endpoints

### 3.1 Cek Keberadaan Artikel

Cek apakah artikel dengan URL tertentu sudah ada di database.

```
GET /api/hermes/incidents/check
```

**Query Parameters:**

| Param           | Tipe   | Wajib | Keterangan                     |
| --------------- | ------ | ----- | ------------------------------ |
| `news_url`      | string | Ya    | URL artikel berita             |
| `incident_type` | string | Ya    | `jibom` / `kbrn` / `wan_teror` |

**Contoh Request:**

```bash
curl -X GET \
  "https://dev.pusdatagegana.my.id/api/hermes/incidents/check?news_url=https://berita.example.com/ledakan-jakarta&incident_type=jibom" \
  -H "Authorization: Bearer xxx-token-xxx"
```

**Response (tidak ditemukan):**

```json
{
    "exists": false
}
```

**Response (ditemukan):**

```json
{
    "exists": true,
    "incident_id": 42,
    "incident_type": "jibom",
    "development_count": 3
}
```

---

### 3.2 Buat / Update Insiden

Membuat insiden baru. Jika `news_url` sudah ada, insiden baru **tidak** dibuat, melainkan ditambahkan sebagai perkembangan.

```
POST /api/hermes/incidents
```

**Request Body:**

| Field               | Tipe   | Wajib | Keterangan                                       |
| ------------------- | ------ | ----- | ------------------------------------------------ |
| `incident_type`     | string | Ya    | `jibom` / `kbrn` / `wan_teror`                   |
| `news_url`          | string | Tidak | URL artikel berita (digunakan untuk deduplikasi) |
| `title`             | string | Tidak | Judul berita / insiden                           |
| `description`       | string | Tidak | Isi berita / deskripsi                           |
| `latitude`          | number | Tidak | Koordinat lintang                                |
| `longitude`         | number | Tidak | Koordinat bujur                                  |
| `province_id`       | string | Tidak | Kode provinsi (2 digit)                          |
| `regency_id`        | string | Tidak | Kode kabupaten (4 digit)                         |
| `district_id`       | string | Tidak | Kode kecamatan (6 digit)                         |
| `village_id`        | string | Tidak | Kode desa (10 digit)                             |
| `incident_category` | string | Tidak | Kategori insiden (default: `ancaman`)            |
| `finding_type`      | string | Tidak | Jenis temuan                                     |
| `reported_at`       | string | Tidak | Tanggal kejadian (ISO 8601)                      |

**Contoh Request (insiden baru):**

```bash
curl -X POST https://dev.pusdatagegana.my.id/api/hermes/incidents \
  -H "Authorization: Bearer xxx-token-xxx" \
  -H "Content-Type: application/json" \
  -d '{
    "incident_type": "jibom",
    "news_url": "https://berita.example.com/ledakan-jakarta",
    "title": "Ledakan Bom di Jakarta Pusat",
    "description": "Terjadi ledakan di kawasan Thamrin pukul 10:00 WIB. Polisi mengamankan TKP.",
    "latitude": -6.186486,
    "longitude": 106.823154,
    "province_id": "31",
    "regency_id": "3171",
    "district_id": "317101",
    "village_id": "3171011001",
    "incident_category": "ledakan",
    "finding_type": "bom-rakitan",
    "reported_at": "2026-07-02T10:00:00+07:00"
  }'
```

**Response (insiden baru dibuat):**

```json
{
    "status": "created",
    "incident_id": 43,
    "message": "Insiden baru dibuat."
}
```

**Response (artikel sudah ada → ditambahkan sebagai perkembangan):**

```json
{
    "status": "updated",
    "incident_id": 42,
    "development_id": 8,
    "message": "Perkembangan ditambahkan ke insiden yang sudah ada."
}
```

**Response (artikel sudah ada & tidak ada info baru):**

```json
{
    "status": "skipped",
    "incident_id": 42,
    "message": "Artikel sudah ada. Tidak ada perkembangan baru."
}
```

---

### 3.3 Tambah Perkembangan Kasus

Menambahkan entri perkembangan ke insiden yang sudah ada.

```
POST /api/hermes/incidents/{id}/developments
```

| Field           | Tipe   | Wajib | Keterangan                     |
| --------------- | ------ | ----- | ------------------------------ |
| `incident_type` | string | Ya    | `jibom` / `kbrn` / `wan_teror` |
| `title`         | string | Ya    | Judul perkembangan             |
| `description`   | string | Tidak | Isi perkembangan               |
| `source_url`    | string | Tidak | URL sumber berita              |
| `reported_at`   | string | Tidak | Tanggal laporan                |

**Contoh Request:**

```bash
curl -X POST https://dev.pusdatagegana.my.id/api/hermes/incidents/42/developments \
  -H "Authorization: Bearer xxx-token-xxx" \
  -H "Content-Type: application/json" \
  -d '{
    "incident_type": "jibom",
    "title": "Update: Korban bertambah menjadi 5 orang",
    "description": "RS Cipto melaporkan 5 korban luka-luka, 2 di antaranya kritis.",
    "source_url": "https://berita.example.com/update-ledakan-jakarta",
    "reported_at": "2026-07-02T15:30:00+07:00"
  }'
```

**Response:**

```json
{
    "status": "created",
    "development_id": 9
}
```

---

### 3.4 Kirim Log Aktivitas

Hermes Agent mengirim log aktivitas realtime ke gegana-map. Log ditampilkan di halaman `/hermes-logs` pada aplikasi.

```
POST /api/hermes/logs
```

**Request Body:**

| Field      | Tipe   | Wajib | Keterangan                       |
| ---------- | ------ | ----- | -------------------------------- |
| `type`     | string | Ya    | Tipe log (lihat daftar di bawah) |
| `title`    | string | Ya    | Judul singkat                    |
| `message`  | string | Tidak | Detail pesan                     |
| `metadata` | object | Tidak | Data tambahan (key-value)        |

**Tipe log yang tersedia:**

| Type           | Keterangan                                               |
| -------------- | -------------------------------------------------------- |
| `scan_start`   | Hermes mulai scanning cycle                              |
| `scan_done`    | Scanning cycle selesai                                   |
| `search_start` | Mulai mencari berita                                     |
| `search_done`  | Pencarian selesai (sertakan `results_count` di metadata) |
| `summarizing`  | Sedang merangkum berita                                  |
| `summary_done` | Rangkuman selesai                                        |
| `insert`       | Insiden baru dibuat                                      |
| `update`       | Insiden existing diupdate                                |
| `skip`         | Berita dilewati (sudah ada)                              |
| `error`        | Terjadi error                                            |
| `info`         | Informasi umum                                           |

**Contoh Request:**

```bash
# Hermes mulai scanning
curl -X POST https://dev.pusdatagegana.my.id/api/hermes/logs \
  -H "Authorization: Bearer xxx-token-xxx" \
  -H "Content-Type: application/json" \
  -d '{"type": "scan_start", "title": "Cycle #42 dimulai", "metadata": {"cycle": "42"}}'

# Pencarian selesai
curl -X POST https://dev.pusdatagegana.my.id/api/hermes/logs \
  -H "Authorization: Bearer xxx-token-xxx" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "search_done",
    "title": "Pencarian selesai: 2 berita ditemukan",
    "message": "Keyword: bom, ledakan, teroris. Sumber: 5 portal berita.",
    "metadata": {"results_count": "2", "category": "jibom"}
  }'

# Rangkuman
curl -X POST https://dev.pusdatagegana.my.id/api/hermes/logs \
  -H "Authorization: Bearer xxx-token-xxx" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "summary_done",
    "title": "Berita dirangkum: Ledakan di Jakarta Pusat",
    "message": "Kesimpulan: Ledakan bom rakitan di Thamrin, 3 korban luka. Koordinat: -6.186, 106.823."
  }'

# Insert insiden
curl -X POST https://dev.pusdatagegana.my.id/api/hermes/logs \
  -H "Authorization: Bearer xxx-token-xxx" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "insert",
    "title": "Insiden baru JIBOM dibuat",
    "metadata": {"incident_id": "43", "news_url": "https://berita.example.com/ledakan-jakarta"}
  }'

# Update development
curl -X POST https://dev.pusdatagegana.my.id/api/hermes/logs \
  -H "Authorization: Bearer xxx-token-xxx" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "update",
    "title": "Perkembangan ditambahkan ke insiden #42",
    "message": "Update dari sumber: https://berita.example.com/update-ledakan",
    "metadata": {"incident_id": "42", "development_id": "9"}
  }'
```

**Response:**

```json
{
    "status": "created",
    "log_id": 127
}
```

---

## 4. Flow Kerja Hermes Agent

```
┌─────────────┐     ┌──────────────┐     ┌─────────────┐
│  Cron Job   │────▶│  Cari Berita │────▶│  Loop tiap  │
│  (per jam)  │     │  (News API)  │     │   artikel   │
└─────────────┘     └──────────────┘     └──────┬──────┘
                                                 │
                    ┌────────────────────────────┘
                    ▼
          ┌────────────────────┐
          │ GET /incidents/    │
          │ check?news_url=... │
          └────────┬───────────┘
                   │
          ┌────────┴────────┐
          │ exists: false   │ exists: true
          ▼                 ▼
   ┌──────────────┐  ┌─────────────────┐
   │ POST         │  │ Ada info baru?  │
   │ /incidents   │  └────┬────────┬───┘
   │ (buat baru)  │       │ Ya     │ Tidak
   └──────────────┘       ▼        ▼
                   ┌────────────┐ ┌────────┐
                   │ POST       │ │ Skip   │
                   │ /develop-  │ └────────┘
                   │ ments      │
                   └────────────┘
```

---

## 5. Kategori Insiden per Tipe

### JIBOM

| incident_category | finding_type                                                       |
| ----------------- | ------------------------------------------------------------------ |
| `ancaman`         | —                                                                  |
| `temuan`          | `bom-militer` / `bom-rakitan` / `bom-ikan` / `petasan` / `lainnya` |
| `ledakan`         | —                                                                  |

### KBRN

| incident_category | finding_type                                                                                                                                                                                                       |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `ancaman`         | —                                                                                                                                                                                                                  |
| `temuan`          | `kimia` / `biologi` / `radioaktif` / `nuklir` / `amoniak` / `gas-beracun` / `klorin` / `asam-sulfat` / `asam-nitrat` / `racun-tikus` / `senyawa-organik` / `sianida` / `logam-berat` / `bahan-radiasi` / `lainnya` |
| `ledakan`         | —                                                                                                                                                                                                                  |

### WAN Teror

| incident_category      |
| ---------------------- |
| `napiter`              |
| `ex-napiter`           |
| `jaringan-terorisme`   |
| `bullying-perundungan` |
| `aksi-teror`           |

---

## 6. Pencarian Berita (Keyword)

Hermes Agent mencari berita dengan keyword:

| Kategori  | Keyword                                                                                                            |
| --------- | ------------------------------------------------------------------------------------------------------------------ |
| JIBOM     | `bom`, `ledakan bom`, `bahan peledak`, `pengeboman`, `temuan bom`, `ancaman bom`                                   |
| KBRN      | `kimia berbahaya`, `biologi`, `radioaktif`, `nuklir`, `radiasi`, `gas beracun`, `klorin`, `amoniak`, `bahan kimia` |
| WAN Teror | `teroris`, `terorisme`, `napiter`, `aksi teror`, `jaringan teror`, `radikal`, `bullying`                           |

---

## 7. Contoh Pseudocode Hermes Agent

```python
import requests
import os

API_BASE = os.getenv("GEGANA_API_BASE")
API_TOKEN = os.getenv("GEGANA_API_TOKEN")
HEADERS = {"Authorization": f"Bearer {API_TOKEN}", "Content-Type": "application/json"}

KEYWORDS = {
    "jibom": ["bom", "ledakan", "bahan peledak", "pengeboman"],
    "kbrn": ["kimia", "radioaktif", "nuklir", "radiasi", "gas beracun"],
    "wan_teror": ["teroris", "terorisme", "aksi teror", "napiter"],
}

def scan_news():
    for category, keywords in KEYWORDS.items():
        articles = fetch_news_articles(keywords)  # implementasi sendiri
        for article in articles:
            # Cek apakah artikel sudah ada
            check = requests.get(
                f"{API_BASE}/incidents/check",
                params={"news_url": article["url"], "incident_type": category},
                headers=HEADERS,
            )

            if check.json()["exists"]:
                # Cek apakah ada info baru → tambah development
                if article["is_update"]:
                    requests.post(
                        f"{API_BASE}/incidents/{check.json()['incident_id']}/developments",
                        json={
                            "incident_type": category,
                            "title": article["title"],
                            "description": article.get("description"),
                            "source_url": article["url"],
                            "reported_at": article.get("published_at"),
                        },
                        headers=HEADERS,
                    )
            else:
                # Buat insiden baru
                requests.post(
                    f"{API_BASE}/incidents",
                    json={
                        "incident_type": category,
                        "news_url": article["url"],
                        "title": article["title"],
                        "description": article.get("description"),
                        "incident_category": classify_incident(article),
                        "finding_type": classify_finding(article),
                        "reported_at": article.get("published_at"),
                    },
                    headers=HEADERS,
                )
```

---

## 8. Error Codes

| HTTP Status | Keterangan                                                      |
| ----------- | --------------------------------------------------------------- |
| 201         | Insiden / development berhasil dibuat                           |
| 401         | Token tidak valid atau tidak disertakan                         |
| 422         | Validasi gagal (field wajib kosong / format salah)              |
| 500         | Server misconfigured (`HERMES_API_TOKEN` tidak diset di server) |

---

## 9. Catatan

- `news_url` adalah primary deduplication key. Dua artikel dengan URL yang sama dianggap berita yang sama.
- `case_developments` bersifat append-only via API. Tidak bisa edit/delete melalui Hermes.
- Semua koordinat, wilayah, dan foto bersifat opsional — Hermes bisa input partial data.
- `news_source` akan otomatis diset ke `ai_agent` untuk semua insiden dari Hermes.
