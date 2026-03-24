# /deploy — IHRP Production Deploy Agent

You are the IHRP deployment agent. When this command runs:

1. Read `DEPLOY.md` at the project root fully before doing anything
2. Ask Raf what changed (or identify it from context) — new migrations? .env keys? composer?
3. Execute the steps below using `deploy.py`

---

## Standard deploy sequence

```bash
# Always start here — show Raf the pending migration list
python deploy.py --step migrate-status

# Push code and trigger cPanel
git push origin master
python deploy.py --step deploy

# Verify .env survived (CRITICAL — cp -R can wipe it)
python deploy.py --step verify-env

# Run pending migrations (will ask for confirmation before running)
python deploy.py --step run-migrations

# Check for errors
python deploy.py --step tail-log

# Smoke test
python deploy.py --step smoke
```

Or run the full interactive flow: `python deploy.py`

---

## Non-negotiable rules

1. **Always run `--step migrate-status` first** and show Raf the list before running anything
2. **Always run `--step verify-env` after deploy** — .cpanel.yml's cp -R can wipe .env
3. **107 tests, 259 assertions** — regression = rollback

---

## All available steps

```bash
python deploy.py --step migrate-status    # Check pending migrations
python deploy.py --step deploy            # Trigger cPanel git deploy
python deploy.py --step verify-env        # Verify .env is intact
python deploy.py --step storage-link      # Verify/recreate public/storage symlink
python deploy.py --step safety-checks     # Check @vite, @livewireScripts, PHP handler
python deploy.py --step run-migrations    # Run pending (asks confirmation)
python deploy.py --step clear-cache       # Rebuild config/route/view cache
python deploy.py --step tail-log          # Last 50 lines of laravel.log
python deploy.py --step smoke             # HTTP smoke test
```

---

## Critical known issues (full list in DEPLOY.md)

| Symptom | Cause | Fix |
|---|---|---|
| Apache 404 | DNS A record wrong | Update `hr` A record in Bluehost Zone Editor |
| App crashes post-deploy | `.env` wiped by cp -R | `--step verify-env`, restore from .env.production.example |
| All pages 500 | `@vite()` in layouts | Remove @vite — no Node.js on Bluehost |
| Blank payroll charts | `@livewireScripts` not `@livewireScriptConfig` | Fix app.blade.php |
| migrate fails on fresh DB | `->after()` timestamp order bug | Test migrate:fresh locally first |
