# .agent — Hermes Agent Skills

Folder ini berisi skill untuk **Hermes Agent** (AI agent by Nous Research) yang terintegrasi dengan gegana-map.

## Struktur

```
.agent/
├── README.md                              # File ini
└── gegana-hermes-integration/             # Skill: integrasi Gegana ↔ Hermes
    ├── SKILL.md                           # Dokumentasi lengkap skill
    ├── scripts/
    │   └── gegana-news-fetch.py           # Stage 1: fetch RSS + dedup
    └── references/
        ├── cron-prompt.md                 # Prompt untuk cron job
        └── wilayah-match-api.md           # Dokumentasi API match wilayah
```

## Cara Pakai di Hermes Agent

```bash
# Copy skill ke Hermes Agent
cp -r .agent/gegana-hermes-integration ~/.hermes/skills/custom/

# Install dependency
hermes skills install maps

# Set environment variable
export GEGANA_API_TOKEN=token-rahasia-anda
```

## Keamanan

- ✅ **Tidak ada credential** di folder ini
- ✅ Token selalu via environment variable `GEGANA_API_TOKEN`
- ✅ Semua path relatif — tidak hardcode path server
