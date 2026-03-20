# Phase 3 Plan — New Features
_Created: 2026-03-19_
_Mode: SEQUENTIAL_

> **For agentic workers:** Read `CLAUDE.md` and the last `✅ [REVIEW]` block in `DEVLOG.md` first.
> Phase 3 adds three net-new features that do not exist in the Electron app.
> The Electron source (`Payroll/`) has no call reporting or placement tables — design from spec below.
> All Phase 2 Blade conventions apply: `$request->expectsJson()` dual-response, `$this->authorize()` on every method.

**Goal:** Add employee call reporting, placement management, and an employee-specific dashboard.
These are the three features that justify the migration from a single-user desktop app to a multi-user web app.

**Architecture:** Same stack as Phase 2 — Blade + Alpine.js. Placements uses Livewire (complex CRUD
with inline state). Call reporting is plain Blade (simple form + table).

---

## Context

Phase 2 delivered all 8 Electron screens as browser pages. Phase 3 adds the features that only
make sense in a multi-user web context:
- Employees can now log in → they need something to do (call reports + view their placement)
- Account Managers need to manage candidate placements in the app (not in a spreadsheet)

No carry-forwards from Phase 2 review that affect Phase 3 (the one open item — auditLog actor gap
for queue contexts — is a Phase 4 concern).

---

## Dependency

**Requires:** Phase 2 complete ✅ (sidebar nav, auth, all controllers exist)
**Unlocks:** Phase 4 (data migration needs all tables fully defined before migrating SQLite data)

---

## Schema Design (new tables)

### `daily_call_reports`

One row per employee per day. Employees submit once per day — duplicate detection on `(user_id, report_date)`.

```
daily_call_reports
  id                  BIGINT UNSIGNED PK
  user_id             BIGINT UNSIGNED FK → users.id
  report_date         DATE NOT NULL
  calls_made          UNSIGNED INT DEFAULT 0
  contacts_reached    UNSIGNED INT DEFAULT 0
  submittals          UNSIGNED INT DEFAULT 0   -- candidates submitted to clients
  interviews_scheduled UNSIGNED INT DEFAULT 0
  notes               TEXT NULL
  created_at / updated_at TIMESTAMPS

  UNIQUE(user_id, report_date)
```

### `placements`

One row per consultant placement. A placement is a confirmed job assignment (distinct from a
consultant record — a consultant may have multiple placements over time).

```
placements
  id                  BIGINT UNSIGNED PK
  consultant_id       BIGINT UNSIGNED FK → consultants.id
  client_id           BIGINT UNSIGNED FK → clients.id
  placed_by           BIGINT UNSIGNED FK → users.id  (the account manager who placed them)
  job_title           VARCHAR(255) NULL
  start_date          DATE NOT NULL
  end_date            DATE NULL
  pay_rate            DECIMAL(12,4) NOT NULL
  bill_rate           DECIMAL(12,4) NOT NULL
  status              ENUM('active','ended','cancelled') DEFAULT 'active'
  notes               TEXT NULL
  created_at / updated_at TIMESTAMPS
```

---

## Step 1 — DB Migrations

Flesh out the two stub migrations from Phase 0.

**Files to modify:**
- `web/database/migrations/2026_03_19_184101_create_daily_call_reports_table.php` — add full column set
- `web/database/migrations/2026_03_19_184102_create_placements_table.php` — add full column set

Add columns exactly as specified in the schema above. Use `DECIMAL(12,4)` for rates (never FLOAT).

- [x] **[Phase 3]** Update `daily_call_reports` migration with full schema
- [x] **[Phase 3]** Update `placements` migration with full schema
- [x] **[Phase 3]** Run `php artisan migrate:fresh --seed` — verify both tables created with correct columns
- [x] **[Phase 3]** Commit: `feat: flesh out daily_call_reports and placements migrations`

---

## Step 2 — Models + Controllers

**Models to create:**
- `web/app/Models/DailyCallReport.php` — fillable fields, `belongsTo(User::class)`, `report_date` cast to date
- `web/app/Models/Placement.php` — fillable fields, `belongsTo(Consultant::class)`, `belongsTo(Client::class)`, `belongsTo(User::class, 'placed_by')`

**Controllers:**

`DailyCallReportController` (check if it already exists from Phase 1 stubs — update if so, create if not):
- `index(Request)` — list reports (admin/AM: all; employee: own only)
- `store(Request)` — submit today's report (employee + AM + admin)
- `aggregate(Request)` — summary view by employee/date range (admin + AM only)

`PlacementController` (new):
- `index(Request)` — list placements (admin/AM: all; employee: own only)
- `store(Request)` — create placement (admin + AM only)
- `update(Request, Placement)` — edit placement (admin + AM only)
- `destroy(Request, Placement)` — cancel/end placement (admin only)

Role rules:
- `calls_made` / `contacts_reached` etc. submitted by all roles (employee, AM, admin)
- Aggregate view: account_manager + admin only
- Placements CRUD: account_manager + admin
- Placement read (own): employee

- [x] **[Phase 3]** Create/update `DailyCallReport` model
- [x] **[Phase 3]** Create `Placement` model
- [x] **[Phase 3]** Create/update `DailyCallReportController` (index, store, aggregate)
- [x] **[Phase 3]** Create `PlacementController` (index, store, update, destroy)
- [x] **[Phase 3]** Register routes in `routes/web.php`
- [x] **[Phase 3]** Commit: `feat: add DailyCallReport + Placement models and controllers`

**Routes to add:**
```php
// Call reports — all authenticated users can submit; aggregate is AM+ only
Route::get('/calls', [DailyCallReportController::class, 'index'])->name('calls.index');
Route::post('/calls', [DailyCallReportController::class, 'store'])->name('calls.store');
Route::get('/calls/report', [DailyCallReportController::class, 'aggregate'])->name('calls.report');

// Placements
Route::get('/placements', [PlacementController::class, 'index'])->name('placements.index');
Route::post('/placements', [PlacementController::class, 'store'])->name('placements.store');
Route::put('/placements/{placement}', [PlacementController::class, 'update'])->name('placements.update');
Route::delete('/placements/{placement}', [PlacementController::class, 'destroy'])->name('placements.destroy');
```

---

## Step 3 — Call Reporting Page (`/calls`)

**Files:**
- Create: `web/resources/views/calls/index.blade.php`
- Modify: `web/app/Http/Controllers/DailyCallReportController.php` — add Blade branch to `index()`

**Page layout:**

Header: "Daily Call Reports" + today's date

**Submit form** (top of page, shown to all roles):
```
Date (default: today, editable)   Calls Made [___]   Contacts Reached [___]
Submittals [___]   Interviews Scheduled [___]
Notes [textarea]
[Submit Report]
```
- If today's report already exists: prefill form and show "Update" instead of "Submit"
- Toast on success

**History table** (below form):
- Employee sees own rows only
- Admin/AM sees all employees (add "Employee" column)
- Columns: Date | Employee (AM/admin only) | Calls | Contacts | Submittals | Interviews | Notes

- [x] **[Phase 3]** Write `calls/index.blade.php`
- [x] **[Phase 3]** Add Blade branch to `DailyCallReportController::index()`
- [x] **[Phase 3]** Smoke: employee submits report → appears in table
- [x] **[Phase 3]** Smoke: admin sees all employees' rows
- [ ] **[Phase 3]** Commit: `feat: add call reporting page`

---

## Step 4 — Call Report Aggregate (`/calls/report`)

**Files:**
- Create: `web/resources/views/calls/report.blade.php`
- Modify: `web/app/Http/Controllers/DailyCallReportController.php` — add Blade branch to `aggregate()`

**Page layout:**

Header: "Call Report Summary" | Access: account_manager + admin only (abort 403 for employees)

**Filter bar:** Employee (dropdown, all or specific) | Date from | Date to | [Apply]

**Summary table:**
- One row per employee
- Columns: Employee | Total Days | Total Calls | Total Contacts | Total Submittals | Total Interviews | Avg Calls/Day

**Daily detail table** (below summary, shows filtered rows):
- Columns: Date | Employee | Calls | Contacts | Submittals | Interviews | Notes

- [x] **[Phase 3]** Write `calls/report.blade.php`
- [x] **[Phase 3]** Add Blade branch to `DailyCallReportController::aggregate()`
- [x] **[Phase 3]** Smoke: AM sees aggregate summary
- [x] **[Phase 3]** Smoke: employee → 403 on `/calls/report`
- [x] **[Phase 3]** Commit: `feat: add call report aggregate view`

---

## Step 5 — Placement Management (`/placements`)

**Files:**
- Create: `web/resources/views/placements/index.blade.php`
- Create: `web/resources/views/livewire/placement-manager.blade.php`
- Create: `web/app/Livewire/PlacementManager.php`
- Modify: `web/app/Http/Controllers/PlacementController.php` — add Blade branch to `index()`

**Why Livewire:** Placements have inline status changes (active → ended/cancelled), date editing,
and real-time filtering that benefit from reactive updates without page reloads.

**Livewire component (`PlacementManager`):**

State: `$placements` (loaded on mount), `$filters` (consultant, client, status), `$showForm`

Methods:
- `mount()` — load placements with filters (AM/admin: all; employee: own only)
- `save()` — create or update placement
- `updateStatus($id, $status)` — inline status change
- `updatedFilters()` — re-query on filter change

**Page layout (PlacementManager component):**

**Header:** "Placements" + "Add Placement" button (AM/admin only)

**Filter bar:** Consultant | Client | Status (active/ended/cancelled/all)

**Table:**
- Columns: Consultant | Client | Job Title | Start | End | Pay Rate | Bill Rate | Status | Actions
- Employee sees own row only (no Actions column)
- Admin/AM see Edit + End/Cancel buttons per row

**Add/Edit modal:**
- Fields: Consultant (dropdown), Client (dropdown), Job Title, Start Date, End Date (optional),
  Pay Rate, Bill Rate, Notes, Status

- [x] **[Phase 3]** Write `PlacementManager` Livewire component (PHP class)
- [x] **[Phase 3]** Write `placement-manager.blade.php` Livewire view
- [x] **[Phase 3]** Write `placements/index.blade.php` (wraps Livewire component)
- [x] **[Phase 3]** Add Blade branch to `PlacementController::index()`
- [x] **[Phase 3]** Smoke: AM creates placement → appears in table
- [x] **[Phase 3]** Smoke: status change works inline
- [x] **[Phase 3]** Smoke: employee sees only own placement, no edit actions
- [x] **[Phase 3]** Commit: `feat: add placement management with Livewire`

---

## Step 6 — Employee Dashboard

The existing `/dashboard` shows `—` stats for employees because `DashboardController::index()`
(`GET /dashboard/stats`) uses `abort_unless(Gate::allows('account_manager'), 403)`.

**Approach:** Dual-view in `DashboardController::page()` — detect role and pass a flag to the
Blade view. The Blade view switches layout based on role.

**Employee dashboard shows:**
1. **My Placement** card — current active placement (consultant name, client, job title, start date, status). If none: "No active placement on file."
2. **My Call Reports** — last 7 days summary (calls made, submittals) + link to `/calls`
3. **Today's Report** — quick-submit inline form (same fields as `/calls` form, saves to daily_call_reports)

**Changes:**
- `DashboardController::page()` — query `Placement::where('consultant_id', ...)` for employee's linked consultant (via `users.consultant_id` FK set by admin)
- `dashboard.blade.php` — add `@if(auth()->user()->role === 'employee')` branch with employee layout

- [x] **[Phase 3]** Update `DashboardController::page()` to pass employee placement + call data
- [x] **[Phase 3]** Update `dashboard.blade.php` with employee branch
- [x] **[Phase 3]** Smoke: employee sees placement card + call summary + quick-submit form
- [x] **[Phase 3]** Smoke: admin still sees original 4-card dashboard
- [x] **[Phase 3]** Commit: `feat: add employee dashboard with placement and call summary`

---

## Step 7 — Sidebar + Final Smoke

**Sidebar updates needed in `layouts/app.blade.php`:**
```blade
{{-- All authenticated users see Calls --}}
<a href="{{ route('calls.index') }}" ...>Calls</a>

@can('account_manager')
{{-- existing links: Clients, Consultants, Timesheets, Invoices, Ledger, Reports --}}
<a href="{{ route('placements.index') }}" ...>Placements</a>
@endcan
```

**Final smoke checklist:**
- [ ] All 3 roles (admin, account_manager, employee) can submit call reports
- [ ] Admin/AM sees aggregate at `/calls/report`; employee gets 403
- [ ] AM can create/edit/close placements; employee sees own placement only
- [ ] Employee dashboard shows placement card + call summary
- [ ] Admin dashboard unchanged (4 stat cards)
- [ ] `php artisan test --filter=OvertimeCalculatorTest` still passes (regression)

- [ ] **[Phase 3]** Update sidebar in `app.blade.php`
- [ ] **[Phase 3]** Remove dead `stub` response block (lines 48–53) from `DashboardController::index()` — employee path now server-rendered, stub is unreachable
- [x] **[Phase 3]** Run full smoke checklist above
- [ ] **[Phase 3]** Commit: `feat: Phase 3 complete — calls, placements, employee dashboard`

---

## Acceptance Criteria

- [ ] Employee can log in, submit a daily call report, and see their own placement
- [ ] Account Manager can view all call reports + aggregate, manage placements
- [ ] Admin has full access to all Phase 3 features
- [ ] All new routes have `$this->authorize()` or equivalent role check
- [ ] New tables use `DECIMAL(12,4)` for money fields
- [ ] Audit log entries written for placement creates/updates/status changes
- [ ] OvertimeCalculatorTest still passes (no regression)
- [ ] `php artisan route:list` — no errors

## Files Planned

```
web/database/migrations/2026_03_19_184101_create_daily_call_reports_table.php  ← update stub
web/database/migrations/2026_03_19_184102_create_placements_table.php          ← update stub
web/app/Models/DailyCallReport.php                                              ← new
web/app/Models/Placement.php                                                    ← new
web/app/Http/Controllers/DailyCallReportController.php                         ← new or update stub
web/app/Http/Controllers/PlacementController.php                                ← new
web/app/Livewire/PlacementManager.php                                           ← new
web/resources/views/calls/index.blade.php                                       ← new
web/resources/views/calls/report.blade.php                                      ← new
web/resources/views/placements/index.blade.php                                  ← new
web/resources/views/livewire/placement-manager.blade.php                        ← new
web/resources/views/dashboard.blade.php                                         ← update (employee branch)
web/app/Http/Controllers/DashboardController.php                                ← update (employee data)
web/resources/views/layouts/app.blade.php                                       ← update (Calls + Placements nav)
web/routes/web.php                                                               ← add new routes
```

## Risks

| Risk | Mitigation |
|---|---|
| Employee→consultant link: `users.consultant_id` may not be set for all employees | Dashboard gracefully handles null — show "No placement linked" instead of crashing |
| Livewire PlacementManager + Alpine toast on same page | Same pattern as TimesheetWizard — `@livewireStyles`/`@livewireScripts` already in layout |
| Call report duplicate on same day | `UNIQUE(user_id, report_date)` in DB + check-then-upsert in controller |
| Placement rates vs consultant rates | Placements snapshot rates at creation (same pattern as timesheets) — don't reference consultant rates live |
