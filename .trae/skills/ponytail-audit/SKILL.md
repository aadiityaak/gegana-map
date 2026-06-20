---
name: ponytail-audit
description: >
  Whole-repo audit for over-engineering. Like ponytail-review, but scans the
  entire codebase instead of a diff: a ranked list of what to delete, simplify,
  or replace with stdlib/native equivalents. Use when the user says "audit this
  codebase", "audit for over-engineering", "what can I delete from this repo",
  "find bloat", or requests a ponytail audit.
---

# Ponytail Audit

## Description

Ponytail-review, but repo-wide. Scan the whole tree instead of a diff. Rank
findings biggest cut first. One-shot report, does not apply fixes.

## When to use

Use when the user:
- Says "audit this codebase", "audit for over-engineering"
- Says "what can I delete from this repo", "find bloat"
- Requests a ponytail audit or full-codebase simplicity review

## Instructions

### Tags (same as ponytail-review):
- **`delete:`** — dead code, unused flexibility, speculative feature. Replacement: nothing.
- **`stdlib:`** — hand-rolled thing the standard library ships. Name the function.
- **`native:`** — dependency or code doing what the platform already does. Name the feature.
- **`yagni:`** — abstraction with one implementation, config nobody sets, layer with one caller.
- **`shrink:`** — same logic, fewer lines. Show the shorter form.

### Hunt targets:
- Dependencies the stdlib or platform already ships
- Single-implementation interfaces
- Factories with one product
- Wrappers that only delegate
- Files exporting one thing
- Dead flags and config
- Hand-rolled stdlib functions

### Audit process:
1. Scan the entire repository (skip `node_modules`, `.git`, `vendor`, build outputs).
2. Look for each hunt target category.
3. Rank findings: biggest potential cut first.
4. Output one line per finding, ranked: `<tag> <what to cut>. <replacement>. [path]`.
5. End with: `net: -<N> lines, -<M> deps possible.`
6. If nothing to cut: `Lean already. Ship.`

### Boundaries:
- Scope: over-engineering and complexity only.
- Correctness bugs, security holes, and performance are explicitly out of scope — route them to a normal review pass.
- Lists findings, applies nothing. One-shot report.

## Examples

```
stdlib: hand-rolled leftPad(). String.prototype.padStart(), 0 deps. [src/utils/string.ts]
yagni: IUserRepository with single MongoUserRepository impl. Inline it. [src/repositories/]
delete: FeatureFlags class, all flags default to true, no overrides in config. Nothing replaces it. [src/config/features.ts]
native: axios imported for one GET. fetch(), 0 deps. [src/api/client.ts]

net: -340 lines, -2 deps possible.
```
