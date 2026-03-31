# IHRP — Internal HR Portal

Multi-user internal web app for Matchpointe Group, live at `hr.matchpointegroup.com`.
Migrates the single-user Electron payroll desktop app to a browser-based system with role-based access.

**Stack:** Laravel 13 · PHP 8.3 · Blade + Alpine.js · Livewire · MySQL · DomPDF
**Hosting:** Bluehost Business Hosting (cPanel, Apache/LiteSpeed)

---

## Roles

| Role | Access |
|---|---|
| `admin` | Full access — payroll uploads, recompute margins, user management, settings, backups |
| `account_manager` | Own payroll data, placements, daily call reports |

---

## Key Directories

| Path | Contents |
|---|---|
| `web/` | Laravel application root |
| `web/app/Http/Controllers/` | All controllers (one per module) |
| `web/app/Services/` | OvertimeCalculator, PayrollParseService, PdfService, etc. |
| `web/resources/views/` | Blade templates |
| `web/database/migrations/` | All 20+ migrations |
| `docs/` | Project documentation |
| `scripts/` | Utility scripts (invoice template, etc.) |
| `references/` | Archived plans, task history, rate data |

---

## Documentation

| File | Purpose |
|---|---|
| `PROJECT_CONTEXT.md` | Permanent project brief — what, why, who, stack decisions |
| `BUSINESS_MODEL.md` | Payroll calculation rules — read before touching earnings/margins |
| `CLAUDE.md` | Phase-by-phase build history |
| `DEVLOG.md` | Full decision + review log (append-only) |
| `PHASES.md` | Phase map and status |
| `TASKLIST.md` | Current open tasks |
| `DEPLOY.md` | Deployment runbook |
| `docs/sop.html` | **Staff SOP** — admin operations + payroll run procedure (open in browser) |

---

## Running Locally

```bash
cd web
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Default admin: `admin@matchpointegroup.com` / `changeme123`

---

## Deploying

```bash
python deploy.py --step deploy
```

See `DEPLOY.md` for full runbook.

---

## Tests

```bash
cd web && php artisan test
```

162 tests · 434 assertions · 0 failures _(as of Phase 11, 2026-03-30)_
