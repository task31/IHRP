# IHRP Master Task List
_Last updated: 2026-03-24 (T019, T024)_
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
- [x] **T002** — Delete Dimarumba corrupted payroll rows on production DB: `DELETE FROM payroll_records WHERE YEAR(check_date) < 2015 AND user_id=7`. **Verified 2026-03-24 on production:** `user_id=7` has zero payroll rows; Leonardo Dimarumba is `user_id=4` with 6 valid 2026 `check_date` rows only; **no** `payroll_records` exist with `YEAR(check_date) < 2015` (global `GROUP BY user_id` empty). DELETE executed anyway (0 rows). Original `user_id=7` note was from non-prod / stale mapping.
- [x] **T003** — Re-upload all 3 AM Excel files on production (fixes corrupted `am_earnings` values; also populates `spread_per_hour` + `commission_pct` correctly). **Confirmed complete by Raf (2026-03-24).**
- [x] **T004** — Enter bill_rates on consultant records in production, then run Recompute Margins (`POST /payroll/recompute-margins`). **Skipped per Raf (2026-03-24)** — not doing this step now; reopen later if margins need a refresh from bill rates.
- [x] **T005** — Wire `.cpanel.yml` auto-deploy to `~/repositories/IHRP` on Bluehost so future `git push` auto-deploys (currently semi-manual). **Resolved 2026-03-24:** `BLUEHOST_CPANEL_TOKEN` in `.deploy.env` + `deploy.py` UAPI auth; `python deploy.py --step diagnose` reports **VersionControl/retrieve OK**. After `git push`, run **`python deploy.py --step deploy`** (or full flow) to trigger cPanel pull + `.cpanel.yml`. Fully unattended “on every push” without a local/CI step = optional GitHub Action → same UAPI or `ssh-deploy`.
- [x] **T006** — Confirm `ADMIN_PASSWORD` env var is set in production `.env` (seeder uses it; falls back to random if missing). **Verified 2026-03-24:** production `.env` has non-empty `ADMIN_PASSWORD=` (grep pattern; value not logged).
- [x] **T007** — Confirm `php artisan storage:link` has been run on production server. **Verified 2026-03-24:** `public/storage` → `storage/app/public` symlink present on Bluehost (`ls -la …/public/storage` shows `-> …/hr/storage/app/public`).
- [x] **T008** — Run full production smoke test: admin role (all features) + AM role (placements, calls, payroll) + security checks (SSL, APP_DEBUG=false, no stack traces). **Verified 2026-03-24:** automated checks + **manual smoke completed by Raf** (admin + AM flows).

---

## 🟡 P1 — Data + Audit Integrity

- [x] **T009** — Fix `audit_log.description` NULL on PAYROLL_UPLOAD and RECOMPUTE_MARGINS entries — populate with AM name + filename + period count for human-readable audit trail. **Done 2026-03-24:** `AppService::auditLog` optional `$description`; payroll upload + recompute margins set readable strings; PHPUnit coverage in `PayrollControllerTest`.
- [x] **T010** — Add admin UI to link `users.consultant_id` → consultant record (currently must be set manually in DB). **Done 2026-03-24:** Admin users index shows **Linked consultant**; create/edit forms already had selector — extended with **inactive linked consultant** in dropdown, **`User::consultant()`** relationship, **`consultant_id` cleared when role is admin**; tests in `AdminUserControllerTest`.
- [x] **T011** — Add pagination + 30-day default filter to `DailyCallReportController::index()` (no pagination exists; will be a problem as team grows). **Done 2026-03-24:** default rolling window last 30 days; `period` query `30|90|365|all`; history `paginate(50)` + `withQueryString()`; filter chips + range label + `links()` on `calls/index`; tests in `DailyCallReportControllerTest`.
- [x] **T012** — Fix `pdo_sqlite` local PHP extension not enabled — blocks running full PHPUnit test suite locally without connecting to MySQL. **Done 2026-03-24:** verified `pdo_sqlite` on dev PHP 8.3 (WinGet); `web/tests/bootstrap.php` fails fast with pointer to `references/local-php-sqlite-testing.md`; **117 tests** pass with `sqlite :memory:`.

---

## 🟠 P2 — UI / Product Backlog

- [x] **T013** — Clients page: show which AM manages each client. **Done 2026-03-24:** nullable `clients.account_manager_id` → `users` (`nullOnDelete`); `Client::accountManager()`; admin create/edit modal **Account manager** dropdown (active AM users); table column **Account manager**; JSON index includes `accountManager`; validation `exists` + `role=account_manager`; audit `MUTABLE` includes `account_manager_id`; migration `2026_03_24_120000_add_account_manager_id_to_clients_table`; tests `ClientControllerTest` (**121 tests**).
- [x] **T014** — Consultants page: merge onboarding 3/7 badge into unified checklist flow. **Done 2026-03-24:** removed duplicate **W-9** pills next to name (W-9 stays in checklist + Actions **W-9** upload); **Checklist** column is a single **Progress** control (bar + `n/7`) that opens the same modal; removed redundant **Checklist** action link; modal **Mark/Done** toggles **admin-only**, AMs see read-only **Pending/Done** + note; admin hint for W-9 upload.
- [x] **T015** — Timesheets: format pay period as human-readable ("Mar 9 – Mar 13, 2026") instead of raw dates. **Done 2026-03-24:** `App\Support\PayPeriodFormatter`; index table + view modal (`pay_period_label` JSON); import wizard preview; audit + duplicate-skip strings; `PayPeriodFormatterTest`.
- [ ] **T016** — Timesheets: allow editing individual entries after import
- [ ] **T017** — Timesheets: auto-populate pay period dates in template based on known biweekly schedule (currently generates with today + 13 days)
- [ ] **T018** — Invoices: optimize PDF preview load time
- [x] **T019** — Reports: format billed/cost columns as `$2,565.00` (currently `2565.0000`). **Done 2026-03-24:** `App\Support\ReportMoney` + monthly/year-end PDF blades (Billed/Cost cells + readable headers); CSV/JSON unchanged for tooling; `ReportMoneyTest`.
- [ ] **T020** — Calls: add monthly + yearly aggregate reporting views
- [ ] **T021** — Clients: consider hiding `po_number` field in client modal once all placements have PO numbers assigned

---

## 🔵 P3 — Tech Debt / Infrastructure

- [ ] **T022** — Fully wire and test `.cpanel.yml` end-to-end with GitHub push → auto-deploy (related to T005; T005 is the prod fix, this is the verification/testing step). **Next:** run one full `python deploy.py --step deploy` after a trivial commit to confirm `.cpanel.yml` tasks on server; optional CI for push-triggered deploy.
- [x] **T023** — Enable `pdo_sqlite` in local PHP so full PHPUnit suite runs locally without MySQL (ties to T012). **Done 2026-03-24** — same closure as T012.

---

## 🧹 P4 — Cleanup + Docs

- [x] **T024** — Remove stale files from project root: `smoke_debug.py`, `smoke_test.py`, completed `phase-N-plan.md` files that are no longer needed. **Done 2026-03-24:** `phase-*-plan.md` moved to `references/archived-phase-plans/` (+ README); smoke `*.py` not present in repo; `CLAUDE.md` SSOT row updated.
- [ ] **T025** — Consolidate all deploy knowledge (LiteSpeed handler notes, deploy process, known DB issues) — already partially captured in `.cursor/rules/ihrp-deploy.mdc` and `references/` folder; ensure nothing is only in loose notes

---

## 📋 How to Work This List

1. Pick the next unchecked item from the top (lowest P-level first).
2. Mark it `[ → in progress]` when starting.
3. Mark it `[x]` when confirmed done (not just coded — verified).
4. If a task reveals new work, append it as a new `T0XX` item at the bottom of the correct section.
5. **Production default path:** after **`git push`** (or when shipping), the **Architect** delegates to the **`ihrp-deploy-expert`** subagent (Cursor **Task** tool) to run **`deploy.py`** / the runbook in **`.cursor/rules/ihrp-deploy.mdc`** — not ad hoc shell from Chat. Migrations still need **explicit Raf confirmation** before `--force`.
6. Deploy agent (`ihrp-deploy-expert`) owns execution of P0 deploy/production steps; Architect reviews output and DEVLOG.
7. Backend agent (`ihrp-backend-expert`) handles all backend/migration/service items.

---

## 🗂 Phase 5 Close Criteria

Phase 5 is not done until all P0 items (T001–T008) are checked off and production smoke test passes.

**Status 2026-03-24:** P0 checklist **T001–T008** all `[x]`; automated + **manual** production smoke confirmed. Architect may still append DEVLOG **REVIEW** + phase close commit per workflow.
