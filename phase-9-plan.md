# Phase 9 Plan — Bug Fixes: P0 Authorization + P1 Correctness
_Created: 2026-03-30_
_Mode: SEQUENTIAL_

## Context

Three documented bugs from the Cursor codebase audit (improvements.md) that are confirmed
and reproducible. No new features — correctness and security fixes only.

**P0 — Placements authorization gap**: The Livewire `PlacementManager` scopes AM queries
correctly, but the raw HTTP endpoints (`PlacementController@index` JSON path,
`PlacementPolicy::update`) do not. Any AM can call `GET /placements` with
`Accept: application/json` and receive all placements across all AMs.

**P1a — ConsultantController GROUP BY + MySQL strict mode**: `index()` uses
`SELECT c.*` with `GROUP BY c.id` plus a LEFT JOIN column (`cl.name`) that is not
functionally dependent on `c.id`. Fails with `ONLY_FULL_GROUP_BY` (MySQL default).
`endDateAlerts()` uses MySQL-specific `DATE_ADD(CURDATE(), ...)` which breaks SQLite tests.

**P1b — Server path info leak**: `w9Path()` and `contractPath()` return the absolute
server filesystem path (`/home2/rbjwhhmy/...`) in JSON responses. Only the filename is needed.

## Dependency
- No prior phase required. These are isolated bug fixes to existing files.
- Unlocks: clean auth story before any future AM-facing feature work.

---

## To-Dos

- [ ] [Phase 9] Fix `PlacementPolicy::view()` — add ownership check: admin OR placed_by === user->id
- [ ] [Phase 9] Fix `PlacementPolicy::update()` — add ownership check: admin OR placed_by === user->id
- [ ] [Phase 9] Fix `PlacementController@index` JSON path — scope query to own placements for AM role
- [ ] [Phase 9] Fix `ConsultantController@index` GROUP BY — add `cl.id` to GROUP BY clause
- [ ] [Phase 9] Fix `ConsultantController@endDateAlerts` — replace MySQL DATE_ADD/CURDATE with PHP-computed cutoff date string
- [ ] [Phase 9] Fix `ConsultantController@w9Path` JSON branch — remove absolute `$full` path from response, return only `fileName`
- [ ] [Phase 9] Fix `ConsultantController@contractPath` JSON branch — same as w9Path
- [ ] [Phase 9] Add test: AM JSON endpoint returns only own placements
- [ ] [Phase 9] Add test: AM cannot update another AM's placement (expect 403)
- [ ] [Phase 9] Run full test suite — must pass at 145+ tests

---

## Acceptance Criteria

- [ ] `GET /placements` with `Accept: application/json` as AM returns only placements where `placed_by = Auth::id()`
- [ ] `PUT /placements/{id}` as AM on a placement owned by a different AM returns 403
- [ ] Consultants page does not 500 on MySQL with `ONLY_FULL_GROUP_BY` enabled
- [ ] `endDateAlerts` query uses no MySQL-specific functions — passes under SQLite
- [ ] W9 and contract JSON responses contain only `fileName`, no server path
- [ ] All existing tests still pass (no regression)

## Files to Modify

- `web/app/Policies/PlacementPolicy.php`
- `web/app/Http/Controllers/PlacementController.php`
- `web/app/Http/Controllers/ConsultantController.php`
- `web/tests/Feature/PlacementPageTest.php` (add ownership tests)

## Exact Fix Specifications

### PlacementPolicy.php

`view()` — replace:
```php
return in_array($user->role, ['admin', 'account_manager'], true);
```
with:
```php
return $user->role === 'admin' || $placement->placed_by === $user->id;
```

`update()` — replace:
```php
return in_array($user->role, ['admin', 'account_manager'], true);
```
with:
```php
return $user->role === 'admin' || $placement->placed_by === $user->id;
```

### PlacementController.php — index() JSON path

Add AM scoping after `$query = Placement::query()...`:
```php
if ($user->role !== 'admin') {
    $query->where('placed_by', $user->id);
}
```

### ConsultantController.php — index()

Change:
```sql
GROUP BY c.id
```
to:
```sql
GROUP BY c.id, cl.id, cl.name
```

### ConsultantController.php — endDateAlerts()

Replace:
```php
$rows = DB::select('
    SELECT c.*, cl.name AS client_name
    FROM consultants c
    LEFT JOIN clients cl ON cl.id = c.client_id
    WHERE c.active = 1
      AND c.project_end_date IS NOT NULL
      AND c.project_end_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
    ORDER BY c.project_end_date ASC
', [$days]);
```
with:
```php
$cutoff = now()->addDays($days)->toDateString();
$rows = DB::select('
    SELECT c.*, cl.name AS client_name
    FROM consultants c
    LEFT JOIN clients cl ON cl.id = c.client_id
    WHERE c.active = 1
      AND c.project_end_date IS NOT NULL
      AND c.project_end_date <= ?
    ORDER BY c.project_end_date ASC
', [$cutoff]);
```

### ConsultantController.php — w9Path() and contractPath()

In the `$request->expectsJson()` branch of each method, replace:
```php
return response()->json(['path' => $full, 'fileName' => $consultant->w9_file_path]);
```
with:
```php
return response()->json(['fileName' => $consultant->w9_file_path]);
```
(Same pattern for contractPath — replace `$full` reference.)

### PlacementPageTest.php — new tests

Add two test methods (see Cursor prompt below for full code).

---

## Commit Message
`"fix(auth): placement ownership scoping + consultant SQL correctness (Phase 9)"`
