# Picking up Vantage on a new machine

Everything needed to go from a bare laptop to a running stack, plus the state
of the project and the decisions already made so none of it has to be worked
out twice.

---

## 0. Before you leave the old laptop

Do these in order. The first one is not optional.

**Push everything.** The repository is the only thing that travels. Anything
uncommitted or unpushed does not exist on the new machine.

```bash
cd /d D:\CVs\vantage
git status
git push
```

`git status` must say *nothing to commit, working tree clean* **and** *Your
branch is up to date with 'origin/main'*. If it says "ahead by N commits",
push again.

**Carry the secrets across by hand.** They are deliberately not in the repo
and never will be. Move them through a password manager — not email, not chat,
not a file in the repo.

| Value | Needed for | Have it yet? |
|---|---|---|
| `ANTHROPIC_API_KEY` | Scoring. Nothing works without it. | Not set yet |
| `ADZUNA_APP_ID` / `ADZUNA_APP_KEY` | Adzuna source (optional) | Not set yet |
| `JOOBLE_API_KEY` | Jooble source (optional) | Not set yet |
| `RAPIDAPI_KEY` | JSearch source (optional) | Not set yet |
| `MAIL_PASSWORD` | Digest email via Resend (optional) | Not set yet |
| `INBOUND_MAIL_SECRET` | Job-alert mail webhook (optional) | Not set yet |

Only the first is required. Every source with a blank key simply disables
itself, and the ATS board adapters need no credentials at all.

**Things that live outside the repo** and won't come with the clone: your CV
PDF in `D:\CVs\`, and the career brief at
<https://claude.ai/code/artifact/e7dbae34-7500-45c4-95bc-a9542bbf9f28>.

---

## 1. What the new machine actually needs

Two things:

- **Docker Desktop** — on Windows Home this requires the WSL2 backend, since
  Hyper-V isn't available on Home editions
- **Git**

That's the whole list. PHP, Composer, Node, PostgreSQL and Redis all run in
containers. You do not install any of them.

Optional but worth having: the **GitHub CLI** (`gh`) for pushing without
fighting credential prompts, and an editor.

---

## 2. Install, in this order

**1.** Install WSL2 from an **Administrator** terminal:

```bash
wsl --install
```

**2.** Restart the machine. Use **Restart**, not *Shut down* then power on —
Windows Fast Startup makes a shutdown a partial hibernate, and pending
optional-component installs sometimes don't complete.

**3.** Install Docker Desktop:

```bash
winget install -e --id Docker.DockerDesktop
```

**4.** Launch Docker Desktop from the Start menu. The first run takes a couple
of minutes while it builds its WSL distros. Wait for the whale icon to read
*Engine running*.

**5.** Open a **new** terminal — `docker` will not be on the PATH of one opened
earlier — and confirm:

```bash
docker --version
```

---

## 3. Get the code

The repository is **public** at <https://github.com/Shakeelkhan8/vantage>, on
the personal `Shakeelkhan8` account. Public means anyone can read it: never
commit an API key, your CV, or personal contact details.

```bash
gh auth login
```

Sign in as **Shakeelkhan8**, not the Elityx account. If both end up
authenticated, `gh auth switch --user Shakeelkhan8`.

```bash
git clone https://github.com/Shakeelkhan8/vantage.git
```

**Then set the commit identity again.** Repo-local git config is not stored in
the repository, so a fresh clone falls back to whatever global identity the new
machine has — which is how work-email commits end up on a personal project:

```bash
git -C vantage config user.email "shakeelkhan4147@gmail.com"
```

---

## 4. Environment file

```bash
copy /Y .env.example .env
```

The defaults already point at the compose service names, so the only value you
must supply is `ANTHROPIC_API_KEY`. Fill the rest in as you enable each source.

`.env` is gitignored and must stay that way.

---

## 5. Start it

```bash
docker compose up -d --build
```

First build is 3–6 minutes. Then generate the application key:

```bash
docker compose exec app php artisan key:generate
```

A fresh clone has no `vendor/`, and the dev stack bind-mounts your source over
the image's copy. The entrypoint detects this and runs `composer install`
inside the app container on first boot — so the first `up` takes an extra
minute and the worker and scheduler wait for it. That is expected, not a fault.

`make help` lists the shortcuts. Everything runs through the containers:

```bash
docker compose exec app php artisan migrate
```

---

## 6. Verify

| Check | Command or URL | Expected |
|---|---|---|
| Containers up | `docker compose ps` | app, worker, scheduler, postgres, redis, vite all running |
| App responds | <http://localhost:8000> | Laravel welcome page |
| Queue dashboard | <http://localhost:8000/horizon> | Horizon, status active |
| Vite | <http://localhost:5173> | dev server responds |
| Database | `docker compose exec postgres psql -U vantage -d vantage -c '\dt'` | table list, no connection error |
| Redis | `docker compose exec redis redis-cli ping` | `PONG` |
| Logs clean | `docker compose logs --tail=50` | no fatal errors |

If the app container restarts in a loop, `docker compose logs app` is the first
place to look — the entrypoint logs every step it takes.

---

## 7. Where the project actually is

**Two commits. P0 is partially done.** Nothing has been run yet — Docker was
never installed on the old laptop, so **the container stack has never been
built or started.** Treat your first `docker compose up` as its first real
test and expect to fix something.

Built:

- Laravel 13.29 / PHP 8.4, Livewire 4.4, Horizon 5.48, Anthropic SDK 0.44
- Dev and production compose stacks, FrankenPHP runtime, entrypoint, Makefile
- `config/vantage.php` — model routing, batch flag, monthly budget ceiling,
  per-model pricing table
- `App\Sources\Contracts\JobSource`, `RawPosting`, `SourceCursor`

Not built yet, and the rest of P0:

- Workspace tenancy — migrations, `workspace_user` pivot, global scope,
  `current_workspace_id()`
- Auth
- Profile / CV ingest with versioning. The versioning matters: it is the
  scoring cache key, so getting it wrong means re-paying for every score.

After that, P1 is the source adapters, starting with the ATS boards.
`docs/PLAN.md` has the full phase breakdown.

---

## 8. Decisions already made

Don't re-litigate these; the reasoning is here so you don't have to.

**FrankenPHP, not nginx + php-fpm.** Caddy and PHP in one process. Four app
containers instead of seven, which is the difference between comfortable and
swapping on a small VPS.

**Livewire, not a separate Next.js frontend.** v1 is roughly 85% queue-driven
backend and six screens of ranked lists. A second deployable buys nothing at
that size.

**Go is v2, deliberately.** Extract ingestion and scoring into a Go service
against the same Postgres once v1 works. The point is not that it exists, but
that there is a measured, defensible reason the hot path moved.

**Composer's platform is pinned** in `composer.json` to the container's PHP,
with `ext-pcntl` and `ext-posix` declared. Horizon requires pcntl, which does
not exist on Windows PHP, so installs fail without this. Consequence:
`php artisan horizon` will not run on a Windows host — only in the container.
That's the intended workflow anyway.

**Cost control is structural, not a setting.** Stage-1 filtering is pure SQL
and PHP and removes ~90% of postings before a model sees them; scoring goes
through the Batch API at half price; scores cache on
`job_fingerprint + profile_version`; a monthly ceiling is checked before
dispatch. Bulk runs on Haiku 4.5, the daily shortlist on Sonnet 5. Both are
config values — raise them when the budget allows.

**No scraping evasion.** The adapter layer accepts any source, and a
LinkedIn/Indeed slot is documented. What is not in this repo is proxy
rotation, fingerprint spoofing or CAPTCHA solving. On a public repo shown to
employers, that is a liability rather than a feature.

---

## 9. Gotchas already hit

Each of these cost time once. They shouldn't cost it twice.

- **`docker` isn't on the PATH** of a terminal opened before Docker Desktop was
  installed. Open a new one.
- **Run compose from the project directory.** `C:\Windows\System32` has no
  compose file.
- **Windows *Restart*, not *Shut down*.** Fast Startup can leave optional
  Windows components half-installed.
- **`npm ci` needs `package-lock.json`.** It's committed; keep it that way or
  the image build fails outright.
- **Vite needs `usePolling`.** inotify events don't cross a Windows bind mount,
  so without it file watching silently does nothing and HMR looks broken.
- **A fresh clone has no `vendor/`,** and the bind mount hides the image's.
  The entrypoint handles it; just expect a slow first boot.
- **Repo-local git config doesn't survive a clone.** Re-set `user.email`.
