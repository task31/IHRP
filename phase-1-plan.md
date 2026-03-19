# Phase 1 Plan — Backend Port
_Created: 2026-03-19_
_Mode: SEQUENTIAL_

## Context

Phase 0 delivered a running Laravel 13 app with auth, role middleware, and 14 database tables.
Phase 1 ports all business logic from the Electron IPC layer into Laravel Controllers and Services.
The OT engine must be ported and tested FIRST — it is the highest-risk piece and every timesheet
and invoice calculation depends on it. No other controller work starts until OvertimeCalculator
PHPUnit tests pass 116+/116.

Carry-forward items from Phase 0 review:
- Fix PHPUnit env (SQLite driver missing → point at MySQL test DB)
- `timesheet_daily_hours.day_index` — confirm or rename before building timesheet controller on top of it
- `daily_call_reports` + `placements` migration stubs — leave as stubs until Phase 3

## Dependency

**Requires:** Phase 0 complete (auth, DB, role middleware all exist) ✅
**Unlocks:** Phase 2 (Blade + Alpine.js pages call these controllers), Phase 3 (new features add on top of this backend)

---

## Source Files Reference

| Source file | Lines | → Laravel target |
|---|---|---|
| `overtime.js` | 606 | `app/Services/OvertimeCalculator.php` |
| `overtime.test.js` | 421 (119 assertions) | `tests/Unit/OvertimeCalculatorTest.php` |
| `settings.js` | 165 | `app/Http/Controllers/SettingsController.php` |
| `clients.js` | ~200 | `app/Http/Controllers/ClientController.php` |
| `consultants.js` | 391 | `app/Http/Controllers/ConsultantController.php` |
| `timesheets-parse.js` | 58 | `app/Services/TimesheetParseService.php` |
| `timesheets.js` | 456 | `app/Http/Controllers/TimesheetController.php` |
| `ledger.js` | 184 | `app/Http/Controllers/LedgerController.php` |
| `budget.js` | ~200 | `app/Http/Controllers/BudgetController.php` |
| `invoice-pdf.js` | 198 | `resources/views/pdf/invoice.blade.php` + `app/Services/PdfService.php` |
| `invoices.js` | 667 | `app/Http/Controllers/InvoiceController.php` |
| `report-pdf.js` | 517 | `resources/views/pdf/report-*.blade.php` + `PdfService.php` |
| `reports.js` | 559 | `app/Http/Controllers/ReportController.php` |
| `email.js` | 244 | `app/Mail/InvoiceMailable.php` |

---

## To-Dos

### Step 1 — Fix test environment (must come first)

- [ ] [Phase 1] Update `web/phpunit.xml`: add `<env name="DB_CONNECTION" value="mysql"/>` and `<env name="DB_DATABASE" value="ihrp_test"/>` so `php artisan test` uses MySQL instead of SQLite
- [ ] [Phase 1] Create `ihrp_test` database in MySQL: `CREATE DATABASE ihrp_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
- [ ] [Phase 1] Verify `php artisan test` runs without SQLite driver error (Breeze tests may fail — acceptable at this stage; goal is the runner works)

---

### Step 2 — OvertimeCalculator.php + PHPUnit tests (GATE — nothing else starts until this passes)

- [ ] [Phase 1] Create `app/Services/OvertimeCalculator.php`
  - Copy STATE_RULES lookup table exactly as a `private const array`
  - Port `NV_MIN_WAGE` / `NV_DAILY_OT_WAGE_CEILING` as class constants
  - Port all private helper methods: `_r()`, `_buildBreakdown()`, `_allocateWeeklyOT()`, `_calcFederal()`, `_calcDailyNoDoubleTime()`, `_calcCalifornia()`, `_calcNevada()`, `_calcKentucky()`, `_calcOregon()`, `_calcRhodeIsland()`, `_calcMaryland()`, `_calcNewYork()`, `_calcPuertoRico()`, `_getLabel()`, `_validate()`
  - Port public methods: `calculateOvertimePay(array $params): array`, `getStateRule(string $state): ?array`, `listAllStateRules(): array`
  - CRITICAL: All rounding must use `round($n, 2)` — not `number_format()` or `ceil()`
  - CRITICAL: Floating-point arithmetic must match JS exactly — use the same order of operations

- [ ] [Phase 1] Create `tests/Unit/OvertimeCalculatorTest.php`
  - Port every `run()` block from `overtime.test.js` → one PHPUnit `public function test...()` method per block
  - Port `assert($label, $actual, $expected, $tol=0.01)` → `$this->assertEqualsWithDelta($expected, $actual, 0.01, $label)`
  - Port `assertEq($label, $actual, $expected)` → `$this->assertEquals($expected, $actual, $label)`
  - Port `assertTrue($label, $value)` → `$this->assertTrue($value, $label)`
  - All 119 assertions from source must appear in the PHPUnit suite

- [ ] [Phase 1] Run `php artisan test --filter=OvertimeCalculatorTest` — must show **116+ passed, 0 failed** before proceeding to Step 3

---

### Step 3 — Shared service layer (required by all controllers)

- [ ] [Phase 1] Create `app/Services/AppService.php` with three static methods:
  - `auditLog(string $table, int $recordId, string $action, array $oldData, array $newData): void`
    — writes to `audit_log` with `user_id = Auth::id()`, `changed_at = now()`
  - `getSetting(string $key, mixed $default = null): mixed`
    — reads from `settings` table, returns `$default` if key missing
  - `setSetting(string $key, mixed $value): void`
    — upserts into `settings` table

---

### Step 4 — Simple CRUD controllers

- [ ] [Phase 1] Create `app/Http/Controllers/ClientController.php`
  - Actions: index, show, store, update, destroy
  - Port from `clients.js`: list, get, create, update, delete
  - Authorize: admin or account_manager (read), admin only (write)
  - Audit log on create/update/delete

- [ ] [Phase 1] Create `app/Http/Controllers/AuditLogController.php`
  - Action: index only (filterable by table, date range, user)
  - Authorize: admin only
  - Port from `auditLog:list` IPC handler

- [ ] [Phase 1] Create `app/Http/Controllers/DashboardController.php`
  - Action: index — aggregates stats from timesheets + invoices + consultants
  - Port from `dashboard:stats` IPC handler
  - Authorize: admin, account_manager (full); employee sees Phase 3 stub

- [ ] [Phase 1] Create `app/Http/Controllers/BudgetController.php`
  - Actions: index (summary), show (by year), store/update, alerts
  - Port from `budget.js`: summary, checkAlerts, get, set
  - Authorize: admin only (write), admin + account_manager (read)

- [ ] [Phase 1] Create `app/Http/Controllers/LedgerController.php`
  - Actions: index (list + summary), consultant list, client list
  - Port from `ledger.js`: list, summary, consultants, clients
  - Authorize: admin + account_manager (read-only)

- [ ] [Phase 1] Create `app/Http/Controllers/InvoiceSequenceController.php`
  - Actions: show, update
  - Port from `invoiceSequence:get` + `invoiceSequence:set` IPC handlers
  - Authorize: admin only

---

### Step 5 — File-handling controllers

- [ ] [Phase 1] Confirm or rename `timesheet_daily_hours.day_index` → `day_of_week`
  - Check `timesheets.js` source to see which column name the logic uses
  - If renaming: write a new migration `2026_03_19_rename_day_index_to_day_of_week.php`
  - Decision must be made here before TimesheetController uses the column

- [ ] [Phase 1] Create `app/Services/TimesheetParseService.php`
  - Port from `timesheets-parse.js` (58 lines) — CSV/XLSX parsing logic
  - Use `PhpSpreadsheet` (install: `composer require phpoffice/phpspreadsheet`) to replace the JS xlsx library
  - Method: `parse(UploadedFile $file): array` — returns normalized rows

- [ ] [Phase 1] Create `app/Http/Controllers/ConsultantController.php`
  - Actions: index, show, store, update, deactivate, onboarding (get/update), endDateAlerts, extendEndDate, w9Upload, w9Path, w9Delete
  - Port from `consultants.js` (391 lines)
  - W9 file handling: `$file->storeAs('uploads/w9s', "consultant_{$id}.pdf", 'local')`
  - Replace `app.getPath('userData')` → `storage_path('app/uploads')`
  - Authorize: admin (write), admin + account_manager (read)
  - Audit log on consultant create/update/deactivate

- [ ] [Phase 1] Create `app/Http/Controllers/SettingsController.php`
  - Actions: index, update, setLogo, testSmtp
  - Port from `settings.js` (165 lines)
  - Logo upload: store in `storage/app/uploads/logo.{ext}`, save path in settings table
  - Replace `nativeImage` (Electron) with standard PHP image handling (GD or Intervention — use GD, it's built-in)
  - SMTP test: use Laravel's `Mail::raw()` with a test message to validate credentials
  - Authorize: admin only

- [ ] [Phase 1] Create `app/Http/Controllers/TimesheetController.php`
  - Actions: index (with filters), show, upload (POST with file), save, checkDuplicate, downloadTemplate
  - Port from `timesheets.js` (456 lines) — uses `TimesheetParseService` for file parsing
  - File upload: `request()->file('timesheet')->storeAs('uploads/timesheets', ...)`
  - Template download: `response()->download(storage_path('app/templates/timesheet_template.xlsx'))`
  - bi-weekly split for OT: call `OvertimeCalculator::calculateOvertimePay()` twice (week 1 + week 2)
  - Authorize: admin (write), admin + account_manager (read)

---

### Step 6 — PDF, email, and invoice/reports controllers

- [ ] [Phase 1] Install `barryvdh/laravel-dompdf` (already installed in Phase 0 — verify it's in composer.json)
- [ ] [Phase 1] Create `app/Services/PdfService.php`
  - Method: `generateInvoice(Invoice $invoice): string` — renders `pdf.invoice` Blade view → returns PDF binary
  - Method: `generateMonthlyReport(array $data): string` — renders `pdf.report-monthly` Blade view
  - Method: `generateYearEndReport(array $data): string` — renders `pdf.report-yearend` Blade view
  - Uses `Pdf::loadView($view, $data)->output()` (Barryvdh facade)

- [ ] [Phase 1] Create `resources/views/pdf/invoice.blade.php`
  - Port layout from `invoice-pdf.js` (198 lines) — pdfkit → HTML/CSS for dompdf
  - Must include: invoice header, line items table, totals (regular, OT, double-time), client + consultant info, PO number

- [ ] [Phase 1] Create `resources/views/pdf/report-monthly.blade.php` and `resources/views/pdf/report-yearend.blade.php`
  - Port from `report-pdf.js` (517 lines)

- [ ] [Phase 1] Create `app/Mail/InvoiceMailable.php`
  - Port from `email.js` (244 lines) — nodemailer → Laravel Mail
  - Constructor: `Invoice $invoice, string $recipientEmail, string $subject, string $note`
  - Attachments: attach PDF binary from PdfService
  - SMTP credentials: read from settings table via AppService::getSetting()

- [ ] [Phase 1] Create `app/Http/Controllers/InvoiceController.php`
  - Actions: index (with filters), show, generate, preview (returns PDF inline), export (download PDF), updateStatus, updatePo, send
  - Port from `invoices.js` (667 lines)
  - preview: `return response($pdf, 200)->header('Content-Type', 'application/pdf')`
  - export: `return response()->download($tempPath, "invoice_{$id}.pdf")->deleteFileAfterSend()`
  - send: dispatch `InvoiceMailable` via `Mail::to($email)->send()`
  - Authorize: admin (write + send), admin + account_manager (read + preview)

- [ ] [Phase 1] Create `app/Http/Controllers/ReportController.php`
  - Actions: monthly, yearEnd, quickbooks (returns CSV), savePdf (download), saveCsv (download)
  - Port from `reports.js` (559 lines)
  - Authorize: admin (full), account_manager (read-only)

- [ ] [Phase 1] Create `app/Http/Controllers/BackupController.php`
  - Actions: index, create (mysqldump → gzip → store), restore (apply .sql.gz to DB)
  - Port from `backups:list/create/restore` IPC handlers
  - Use `Artisan::call('migrate')` pattern or raw PDO for restore
  - Authorize: admin only
  - **RISK**: mysqldump path may not be in PATH on Bluehost — use `which mysqldump` at runtime, fallback gracefully

---

### Step 7 — Routes

- [ ] [Phase 1] Update `routes/web.php` — add all new resource routes under auth middleware:
  ```php
  // Admin only
  Route::middleware(['auth', 'role:admin'])->group(function () {
      Route::resource('settings', SettingsController::class)->except(['create', 'show']);
      Route::resource('invoice-sequence', InvoiceSequenceController::class)->only(['index', 'update']);
      Route::resource('audit-log', AuditLogController::class)->only(['index']);
      Route::resource('backups', BackupController::class)->only(['index', 'store', 'show']);
  });

  // Admin + Account Manager
  Route::middleware(['auth', 'role:admin,account_manager'])->group(function () {
      Route::resource('clients', ClientController::class);
      Route::resource('consultants', ConsultantController::class);
      Route::resource('timesheets', TimesheetController::class);
      Route::resource('invoices', InvoiceController::class);
      Route::resource('ledger', LedgerController::class)->only(['index']);
      Route::resource('reports', ReportController::class)->only(['index']);
      Route::resource('budget', BudgetController::class);
      Route::resource('dashboard', DashboardController::class)->only(['index']);
  });
  ```

---

### Step 8 — Verification

- [ ] [Phase 1] Run `php artisan test` — OvertimeCalculatorTest must still pass 116+/116
- [ ] [Phase 1] Run `php artisan route:list` — verify all routes registered without errors
- [ ] [Phase 1] Smoke test each controller via `php artisan tinker` or curl:
  - `GET /clients` → 200 (admin session)
  - `GET /clients` → 403 (employee session)
  - `GET /consultants` → 200 (admin session)
  - `GET /timesheets` → 200 (admin session)
  - `GET /invoices` → 200 (admin session)
  - `GET /invoices/{id}/preview` → PDF response (admin session)
  - Invoice email send → check Laravel log for SMTP attempt
- [ ] [Phase 1] Run `php artisan migrate` — confirm no new migration errors

---

## Acceptance Criteria

- [ ] `php artisan test --filter=OvertimeCalculatorTest` → 116+ passed, 0 failed
- [ ] `php artisan test` → runs without driver/environment errors
- [ ] All 13 controllers exist in `app/Http/Controllers/`
- [ ] Every controller method calls `$this->authorize()` or role check
- [ ] Every write operation logs to `audit_log` with `user_id` = `Auth::id()`
- [ ] No money values stored as FLOAT (all `DECIMAL(12,4)` in DB, `float` in PHP is acceptable for intermediate math only)
- [ ] Invoice PDF renders and downloads
- [ ] `php artisan route:list` shows all routes without errors

## Files Planned

```
app/Services/OvertimeCalculator.php          ← port of overtime.js (priority 1)
app/Services/AppService.php                  ← auditLog(), getSetting(), setSetting()
app/Services/TimesheetParseService.php       ← port of timesheets-parse.js
app/Services/PdfService.php                  ← dompdf wrapper
app/Mail/InvoiceMailable.php                 ← port of email.js (Laravel Mail)
app/Http/Controllers/ClientController.php
app/Http/Controllers/AuditLogController.php
app/Http/Controllers/DashboardController.php
app/Http/Controllers/BudgetController.php
app/Http/Controllers/LedgerController.php
app/Http/Controllers/InvoiceSequenceController.php
app/Http/Controllers/ConsultantController.php
app/Http/Controllers/SettingsController.php
app/Http/Controllers/TimesheetController.php
app/Http/Controllers/InvoiceController.php
app/Http/Controllers/ReportController.php
app/Http/Controllers/BackupController.php
resources/views/pdf/invoice.blade.php
resources/views/pdf/report-monthly.blade.php
resources/views/pdf/report-yearend.blade.php
tests/Unit/OvertimeCalculatorTest.php
routes/web.php                               ← updated with all new routes
web/phpunit.xml                              ← updated to use MySQL test DB
```

## Risks for Cursor to Watch

| Risk | Mitigation |
|---|---|
| OT floating-point mismatch (PHP vs JS) | Use `round($n, 2)` exactly as JS `Math.round(n*100)/100`. Run tests immediately. |
| dompdf CSS limitations | dompdf supports a subset of CSS2 — avoid flexbox/grid in PDF templates. Use tables for layout. |
| phpspreadsheet memory | Large XLSX files can hit PHP memory limit. Set `ini_set('memory_limit', '256M')` in TimesheetParseService. |
| `timesheet_daily_hours.day_index` | Confirm column name before building TimesheetController. If renaming, write the migration FIRST. |
| mysqldump path on Bluehost | BackupController should detect path at runtime — don't hardcode `/usr/bin/mysqldump`. |
