# Phase 4 Plan — Data Migration + QA
_Created: 2026-03-20_
_Mode: SEQUENTIAL_

## Context

All app functionality is complete (Phases 0–3). This phase migrates the live data from
the Electron desktop app's SQLite database into the Laravel MySQL database, validates
data integrity, and performs a full regression pass before Phase 5 (deploy).

The Electron app's live data lives at:
  `C:/Users/zobel/AppData/Roaming/payroll-app/payroll.db`

Three Artisan commands were written by Claude Code and already committed (commit 4316bac):
- `php artisan migrate:from-sqlite` — migrates all 11 tables, idempotent
- `php artisan migrate:validate`    — row counts + money checksums
- `php artisan migrate:files`       — copies invoice PDFs, XLSXs, W-9s

Migration has already been run and validated (12/12 checks ✅).

## Dependency

Requires Phase 3 complete (all features must exist before QA). ✅

---

## To-Dos

### Completed by Claude Code (2026-03-20)

- [x] [Phase 4] Enable pdo_sqlite (already enabled in Laragon's php.ini)
- [x] [Phase 4] Write MigrateFromSqlite Artisan command
- [x] [Phase 4] Write ValidateMigration Artisan command
- [x] [Phase 4] Write MigrateFiles Artisan command
- [x] [Phase 4] Run migration: 2 clients, 2 consultants, 2 timesheets, 2 invoices, 14 onboarding items, 28 daily hours, 2 invoices, 2 line items, 15 audit log rows
- [x] [Phase 4] Run validation: 12/12 ✅ — $6,840 billable, $5,380 cost match SQLite
- [x] [Phase 4] Run file migration: 2 invoice PDFs + 2 timesheet XLSXs copied
- [x] [Phase 4] Confirm users.consultant_id dropdown exists in create/edit views
- [x] [Phase 4] OT tests: 44 passed, 120 assertions, 0 failures

### Remaining for Cursor

- [ ] [Phase 4] Delete `smoke_debug.py` from project root
- [ ] [Phase 4] Delete `smoke_test.py` from project root
- [ ] [Phase 4] Commit: `chore: remove smoke test Python scripts`

### Regression Smoke Test (manual — Raf runs this)

**Role: admin** (`admin@matchpointegroup.com` / `changeme123`)
- [ ] [Phase 4] Login works
- [ ] [Phase 4] Dashboard stat cards show real data (not all zeros) — check clients count, consultants, open invoices
- [ ] [Phase 4] Clients: list shows 2 migrated clients
- [ ] [Phase 4] Consultants: list shows 2 migrated consultants with correct rates
- [ ] [Phase 4] Invoices: list shows 2 migrated invoices — click one → PDF previews correctly
- [ ] [Phase 4] Timesheets: list shows 2 migrated timesheets with correct OT breakdown
- [ ] [Phase 4] Reports: year-end PDF generates without error
- [ ] [Phase 4] Settings: loads all 6 tabs, existing settings values are present
- [ ] [Phase 4] Placements: list loads, admin can create/edit/delete
- [ ] [Phase 4] Calls: submit a report, view own history
- [ ] [Phase 4] Admin Users: create a user → assign consultant via dropdown → save → consultant_id set

**Role: account_manager**
- [ ] [Phase 4] Dashboard loads
- [ ] [Phase 4] Placements: full access (create/edit/status change)
- [ ] [Phase 4] Calls Report: aggregate table visible

**Role: employee** (create a test employee user and link to consultant 1 first)
- [ ] [Phase 4] Employee dashboard: My Placement card shows data
- [ ] [Phase 4] Placements: read-only, scoped to own consultant
- [ ] [Phase 4] Calls: can submit, sees own history
- [ ] [Phase 4] Calls Report: 403 as expected

---

## Acceptance Criteria

- [ ] `php artisan migrate:validate` shows all ✅ (already passing)
- [ ] Invoice PDF preview works for at least one migrated invoice
- [ ] OT tests: 44 passed, 120 assertions, 0 failures (already passing)
- [ ] `smoke_*.py` files removed from repo
- [ ] All 3 roles pass the regression smoke checklist

## Files Modified/Created

| File | Action | Status |
|---|---|---|
| `web/app/Console/Commands/MigrateFromSqlite.php` | Create | ✅ Done |
| `web/app/Console/Commands/ValidateMigration.php` | Create | ✅ Done |
| `web/app/Console/Commands/MigrateFiles.php` | Create | ✅ Done |
| `smoke_debug.py` | Delete | ⏳ Cursor |
| `smoke_test.py` | Delete | ⏳ Cursor |
