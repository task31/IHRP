# Phase 5 Plan — Deploy
_Created: 2026-03-20_
_Mode: SEQUENTIAL_

## Context

All four feature phases are complete and regression-passed. This phase ships the app to
`hr.matchpointegroup.com` on Bluehost Business Hosting. Two pre-deploy code fixes are
required before the first push: the `@vite()` directive must be removed (its assets are
already covered by CDN, and it will fail on Bluehost with no Node.js/build pipeline), and
`vendor/` must be committed to the repo (Bluehost's Composer CLI access is unreliable).

## Dependency

Requires [Phase 4] complete — QA gate must be passed before production deploy. ✅

Unlocks: App is live at hr.matchpointegroup.com. Project complete.

---

## Pre-Deploy Facts (read before starting)

| Item | Value |
|---|---|
| Hosting | Bluehost Business Hosting |
| Server IP | 50.6.53.175 |
| Target URL | https://hr.matchpointegroup.com |
| DNS | A record `hr → 50.6.53.175` in WordPress Plus cPanel |
| Deploy method | Bluehost cPanel Git Version Control |
| Post-deploy hooks | `.cpanel.yml` in repo root |
| Laravel root | `web/` (document root must point to `web/public/`) |
| PHP version | 8.2+ (Bluehost Business supports it) |
| Seeded admin | `admin@matchpointegroup.com` / `changeme123` — **change on first login** |

---

## To-Dos

### Step 1 — Code Pre-deploy Fixes (Cursor)

**Why:** `@vite()` will 500 on Bluehost (no Vite manifest). `vendor/` is gitignored so
Bluehost gets an empty vendor dir and every request 500s. Both must be fixed before push.

- [x] [Phase 5] Remove `@vite(['resources/css/app.css', 'resources/js/app.js'])` from
  `web/resources/views/layouts/app.blade.php`
  _(app.css = Tailwind directives only, already covered by Tailwind CDN in the same file)_
  _(app.js = Alpine npm init, already covered by Alpine CDN in the same file)_
- [x] [Phase 5] Remove `@vite([...])` from `web/resources/views/layouts/guest.blade.php`
  if present (same reason) _(Tailwind CDN added — guest had no CDN before)_
- [x] [Phase 5] Run `composer install --no-dev --optimize-autoloader` inside `web/`
- [x] [Phase 5] Remove `/vendor` line from `web/.gitignore`
- [x] [Phase 5] `git add web/vendor` — stage vendor directory for commit
- [x] [Phase 5] Create `.cpanel.yml` in repo root (see spec below)
- [x] [Phase 5] Create `web/.env.production.example` (see spec below)
- [x] [Phase 5] Commit: `feat: prepare Bluehost production deploy — remove @vite, commit vendor, add cpanel config` _(PM message)_
- [x] [Phase 5] Verify: `php artisan route:list` still works after changes; layouts render
  without errors locally

#### `.cpanel.yml` spec (create in repo root)

```yaml
---
deployment:
  tasks:
    - export DEPLOYPATH=/home2/rbjwhhmy/public_html/hr
    - /bin/mkdir -p $DEPLOYPATH
    - /bin/cp -R web/. $DEPLOYPATH/
    - /usr/local/bin/php $DEPLOYPATH/artisan config:cache
    - /usr/local/bin/php $DEPLOYPATH/artisan route:cache
    - /usr/local/bin/php $DEPLOYPATH/artisan view:cache
```

> **Note:** `rbjwhhmy` = Bluehost cPanel username. Raf to confirm exact username and update
> before first deploy. Migrations are intentionally NOT in `.cpanel.yml` — run manually.

#### `web/.env.production.example` spec (create in web/)

```
APP_NAME="IHRP"
APP_ENV=production
APP_KEY=          ← copy from local .env
APP_DEBUG=false
APP_URL=https://hr.matchpointegroup.com

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=          ← from Bluehost cPanel MySQL
DB_USERNAME=          ← from Bluehost cPanel MySQL
DB_PASSWORD=          ← from Bluehost cPanel MySQL

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=          ← from Settings page in current app
MAIL_PORT=          ← from Settings page in current app
MAIL_USERNAME=      ← from Settings page in current app
MAIL_PASSWORD=      ← from Settings page in current app
MAIL_FROM_ADDRESS=  ← from Settings page in current app
MAIL_FROM_NAME="IHRP"
```

---

### Step 2 — Bluehost cPanel Setup (Manual — Raf)

**Raf completes these steps in Bluehost cPanel. Claude provides instructions.**

- [ ] [Phase 5] Log into Bluehost cPanel
- [ ] [Phase 5] MySQL Databases wizard → create database (e.g. `rbjwhhmy_ihrp`) + user
  (e.g. `rbjwhhmy_ihrp`) + strong password → grant all privileges
- [ ] [Phase 5] Subdomains → create `hr.matchpointegroup.com` → set Document Root to
  `/home2/rbjwhhmy/public_html/hr/public` (the Laravel public/ dir)
  _(This is critical — pointing to `hr/` instead of `hr/public/` exposes the entire app)_
- [ ] [Phase 5] SSL/TLS → AutoSSL → run for `hr.matchpointegroup.com`
  (may take a few minutes to provision)

---

### Step 3 — Git Deploy Configuration (Raf + Claude)

- [ ] [Phase 5] cPanel → Git Version Control → Create Repository
  - Repository Path: `/home2/rbjwhhmy/repos/ihrp.git` (bare repo on server)
  - OR: clone from GitHub if Bluehost allows external clone
- [ ] [Phase 5] Locally: add Bluehost SSH remote
  ```bash
  git remote add bluehost ssh://rbjwhhmy@matchpointegroup.com/home2/rbjwhhmy/repos/ihrp.git
  ```
- [ ] [Phase 5] Test SSH connection: `ssh rbjwhhmy@matchpointegroup.com`
- [ ] [Phase 5] Confirm cPanel Git deploy path is set to `/home2/rbjwhhmy/public_html/hr`
  OR confirm `.cpanel.yml` copy task is correct

---

### Step 4 — First Push + Server .env (Raf + Claude via SSH)

- [ ] [Phase 5] `git push bluehost master` — triggers `.cpanel.yml` deploy
- [ ] [Phase 5] SSH into server → verify files landed:
  `ls /home2/rbjwhhmy/public_html/hr/`
- [ ] [Phase 5] SSH: copy .env.production.example → .env and fill in all values:
  ```bash
  cp /home2/rbjwhhmy/public_html/hr/.env.production.example \
     /home2/rbjwhhmy/public_html/hr/.env
  nano /home2/rbjwhhmy/public_html/hr/.env
  ```
- [ ] [Phase 5] Generate fresh APP_KEY if needed:
  `php /home2/rbjwhhmy/public_html/hr/artisan key:generate`

---

### Step 5 — Database Initialization (SSH)

Choose **Option A** (fresh production DB) or **Option B** (migrate real data).

**Option A — Fresh DB (recommended for first deploy)**
- [ ] [Phase 5] SSH: `php artisan migrate --force`
  (runs all 14 migrations on the empty Bluehost MySQL)
- [ ] [Phase 5] SSH: `php artisan db:seed`
  (creates admin@matchpointegroup.com / changeme123)

**Option B — Import local data**
- [ ] [Phase 5] Local: `mysqldump -u root ihrp_local > ihrp_export.sql`
- [ ] [Phase 5] Upload `ihrp_export.sql` to Bluehost via cPanel File Manager
- [ ] [Phase 5] cPanel phpMyAdmin → select `rbjwhhmy_ihrp` → Import → choose file

> **Recommendation:** Use Option A for production launch. Raf enters real client, consultant,
> and invoice data through the UI. This avoids importing local test data into production.

---

### Step 6 — Post-Deploy Commands (SSH)

- [ ] [Phase 5] `php artisan storage:link`
  (creates public/storage → storage/app/public symlink; required for W-9 + logo uploads)
- [ ] [Phase 5] Verify caches already ran via `.cpanel.yml`:
  `ls /home2/rbjwhhmy/public_html/hr/bootstrap/cache/`
  (should see `config.php`, `routes-v7.php`)
- [ ] [Phase 5] If caches failed, run manually:
  ```bash
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  ```

---

### Step 7 — Final Smoke Test (Raf)

Test all core flows against production URL `https://hr.matchpointegroup.com`.

**Admin role:**
- [ ] [Phase 5] Login → redirects to /dashboard ✓
- [ ] [Phase 5] Dashboard — stat cards load (will show zeros if fresh DB)
- [ ] [Phase 5] Clients — create a test client
- [ ] [Phase 5] Consultants — create a test consultant
- [ ] [Phase 5] Timesheets — upload timesheet template XLSX
- [ ] [Phase 5] Invoices — generate + preview PDF
- [ ] [Phase 5] Ledger — transactions visible
- [ ] [Phase 5] Reports — PDF + CSV download
- [ ] [Phase 5] Settings — save SMTP settings → send test email
- [ ] [Phase 5] Admin Users — create an AM user
- [ ] [Phase 5] Placements — create a placement
- [ ] [Phase 5] Calls — submit a call report

**AM role:**
- [ ] [Phase 5] Login → redirects to /placements (not dashboard)
- [ ] [Phase 5] Nav: only Calls + Placements visible
- [ ] [Phase 5] Placements: only own records visible
- [ ] [Phase 5] Dashboard: 403
- [ ] [Phase 5] Calls Report: 403

**Security:**
- [ ] [Phase 5] https:// works (SSL cert active)
- [ ] [Phase 5] http:// redirects to https://
- [ ] [Phase 5] APP_DEBUG=false — no stack traces shown on errors
- [ ] [Phase 5] Change admin password from `changeme123`

---

## Acceptance Criteria

- [ ] `https://hr.matchpointegroup.com` loads the login page
- [ ] Admin can log in and access all features
- [ ] AM role redirect + access restrictions work
- [ ] SSL certificate active (green lock)
- [ ] No 500 errors across all core pages
- [ ] `storage:link` in place (uploads work)
- [ ] APP_DEBUG=false confirmed in production
- [ ] Admin password changed from default

---

## Files Planned

| File | Action | Owner |
|---|---|---|
| `web/resources/views/layouts/app.blade.php` | Remove `@vite()` line | Cursor |
| `web/resources/views/layouts/guest.blade.php` | Remove `@vite()` line | Cursor |
| `web/.gitignore` | Remove `/vendor` line | Cursor |
| `web/vendor/` | Add to git (after composer install --no-dev) | Cursor |
| `.cpanel.yml` | Create in repo root | Cursor |
| `web/.env.production.example` | Create in web/ | Cursor |

_All other deploy steps are server-side (SSH/cPanel) — no code changes required._

---

## Rollback Plan

If the production deploy breaks:
1. SSH → `php artisan down` (maintenance mode)
2. `git push bluehost <previous-commit-hash>:master --force` to revert
3. `php artisan up` once stable

