# Wilayah Match API

Endpoint: `GET /api/wilayah/match` (public, no auth)  
Repo: `github.com/aadiityaak/gegana-map` (commit `0df3e21`+)

## Query Params

| Param | Required | Description |
|-------|----------|-------------|
| `type` | yes | `province` / `regency` / `district` / `village` |
| `name` | yes | Partial or full name (LIKE `%name%`) |
| `parent_id` | no | Scope to parent (province_id for regency, regency_id for district, etc.) |

## Response

```json
{
  "matches": [
    {"id": "32", "name": "Jawa Barat"}
  ]
}
```

- Exact match returns single result
- No match returns `{"matches": []}`
- Invalid type returns 422

## Usage in Cron Flow

After reverse geocoding Nominatim response:

```python
import urllib.parse, json, urllib.request

def match_wilayah(name, wtype, parent_id=None):
    params = {"type": wtype, "name": name}
    if parent_id:
        params["parent_id"] = parent_id
    url = f"https://dev.pusdatagegana.my.id/api/wilayah/match?{urllib.parse.urlencode(params)}"
    req = urllib.request.Request(url, headers={"Accept": "application/json"})
    data = json.loads(urllib.request.urlopen(req).read())
    matches = data.get("matches", [])
    return matches[0]["id"] if matches else None
```

## Nominatim → Gegana Mapping

| Nominatim `address` key | Gegana `type` | Example |
|--------------------------|---------------|---------|
| `state` | province | "Jawa Barat" → "32" |
| `county` / `city` | regency | "Kab Bogor" → "3201" |
| `district` | district | "Gunung Sindur" → "3201260" |

**Gotcha:** Nominatim county names may include prefix ("Kabupaten Bogor" vs "Kab Bogor" vs "Bogor"). Try stripped versions if exact match fails.

## Test

```bash
# Province
curl "https://dev.pusdatagegana.my.id/api/wilayah/match?name=Jawa+Barat&type=province"
# → {"matches":[{"id":"32","name":"Jawa Barat"}]}

# Regency scoped to province
curl "https://dev.pusdatagegana.my.id/api/wilayah/match?name=Bogor&type=regency&parent_id=32"
# → {"matches":[...]}  (multiple Bogor entries, pick best match)
```
