# Phase 8 Plan — Rate Resolution (Formal Completion)
_Created: 2026-03-30_
_Mode: SEQUENTIAL_

## Context

T028 and T029 (completed 2026-03-30) built `scripts/resolve_rates.py` and applied production
DB updates for all provable consultant rates. The script already handles:
- Reading Rate Sheet tabs from all 3 workbooks
- Cross-workbook resolution (Sibug spread-only vs Dimarumba/Harsono fully-known)
- W2 and C2C spread verification
- Ledger + DB preview CSV generation
- `--apply` flag for DB writes

**What this phase completes (the 4 remaining gaps from PayBillRates.md):**

1. **Workbook Rate Sheet updater** — fill proven pay/bill cells and source notes into the
   3 Excel files directly. Currently the workbooks still have blank cells where rates are now proven.

2. **Exception report** — formal CSV listing every unresolved, spread-only, special, and
   ambiguous consultant with a `suggested_next_action` column.

3. **Artisan recompute command** — `php artisan payroll:recompute-am {user_id}`.
   The current script tries to POST to the HTTP endpoint, which requires an admin session and
   fails from CLI/SSH. Cursor must wrap the existing `PayrollController::recomputeMargins`
   logic as a proper artisan command callable without HTTP auth.

4. **Output path + ledger column alignment** — current script writes to `scripts/output/`.
   All outputs must move to `references/`. Ledger must add the missing columns from
   PayBillRates.md spec so the CSV is audit-complete.

## Reference Documents

- `C:\Users\zobel\Downloads\PayBillRates.md` — master spec. Decision-complete. Read this
  before implementing any of the 4 tasks. All business rules, evidence rules, status enums,
  column specs, and workbook-specific consultant lists are defined there.
- `BUSINESS_MODEL.md` — spread formulas (W2 vs C2C). Mandatory read before touching
  any rate or spread logic.
- `scripts/resolve_rates.py` — existing script. All 4 tasks either extend this file or
  complement it. Do not break existing `--apply` behaviour.

## Dependency

Phase 7 (Performance) must be complete — ✅ it is.
T028 and T029 must be complete — ✅ they are (production DB already updated 2026-03-30).

---

## To-Dos

### [Phase 8] Task 1 — Artisan Recompute Command

- [ ] [Phase 8] Create `web/app/Console/Commands/RecomputeAmMargins.php`
      Signature: `payroll:recompute-am {user_id}`
      - Accept a single `{user_id}` argument (the AM's users.id)
      - Re-implement the exact same logic as `PayrollController::recomputeMargins()`:
        1. Load all PayrollConsultantEntries for that user
        2. Load bill_rate map from consultants table (only where bill_rate is not null)
        3. For each entry: revenue = hours × bill_rate; margin = revenue − am_earnings
        4. Also derive pay_rate = bill_rate − spread_per_hour (only where pay_rate is null)
        5. Recompute pct_of_total per year group
        6. Bust Laravel cache keys: `payroll_dashboard_{user_id}_{year}` and `payroll_aggregate_{year}`
        7. Call AppService::auditLog with action RECOMPUTE_MARGINS
      - Output: print "Updated {N} entries for {AM name}" on success
      - Error handling: if user not found or role is not account_manager, exit with error message
      - Do NOT change PayrollController — this command is a CLI parallel, not a refactor
      - Uses DB and Laravel services directly (no HTTP, no session, no auth guard)

### [Phase 8] Task 2 — Extend resolve_rates.py: Output Path + Exception Report

- [ ] [Phase 8] Change all output paths in `scripts/resolve_rates.py` from
      `scripts/output/` to `references/`. Create `references/` if it doesn't exist.
      Affected files:
        - `references/rate-resolution-ledger.csv`
        - `references/rate-db-update-preview.csv`
        - `references/rate-update-log.txt`

- [ ] [Phase 8] Add exception report generation to `scripts/resolve_rates.py`.
      After building the ledger, write `references/rate-resolution-exceptions.csv`.
      A row enters the exception report if its status is one of:
        `unresolved`, `spread_only`, `spread_mismatch`, or `special_nonhourly`
      OR if `needs_manual_review` is true.
      Required columns (exact names, exact order):
        workbook, consultant, normalized_name, tax_class,
        known_spread, known_pay_rate, known_bill_rate,
        reason_not_updated, source_tabs_checked,
        ihrp_match_status, suggested_next_action
      `suggested_next_action` values (use these exactly):
        `need_external_client_contract`
        `need_manual_name_mapping`
        `nonhourly_excluded`
        `current_rate_unclear`
        `formula_conflict_review`
        `no_consultant_record_in_ihrp`
      Derive `suggested_next_action` as follows:
        - status = `special_nonhourly` → `nonhourly_excluded`
        - db_id is None → `no_consultant_record_in_ihrp`
        - status = `spread_mismatch` → `formula_conflict_review`
        - status = `unresolved` and source_am is not None → `current_rate_unclear`
        - status = `unresolved` and source_am is None → `need_external_client_contract`
        - `ihrp_match_status` = `multiple_candidates` → `need_manual_name_mapping`

- [ ] [Phase 8] Add `ihrp_match_status` column to the ledger in `resolve_rates.py`.
      Use these exact values:
        `exact_name_match` — fuzzy_lookup returned norm key exactly
        `mapped_name_match` — fuzzy_lookup matched via prefix/suffix
        `no_match` — db_id is None
      Add this column to both the ledger CSV and the LedgerRow dataclass.
      The existing `db_id` attach logic already resolves matches — just label how the
      match was made.

### [Phase 8] Task 3 — Workbook Rate Sheet Updater

- [ ] [Phase 8] Create `scripts/rate-resolution/update_workbooks.py`
      This script reads the ledger CSV and updates the Rate Sheet tab in each of the
      3 workbooks. It does NOT re-parse workbooks from scratch — it reads the existing
      Rate Sheet structure cell by cell and fills in values where the ledger says
      `status = resolved_current` or `resolved_own` or `resolved_cross`.

      **Input:**
        --ledger   Path to `references/rate-resolution-ledger.csv` (required)
        --sibug    Path to sibug paybillrates.xlsx (required)
        --dimarumba  Path to Dimarumba PayBill rates.xlsx (required)
        --harsono  Path to Harsono paybillrates.xlsx (required)
        --dry-run  Print what would be changed without saving (default: dry-run mode on)
        --apply    Actually save the workbooks (requires confirmation prompt)

      **Workbook update logic:**
        1. Load workbook with openpyxl (data_only=False to preserve formulas)
        2. Find the Rate Sheet tab
        3. Scan rows to find the header row (look for "Name" / "Pay Rate" / "Bill Rate" columns)
        4. For each data row:
           a. Read the consultant name in column A (normalize it)
           b. Look up that normalized name in the ledger CSV for this workbook
           c. If ledger status is resolved (resolved_own / resolved_cross) and pay_rate + bill_rate
              are populated:
              - Write pay_rate value to the Pay Rate column cell
              - Write bill_rate value to the Bill Rate column cell
              - If a Notes/Source column exists, write the source note (see note format below)
           d. If ledger status is unresolved / spread_only: leave pay/bill cells untouched;
              if Notes column exists, write "Spread only — pay/bill not provable from available data"
           e. If a spread formula column exists and it was already a formula, do NOT overwrite it —
              preserve the existing formula
        5. Do NOT change row positions, merge cells, column widths, fills, borders, or fonts
        6. Save workbook in place (same path as input)

      **Source note format** (write to Notes/Source cell where column exists):
        Resolved own:    `Confirmed from Rate Sheet explicit pay/bill pair`
        Resolved cross:  `Confirmed via {source_am} workbook — spread verified ({tax_class})`
        Spread only:     `Spread only visible across checked tabs; pay/bill not provable from workbook`

      **Safety rules:**
        - Never write to a cell that already has a formula (check cell.data_type == 'f')
        - Never write to cells outside the Rate Sheet tab
        - Never delete rows or columns
        - If the workbook cannot be opened or the Rate Sheet tab is missing, print an error
          and skip that workbook (do not abort the entire run)
        - After saving, print a summary: "X cells updated in {workbook name}"

---

## Acceptance Criteria

- [ ] `php artisan payroll:recompute-am {user_id}` runs to completion for each of the 3 AMs
      without requiring an HTTP session; prints updated entry count
- [ ] Running `python scripts/resolve_rates.py --sibug X --dimarumba X --harsono X` produces:
      - `references/rate-resolution-ledger.csv` (with `ihrp_match_status` column)
      - `references/rate-db-update-preview.csv`
      - `references/rate-resolution-exceptions.csv`
      All 3 land in `references/` not `scripts/output/`
- [ ] `python scripts/rate-resolution/update_workbooks.py --ledger references/rate-resolution-ledger.csv --sibug X --dimarumba X --harsono X --dry-run`
      prints a diff of what would change without saving
- [ ] Running with `--apply` updates Rate Sheet cells for all resolved consultants,
      leaves spread-only and unresolved rows untouched, and preserves all formulas
- [ ] No formula errors in any updated workbook
- [ ] 145 existing PHP tests still pass (`php artisan test`)

## Files Planned

**New files:**
- `web/app/Console/Commands/RecomputeAmMargins.php`
- `scripts/rate-resolution/update_workbooks.py`

**Modified files:**
- `scripts/resolve_rates.py` — output path change + exception report + ihrp_match_status column

**Output artifacts (not committed):**
- `references/rate-resolution-ledger.csv`
- `references/rate-db-update-preview.csv`
- `references/rate-resolution-exceptions.csv`
- `references/rate-update-log.txt`
