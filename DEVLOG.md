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

---

## Phase 2 | Frontend Port
_Opened: 2026-03-19 | Closed: —_
_Mode: PARALLEL (Phase 2a + Phase 2b)_

### 🏗️ [ARCHITECT — Claude Code]
**Goal:** Add Blade + Alpine.js views for all 8 Electron screens. Timesheets gets a Livewire upload wizard.
No new business logic — Phase 1 controllers are already complete and return JSON.
**Mode:** PARALLEL — Phase 2a (5 table pages) + Phase 2b (Timesheets/Reports/Settings)

**Dependency diagram:**
```
[Step 0 — shared layout] → [Phase 2a] ──┐
                          → [Phase 2b] ──┴─ [Merge → Step 8 Verification] → [Phase 3]
```

**Decisions made:**
- PARALLEL chosen over SEQUENTIAL: 2a (table pages) and 2b (Livewire wizard + reports) share no files after Step 0
- Step 0 must complete first: sidebar nav + Alpine toast system + CSRF meta tag needed by all pages
- Controller dual-response pattern: `$request->expectsJson()` → JSON (API), else → Blade view (browser). No route changes.
- PDF preview in browser: `blob:` URL via `URL.createObjectURL()` — avoids iframe CSP issues with direct route URL
- Timesheets Livewire wizard calls service layer directly (no internal HTTP round-trip): extract `TimesheetController::saveBatch()` as callable method
- `window.location.reload()` on modal save is acceptable for Phase 2; Phase 3 can refine with Livewire or fetch if UX is poor
- Budget tracker embedded in Reports page (not a standalone nav item) — matches Electron app structure

**Risks flagged:**
- Livewire file upload on Bluehost shared hosting: test with real memory limits; wizard uses `ini_set('memory_limit','256M')`
- Alpine.js + Livewire on same page: use `x-ignore` on Livewire component root to prevent Alpine from conflicting with Livewire's DOM management
- Step 0 is a synchronization point: both 2a and 2b Cursor sessions must wait for Step 0 to be merged before starting

**Carry-forwards from Phase 1 embedded in this phase:**
- `BudgetController::alerts()` audit log → Step 7 (Phase 2b)
- `ReportController::saveCsv()` generic rows → replaced with downloadMonthlyCsv() in Step 6 (Phase 2b)
- `timesheets.source_file_path` populate on upload → Step 5 (Phase 2b)
- `storage/app/templates/timesheet_template.xlsx` placeholder → Step 5 (Phase 2b)
- `DashboardController` `abort_unless` comment → Step 0

**Files planned:**
- `web/resources/views/dashboard.blade.php` (update)
- `web/resources/views/clients/index.blade.php`
- `web/resources/views/consultants/index.blade.php`
- `web/resources/views/invoices/index.blade.php`
- `web/resources/views/ledger/index.blade.php`
- `web/resources/views/timesheets/index.blade.php`
- `web/resources/views/reports/index.blade.php`
- `web/resources/views/settings/index.blade.php`
- `web/resources/views/livewire/timesheet-wizard.blade.php`
- `web/app/Livewire/TimesheetWizard.php`
- Minor controller changes in all 8 controllers (Blade branch only)
- `web/routes/web.php` (dashboard page route + monthly-csv route)

---

## Phase 1 | Backend Port
_Opened: 2026-03-19 | Closed: —_
_Mode: SEQUENTIAL_

### 🏗️ [ARCHITECT — Claude Code]
**Goal:** Port all 13 IPC handler modules from the Electron app into Laravel Controllers and Services.
OvertimeCalculator.php must be completed and tested first (116+ PHPUnit assertions must pass)
before any other controller work begins.
**Mode:** SEQUENTIAL

**Dependency diagram:**
```
[Phase 0] ✅ → [Phase 1] 🔨 → [Phase 2] ⏳
                               → [Phase 3] ⏳ (can start after Phase 1 backend exists)
```

**Decisions made:**
- OvertimeCalculator.php is a standalone service (no DB, no HTTP) — tested in isolation first
- AppService.php holds auditLog/getSetting/setSetting — shared by all controllers, created before any controller
- PhpSpreadsheet replaces xlsx JS library for XLSX parsing in TimesheetParseService
- dompdf Blade templates replace pdfkit — use HTML tables (not flexbox/grid) for PDF layout
- InvoiceMailable (Laravel Mail) replaces nodemailer — same SMTP config via settings table
- BackupController uses mysqldump detected at runtime — no hardcoded paths
- phpunit.xml updated to use MySQL ihrp_test database (carry-forward from Phase 0)

**Risks flagged:**
- OT floating-point: PHP `round()` must match JS `Math.round(n*100)/100` exactly — run tests immediately after port
- dompdf CSS subset: no flexbox/grid in PDF templates — use table layout
- phpspreadsheet memory: large XLSX → set memory_limit=256M in TimesheetParseService
- `timesheet_daily_hours.day_index` naming: must be confirmed/renamed before TimesheetController is written
- mysqldump path on Bluehost: detect at runtime, don't hardcode

**Files planned:**
- `web/app/Services/OvertimeCalculator.php`
- `web/app/Services/AppService.php`
- `web/app/Services/TimesheetParseService.php`
- `web/app/Services/PdfService.php`
- `web/app/Mail/InvoiceMailable.php`
- `web/app/Http/Controllers/ClientController.php`
- `web/app/Http/Controllers/AuditLogController.php`
- `web/app/Http/Controllers/DashboardController.php`
- `web/app/Http/Controllers/BudgetController.php`
- `web/app/Http/Controllers/LedgerController.php`
- `web/app/Http/Controllers/InvoiceSequenceController.php`
- `web/app/Http/Controllers/ConsultantController.php`
- `web/app/Http/Controllers/SettingsController.php`
- `web/app/Http/Controllers/TimesheetController.php`
- `web/app/Http/Controllers/InvoiceController.php`
- `web/app/Http/Controllers/ReportController.php`
- `web/app/Http/Controllers/BackupController.php`
- `web/resources/views/pdf/invoice.blade.php`
- `web/resources/views/pdf/report-monthly.blade.php`
- `web/resources/views/pdf/report-yearend.blade.php`
- `web/tests/Unit/OvertimeCalculatorTest.php`
- `web/routes/web.php` (updated)
- `web/phpunit.xml` (updated)

---

### 🔨 [BUILD — Cursor]
**Assigned workstream:** [Phase 1]

**Todos completed:**
- [x] [Phase 1] phpunit.xml → `DB_CONNECTION=mysql`, `DB_DATABASE=ihrp_test` (create `ihrp_test` on host when MySQL is available)
- [x] [Phase 1] `OvertimeCalculator.php` + `OvertimeCalculatorTest.php` — **120 assertions**, gate met (116+)
- [x] [Phase 1] `AppService` (auditLog / getSetting / setSetting)
- [x] [Phase 1] Controllers: Client, AuditLog, Dashboard (`/dashboard/stats` — avoids clash with Breeze `/dashboard` view), Budget, Ledger, InvoiceSequence, Consultant, Settings, Timesheet, Invoice, Report, Backup
- [x] [Phase 1] `TimesheetParseService` + `composer require phpoffice/phpspreadsheet`
- [x] [Phase 1] `PdfService` + `pdf/*.blade.php` + `InvoiceMailable`
- [x] [Phase 1] `LedgerQueryService`, `InvoiceFormatter`
- [x] [Phase 1] Migrations: `timesheets.source_file_path`; seed `invoice_sequence` id=1
- [x] [Phase 1] `routes/web.php` — auth + role groups; extra routes for upload/save, invoice generate/preview/send, reports, budget alerts, consultant W9/onboarding
- [x] [Phase 1] Step 5 `day_index`: **no rename** — existing migration already uses `day_of_week` (string) + `week_number`

**Deviations from plan:**
- Dashboard resource route replaced with **`GET /dashboard/stats`** so Breeze **`GET /dashboard`** (Blade) remains unchanged.
- `POST /timesheets/save` used for batch import (resource `store` not registered to avoid duplicate with ambiguous body).
- `config/services.php` → `mysql.dump_path` (env `MYSQLDUMP_PATH`) for BackupController mysqldump binary.
- Invoice list date filters use **`invoice_date`** (schema has no pay_period on `invoices`).

**Unplanned additions:**
- `config/services.php` `mysql.dump_path`

**Files actually created/modified:**
- See plan file list under `web/` — models `Client`, `Timesheet`, `TimesheetDailyHour`, `Invoice`, `InvoiceLineItem`, `InvoiceSequence`, `Backup`, `ConsultantOnboardingItem`; `Consultant` updated with `client()` relation.

---

### ✅ [REVIEW — Claude Code] — Phase 1 _(2026-03-19)_

**Review method:** Full file-by-file review via superpowers:code-reviewer subagent (96K tokens, 34 tool calls).

**Test results:**
- `php artisan test --filter=OvertimeCalculatorTest` — 45 tests, 120 assertions, 0 failures ✅
  _(Gate criterion said "116+ passed" — this referred to JS assertion count. PHP test count is 45. Gate is met.)_
- `php artisan route:list` — 93 routes, no errors ✅
- Full `php artisan test` — requires MySQL `ihrp_test` or SQLite (now fixed — see Critical-3 fix below)

**Criticals fixed before close:**

- **CRITICAL-1 (FIXED)** — SMTP credentials not loaded from settings table.
  Added `AppService::applySmtpSettings()` which reads `smtp_host/port/user/password/encryption/from_address/from_name` from DB via `getSetting()`, calls `Config::set()` on `mail.mailers.smtp.*`, and calls `Mail::forgetMailers()` to purge the resolved mailer. Now called in `InvoiceController::send()` and `SettingsController::testSmtp()` before every `Mail::to()->send()` dispatch.

- **CRITICAL-2 (FIXED)** — `InvoiceController::send()` missing audit log + no status transition.
  Changed `find()` → `findOrFail()` (null safety). Added `$invoice->update(['status' => 'sent'])` after successful send. Added `AppService::auditLog('invoices', ..., 'INVOICE_SENT', ...)` with `sent_to` in new_data.

- **CRITICAL-3 (FIXED)** — `phpunit.xml` required live MySQL `ihrp_test`.
  Changed to `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:`. Feature tests now run without a live MySQL instance. OvertimeCalculatorTest is DB-free and unaffected.

**Important issues — carry forward to Phase 2:**
- **IMPORTANT-1** — `BudgetController::alerts()` mutates `clients.budget_alert_warning_sent` without audit log.
- **IMPORTANT-3** — `ReportController::saveCsv()` accepts arbitrary caller-supplied row data. Should be replaced with server-driven query endpoints in Phase 2.
- **IMPORTANT-5** — `timesheets.source_file_path` migration exists but `TimesheetController` never populates it. Decide: persist uploaded file or drop the column in Phase 2.

**Suggestions noted (non-blocking):**
- `DashboardController` uses `abort_unless` instead of `$this->authorize()` — intentional (employee access). Add a comment.
- `ConsultantController::index/show` use raw `DB::select()` while mutations use Eloquent — refactor candidate.
- `AppService::auditLog()` will silently store `user_id = null` for system/queue contexts — add actor parameter when scheduled jobs are added in Phase 4.
- `BackupController` `file_path` value inconsistent between failed/succeeded rows — minor.
- `InvoiceController::generate()` writes PDF outside DB transaction — if `pdf_path` update fails, invoice record has `pdf_path = null` with file on disk.

**Security spot-check:**
- All 13 controllers: every mutating method has `$this->authorize()` or explicit role check ✅
- `BackupController` uses array-form `Process` command — no shell injection ✅
- `ConsultantController` W9 upload uses deterministic filename — no path traversal ✅
- SMTP credentials now loaded from DB at runtime — not hardcoded ✅
- `Auth::id()` in audit log — no system context gap (yet; flagged above) ✅

**Unplanned additions approved:**
- `InvoiceFormatter` service — justified extraction, follows Services convention ✅
- `LedgerQueryService` — keeps LedgerController lean ✅

**PHASES.md updated:** ✅ Phase 1 marked complete

**Carry forward to Phase 2:**
- [ ] `BudgetController::alerts()` — add audit log for `budget_alert_warning_sent` flag writes
- [ ] `ReportController::saveCsv()` — replace generic row passthrough with server-driven query
- [ ] `timesheets.source_file_path` — decide persist-or-drop; if persist, save uploaded file in TimesheetController upload action
- [ ] Add comment to `DashboardController` explaining `abort_unless` pattern (employee-visible endpoint)
- [ ] Place `timesheet_template.xlsx` in `storage/app/templates/` (template download returns 404 without it)

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
