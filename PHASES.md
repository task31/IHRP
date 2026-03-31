# PHASES — IHRP (Internal HR Portal)
_Last updated: 2026-03-31 (Phase 12 planned)_

## Phase Map

[Phase 0] — Scaffold + Auth | SEQUENTIAL
  └─ Create Laravel 11 project
  └─ MySQL migrations (11 existing + 3 new tables)
  └─ Laravel Breeze auth + role middleware
  └─ Login page + Admin user management
  └─ Deploy to Bluehost (Phase 0 verify)

[Phase 1] — Backend Port | SEQUENTIAL
  └─ OvertimeCalculator.php (rewrite overtime.js) + 116 PHPUnit tests
  └─ All 13 Controllers ported from IPC handlers
  └─ Role guards on every controller method
  └─ dompdf PDF generation (invoice + reports)
  └─ Laravel Mail (replaces nodemailer)

[Phase 2] — Frontend Port | PARALLEL
  ├─ [Phase 2a] — Table Pages (Dashboard, Clients, Consultants, Invoices, Ledger)
  │    └─ Step 0: shared layout fixes → Step 1–4: Blade + Alpine.js per page
  └─ [Phase 2b] — Complex Pages (Timesheets Livewire wizard, Reports, Budget/Settings)
       └─ Livewire TimesheetWizard → Reports PDF/CSV → Budget+Settings Blade

[Phase 3] — New Features | SEQUENTIAL
  └─ Step 1: DB migrations (daily_call_reports + placements full schema)
  └─ Step 2: Models + DailyCallReportController + PlacementController
  └─ Step 3: Call reporting page (/calls) — all roles submit; own history
  └─ Step 4: Call aggregate (/calls/report) — AM + admin summary view
  └─ Step 5: Placement management (/placements) — Livewire PlacementManager
  └─ Step 6: Employee dashboard (placement card + call summary + quick-submit)
  └─ Step 7: Sidebar updates + final smoke

[Phase 4] — Data Migration + QA | SEQUENTIAL
  └─ PHP migration script (SQLite → MySQL)
  └─ Row-count + money checksum validation
  └─ File migration (W-9s + invoice PDFs)
  └─ Full regression pass on migrated data

[Phase 5] — Deploy | SEQUENTIAL
  └─ Production MySQL on Bluehost
  └─ hr.matchpointegroup.com subdomain + DNS
  └─ Bluehost Git deploy hook
  └─ Run migration on production
  └─ Smoke test all features

[Phase 6] — Payroll Integration | SEQUENTIAL
  └─ Step 1: 5 migrations (payroll_uploads, payroll_records, payroll_consultant_entries, payroll_consultant_mappings, payroll_goals)
  └─ Step 2: 5 Eloquent models (PayrollUpload, PayrollRecord, PayrollConsultantEntry, PayrollConsultantMapping, PayrollGoal)
  └─ Step 3: PayrollParseService (XLSX extraction) + PayrollParseServiceTest (8 tests)
  └─ Step 4: PayrollDataService (aggregation + projections) + PayrollDataServiceTest (8 tests)
  └─ Step 5: PayrollController (index, upload, apiDashboard, apiConsultants, apiAggregate, apiGoalSet, apiMappings, apiMappingsUpdate)
  └─ Step 6: Routes (8 payroll routes in web.php)
  └─ Step 7: Blade views (payroll/index.blade.php + payroll/mappings.blade.php)
  └─ Step 8: Sidebar nav link (layouts/app.blade.php)
  └─ Step 9: PayrollControllerTest (feature tests)
  └─ Step 10: Manual smoke test (upload 3 AM files, verify AM #4 empty state, verify all charts + scoping)

[Phase 10] — P2 Bug Fixes: Dead Code + SQL Portability + Float Math | SEQUENTIAL
  └─ Task 1: DashboardController — remove dead AM branch, replace DATE_FORMAT with whereBetween
  └─ Task 2: TimesheetController — bcmath for computeTimesheetAggregates money totals
  └─ Task 3: php artisan test (160 pass gate)

[Phase 11] — Payroll Semantics: Fix Missing Bill-Rate Revenue Fallback | SEQUENTIAL
  └─ Task 1: Write 2 failing tests (upload + recompute, missing bill_rate → revenue=0.0000)
  └─ Task 2: Fix upload() else branch — $revenue = '0.0000'
  └─ Task 3: Fix recomputeMargins() else branch — $revenue = '0.0000'
  └─ Task 4: php artisan test (162 pass gate)

[Phase 12] — Resume Redaction Tool | SEQUENTIAL
  └─ Step 1: composer require smalot/pdfparser
  └─ Step 2: ResumeRedactionService (extractLines, redactContactInfo, buildPdf)
  └─ Step 3: PDF Blade template (resources/views/resume-redact/pdf.blade.php)
  └─ Step 4: ResumeRedactionController (index + process)
  └─ Step 5: Routes (GET + POST /resume-redact)
  └─ Step 6: Upload view (resources/views/resume-redact/index.blade.php)
  └─ Step 7: Sidebar sub-link below Calls
  └─ Step 8: Tests (7 unit + 4 feature, fixtures/sample-resume.pdf)
  └─ Step 9: php artisan test (173 pass gate)

## Dependency Rules

- [Phase 1] requires [Phase 0] complete (auth + DB must exist)
- [Phase 2a] and [Phase 2b] require [Phase 1] complete (all controllers + routes must exist)
- [Phase 2a] and [Phase 2b] can run simultaneously (no shared files except app.blade.php — do Step 0 first)
- [Phase 3] requires [Phase 2a] + [Phase 2b] merged (frontend must exist for new Phase 3 pages to follow)
- [Phase 3] requires [Phase 0] complete (auth/roles needed; can run alongside Phase 2 if needed)
- [Phase 4] requires [Phase 1] + [Phase 2] complete (full feature parity before migration)
- [Phase 5] requires [Phase 4] complete (QA gate before production deploy)
- [Phase 6] requires [Phase 4] complete (roles + consultants table must exist); can be implemented locally while Phase 5 is in-progress

## Status

- [Phase 0] ✅ Complete _(2026-03-19)_
- [Phase 1] ✅ Complete _(2026-03-19)_
- [Phase 2a] ✅ Complete _(2026-03-19)_
- [Phase 2b] ✅ Complete _(2026-03-19)_
- [Phase 3] ✅ Complete _(2026-03-19)_
- [Phase 4] ✅ Complete _(2026-03-20)_
- [Phase 5] ✅ Complete _(2026-03-24)_
- [Phase 6] ✅ Complete _(2026-03-22)_
- [Phase 7] ✅ Complete _(2026-03-23)_
- [Phase 8] ✅ Complete _(2026-03-30)_
- [Phase 9] ✅ Complete _(2026-03-30)_
- [Phase 10] ✅ Complete _(2026-03-30)_
- [Phase 11] ✅ Complete _(2026-03-30)_
- [Phase 12] ✅ Complete _(2026-03-31)_
