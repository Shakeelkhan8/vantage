# Vantage

A job-hunting engine that ingests postings from many sources, deduplicates
them, filters out the noise deterministically, and scores what survives against
a candidate profile using Claude.

Built to answer one question every morning: **which five things are worth
applying to today, and why.**

## Why it exists

Job boards optimise for volume. Fifty mediocre listings a day is not a pipeline,
it is a chore. Vantage inverts that — broad ingestion, aggressive filtering, and
a scored shortlist small enough to act on.

## How it works

```
Sources → Ingestion → Dedupe → Stage-1 filter → Stage-2 scoring → Inbox + digest
```

**Sources** are adapters behind a single `JobSource` contract. Company ATS
boards (Greenhouse, Lever, Ashby, Workable) need no credentials and give the
cleanest signal — point the watchlist at the companies you actually want.
Aggregator APIs, parsed job-alert email, and manual paste fill the gaps.

**Stage one is free.** Work authorisation, seniority band, stack, salary floor,
posting age and blocklist are pure SQL and PHP. Roughly nine in ten postings
never reach a model.

**Stage two is batched.** Survivors are scored through the Batch API at half
price, cached on `job_fingerprint + profile_version` so the same role syndicated
across five boards is scored once and a re-run costs nothing. A hard monthly
ceiling is checked before dispatch, not after the bill arrives.

That combination is what keeps the running cost in single-digit dollars.

## Stack

Laravel 13 · PHP 8.4 · PostgreSQL 18 · Redis 8 · Horizon · Livewire 4 ·
FrankenPHP · Docker

## Running it

Docker is the only prerequisite. Nothing else needs to be installed — PHP,
Postgres, Redis and Node all live in containers.

```bash
docker compose up -d --build
docker compose exec app php artisan key:generate
```

Copy the example environment file to the real one first; the defaults already
point at the compose service names, so the only value you must supply is your
Anthropic API key.

The app is on <http://localhost:8000>, Vite on 5173, Horizon at `/horizon`.
`make help` lists everything else.

## Deploying

Single VPS, one command:

```bash
docker compose -f compose.prod.yaml up -d --build
```

Set `APP_DOMAIN` to a hostname pointed at the box and Caddy provisions TLS on
first boot. Postgres is not published to the host in production, and every
service carries a memory ceiling so one runaway worker cannot take the box down.

## Licence

MIT.
