---
name: ponytail-debt
description: >
  Harvest every `ponytail:` comment in the codebase into a debt ledger, so the
  deliberate shortcuts and deferrals ponytail leaves behind get tracked instead
  of rotting into "later means never". Use when the user says "ponytail debt",
  "what did ponytail defer", "list the shortcuts", "ponytail ledger", or
  "what did we mark to do later". One-shot report, changes nothing.
---

# Ponytail Debt

## Description

Every deliberate ponytail shortcut is marked with a `ponytail:` comment naming
its ceiling and upgrade path. This skill collects them into one ledger so a
deferral can't quietly become permanent.

## When to use

Use when the user:
- Says "ponytail debt", "what did ponytail defer"
- Says "list the shortcuts", "ponytail ledger"
- Says "what did we mark to do later"
- Wants an overview of all intentional simplifications in the codebase

## Instructions

### Scan:
Grep the repo for comment markers (skip `node_modules`, `.git`, `vendor`,
build output):

```
grep -rnE '(#|//|<!--) ?ponytail:' .
```

Each hit is one ledger row. The comment prefix keeps prose that merely mentions
the convention out of the ledger.

### Output format:
One row per marker, grouped by file:

```
<file>:<line>, <what was simplified>.
  ceiling: <the limit named>
  upgrade: <the trigger to revisit>
```

The convention is `ponytail: <ceiling>, <upgrade path>`, so pull the ceiling
and the trigger straight from the comment.

### Rot risk:
Flag any `ponytail:` comment that names no upgrade path or trigger with a
`no-trigger` tag — those are the ones that silently rot.

End with: `<N> markers, <M> with no trigger.`

If nothing found: `No ponytail: debt. Clean ledger.`

### Boundaries:
- Reads and reports only, changes nothing.
- To persist it, ask the user and write the ledger to a file (e.g., `PONYTAIL-DEBT.md`).
- One-shot. "stop ponytail-debt" or "normal mode" to deactivate.

## Examples

```
src/cache.ts:42, single global lock for cache writes.
  ceiling: contention under >100 concurrent writes
  upgrade: per-key locks if throughput matters

src/parser.ts:88, O(n²) scan over tokens.
  ceiling: input >10k tokens
  upgrade: switch to Map-based lookup if parse time exceeds 50ms
  [no-trigger] ← rot risk: no measurable trigger named

api/routes.ts:15, naive rate limiter: counter in memory, no TTL.
  ceiling: multi-process deploys
  upgrade: Redis-backed sliding window when deploying >1 instance

12 markers, 1 with no trigger.
```
