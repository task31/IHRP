# IHRP Deployment Reference

> Full deployment knowledge base for hr.matchpointegroup.com.
> Read by: CLAUDE.md session init, `.claude/commands/deploy.md` slash command, Cursor ihrp-deploy.mdc rule.
> The executable deploy script is `deploy.py` at the project root.

---

## Server Quick Reference

| Item | Value |
|---|---|
| **App URL** | https://hr.matchpointegroup.com |
| **Host** | sh00858.bluehost.com |
| **cPanel user** | `rbjwhhmy` |
| **Home dir** | `/home2/rbjwhhmy/` |
| **Git repo (server)** | `/home2/rbjwhhmy/repositories/IHRP` |
| **Deploy target** | `/home2/rbjwhhmy/public_html/hr/` |
| **Document root** | `/home2/rbjwhhmy/public_html/hr/public` |
| **PHP CLI** | `/opt/cpanel/ea-php83/root/usr/bin/php` |
| **DB name / user** | `matchpo3_ihrp` |
| **SSH password** | `Vape13578!` |
| **cPanel port** | `2083` |

---

## Three Non-Negotiable Rules

1. **Never overwrite protected files** — `.env`, `storage/app/uploads/`, `storage/app/templates/timesheet_template.xlsx`, `public/storage` symlink. After every deploy, run `--step verify-env`.
2. **Migrations are manual and deliberate** — Always run `--step migrate-status` first. Show the pending list. Wait for explicit "yes" from Raf before running `migrate --force`.
3. **Test gate** — 107 tests, 259 assertions, 0 failures. Regression = rollback immediately.

---

## Using deploy.py

```bash
pip install paramiko requests   # one-time setup

python deploy.py                          # Full interactive deploy
python deploy.py --step migrate-status    # Check pending migrations (always run first)
python deploy.py --step deploy            # Trigger cPanel git deploy only
python deploy.py --step verify-env        # Verify .env survived
python deploy.py --step storage-link      # Verify/recreate public/storage symlink
python deploy.py --step safety-checks     # Check @vite, @livewireScripts, PHP handler
python deploy.py --step run-migrations    # Run pending migrations (confirmation gate)
python deploy.py --step clear-cache       # Rebuild config/route/view cache
python deploy.py --step tail-log          # Tail last 50 lines of laravel.log
python deploy.py --step smoke             # HTTP smoke test
```

---

## Deploy Decision Tree

```
What changed?
├── New migration files?        → step migrate-status → show list → step run-migrations
├── New .env keys?              → SFTP .env update BEFORE triggering cPanel deploy
├── composer.json changed?      → rebuild vendor/ locally, commit, then deploy
├── Blade layouts changed?      → step safety-checks (checks @vite + @livewireScripts)
└── PayrollParseService changed? → test all 3 AM Excel formats after deploy
```

---

## Known Issues

### Issue 1 — Apache 404 (DNS Mismatch) 🔴
**Symptom:** Site returns Apache 404 with double-error message.  
**Cause:** `hr` DNS A record pointing to wrong server.  
**Fix:** Update `hr` A record in Bluehost Zone Editor → Business Hosting IP. Propagation 1–4h.

### Issue 2 — PHP 500 (Wrong .htaccess Handler) 🔴
**Symptom:** PHP files served as text or 500.  
**Cause:** Wrong `AddHandler` directive. Must be `application/x-httpd-ea-php83___lsphp`.  
**Fix:** `deploy.py --step safety-checks` verifies this. Do NOT change the handler line.

### Issue 3 — App Crashes Post-Deploy (.env Wiped) 🔴
**Symptom:** `No application encryption key has been specified` immediately after deploy.  
**Cause:** `.cpanel.yml` does `cp -R web/. public_html/hr/` — overwrites everything.  
**Fix:** Restore `.env` from `.env.production.example`. **Always run `--step verify-env` after deploy.**

### Issue 4 — All Pages 500 (@vite Directive) 🔴
**Symptom:** `Vite manifest not found` in Laravel log.  
**Cause:** `@vite()` in layouts. No Node.js/Vite on Bluehost.  
**Fix:** Remove `@vite()`. It was removed in commit `d255873`. Never add it back.

### Issue 5 — Blank Payroll Charts (Dual Alpine) 🟡
**Symptom:** Payroll page loads but all charts are empty. Livewire flashes and disappears.  
**Cause:** `@livewireScripts` loads Alpine internally, conflicting with CDN Alpine.  
**Fix:** Must be `@livewireScriptConfig`. Never revert to `@livewireScripts`.

### Issue 6 — Migration Fails on Fresh DB (Timestamp Order) 🟡
**Symptom:** `Unknown column 'hours' in 'payroll_consultant_entries'` on fresh `migrate`.  
**Cause:** Migrations `060326` and `093156` used `->after('hours')` before the `hours` column existed.  
**Fix:** Applied in commit `f2f0de0`. Always test `migrate:fresh` locally before deploying new migrations.

### Issue 7 — Zero Payroll Entries After Upload 🟡
**Symptom:** Payroll consultant entries = 0 for Harsono or Dimarumba after upload.  
**Cause:** PayrollParseService bugs with multi-format XLSX (year detection, tier label regex, break vs continue).  
**Fix:** All fixed. If it recurs after a PayrollParseService change, test all 3 AM files.

### Issue 8 — Ancient Dates in Payroll DB 🟡
**Symptom:** Dashboard shows years 0019, 0209, 2002, 2010.  
**Cause:** PhpSpreadsheet misread numeric cells as Excel serial dates.  
**Fix (production one-time):**
```sql
DELETE FROM payroll_records WHERE YEAR(check_date) < 2015 AND user_id = 7;
```

### Issue 9 — Uploads 404 (Missing Storage Symlink) 🟡
**Symptom:** File uploads succeed but files are inaccessible.  
**Fix:** `python deploy.py --step storage-link`

### Issue 10 — Wrong Margin Numbers (am_earnings Corruption) 🟡
**Symptom:** AM Earnings = Agency Revenue. Gross Profit = $0.  
**Cause:** Earlier uploads used wrong formula (raw column D, not column D × commission%).  
**Fix:** Re-upload all 3 AM Excel files. Click "Recompute Margins" in admin upload modal.  
**Prevention:** Always read `BUSINESS_MODEL.md` before touching payroll calculations.

### Issue 11 — Blade Directive in HTML Attribute (500) 🔴
**Symptom:** Specific page returns 500 PHP ParseError.  
**Cause:** `@can`/`@if` inside HTML attribute value — Blade skips it, leaving unclosed PHP `if`.  
**Fix:** Replace with `{{ auth()->user()?->can('role') ? x : y }}`.

### Issue 12 — cPanel `VersionControlDeployment/create` rejects request (repository_root) 🟡
**Symptom:** `VersionControl/retrieve` succeeds, but deployment trigger returns `Provide the "repository_root" argument` and `.cpanel.yml` tasks never run.  
**Cause:** UAPI `VersionControlDeployment/create` on this host expects a `repository_root` (or equivalent) parameter; `deploy.py` currently sends only `repository`.  
**Fix:** Run `python deploy.py --step ssh-deploy` (mirrors `.cpanel.yml` over SSH), or extend `step_cpanel_deploy()` to pass the argument cPanel expects.  
**Prevention:** After `diagnose`, if `--step deploy` fails at create, use `ssh-deploy` without retrying blindly.

---

## .cpanel.yml Tasks (what runs on every deploy)

```yaml
- mkdir -p /home2/rbjwhhmy/public_html/hr
- cp -R /home2/rbjwhhmy/repositories/IHRP/web/. /home2/rbjwhhmy/public_html/hr/
- composer install --no-dev --optimize-autoloader
- php artisan config:cache
- php artisan route:cache
- php artisan view:cache
- php artisan timesheets:generate-template
```

---

## Protected Files (Never Overwrite)

```
/home2/rbjwhhmy/public_html/hr/.env
/home2/rbjwhhmy/public_html/hr/storage/app/uploads/
/home2/rbjwhhmy/public_html/hr/storage/app/templates/timesheet_template.xlsx
/home2/rbjwhhmy/public_html/hr/public/storage   ← symlink
```

---

## Key Artisan Commands (server)

```bash
# Full path required:
/opt/cpanel/ea-php83/root/usr/bin/php /home2/rbjwhhmy/public_html/hr/artisan <cmd>

migrate:status          # Always run first
migrate --force         # Run pending (confirm with Raf first)
migrate:rollback        # Emergency rollback
storage:link            # Recreate symlink
config:cache / route:cache / view:cache
```

---

## Infrastructure Map

| Service | Provider | Notes |
|---|---|---|
| hr.matchpointegroup.com | Bluehost Business Hosting (`rbjwhhmy`) | ← deploy target |
| matchpointegroup.com | Google Cloud | main WordPress — do NOT touch |
| Email | GoDaddy | email only — do NOT touch |
| DNS Zone | Bluehost Zone Editor | only `hr` A record is ours |
| Old WordPress Plus (`matchpo3`) | Bluehost (expired) | dead — ignore |

---

## Post-Deploy Checklist

- [ ] `.env` intact: APP_ENV=production, APP_DEBUG=false
- [ ] `public/storage` symlink exists
- [ ] config/route/view caches rebuilt
- [ ] Pending migrations ran
- [ ] `/login` returns 200
- [ ] Payroll charts render (not blank)
- [ ] No errors in `storage/logs/laravel.log`

---

## Post-First-Payroll-Upload Actions (one-time, production)

1. Delete orphaned Dimarumba records: `DELETE FROM payroll_records WHERE YEAR(check_date) < 2015 AND user_id = 7`
2. Enter bill_rates on Consultants page (inline editing)
3. Click "Recompute Margins" in admin upload modal
4. Re-upload all 3 AM Excel files if am_earnings looks wrong
