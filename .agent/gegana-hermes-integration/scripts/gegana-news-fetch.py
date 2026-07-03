#!/usr/bin/env python3
"""Stage 1: Fetch Google News RSS. Output JSON for Hermes AI to analyze + POST."""
import os, sys, json, re, time
import xml.etree.ElementTree as ET
from urllib.request import Request, urlopen
from urllib.parse import quote
from urllib.error import HTTPError, URLError
from datetime import datetime, timezone, timedelta

API_BASE = os.environ.get("GEGANA_API_BASE", "https://dev.pusdatagegana.my.id/api/hermes")
API_TOKEN = os.environ.get("GEGANA_API_TOKEN", "")
MAX_PER_CAT = 30
HOURS_BACK = 4

CATEGORIES = {
    "jibom":     {"query": "bom ledakan",     "type": "jibom"},
    "kbrn":      {"query": "nuklir radioaktif","type": "kbrn"},
    "wan_teror": {"query": "teroris",          "type": "wan_teror"},
}

RSS_URL = "https://news.google.com/rss/search?q={query}&hl=id&gl=ID&ceid=ID:id"


def check_exists(news_url, incident_type):
    encoded = quote(news_url, safe="")
    url = f"{API_BASE}/incidents/check?news_url={encoded}&incident_type={incident_type}"
    req = Request(url)
    req.add_header("Authorization", f"Bearer {API_TOKEN}")
    try:
        with urlopen(req, timeout=10) as resp:
            return json.loads(resp.read())
    except:
        return {"exists": False}


def fetch_rss(query):
    url = RSS_URL.format(query=quote(query))
    req = Request(url, headers={"User-Agent": "HermesAgent/1.0"})
    try:
        with urlopen(req, timeout=20) as resp:
            root = ET.fromstring(resp.read())
    except Exception as e:
        return []

    cutoff = datetime.now(timezone.utc) - timedelta(hours=HOURS_BACK)
    articles = []
    for item in root.iter("item"):
        title = item.findtext("title", "").strip()
        link = item.findtext("link", "").strip()
        pub_date_str = item.findtext("pubDate", "").strip()
        description = item.findtext("description", "").strip()
        source_el = item.find("source")
        source_name = source_el.text if source_el is not None else ""

        pub_date = None
        if pub_date_str:
            try:
                pub_date = datetime.strptime(pub_date_str, "%a, %d %b %Y %H:%M:%S %Z").replace(tzinfo=timezone.utc)
            except:
                pass

        if pub_date and pub_date < cutoff:
            continue

        # Clean description: strip HTML tags from Google News snippet
        desc_clean = re.sub(r"<[^>]+>", " ", description).strip()
        desc_clean = re.sub(r"\s+", " ", desc_clean)

        articles.append({
            "title": title,
            "google_url": link,  # used as dedup key
            "pub_date": pub_date.isoformat() if pub_date else "",
            "source_name": source_name,
            "snippet": desc_clean[:500],
        })

    return articles[:MAX_PER_CAT]


def main():
    if not API_TOKEN:
        print(json.dumps({"error": "GEGANA_API_TOKEN not set"}))
        sys.exit(1)

    output = {"scanned_at": datetime.now().isoformat(), "categories": {}}

    for key, cfg in CATEGORIES.items():
        cat_output = {"articles": []}
        articles = fetch_rss(cfg["query"])

        for art in articles:
            existing = check_exists(art["google_url"], cfg["type"])
            if existing.get("exists"):
                art["status"] = "skipped"
                art["incident_id"] = existing.get("incident_id")
                art["development_count"] = existing.get("development_count", 0)
            else:
                art["status"] = "new"
            cat_output["articles"].append(art)
            typ = art["status"]
            print(f"  [{cfg['type']}] {typ}: {art['title'][:60]}", file=sys.stderr)

        cat_output["total"] = len(articles)
        cat_output["new"] = sum(1 for a in articles if a["status"] == "new")
        output["categories"][cfg["type"]] = cat_output

    print(json.dumps(output, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
