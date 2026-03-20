# CLAUDE.md — IHRP (Internal HR Portal)

> Lean summary of completed phases. Append-only — phases are never removed.
> Full decision + build history lives in DEVLOG.md.
> Read this at the start of every session to understand project state.

---

## Project Overview

Laravel 11 (PHP) web app migrating the Matchpointe Electron payroll desktop app to
`hr.matchpointegroup.com`. Multi-user with role-based access (admin, account_manager,
employee). Hosted on Bluehost Business Hosting (PHP/MySQL/Apache, $0 extra).

See `PROJECT_CONTEXT.md` for full brief, stack decisions, and constraints.

---

## Project Location

All projects live at: `C:\Users\zobel\Claude-Workspace\projects\`

This project is at: `C:\Users\zobel\Claude-Workspace\projects\IHRP\`

Source (Electron app): `C:\Users\zobel\Claude-Workspace\projects\Payroll\`

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
- Known carry-forward: PHPUnit needs SQLite fix for test env; `daily_call_reports` + `placements` are stubs

---

### Phase 1 — Backend Port ✅ _(closed 2026-03-19)_

- 13 controllers ported from Electron IPC (Client, AuditLog, Dashboard, Budget, Ledger, InvoiceSequence, Consultant, Settings, Timesheet, Invoice, Report, Backup + existing Admin)
- `OvertimeCalculator.php` — 52-jurisdiction PHP port of `overtime.js`; 45 PHPUnit tests, 120 assertions, 0 failures
- `AppService` (auditLog / getSetting / setSetting / **applySmtpSettings** — runtime SMTP override from settings table)
- `TimesheetParseService` (PhpSpreadsheet), `PdfService` (DomPDF), `InvoiceFormatter`, `LedgerQueryService`
- `InvoiceMailable` + `mail/invoice-note.blade.php` + `pdf/invoice.blade.php` + `pdf/report-monthly.blade.php` + `pdf/report-yearend.blade.php`
- phpunit.xml → SQLite in-memory (feature tests run without live MySQL)
- SMTP credentials loaded from settings table at runtime via `AppService::applySmtpSettings()` + `Mail::forgetMailers()`
- Known carry-forwards: `BudgetController::alerts()` audit log; `ReportController::saveCsv()` server-side query; `timesheets.source_file_path` populate-or-drop; `storage/app/templates/timesheet_template.xlsx` placeholder needed

---

## Active Phase

**Phase 3** — New Features — NEXT _(browser smoke gate must pass first — see PHASES.md)_

---

### Phase 2 — Frontend Port ✅ _(closed 2026-03-19)_

- Step 0: sidebar nav wired to named routes (`@can` gates, `routeIs()` active state), Alpine toast system, global `apiFetch()` with CSRF header merge
- Phase 2a (Steps 1–4): Dashboard (4 stat cards + end-date alerts + budget bars via Alpine fetch), Clients (CRUD modal, client-side sort), Consultants (onboarding modal, W-9 upload), Invoices (PDF preview iframe via blob URL), Ledger (detail + summary toggle)
- Phase 2b (Steps 5–7): Timesheets Livewire wizard (`TimesheetWizard.php` — upload→parse→preview-OT→import), Reports (year-end PDF, monthly CSV server-driven), Settings (6-tab layout, SMTP test, logo upload, backup download)
- All 3 Phase 1 carry-forwards fixed: budget alerts audit log, `downloadMonthlyCsv()` server-driven, `source_file_path` populated on import
- Template file placed: `storage/app/templates/timesheet_template.xlsx`
- `reports/save-csv` route removed; `reports/save-pdf` and `reports/monthly-csv` are the two report write paths
- OT tests: 44 tests, 120 assertions, 0 failures (no regression)
- Browser smoke (Step 8 checklist) deferred — carry-forward gate for Phase 3 start

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
| Full decision + build history | `DEVLOG.md` |
| Phase summaries (completed) | This file (`CLAUDE.md`) |
| Current phase plan | `phase-N-plan.md` |
| Phase map + status | `PHASES.md` |
