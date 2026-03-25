# IHRP Task Archive
_Completed tasks moved here from TASKLIST.md to keep the active list lean._
_Do not reopen items in this file. Cross-reference by T0XX ID if needed._

---

## ✅ Completed Phases

- [x] Phase 0 — Scaffold + Auth _(2026-03-19)_
- [x] Phase 1 — Backend Port _(2026-03-19)_
- [x] Phase 2a — Frontend Port: Table Pages _(2026-03-19)_
- [x] Phase 2b — Frontend Port: Complex Pages _(2026-03-19)_
- [x] Phase 3 — New Features: Calls + Placements _(2026-03-19)_
- [x] Phase 4 — Data Migration + QA _(2026-03-20)_
- [x] Phase 6 — Payroll Integration _(2026-03-22)_
- [x] Phase 7 — Performance + Payroll Margin Overhaul _(2026-03-23)_

---

## ✅ P0 — Blockers (Phase 5 Deploy)

- [x] **T001** — Verify Add Placement button fix is live on production. **Verified 2026-03-24.**
- [x] **T002** — Delete Dimarumba corrupted payroll rows on production DB. **Verified 2026-03-24** — no rows found, DELETE executed (0 rows).
- [x] **T003** — Re-upload all 3 AM Excel files on production. **Confirmed 2026-03-24.**
- [x] **T004** — Enter bill_rates + run Recompute Margins. **Skipped per Raf (2026-03-24)** — reopen if margins need refresh.
- [x] **T005** — Wire `.cpanel.yml` auto-deploy. **Resolved 2026-03-24.** Note: cPanel UAPI deploy path broken — `--step ssh-deploy` is standard going forward.
- [x] **T006** — Confirm `ADMIN_PASSWORD` env var on prod. **Verified 2026-03-24.**
- [x] **T007** — Confirm `php artisan storage:link` on prod. **Verified 2026-03-24.**
- [x] **T008** — Full production smoke test. **Verified 2026-03-24** — admin + AM roles, manual smoke by Raf.

---

## ✅ P1 — Data + Audit Integrity

- [x] **T009** — Fix `audit_log.description` NULL on PAYROLL_UPLOAD and RECOMPUTE_MARGINS entries. **Done 2026-03-24.**
- [x] **T010** — Add admin UI to link `users.consultant_id` → consultant record. **Done 2026-03-24.**
- [x] **T011** — Add pagination + 30-day default filter to `DailyCallReportController::index()`. **Done 2026-03-24.**
- [x] **T012** — Fix `pdo_sqlite` local PHP extension not enabled. **Done 2026-03-24:** 117 tests pass with `sqlite :memory:`.

---

## ✅ P2 — UI / Product Backlog

- [x] **T013** — Clients page: show which AM manages each client. **Done 2026-03-24.**
- [x] **T014** — Consultants page: merge onboarding 3/7 badge into unified checklist flow. **Done 2026-03-24.**
- [x] **T015** — Timesheets: format pay period as human-readable. **Done 2026-03-24.**
- [x] **T016** — Timesheets: allow editing individual entries after import. **Done 2026-03-24.**
- [x] **T017** — Timesheets: auto-populate pay period dates in template. **Done 2026-03-24.**
- [x] **T018** — Invoices: optimize PDF preview load time. **Done 2026-03-24.**
- [x] **T019** — Reports: format billed/cost columns as `$2,565.00`. **Done 2026-03-24.**
- [x] **T020** — Calls: add monthly + yearly aggregate reporting views. **Done 2026-03-24.**
- [x] **T021** — Clients: removed `po_number` from client record entirely. **Done 2026-03-25.** Applied locally + prod.

---

## ✅ P3 — Tech Debt / Infrastructure

- [x] **T022** — Verify end-to-end deploy via `python deploy.py`. **Done 2026-03-25:** SSH fallback (`--step ssh-deploy`) confirmed as standard deploy path. cPanel UAPI deploy broken. 3 migrations applied on prod. Consultant 500 (duplicate method bug) found and fixed same session.
- [x] **T023** — Enable `pdo_sqlite` in local PHP. **Done 2026-03-24** — same closure as T012.
- [x] **T026** — Admin email inbox: Microsoft Graph sync, `email_inbox_*` tables, UI, `EmailHtmlSanitizer`; 145 tests pass. **Done 2026-03-25.**
- [x] **T027** — Consultant contract file upload (unplanned — built by Cursor during T026 session). **Done 2026-03-25:** `contract_file_path` + `contract_on_file` columns added to `consultants`; `msa_contract` onboarding item seeded; `contractUpload/Path/Delete` methods in `ConsultantController`; migration applied on prod. Duplicate method bug introduced and fixed same session.

---

## ✅ P4 — Cleanup + Docs

- [x] **T024** — Remove stale files from project root. **Done 2026-03-24.**
- [x] **T025** — Consolidate all deploy knowledge. **Done 2026-03-25:** `references/deploy-learning-log.md` (full deploy incident history, append-only) + `references/deploy-preflight-checks.md` (reusable preflight checklist) committed to `origin/master`. All deploy knowledge captured — nothing in loose notes.
