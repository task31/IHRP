# Phase 6 Plan — Payroll Integration
_Created: 2026-03-22_
_Mode: SEQUENTIAL_

## Context

Port the MyPayroll Flask app into IHRP as a native Laravel payroll module.
Admin uploads `.xlsx` payroll files per AM; data is parsed and stored in MySQL;
AMs see their own payroll dashboard; admins see aggregate + per-AM comparison.
Source app: `C:\Users\zobel\Claude-Workspace\projects\MyPayroll\`
Full spec: `payroll-integration-plan.md` (read it before starting — it has all
critical porting notes, edge cases, and rationale).

**Note on Phase 5:** This phase can be implemented locally while Phase 5 deploy
is being resolved. The new migrations and code are additive — no existing files
are modified except `routes/web.php` and `layouts/app.blade.php`.

## Dependency

Requires [Phase 4] complete (roles, placements, consultants all exist). ✅
Phase 5 (deploy) can be in-progress — implement locally, deploy alongside Step 5
infrastructure once DNS/hosting is resolved.

Unlocks: Payroll dashboard live for all 4 AMs at hr.matchpointegroup.com.

---

## Reference Files (read before implementing)

| File | Purpose |
|---|---|
| `payroll-integration-plan.md` | Complete spec — porting notes, edge cases, upload flow, test plan |
| `web/app/Services/TimesheetParseService.php` | Pattern: PhpSpreadsheet usage, memory limit, file storage |
| `web/app/Http/Controllers/TimesheetController.php` | Pattern: auth, upsert, JSON/View dual response, auditLog |
| `web/resources/views/layouts/app.blade.php` | Sidebar nav link pattern + global `apiFetch()` JS |
| `MyPayroll/app.py` | Source: all data logic to port |
| `MyPayroll/static/js/app.js` | Source: all Chart.js chart builders, drawer, upload handler |

---

## To-Dos

### Step 1 — Migrations (5 files, run in order)

- [ ] [Phase 6] Create migration: `create_payroll_uploads_table`
  Columns: `id, user_id (FK→users), original_filename, stored_path, stop_name (VARCHAR),
  record_count, warnings (JSON nullable), timestamps`
- [ ] [Phase 6] Create migration: `create_payroll_records_table`
  Columns: `id, user_id (FK→users), check_date (DATE), gross_pay, net_pay, federal_tax,
  state_tax, social_security, medicare, retirement_401k, health_insurance,
  other_deductions, commission_subtotal, salary_subtotal, timestamps`
  Constraint: `UNIQUE(user_id, check_date)`
  All money: `DECIMAL(12,4)`
- [ ] [Phase 6] Create migration: `create_payroll_consultant_entries_table`
  Columns: `id, user_id (FK→users), consultant_name (VARCHAR), year (SMALLINT),
  revenue, cost, margin, pct_of_total (all DECIMAL(12,4)),
  consultant_id (FK→consultants nullable), timestamps`
  Constraint: `UNIQUE(user_id, consultant_name, year)`
- [ ] [Phase 6] Create migration: `create_payroll_consultant_mappings_table`
  Columns: `id, raw_name (VARCHAR), consultant_id (FK→consultants),
  user_id (FK→users), created_by (FK→users), timestamps`
  Constraint: `UNIQUE(raw_name, user_id)`
- [ ] [Phase 6] Create migration: `create_payroll_goals_table`
  Columns: `id, user_id (FK→users), year (SMALLINT), goal_amount (DECIMAL(12,4)),
  timestamps`
  Constraint: `UNIQUE(user_id, year)`
- [ ] [Phase 6] Run: `php artisan migrate` — confirm 5 new tables created

### Step 2 — Models (5 files)

- [ ] [Phase 6] Create `app/Models/PayrollUpload.php`
  `belongsTo(User::class)`, local scope `scopeForOwner`
- [ ] [Phase 6] Create `app/Models/PayrollRecord.php`
  `belongsTo(User::class)`, `$casts = ['check_date' => 'date']`, `scopeForOwner`
- [ ] [Phase 6] Create `app/Models/PayrollConsultantEntry.php`
  `belongsTo(User::class)`, `belongsTo(Consultant::class)`, `scopeForOwner`
- [ ] [Phase 6] Create `app/Models/PayrollConsultantMapping.php`
  `belongsTo(User::class)`, `belongsTo(Consultant::class)`, `scopeForOwner`
- [ ] [Phase 6] Create `app/Models/PayrollGoal.php`
  `belongsTo(User::class)`, `scopeForOwner`

### Step 3 — PayrollParseService + Unit Tests

- [ ] [Phase 6] Create `app/Services/PayrollParseService.php`
  See `payroll-integration-plan.md §PayrollParseService` for full method signatures.
  **Critical porting notes (all must be handled):**
  - `"Social Security "` has a trailing space — normalize via `trim()` during header detection
  - Date cells: use `ExcelDate::isDateTime($cell)`, fallback to `DateTime::createFromFormat('m/d/Y', $value)`
  - Stop condition: `str_starts_with($row[0], $stopName)` — stop reading, don't include that row
  - `"Commission...Subtotal"` detection: also handle typo `"Subttal"`
  - `ini_set('memory_limit', '256M')` at parse start
  - Sheet skip list: `['Payroll Summary', 'Full Time Recon']`
  - Header search: scan up to row 20 for the row containing "Check Date"
  - Returns `PayrollParseResult` DTO: `{ ownerName, records[], consultantsByYear[], warnings[] }`
- [ ] [Phase 6] Create `tests/Unit/PayrollParseServiceTest.php`
  Required tests (from `payroll-integration-plan.md §Test Plan`):
  - `test_parse_returns_owner_name`
  - `test_parse_correct_record_count`
  - `test_check_dates_are_iso_strings`
  - `test_grouping_sums_by_check_date`
  - `test_trailing_space_column_name_handled` ← critical
  - `test_commission_subtotal_typo_handled` ← critical
  - `test_consultant_data_aggregates_by_year`
  - `test_stop_name_row_excluded_from_records` ← critical
  Use the real fixture: `MyPayroll/03.12.2026.xlsx` (copy to `tests/Fixtures/` if needed)
- [ ] [Phase 6] Run: `php artisan test --filter=PayrollParseServiceTest` — all must pass

### Step 4 — PayrollDataService + Unit Tests

- [ ] [Phase 6] Create `app/Services/PayrollDataService.php`
  Methods: `getYears, getSummary, getMonthly, getAnnualTotals, getConsultants,
  getProjection, getAggregateSummary, getPerAmBreakdown`
  **Projection logic:**
  - Linear extrapolation: `(YTD net ÷ periods_elapsed) × 26`
  - Periods elapsed = count of `payroll_records` for that user+year
  - Suppress if periods elapsed < 4 → return `{ projectionSuppressed: true, reason: 'too_early' }`
  - Suppress if no records → return `{ projectionSuppressed: true, reason: 'no_data' }`
  **getPerAmBreakdown:** `User::where('role', 'account_manager')->orderBy('name')->get()`
  — never hard-code AM list. Left-join payroll_records so AMs with no data show $0.
  **Null safety:** all percentage calcs guard against division-by-zero when total = $0.
- [ ] [Phase 6] Create `tests/Unit/PayrollDataServiceTest.php`
  Required tests (from `payroll-integration-plan.md §Test Plan`):
  - `test_projection_suppressed_under_4_periods`
  - `test_projection_returns_no_data_for_empty_am`
  - `test_projection_linear_extrapolation_correct`
  - `test_aggregate_sums_across_all_owners`
  - `test_per_am_breakdown_includes_all_account_managers`
  - `test_per_am_breakdown_shows_zero_for_empty_am`
  - `test_aggregate_handles_zero_total_without_division_error`
  - `test_consultant_pct_of_total_sums_to_100`
- [ ] [Phase 6] Run: `php artisan test --filter=PayrollDataServiceTest` — all must pass

### Step 5 — PayrollController

- [ ] [Phase 6] Create `app/Http/Controllers/PayrollController.php`
  Methods: `index, upload, apiDashboard, apiConsultants, apiAggregate, apiGoalSet,
  apiMappings, apiMappingsUpdate`
  **Private helper — always use for read endpoints:**
  ```php
  private function getOwnerId(Request $request): int
  {
      if (Auth::user()->isAdmin() && $request->has('user_id')) {
          $user = User::findOrFail($request->integer('user_id'));
          abort_if($user->role !== 'account_manager', 422, 'Target user must be an account manager');
          return $user->id;
      }
      return Auth::id();
  }
  ```
  **Upload flow (admin-only) — see full 8-step flow in `payroll-integration-plan.md §upload flow`:**
  Validate file → validate user_id (must be AM) → validate stop_name →
  parse via PayrollParseService → resolve consultant mappings →
  DB::transaction { upsert records, delete+reinsert consultant_entries for affected_years,
  create PayrollUpload } → auditLog → return JSON
  **All money fields:** DECIMAL(12,4), never float arithmetic in PHP

### Step 6 — Routes

- [ ] [Phase 6] Add to `web/routes/web.php` inside the auth + account_manager group:
  ```php
  Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
  Route::get('payroll/api/dashboard', [PayrollController::class, 'apiDashboard'])->name('payroll.api.dashboard');
  Route::get('payroll/api/consultants', [PayrollController::class, 'apiConsultants'])->name('payroll.api.consultants');
  ```
  Add to admin-only group:
  ```php
  Route::post('payroll/upload', [PayrollController::class, 'upload'])->name('payroll.upload');
  Route::get('payroll/api/aggregate', [PayrollController::class, 'apiAggregate'])->name('payroll.api.aggregate');
  Route::post('payroll/api/goal', [PayrollController::class, 'apiGoalSet'])->name('payroll.api.goal.set');
  Route::get('payroll/api/mappings', [PayrollController::class, 'apiMappings'])->name('payroll.api.mappings');
  Route::put('payroll/api/mappings', [PayrollController::class, 'apiMappingsUpdate'])->name('payroll.api.mappings.update');
  ```
- [ ] [Phase 6] Verify: `php artisan route:list | grep payroll` — 8 routes listed

### Step 7 — Blade Views

- [ ] [Phase 6] Create `resources/views/payroll/index.blade.php`
  Wraps in `<x-app-layout>`. Chart.js via CDN (pinned version — match Alpine CDN pattern).
  **Layout sections (top to bottom):**
  1. Header: Title + Year selector + AM selector (admin only) + Upload button (admin only)
  2. 4 KPI cards: YTD Net, YTD Gross, Taxes Paid, Projected Annual
     (projection: show "Too early to project" / "No payroll data yet" instead of $0)
  3. Bar chart (bi-weekly/monthly toggle) + Tax donut (2/3 + 1/3 grid)
  4. YoY cumulative line + Goal tracker (2-col grid)
  5. Multi-year trend (full width)
  6. Period data table
  7. "Consultant Breakdown" button → slide-in drawer
  8. Admin-only: AM comparison table (total net/gross per AM for selected year)
  **JS approach:**
  - `IS_ADMIN = @json(auth()->user()->role === 'admin')` gates admin-only UI
  - Single `fetch('/payroll/api/dashboard?year=Y&user_id=X')` on page load
  - `fetch('/payroll/api/consultants?year=Y')` on drawer open only
  - `fetch('/payroll/api/aggregate?year=Y')` for admin comparison section
  - All POST via existing `apiFetch()` global helper (CSRF-aware)
  - AM selector change triggers dashboard re-fetch with new user_id
  **Upload modal (admin only — three required fields):**
  1. File input (.xlsx only)
  2. AM selector — dropdown of all `account_manager` users (required)
  3. Stop name — text input. Label: "Stop reading at row starting with..."
     Placeholder: "e.g. Rafael Zobel". Help text: "This is the AM's full name
     as it appears in the payroll file."
  Upload response shows: record count, years affected, unresolved consultant names
  (each name is a button that opens the mappings panel inline)
- [ ] [Phase 6] Create `resources/views/payroll/mappings.blade.php` (or inline admin modal)
  Table: Raw Name | Matched Consultant | Action
  Shows entries from `payroll_consultant_mappings` where `consultant_id IS NULL`
  Admin selects from consultant dropdown → PUT to `/payroll/api/mappings`

### Step 8 — Sidebar Nav

- [ ] [Phase 6] Edit `web/resources/views/layouts/app.blade.php`
  Inside the `@can('account_manager')` block, after "Placements":
  ```blade
  <a href="{{ route('payroll.index') }}" @class([...active/inactive classes...])>Payroll</a>
  ```
  Match the exact class pattern used for the Placements link above it.

### Step 9 — Feature Tests

- [ ] [Phase 6] Create `tests/Feature/PayrollControllerTest.php`
  Uses `RefreshDatabase` + SQLite in-memory.
  Required tests (from `payroll-integration-plan.md §Test Plan`):
  - Access control: all routes (admin/AM/unauthenticated)
  - Upload: valid file + valid stop_name → success
  - Upload: missing stop_name → 422
  - Upload: missing user_id → 422
  - Upload: user_id referencing admin → 422
  - Upload: invalid MIME → 422
  - Upload: file > 50 MB → 422
  - Upload: non-admin → 403
  - Dashboard endpoint returns correct JSON shape
  - `test_am_sees_only_own_data`
  - `test_admin_sees_aggregate`
  - `test_admin_can_switch_am_view`
  - `test_admin_cannot_pass_own_user_id_to_read_endpoint`
  - Goal set/get CRUD (scoped per user+year)
  - Goal set: user_id referencing admin → 422
  - Mapping CRUD: unresolved list, update mapping, verify auto-resolve on next upload
- [ ] [Phase 6] Run: `php artisan test` — all tests pass including OvertimeCalculatorTest (44 tests, 120 assertions)

### Step 10 — Manual Smoke Test

- [ ] [Phase 6] Upload Raf's payroll XLSX (stop_name = "Rafael Zobel") → verify record count
- [ ] [Phase 6] Upload AM #2 and AM #3 payroll files
- [ ] [Phase 6] AM #4 empty state: dashboard loads without errors, KPI cards show $0/—,
  charts render (empty datasets, not broken), projection says "No payroll data yet",
  goal tracker shows 0% or "No goal set"
- [ ] [Phase 6] Admin aggregate: sums correctly, AM #4 shows $0 row (never omitted)
- [ ] [Phase 6] Admin AM-switcher: lists all `account_manager` users
- [ ] [Phase 6] Consultant drawer: shows scoped data; empty for AM #4
- [ ] [Phase 6] AM login: sees only own payroll data (not other AMs')
- [ ] [Phase 6] Unresolved consultant names: surface in upload response and mapping UI
- [ ] [Phase 6] Goal set for AM #4: shows 0% progress
- [ ] [Phase 6] Re-upload wrong stop_name → wrong record count (surfaced in response)
- [ ] [Phase 6] Re-upload correct stop_name → correct count; upsert does not wipe earlier records
- [ ] [Phase 6] All 4 chart types render (bar, donut, line, trend) for each AM with data

---

## Acceptance Criteria

- [ ] 5 migrations exist and run cleanly on both SQLite (test) and MySQL (local)
- [ ] `PayrollParseServiceTest` — all 8 tests pass
- [ ] `PayrollDataServiceTest` — all 8 tests pass
- [ ] `PayrollControllerTest` — all route/scope/validation tests pass
- [ ] OT regression: `php artisan test --filter=OvertimeCalculatorTest` — 44 tests, 120 assertions, 0 failures
- [ ] AM #4 empty state: no broken charts, no division-by-zero errors, no console errors
- [ ] Admin cross-AM scoping: AMs cannot access other AMs' data via `?user_id=X`
- [ ] Upload modal: all 3 fields validated before submit

---

## Files Planned

### New files
- `database/migrations/YYYY_MM_DD_create_payroll_uploads_table.php`
- `database/migrations/YYYY_MM_DD_create_payroll_records_table.php`
- `database/migrations/YYYY_MM_DD_create_payroll_consultant_entries_table.php`
- `database/migrations/YYYY_MM_DD_create_payroll_consultant_mappings_table.php`
- `database/migrations/YYYY_MM_DD_create_payroll_goals_table.php`
- `app/Models/PayrollUpload.php`
- `app/Models/PayrollRecord.php`
- `app/Models/PayrollConsultantEntry.php`
- `app/Models/PayrollConsultantMapping.php`
- `app/Models/PayrollGoal.php`
- `app/Services/PayrollParseService.php`
- `app/Services/PayrollDataService.php`
- `app/Http/Controllers/PayrollController.php`
- `resources/views/payroll/index.blade.php`
- `resources/views/payroll/mappings.blade.php`
- `tests/Unit/PayrollParseServiceTest.php`
- `tests/Unit/PayrollDataServiceTest.php`
- `tests/Feature/PayrollControllerTest.php`

### Modified files
- `web/routes/web.php` (add 8 payroll routes)
- `web/resources/views/layouts/app.blade.php` (add Payroll nav link)
