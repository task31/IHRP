# IHRP Master Task List
_Last updated: 2026-03-25_
_Source of truth for all remaining work. Check items off as completed. Append new items — never delete._
_Completed tasks → `references/tasklist-archive.md`_

---

## Production Status
**Production last verified: 2026-03-25 (Raf)** — SSH deploy + all migrations applied. Working tree clean. No open tasks.

---

## 📋 How to Work This List

1. Pick the next unchecked item from the top (lowest P-level first).
2. Mark it `[ → in progress]` when starting.
3. Mark it `[x]` when confirmed done (not just coded — verified).
4. If a task reveals new work, append it as a new `T0XX` item at the bottom of the correct section.
5. **Production default path:** after **`git push`**, use **`python deploy.py --step ssh-deploy`** (cPanel UAPI deploy path is broken — `repository_root` param rejected). Then run `python deploy.py --step migrate-status` and `python deploy.py --step run-migrations` if migrations are pending. Migrations need **explicit Raf confirmation** before running.
6. Deploy agent (`ihrp-deploy-expert`) owns execution of deploy/production steps; Architect reviews output and DEVLOG.
7. Backend agent (`ihrp-backend-expert`) handles all backend/migration/service items.

---

## ✅ All tasks complete as of 2026-03-25

---

## 🔲 Open Tasks

- [x] **T028** — Commission tier + placement role overhaul. Done 2026-03-30.
- [x] **T029** — Rate resolution script: cross-workbook pay/bill lookup. Done 2026-03-30.

---

## T029 — Rate Resolution Script (Cross-Workbook Pay/Bill Lookup)

**Why:** Consultants in IHRP are missing `bill_rate` and `pay_rate` values because those
rates live in the AM's Excel workbooks, not in the app. Without them, `revenue` and
`gross_margin` on `payroll_consultant_entries` cannot be computed. This script reads all
three payroll workbooks, resolves pay/bill rates using the cross-workbook ownership rule
(see BUSINESS_MODEL.md), and produces a DB update preview before touching anything.

**This is a standalone Python script — no Laravel changes.**

**Workbook paths (OneDrive Desktop):**
- `C:/Users/zobel/OneDrive/Desktop/Dimarumba PayBill rates.xlsx`
- `C:/Users/zobel/OneDrive/Desktop/sibug paybillrates.xlsx`
- `C:/Users/zobel/OneDrive/Desktop/Harsono paybillrates.xlsx`

---

### Cursor Prompt — paste this directly

Read CLAUDE.md and BUSINESS_MODEL.md before writing any code.

**T029 — Rate Resolution Script**

**Context**

Consultants in IHRP are missing bill_rate / pay_rate values. The rates live in three
payroll Excel workbooks. This script reads those workbooks, applies the cross-workbook
ownership rule from BUSINESS_MODEL.md, and outputs a preview CSV before touching the DB.

The key ownership rule:
- A consultant entry in Sibug's workbook with commission tier = 50% means Raf IS the AM.
  His workbook has the full pay/bill. Use it directly.
- A consultant entry with tier < 50% means Raf is the recruiter. The placing AM's workbook
  (Dimarumba or Harsono) has the full pay/bill. Look it up there.
- If the AM's workbook also lacks pay/bill for that consultant → unresolved. Do not guess.

**Create the file: scripts/resolve_rates.py**

The script must do the following in order:

PHASE 1 — Read all three Rate Sheets

For each workbook, read the "Rate Sheet" tab using openpyxl (data_only=True).
Parse two sections per sheet:
- "FULLY KNOWN RATES" section: extract name, tax_class, pay_rate, bill_rate, spread
- "SPREAD ONLY" section: extract name, commission_tier (as float), spread_per_hour

Store each workbook's data in a dict keyed by normalized name.
Name normalization: lowercase, strip whitespace, remove suffixes like "(current)"/"(prior)".
When multiple rows exist for the same name (e.g. Gabriela Ibarra current/prior), keep the
most recent (labeled "current" or the last row in the section).

PHASE 2 — Cross-workbook lookup for Sibug spread-only entries

For each spread-only row in Sibug's sheet:
1. Look up the normalized name in Dimarumba's fully-known dict.
2. If not found, look up in Harsono's fully-known dict.
3. If found with pay_rate and bill_rate:
   - Verify: abs((bill_rate - pay_rate) - spread_per_hour) <= 0.02
   - If verified: mark status = "resolved_cross", source_am = "Dimarumba" or "Harsono"
   - If spread mismatch > 0.02: mark status = "spread_mismatch", flag for review
4. If found but that AM also has them spread-only: status = "unresolved"
5. If not found anywhere: status = "unresolved"

For Sibug's fully-known rows (tier = 0.5): status = "resolved_own", source_am = "Sibug"
For Dimarumba's fully-known rows: status = "resolved_own", source_am = "Dimarumba"
For Harsono's fully-known rows: status = "resolved_own", source_am = "Harsono"

PHASE 3 — Query IHRP database

Read DB credentials from web/.env (parse the file manually — do not import Laravel).
Connect using pymysql. Run:

    SELECT id, full_name, bill_rate, pay_rate FROM consultants ORDER BY full_name

For each resolved consultant from Phases 1-2, attempt to match against the DB by
normalized name. A match is: normalized(db.full_name) == normalized(workbook.name).

PHASE 4 — Output two CSV files in scripts/output/

1. rate-resolution-ledger.csv
   Columns: workbook_source, consultant_name, tax_class, pay_rate, bill_rate,
            spread_per_hour, commission_tier, status, source_am, db_id,
            db_current_bill_rate, db_current_pay_rate, spread_verified, notes

2. rate-db-update-preview.csv
   Only rows where status IN ("resolved_own", "resolved_cross") AND db_id IS NOT NULL
   AND (db_current_bill_rate != bill_rate OR db_current_pay_rate != pay_rate)
   Columns: db_id, consultant_name, current_bill_rate, proposed_bill_rate,
            current_pay_rate, proposed_pay_rate, source_am, status

PHASE 5 — Optional DB update (flag-gated)

Add a --apply flag. When NOT passed, print a summary and exit (preview mode).
When --apply is passed:
- Print the preview table and ask for confirmation: "Apply X updates to consultants table? (yes/no)"
- If confirmed: UPDATE consultants SET bill_rate=?, pay_rate=? WHERE id=?
  Only update pay_rate if the current DB value is 0.0000 (never overwrite a non-zero pay_rate)
  Always update bill_rate if it differs.
- After all updates: call POST /payroll/recompute-margins via requests.post() using the
  admin session cookie from web/.env APP_URL. Print the response status.
- Log all changes to scripts/output/rate-update-log.txt with timestamp.

**Requirements:**
- pip install openpyxl pymysql python-dotenv requests
- Default run (no flags): preview only, no DB writes
- All money comparisons use round(value, 4)
- Print a clear summary at the end: X resolved_own, X resolved_cross, X unresolved, X spread_mismatch

**Acceptance Criteria**
- Running `python scripts/resolve_rates.py` produces both CSV files with no errors
- rate-resolution-ledger.csv contains all consultants from all three workbooks
- rate-db-update-preview.csv contains only rows that differ from current DB values
- --apply flag prompts for confirmation before writing anything
- Script is idempotent — running it twice produces the same output

**Commit message:** "feat: add rate resolution script with cross-workbook pay/bill lookup (T029)"

Then write a [BUILD] block to DEVLOG.md in the standard format.
