# Build plan

Scope for v1 is **find & score** only. Apply, track and prep are deliberately
out — see *Not in v1* at the bottom.

## Architecture

```
Sources → Ingestion → Dedupe → Stage-1 filter → Stage-2 scoring → Inbox + digest
```

### 1. Sources

One adapter per source behind `App\Sources\Contracts\JobSource`. The pipeline
never knows which is which, so adding a source never means touching dedupe,
filtering or scoring.

| Adapter | Credentials | Notes |
|---|---|---|
| `AtsBoardSource` | none | Greenhouse, Lever, Ashby, Workable. Driven by the company watchlist. Highest signal, zero noise — build first. |
| `AdzunaSource` | free tier | Broad coverage, EU/US strong |
| `JoobleSource` | free key | |
| `RemotiveSource` | none | Remote-only |
| `RemoteOkSource` | none | Remote-only |
| `ArbeitnowSource` | none | EU-weighted |
| `JSearchSource` | RapidAPI free tier | Aggregates several boards |
| `MailboxSource` | shared secret | Parses forwarded job-alert email. How Gulf and LinkedIn coverage arrives without scraping either. |
| `ManualSource` | none | Paste a URL or raw description |
| `HeadlessSource` | none | Playwright, for boards that permit it |

**Scraping policy.** The adapter layer accepts any source. What is not built
here is detection evasion — proxy rotation, fingerprint spoofing, CAPTCHA
solving. A LinkedIn/Indeed adapter slot exists and is documented; filling it is
a deliberate decision, not something that arrives by accident.

### 2. Ingestion

Horizon queues, per-source cursors, exponential backoff. Every fetch writes a
`source_runs` row with counts and errors. Raw payloads are stored immutably in
`raw_postings`, so a parser bug is replayable rather than a data loss.

### 3. Dedupe

Canonical fingerprint over normalised (company, title, location), plus
`pg_trgm` trigram similarity on the description body with a GIN index. One role
syndicated across five boards collapses to one `jobs` row with five source links.

### 4. Stage-1 filter — free

Pure SQL and PHP: work-authorisation compatibility, seniority band, stack match,
salary floor, posting age, company blocklist. Removes roughly 90% of volume
before a token is spent. This is the cost model.

### 5. Stage-2 scoring — LLM

Survivors only, scored against the 7-factor rubric (compensation 25, skill fit
20, interview probability 15, career growth 15, location fit 10, employer
quality 10, skill leverage 5), returned as structured JSON.

- Bulk on `claude-haiku-4-5`, dispatched through the **Batch API** at 50% off
- Top N per day on `claude-sonnet-5` for deeper analysis
- Prompt caching on the stable profile + rubric prefix
- Cached on `job_fingerprint + profile_version` — never score the same job twice
- Hard monthly ceiling checked **before** dispatch
- Actual tokens and USD written to each `scores` row

### 6. Quality filter

Rule signals first — no corporate domain, compensation >3× market band,
pay-to-apply, MLM vocabulary, contact only via WhatsApp/Telegram, domain
registered recently. LLM confirmation second. Flagged and surfaced, never
silently deleted: a false positive that hides a real job is worse than one
dismissed in two seconds.

## Multi-tenancy

`workspaces` + `workspace_user` pivot, global query scope, `current_workspace_id()`.
Single real user at first, but the isolation is real from day one.

## Data model

`workspaces` · `users` · `workspace_user` · `profiles` (versioned — scoring
cache keys on this) · `companies` (watchlist flag, ATS slug) · `sources` ·
`source_runs` · `raw_postings` (immutable) · `jobs` · `job_fingerprints` ·
`scores` (rubric breakdown, model, token cost) · `flags` · `digests`

## Phases

| | Work | Estimate |
|---|---|---|
| **P0** | Skeleton, Docker, tenancy, auth, profile/CV ingest and versioning | 1 weekend |
| **P1** | Source contract, ATS watchlist, 3 aggregator APIs, manual paste | 2 weekends |
| **P2** | Dedupe, stage-1 filter, stage-2 scoring, cost caps, quality flags | 2 weekends |
| **P3** | Inbox UI, daily digest email, tuning loop | 1–2 weekends |

Roughly 60–80 hours. **If P2 slips past two weekends, stop and go apply to
things.** The tool is not the point.

**Done** means a daily email and a web inbox showing 5–15 deduplicated,
filtered, scored opportunities with the rubric breakdown and an
APPLY / MAYBE / SKIP call.

## v2 — the deliberate one

Extract ingestion and scoring into a **Go service** against the same Postgres,
queue in between. Real workload, measured before/after. This is the point of
the whole exercise as a portfolio artifact: not that it exists, but that there
is a defensible reason the hot path moved.

## Not in v1

CV tailoring and ATS keyword analysis, cover letters, the application tracker
and follow-up scheduling, interview prep, company dossiers, negotiation prep.
All are in the wider design; none ship until find & score works.
