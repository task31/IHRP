# CLAUDE.md — IHRP (Internal HR Portal)

> Lean summary of completed phases. Append-only — phases are never removed.
> Full decision + build history lives in DEVLOG.md.
> Read this at the start of every session to understand project state.
> **MANDATORY: Also read `BUSINESS_MODEL.md` before writing any code that touches earnings, margins, payroll, or commissions.**

---

## Project Overview

Laravel 13 (PHP 8.3) web app migrating the Matchpointe Electron payroll desktop app to
`hr.matchpointegroup.com`. Multi-user with role-based access (admin, account_manager).
Hosted on Bluehost Business Hosting (PHP/MySQL/Apache, $0 extra).

See `PROJECT_CONTEXT.md` for full brief, stack decisions, and constraints.

---

## Project Location

All projects live at: `C:\Users\zobel\Claude-Workspace\projects\`

This project is at: `C:\Users\zobel\Claude-Workspace\projects\IHRP\`

Source (Electron app): `C:\Users\zobel\Claude-Workspace\projects\Payroll\`

---

## Current Status

**Phase 5 (Deploy) — COMPLETE ✅** _(closed 2026-03-24)_
Production live at `hr.matchpointegroup.com`. All P0 blockers resolved. Manual smoke passed.
Remaining open work: T021 (decision), T022 (deploy verification), T025 (docs consolidation) — see TASKLIST.md.

---

## Completed Phases

### Phase 0 — Scaffold + Auth ✅ _(closed 2026-03-19)_

- Laravel 13 + PHP 8.3 scaffolded into `web/` (Composer resolved 13.x; plan said 11 — runtime is 13)
- 14 MySQL migrations: 11 from SQLite schema + `daily_call_reports`, `placements`, `users` (extended)
- Laravel Breeze (Blade), Livewire, barryvdh/laravel-dompdf, Alpine.js via CDN
- `RequireRole` middleware (variadic roles, `abort(403)`); Gate `admin` + `account_manager`
- `AdminUserController` + `/admin/users` CRUD (list, create, edit, toggle active)
- Seeded admin: `admin@matchpointegroup.com` / `changeme123` / role=admin
- Shell layout: Tailwind CDN, sidebar placeholders, `@can('admin')` nav, flash messages
- HTTP smoke passed: `/login` 200, admin→`/admin/users` 200, employee→403
- Carry-forward: PHPUnit SQLite fix → resolved T012; `daily_call_reports` + `placements` stubs → resolved Phase 3

---

### Phase 1 — Backend Port ✅ _(closed 2026-03-19)_

- 13 controllers ported from Electron IPC (Client, AuditLog, Dashboard, Budget, Ledger, InvoiceSequence, Consultant, Settings, Timesheet, Invoice, Report, Backup + existing Admin)
- `OvertimeCalculator.php` — 52-jurisdiction PHP port of `overtime.js`; 45 PHPUnit tests, 120 assertions, 0 failures
- `AppService` (auditLog / getSetting / setSetting / **applySmtpSettings** — runtime SMTP override from settings table)
- `TimesheetParseService` (PhpSpreadsheet), `PdfService` (DomPDF), `InvoiceFormatter`, `LedgerQueryService`
- `InvoiceMailable` + `mail/invoice-note.blade.php` + `pdf/invoice.blade.php` + `pdf/report-monthly.blade.php` + `pdf/report-yearend.blade.php`
- phpunit.xml → SQLite in-memory (feature tests run without live MySQL)
- SMTP credentials loaded from settings table at runtime via `AppService::applySmtpSettings()` + `Mail::forgetMailers()`
- Carry-forwards: `BudgetController::alerts()` audit log → resolved Phase 2b; `ReportController::saveCsv()` → resolved Phase 2b; `timesheets.source_file_path` → resolved Phase 2b; timesheet template placeholder → resolved Phase 2b

---

### Phase 2 — Frontend Port ✅ _(closed 2026-03-19)_

- Step 0: sidebar nav wired to named routes (`@can` gates, `routeIs()` active state), Alpine toast system, global `apiFetch()` with CSRF header merge
- Phase 2a (Steps 1–4): Dashboard (4 stat cards + end-date alerts + budget bars via Alpine fetch), Clients (CRUD modal, client-side sort), Consultants (onboarding modal, W-9 upload), Invoices (PDF preview iframe via blob URL), Ledger (detail + summary toggle)
- Phase 2b (Steps 5–7): Timesheets Livewire wizard (`TimesheetWizard.php` — upload→parse→preview-OT→import), Reports (year-end PDF, monthly CSV server-driven), Settings (6-tab layout, SMTP test, logo upload, backup download)
- All Phase 1 carry-forwards resolved
- Template file placed: `storage/app/templates/timesheet_template.xlsx`
- OT tests: 44 tests, 120 assertions, 0 failures

---

### Phase 3 — New Features ✅ _(closed 2026-03-19)_

- `daily_call_reports` + `placements` full schema migrations (DECIMAL(12,4) rates, UNIQUE constraint on call reports)
- `DailyCallReport` + `Placement` models; `DailyCallReportController` + `PlacementController`
- `/calls` — all roles submit; own history; AM/admin see all employees
- `/calls/report` — AM + admin aggregate summary; employee → 403
- `/placements` — Livewire `PlacementManager` (filters, inline status change, CRUD modal); employee read-only scoped view
- Employee dashboard — My Placement card + 7-day call activity + quick-submit form
- Smoke: 12/12 PASS across all roles
- Carry-forwards: `users.consultant_id` admin UI → resolved T010; auditLog actor gap → deferred; smoke `*.py` cleanup → resolved T024

---

### Phase 4 — Data Migration + QA ✅ _(closed 2026-03-20)_

- SQLite → MySQL migration script (`migrate:run`) + validation command (`migrate:validate`) — 12 tables, row-count + money checksum
- File migration (`migrate:files`) — W-9s and invoice PDFs moved to `storage/app/uploads/`
- Full manual regression smoke — all pages PASS for admin + account_manager roles
- **Employee role removed** — DB enum altered, controllers/policies/views updated, existing users migrated to account_manager
- **Placements refactor** — `consultant_name` free-text field; auto-creates consultant on save; always-editable status dropdown; AM column added
- **AM access restricted** — nav limited to Calls + Placements; placements scoped to own records; dashboard blocked (403); login redirects to `/placements`
- OT tests: 44 passed, 120 assertions, 0 failures

---

### Phase 6 — Payroll Integration ✅ _(closed 2026-03-22)_

- **5 migrations:** `payroll_uploads`, `payroll_records`, `payroll_consultant_entries`, `payroll_consultant_mappings`, `payroll_goals` — all money `DECIMAL(12,4)`
- **5 models** with `scopeForOwner` and `belongsTo`
- **Services:** `PayrollParseService`, `PayrollDataService` (bcmath throughout, projection, per-AM breakdown)
- **HTTP:** `PayrollController` — 8 methods, 8 auth guards; upload uses `DB::transaction` + `AppService::auditLog`
- **UI:** `payroll/index.blade.php` (Chart.js 4.4.3, KPIs, bar/donut/YoY/trend/table, consultant drawer, admin upload modal, goal tracker); `payroll/mappings.blade.php`
- **Tests:** 107 total, 259 assertions, 0 failures. Smoke: 19/19 Playwright checks pass.
- Carry-forward: Dimarumba corrupted payroll rows → resolved T002

---

### Phase 7 — Performance + Payroll Margin Overhaul ✅ _(closed 2026-03-23)_

- **9 DB indexes** across 6 tables via single migration
- **Consultant query fix** — N+2 → 1 query
- **Payroll cache** — `apiDashboard` + `apiAggregate` cached per user+year (1hr TTL), busted on upload/goal-set
- **Correct margin formula:** Agency Gross Profit = (hours × bill_rate) − AM Earnings. See `BUSINESS_MODEL.md` for full rules.
- **`recomputeMargins()` endpoint** — `POST /payroll/recompute-margins` (admin); never modifies `am_earnings`
- **107 tests, 259 assertions, 0 failures**
- Carry-forward: Existing `am_earnings` corrupted values → resolved T003

---

### Phase 9 — Bug Fixes: P0 Auth + P1 Correctness ✅ _(closed 2026-03-30)_

- **Placement authorization** — `PlacementPolicy::view()` + `update()` now enforce ownership (`admin || placed_by === user->id`); `PlacementController@index` JSON scoped to own placements for AMs
- **ConsultantController GROUP BY** — `GROUP BY c.id, cl.id, cl.name` — safe under MySQL `ONLY_FULL_GROUP_BY`
- **endDateAlerts SQL** — removed MySQL-specific `DATE_ADD/CURDATE`, replaced with PHP `now()->addDays()->toDateString()`
- **Server path info leak** — W9 + contract JSON responses now return only `fileName`, not absolute server path
- **Factories** — Client, Consultant, Placement factories added; `HasFactory` enabled on all three models
- **160 tests, 424 assertions, 0 failures**

---

### Phase 10 — P2 Bug Fixes: Dead Code + SQL Portability + Float Math ✅ _(closed 2026-03-30)_

- `DashboardController::index()` dead AM branch removed (unreachable after `abort_unless(..., 403)`)
- `DATE_FORMAT` MySQL-only calls replaced with `whereBetween('pay_period_start', [$mtdStart, $mtdEnd])`
- `TimesheetController::computeTimesheetAggregates()` money accumulation via bcmath (scale=10) + `round((float), 4)` on write-out; eliminates float drift on five money fields
- 160 tests, 424 assertions, 0 failures

---

### Phase 5 — Deploy ✅ _(closed 2026-03-24)_

- All 8 P0 blockers (T001–T008) resolved — see `references/tasklist-archive.md` for details
- Production smoke: admin + AM roles verified manually by Raf
- `deploy.py` + `.cpanel.yml` wired; `python deploy.py --step deploy` triggers cPanel pull
- 145 tests, 0 failures at close

---

## Conventions for This Project

- All Controllers in `app/Http/Controllers/` — one controller per IPC module
- Blade views in `resources/views/` — mirroring page names from original app
- Services in `app/Services/` — OvertimeCalculator, PdfService, etc.
- Every controller method must call `$this->authorize()` or check role explicitly
- Money fields stored as `DECIMAL(12,4)` in MySQL — never FLOAT
- OT calculation always goes through `OvertimeCalculator` — never inline
- File uploads stored in `storage/app/uploads/` — served via storage symlink
- Audit log entries must include `user_id` = `Auth::id()`

---

## SSOT (Single Source of Truth)

| Info type | Lives in |
|---|---|
| What we're building | `PROJECT_CONTEXT.md` |
| **MPG business model + calculation rules** | **`BUSINESS_MODEL.md`** |
| Full decision + build history | `DEVLOG.md` |
| Phase summaries (completed) | This file (`CLAUDE.md`) |
| Current open tasks | `TASKLIST.md` |
| Completed task history | `references/tasklist-archive.md` |
| Current phase plan | `phase-N-plan.md` (active); completed plans in `references/archived-phase-plans/` |
| Phase map + status | `PHASES.md` |
