# IHRP Master Task List
_Last updated: 2026-03-24_
_Source of truth for all remaining work. Check items off as completed. Append new items — never delete._

---

## ✅ Completed Phases (closed — do not reopen)

- [x] Phase 0 — Scaffold + Auth _(2026-03-19)_
- [x] Phase 1 — Backend Port _(2026-03-19)_
- [x] Phase 2a — Frontend Port: Table Pages _(2026-03-19)_
- [x] Phase 2b — Frontend Port: Complex Pages _(2026-03-19)_
- [x] Phase 3 — New Features: Calls + Placements _(2026-03-19)_
- [x] Phase 4 — Data Migration + QA _(2026-03-20)_
- [x] Phase 6 — Payroll Integration _(2026-03-22)_
- [x] Phase 7 — Performance + Payroll Margin Overhaul _(2026-03-23)_

---

## 🔴 P0 — Blockers (must close before Phase 5 is done)

- [x] **T001** — Verify Add Placement button fix is live on production (`wire:click.self="cancelForm"` + Alpine scope fix committed). **Verified working (2026-03-24) after layout Livewire script fix + regression test added.**
- [ ] **T002** — Delete Dimarumba corrupted payroll rows on production DB: `DELETE FROM payroll_records WHERE YEAR(check_date) < 2015 AND user_id=7`
- [ ] **T003** — Re-upload all 3 AM Excel files on production (fixes corrupted `am_earnings` values; also populates `spread_per_hour` + `commission_pct` correctly)
- [ ] **T004** — Enter bill_rates on consultant records in production, then run Recompute Margins (`POST /payroll/recompute-margins`)
- [ ] **T005** — Wire `.cpanel.yml` auto-deploy to `~/repositories/IHRP` on Bluehost so future `git push` auto-deploys (currently semi-manual)
- [ ] **T006** — Confirm `ADMIN_PASSWORD` env var is set in production `.env` (seeder uses it; falls back to random if missing)
- [ ] **T007** — Confirm `php artisan storage:link` has been run on production server
- [ ] **T008** — Run full production smoke test: admin role (all features) + AM role (placements, calls, payroll) + security checks (SSL, APP_DEBUG=false, no stack traces)

---

## 🟡 P1 — Data + Audit Integrity

- [ ] **T009** — Fix `audit_log.description` NULL on PAYROLL_UPLOAD and RECOMPUTE_MARGINS entries — populate with AM name + filename + period count for human-readable audit trail
- [ ] **T010** — Add admin UI to link `users.consultant_id` → consultant record (currently must be set manually in DB)
- [ ] **T011** — Add pagination + 30-day default filter to `DailyCallReportController::index()` (no pagination exists; will be a problem as team grows)
- [ ] **T012** — Fix `pdo_sqlite` local PHP extension not enabled — blocks running full PHPUnit test suite locally without connecting to MySQL

---

## 🟠 P2 — UI / Product Backlog

- [ ] **T013** — Clients page: show which AM manages each client
- [ ] **T014** — Consultants page: merge onboarding 3/7 badge into unified checklist flow
- [ ] **T015** — Timesheets: format pay period as human-readable ("Mar 9 – Mar 13, 2026") instead of raw dates
- [ ] **T016** — Timesheets: allow editing individual entries after import
- [ ] **T017** — Timesheets: auto-populate pay period dates in template based on known biweekly schedule (currently generates with today + 13 days)
- [ ] **T018** — Invoices: optimize PDF preview load time
- [ ] **T019** — Reports: format billed/cost columns as `$2,565.00` (currently `2565.0000`)
- [ ] **T020** — Calls: add monthly + yearly aggregate reporting views
- [ ] **T021** — Clients: consider hiding `po_number` field in client modal once all placements have PO numbers assigned

---

## 🔵 P3 — Tech Debt / Infrastructure

- [ ] **T022** — Fully wire and test `.cpanel.yml` end-to-end with GitHub push → auto-deploy (related to T005; T005 is the prod fix, this is the verification/testing step)
- [ ] **T023** — Enable `pdo_sqlite` in local PHP so full PHPUnit suite runs locally without MySQL (ties to T012)

---

## 🧹 P4 — Cleanup + Docs

- [ ] **T024** — Remove stale files from project root: `smoke_debug.py`, `smoke_test.py`, completed `phase-N-plan.md` files that are no longer needed
- [ ] **T025** — Consolidate all deploy knowledge (LiteSpeed handler notes, deploy process, known DB issues) — already partially captured in `.cursor/rules/ihrp-deploy.mdc` and `references/` folder; ensure nothing is only in loose notes

---

## 📋 How to Work This List

1. Pick the next unchecked item from the top (lowest P-level first).
2. Mark it `[ → in progress]` when starting.
3. Mark it `[x]` when confirmed done (not just coded — verified).
4. If a task reveals new work, append it as a new `T0XX` item at the bottom of the correct section.
5. Deploy agent (`ihrp-deploy-expert`) handles all P0 deploy/production items.
6. Backend agent (`ihrp-backend-expert`) handles all backend/migration/service items.

---

## 🗂 Phase 5 Close Criteria

Phase 5 is not done until all P0 items (T001–T008) are checked off and production smoke test passes.
