# DEVLOG — [Project Name]

> Append-only audit trail. One file. Three blocks per phase.
> Claude Code writes 🏗️ and ✅ blocks. Cursor writes 🔨 blocks.
> No other notes files are created anywhere in this repo.
> Every todo references its phase with a [Phase X] prefix.

---

## Phase 0 | Scaffold + Auth
_Opened: 2026-03-19 | Closed: —_
_Mode: SEQUENTIAL_

### 🏗️ [ARCHITECT — Claude Code]
**Goal:** Create running Laravel 11 app with MySQL migrations (14 tables), role-based auth,
login page, and admin user management. Deployable skeleton — no Electron features ported yet.
**Mode:** SEQUENTIAL

**Dependency diagram:**
```
[Phase 0] → [Phase 1] → [Phase 2] → [Phase 3] → [Phase 4] → [Phase 5]
```

**Decisions made:**
- PHP + Laravel (not Next.js) — manager decision, enables free Bluehost Business Hosting
- Blade + Alpine.js frontend (not React) — pure PHP, no npm build pipeline
- Livewire for complex interactive pages (Timesheets, Placements)
- MySQL (Bluehost included) instead of PostgreSQL — Prisma not used
- No Railway needed — Bluehost covers everything at $0 extra
- Laravel Breeze for auth scaffolding (fastest path to working auth)
- dompdf for PDF generation (replaces pdfkit)

**Risks flagged:**
- Bluehost `.htaccess` / AllowOverride: Apache may ignore .htaccess on shared hosting → routes 404. Verify or contact Bluehost support before Phase 5.
- PHP version: confirm PHP 8.2+ available in cPanel before starting Phase 0.
- OT engine is a full rewrite (not a port) — highest regression risk. 116 PHPUnit tests are the safety net.
- dompdf produces different layout than pdfkit — PDF templates need visual comparison against original invoices.

**Files planned:**
- `app/Http/Controllers/AdminUserController.php`
- `app/Http/Middleware/RequireRole.php`
- `database/migrations/[14 files]`
- `database/seeders/DatabaseSeeder.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/admin/users/*.blade.php`
- `routes/web.php`

---

### 🔨 [BUILD — Cursor]
**Assigned workstream:** [Phase 1] | [Phase 1a] | [Phase 1b]

**Todos completed:**
- [x] [Phase 1] Create /src/auth/login.ts
- [x] [Phase 1] Build token refresh logic
- [ ] [Phase 1] Write auth tests — skipped (see deviations)

**Deviations from plan:**
- [What changed from the architect's plan and why — or "None"]

**Unplanned additions:**
- [Anything added that wasn't in phase-N-plan.md — or "None"]

**Files actually created/modified:**
- `/path/to/file.ts` ✅ (as planned)
- `/path/to/other.ts` ✅ (modified from plan)
- `/path/to/new.ts` ➕ (unplanned addition)
- `/path/to/skipped.ts` ❌ (skipped — reason)

---

### 🔨 [BUILD — Cursor] — Phase 0 completion _(appended 2026-03-19)_
**Assigned workstream:** [Phase 0]

**Todos completed:**
- [x] [Phase 0] Scaffold Laravel via `composer create-project` into `web/` (IHRP root not empty — subfolder per PM)
- [x] [Phase 0] Install Breeze (blade), Livewire, barryvdh/laravel-dompdf
- [x] [Phase 0] Alpine.js via CDN in `web/resources/views/layouts/app.blade.php`
- [x] [Phase 0] Configure `.env` / `.env.example` for MySQL (`ihrp_local`, root, empty password, `APP_URL`)
- [x] [Phase 0] Migrations for 14 Phase 0 domain tables from `Payroll/src/main/database.js` (money → `DECIMAL(12,4)`, ints → `boolean`); `users` extended with `role`, `consultant_id`, `active`
- [x] [Phase 0] `php artisan migrate` verified on `127.0.0.1:3306` (order fixes: consultants after clients, timesheets before invoices/daily hours; MySQL unique index name shortened on `timesheet_daily_hours`)
- [x] [Phase 0] Login branding (`web/resources/views/auth/login.blade.php`)
- [x] [Phase 0] `RequireRole` middleware + `role` alias in `web/bootstrap/app.php`; Gates `admin` / `account_manager` in `AppServiceProvider`
- [x] [Phase 0] `User` model `$fillable` / casts for role fields
- [x] [Phase 0] `DatabaseSeeder` — admin `admin@matchpointegroup.com` / `changeme123` / role `admin`
- [x] [Phase 0] `AdminUserController` + `admin/users` resource routes (`admin.users.*`) + Blade CRUD views + per-action `authorize('admin')`
- [x] [Phase 0] Shell layout: Tailwind CDN, sidebar placeholders, `@can('admin')` nav, flash messages

**Deviations from plan:**
- Scaffold path: `IHRP/web/` instead of `IHRP/.` (repo root had existing phase/docs + `.git`)
- Composer resolved **Laravel 13** (`laravel/laravel` v13.x) while phase text says “Laravel 11” — runtime stack is Laravel 13 + PHP 8.3
- `invoice_sequence`: plan fields `next_number`, `fiscal_year_start` (SQLite uses `current_number`, no fiscal column)
- `timesheet_daily_hours`: plan uses `day_of_week` string (+ unique with `week_number`); SQLite uses `day_index` 0–6
- `daily_call_reports` / `placements`: SQLite has no DDL — migrations left as minimal stubs (id + timestamps) pending Phase 3 spec
- Dashboard route: `verified` middleware removed so seeded admin can use app without email verification in Phase 0
- `.env.example`: DB/APP_URL keys present with **empty** values for safe commit; real values live in local `.env` only

**Unplanned additions:**
- Explicit `->names('admin.users')` on admin resource route
- `Consultant` Eloquent model (`web/app/Models/Consultant.php`) for admin user consultant dropdown
- Base `web/app/Http/Controllers/Controller.php` uses `AuthorizesRequests` so `$this->authorize()` works (upstream skeleton shipped an empty `Controller`)

**Files actually created/modified:** _(paths from repo root `IHRP/`; Laravel app lives under `web/`)_
- `web/` ➕ (full Laravel application)
- `web/database/migrations/0001_01_01_000000_create_users_table.php` ✅ (role, consultant_id, active)
- `web/database/migrations/2026_03_19_*` ✅ (settings, clients, consultants, onboarding, timesheets, invoice_sequence, invoices, timesheet_daily_hours, invoice_line_items, audit_log, backups, daily_call_reports, placements)
- `web/app/Http/Middleware/RequireRole.php` ✅
- `web/app/Http/Controllers/AdminUserController.php` ✅
- `web/app/Models/User.php` ✅
- `web/app/Models/Consultant.php` ✅ (Eloquent model for `consultants` table — admin user forms)
- `web/bootstrap/app.php` ✅
- `web/app/Providers/AppServiceProvider.php` ✅
- `web/routes/web.php` ✅
- `web/database/seeders/DatabaseSeeder.php` ✅
- `web/resources/views/layouts/app.blade.php` ✅
- `web/resources/views/auth/login.blade.php` ✅
- `web/resources/views/admin/users/index.blade.php` ✅
- `web/resources/views/admin/users/create.blade.php` ✅
- `web/resources/views/admin/users/edit.blade.php` ✅
- `web/.env` / `web/.env.example` ✅

**Verification notes (CLI + HTTP smoke, 2026-03-19):**
- `php artisan migrate:fresh --force` against MySQL `127.0.0.1:3306` / `ihrp_local` — OK
- `php artisan db:seed --force` — seeded admin user present
- `php artisan serve` — OK
- `GET /login` — 200
- Admin session (`admin@matchpointegroup.com`) — `GET /admin/users` — 200
- Employee session — `GET /admin/users` — 403 _(confirmed after `AuthorizesRequests` fix on base `Controller`; before fix, admin `/admin/users` returned 500)_

---

### ✅ [REVIEW — Claude Code] — Phase 0 _(2026-03-19)_

**Test results:** PHPUnit skipped — PHP build on local Windows machine lacks SQLite PDO driver (tests default to in-memory SQLite). Runtime on MySQL is unaffected. **Carry forward: fix test env in Phase 1.**

**Issues found:**
- **LOW** — `timesheet_daily_hours` uses `day_index` (0–6 int) instead of source schema's `day_of_week` string + `week_number` unique. Intentional deviation by Cursor (MySQL unique index name length limit). Acceptable for Phase 0; reconcile column naming convention in Phase 1 migration review.
- **LOW** — `daily_call_reports` and `placements` migrations are minimal stubs (id + timestamps only). Full column sets defined in PHASES.md; flesh out in Phase 3.
- **FIXED** — Base `Controller.php` was missing `AuthorizesRequests` trait; `$this->authorize()` in AdminUserController caused 500. Cursor added the trait; admin `/admin/users` now returns 200 ✅.
- **FIXED** — DEVLOG.md contained leaked `</think>` tag and `<｜tool▁calls▁begin｜>` junk from Cursor output. Cursor cleaned up.

**Security spot-check:**
- `RequireRole` middleware uses strict `in_array(..., true)` — no type coercion bypass ✅
- `AdminUserController` calls `$this->authorize('admin')` on all 8 methods (index, create, store, show, edit, update, destroy, toggleActive) ✅
- Passwords hashed via `Hash::make()` (bcrypt) — never stored plain ✅
- `.env` not committed; `.env.example` has empty values ✅

**HTTP smoke results (2026-03-19):**
- `GET /login` → 200 ✅
- Admin session → `GET /admin/users` → 200 ✅
- Employee session → `GET /admin/users` → 403 ✅
- Unauthenticated → `GET /dashboard` → redirect to `/login` ✅

**PHASES.md updated:** ✅ Phase 0 marked complete

**Carry forward to Phase 1:**
- [ ] Fix PHPUnit environment: set `DB_CONNECTION=mysql` in `phpunit.xml` (or add MySQL test DB) so `php artisan test` runs without SQLite driver
- [ ] Confirm `timesheet_daily_hours.day_index` naming is intentional or rename to `day_of_week` with a new migration
- [ ] Flesh out `daily_call_reports` and `placements` migrations with full column sets (Phase 3 spec must be written first)
- [ ] Port `OvertimeCalculator.php` first — highest-risk piece; 116 PHPUnit tests must pass before any other controller work

---

<!--
  Copy the block below for each new phase.
  Replace N with the phase number.
  Do not delete completed phases — this is a permanent record.
-->

<!--
## Phase N | [Phase Name]
_Opened: YYYY-MM-DD | Closed: YYYY-MM-DD_
_Mode: SEQUENTIAL | PARALLEL_

### 🏗️ [ARCHITECT — Claude Code]
**Goal:**
**Mode:** SEQUENTIAL | PARALLEL

**Dependency diagram:**
```
[Phase N] → [Phase N+1]
```

**Decisions made:**
-

**Risks flagged:**
-

**Files planned:**
-

---

### 🔨 [BUILD — Cursor]
**Assigned workstream:** [Phase N]

**Todos completed:**
- [x] [Phase N] ...
- [ ] [Phase N] ... (skipped — reason)

**Deviations from plan:**
-

**Unplanned additions:**
-

**Files actually created/modified:**
-

---

### ✅ [REVIEW — Claude Code]
**Test results:**

**Issues found:**
-

**PHASES.md updated:**

**Carry forward to Phase N+1:**
- [ ]

---
-->
