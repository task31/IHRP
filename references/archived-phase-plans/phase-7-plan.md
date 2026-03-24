# Phase 7 Plan — Performance Foundations

_Status: In Progress_
_Architect: Chat Pane | Agent: backend-dev_

---

## Goal

Address the four confirmed query performance issues before data volume makes them painful.
No new features — fixes only.

---

## Analysis Summary

### Missing Indexes (confirmed from migrations)
Foreign key columns get automatic indexes in MySQL, but these non-FK filter/sort columns do not:

| Table | Column(s) | Why needed |
|---|---|---|
| `consultants` | `active` | Every page load filters `WHERE active = 1` |
| `consultants` | `project_end_date` | End-date alerts query + ORDER BY |
| `placements` | `status` | Filter dropdown in Livewire PlacementManager |
| `placements` | `start_date` | ORDER BY on every load |
| `timesheets` | `invoice_status` | Ledger + timesheet list filters |
| `invoices` | `status` | Invoice list filters |
| `invoices` | `invoice_date` | ORDER BY on every load |
| `payroll_records` | `check_date` | Year-range WHERE clauses in PayrollDataService |
| `payroll_consultant_entries` | `year` | Year filter in every payroll query |

### N+2 Query Problem — ConsultantController::index()
Current query runs 2 correlated subqueries per consultant row:
```sql
(SELECT COUNT(*) FROM consultant_onboarding_items WHERE consultant_id = c.id AND completed = 1)
(SELECT COUNT(*) FROM consultant_onboarding_items WHERE consultant_id = c.id)
```
At 50 consultants = 100 extra queries on every Consultants page load.
Fix: Replace with single LEFT JOIN + conditional SUM aggregation.

### Unbounded Load — PlacementManager (Livewire)
`$query->get()` on every load + every filter change. No pagination.
Fix: `->paginate(50)` with simple prev/next controls in Blade.

### No Caching — Payroll Dashboard + Aggregate
`apiDashboard` recomputes 6 service calls (getYears, getSummary, getMonthly, getAnnualTotals,
getProjection, getGoal) on every page visit.
`apiAggregate` recomputes across all AMs on every admin visit.
Fix: Cache per `user_id + year` key, 60-minute TTL, busted when a new upload is saved.

---

## Todos

- [Phase 7] Add performance indexes migration
- [Phase 7] Fix consultant correlated subqueries → single JOIN aggregate
- [Phase 7] Add pagination to PlacementManager (Livewire + Blade)
- [Phase 7] Cache apiDashboard + apiAggregate + bust on upload

---

## Implementation Notes

### [Phase 7] Add performance indexes migration

Create a new migration: `2026_03_23_000000_add_performance_indexes.php`

```php
Schema::table('consultants', function (Blueprint $table) {
    $table->index('active');
    $table->index('project_end_date');
});
Schema::table('placements', function (Blueprint $table) {
    $table->index('status');
    $table->index('start_date');
});
Schema::table('timesheets', function (Blueprint $table) {
    $table->index('invoice_status');
});
Schema::table('invoices', function (Blueprint $table) {
    $table->index('status');
    $table->index('invoice_date');
});
Schema::table('payroll_records', function (Blueprint $table) {
    $table->index('check_date');
});
Schema::table('payroll_consultant_entries', function (Blueprint $table) {
    $table->index('year');
});
```

down() must drop all added indexes.

### [Phase 7] Fix consultant correlated subqueries

In `ConsultantController::index()`, replace the current raw SQL with:

```sql
SELECT c.*, cl.name AS client_name,
       SUM(CASE WHEN oi.completed = 1 THEN 1 ELSE 0 END) AS onboarding_complete,
       COUNT(oi.id) AS onboarding_total
FROM consultants c
LEFT JOIN clients cl ON cl.id = c.client_id
LEFT JOIN consultant_onboarding_items oi ON oi.consultant_id = c.id
WHERE c.active = 1
GROUP BY c.id, cl.name
ORDER BY c.full_name
```

This is a single query regardless of consultant count.

### [Phase 7] Add pagination to PlacementManager

In `PlacementManager.php`:
- Add `public int $page = 1;` and `public int $perPage = 50;` properties
- Change `$query->get()` to `$query->paginate($this->perPage, ['*'], 'page', $this->page)`
- Add `public int $totalPlacements = 0;` — set from `$result->total()`
- Add `public function nextPage()` and `public function prevPage()` methods
- Reset `$this->page = 1` when filters change (in `updated()`)

In `placements/index.blade.php`, add simple prev/next pagination bar below the table:
```
Showing X–Y of Z    [← Prev]  [Next →]
```
Show/hide buttons based on `$page > 1` and `($page * $perPage) < $totalPlacements`.

### [Phase 7] Cache apiDashboard + apiAggregate

In `PayrollController`:

**apiDashboard** — wrap the response data in:
```php
$cacheKey = "payroll_dashboard_{$ownerId}_{$year}";
$payload = Cache::remember($cacheKey, 3600, function () use ($data, $ownerId, $year) {
    // ... all 6 service calls ...
});
return response()->json($payload);
```

**apiAggregate** — wrap in:
```php
$cacheKey = "payroll_aggregate_{$year}";
$payload = Cache::remember($cacheKey, 3600, function () use ($data, $year) { ... });
```

**Cache bust on upload** — at the end of `PayrollController::upload()`, after the DB transaction,
add:
```php
foreach ($affectedYears as $yr) {
    Cache::forget("payroll_dashboard_{$ownerId}_{$yr}");
    Cache::forget("payroll_aggregate_{$yr}");
}
```

Goal-set endpoint (`apiGoalSet`) should also bust `payroll_dashboard_{$ownerId}_{$year}`.

---

## Files to Create/Modify

| File | Action |
|---|---|
| `database/migrations/2026_03_23_000000_add_performance_indexes.php` | CREATE |
| `app/Http/Controllers/ConsultantController.php` | MODIFY — fix index() query |
| `app/Livewire/PlacementManager.php` | MODIFY — add pagination |
| `resources/views/placements/index.blade.php` | MODIFY — add pagination controls |
| `app/Http/Controllers/PayrollController.php` | MODIFY — add cache to apiDashboard, apiAggregate, bust in upload + apiGoalSet |

---

## Test Expectations

- Run `php artisan migrate` — migration must apply cleanly with 0 errors
- Run `php artisan test` — 107 existing tests must still pass, 0 failures
- No existing test should need modification (these are additive/query-shape changes only)
