---
name: ponytail-help
description: >
  Quick-reference card for all ponytail modes, skills, and commands.
  One-shot display, not a persistent mode. Use when the user says
  "ponytail help", "what ponytail commands", or "how do I use ponytail".
---

# Ponytail Help

## Description

Display a reference card of all ponytail capabilities when invoked. One-shot —
do NOT change mode or persist anything.

## When to use

Use when the user:
- Says "ponytail help", "what ponytail commands", "how do I use ponytail"
- Wants an overview of available ponytail functionality

## Instructions

Display this reference card:

### Intensity Levels

| Level | Trigger | Behavior |
|-------|---------|----------|
| **Lite** | "ponytail lite" | Build what's asked, name the lazier alternative in one line. |
| **Full** | "ponytail" | The ladder enforced: YAGNI → stdlib → native → one line → minimum. Default. |
| **Ultra** | "ponytail ultra" | YAGNI extremist. Deletion before addition. Challenges requirements before building. |

Level sticks until changed or session end.

### Skills

| Skill | Trigger | What it does |
|-------|---------|--------------|
| **ponytail** | "ponytail" / "lazy mode" | Lazy mode itself. Simplest solution that works. |
| **ponytail-review** | "ponytail-review" / "review for over-engineering" | Over-engineering review: `L42: yagni: factory, one product. Inline.` |
| **ponytail-audit** | "ponytail-audit" / "audit codebase" | Repo-wide over-engineering audit. Ranked list of what to cut. |
| **ponytail-debt** | "ponytail-debt" / "list shortcuts" | Harvest all `ponytail:` comments into a debt ledger. |
| **ponytail-help** | "ponytail-help" / "ponytail help" | This reference card. |

### Deactivate

Say "stop ponytail" or "normal mode". Resume anytime by requesting ponytail again.

### More

Full docs + examples: https://github.com/DietrichGebert/ponytail
