# MPG Business Model — SSOT for All IHRP Calculations

> This file is the single source of truth for how MatchPointe Group (MPG) makes money.
> Every calculation in IHRP must conform to this model. Never deviate.
> Read this before writing any code that touches earnings, margins, payroll, or commissions.

---

## Who Is Who

| Role | Description |
|---|---|
| **MPG** | MatchPointe Group — the staffing agency itself |
| **AM (Account Manager)** | An MPG employee who places consultants with clients. Paid 100% commission — no base salary. Multiple AMs exist, each with their own payroll sheet. |
| **Consultant** | The worker placed at a client site. Completely separate from AMs — NOT the same person. |
| **Client** | The company that hires consultants through MPG. |

---

## The Money Flow

1. A consultant works at a client site.
2. The **client pays MPG** at the **Bill Rate** (per hour).
3. **MPG pays the consultant** at the **Pay Rate** (per hour).
4. **Spread** = Bill Rate − Pay Rate. This is MPG's total markup on that consultant.
5. The Spread is **split 3 ways**: MPG's cut + AM's cut + Recruiter's cut = 100% of Spread.
6. The placing AM earns their % of the Spread.
7. If a recruiter sourced the candidate for that AM, the recruiter earns their % of the Spread.
8. MPG keeps what remains.

---

## Calculation Rules — Never Deviate

### AM Earnings (per consultant, per period)
```
AM Earnings = hours × (Bill Rate − Pay Rate) × commission%
           = hours × spread_per_hour × commission_pct
```
- AM Earnings is a **COST to MPG**, not revenue.
- It is deducted from the Spread.
- **NEVER** calculate AM Earnings as a % of Bill Rate or Pay Rate alone — always % of the Spread.

### Agency Gross Profit (per consultant)
```
Agency Gross Profit = (hours × Bill Rate) − AM Earnings
```
- This is what MPG actually keeps after paying the AM.

### Spread (per consultant hour)
```
Spread = Bill Rate − Pay Rate
```

---

## Commission % Rules

- Commission % is **NOT a global fixed rate**.
- It **varies per consultant** and comes from the AM's payroll Excel file.
- Consultants are grouped in the Excel by commission % tier (e.g., 10%, 20%, 35%, 50% sections).
- Each section has a subtotal. The sheet ends with the AM's total Gross Pay.
- The tier % represents **this person's cut** of the total spread — NOT the total AM commission.

### How `commission_pct` Is Derived in the Parser

The `PayrollParseService` infers `commission_pct` from the **"Commission X% Subtotal" row labels** in the Excel sheet. Each group of consultant rows is followed by a subtotal row whose label contains the tier percentage (e.g., `"Commission 35% Subtotal"`). The parser:
1. Reads rows top-to-bottom.
2. When it encounters a subtotal row, it captures the tier % from the label.
3. All preceding consultant rows in that group are assigned that `commission_pct`.

`pay_rate` is NOT in the Excel. It can be derived if needed: `pay_rate = bill_rate − spread_per_hour` (requires `bill_rate` entered manually on the consultant record).

### Recruiter Role

- An AM can also be a **Recruiter** for other AMs' placements.
- Their payroll sheet includes BOTH their own placements AND consultants they recruited for other AMs.
- Higher tiers (e.g., 50%) = typically their own placements (they are the AM).
- Lower tiers (e.g., 10%, 20%, 35%) = they recruited the candidate for another AM.
- **Each person's sheet shows only THEIR cut.** The tier % in their sheet is their personal commission rate, not the full split.
- Example (illustrative only — actual splits vary per deal): Total spread = $10/hr. MPG keeps 50%, placing AM gets 35%, recruiter gets 15%. The recruiter's sheet would show this consultant at 15% tier; the placing AM's sheet would show them at 35%. Split percentages are negotiated per placement, not system-enforced.

---

## Payroll Excel Structure

- Each AM has **one payroll sheet**.
- Rows = consultants the AM earned on (own placements + recruited for others), grouped by commission % tier.
- Pay calc section columns:
  - **Col A** = Consultant name
  - **Col B** = Hours worked
  - **Col C** = Spread per hour (bill_rate − pay_rate = markup per hour)
  - **Col D** = Total spread (hours × spread_per_hour)
  - "Commission X% Subtotal" rows define the tier for the preceding consultant rows
- AM Earnings for a consultant = col D × tier% (their cut of the total spread).
- All AM payroll sheets follow the same structure.

---

## Multi-AM Architecture

- IHRP supports **multiple AMs**.
- Each AM has their own `payroll_records` and `payroll_consultant_entries`.
- **Admins** see data for all AMs.
- **AMs** only see their own data (scoped by `user_id`).

---

## Recompute Margins Behavior

`POST /payroll/recompute-margins` (admin-only) recalculates `revenue` and `gross_margin` for all `payroll_consultant_entries` using current `bill_rates` from the `consultants` table.

Rules:
- **Never modifies `am_earnings`** — that value comes from the uploaded Excel and is treated as the source of truth.
- Requires `bill_rate` to be entered on the consultant record first; entries with null `bill_rate` are skipped.
- Intended use: after bulk-entering bill rates, run once to populate margin columns across historical data.
- Edge case: if `am_earnings` was corrupted by a previous bad upload, fix it by re-uploading the AM's Excel file (T003 pattern) — do not manually patch `am_earnings` values in DB.

---

## What This Means in Code

| Concept | DB Field | Formula |
|---|---|---|
| Client pays MPG | `bill_rate` | — |
| MPG pays consultant | `pay_rate` | — |
| Spread | derived | `bill_rate − pay_rate` |
| AM commission % | `commission_pct` on `payroll_consultant_entries` | varies per consultant, parsed from Excel tier label |
| AM Earnings (cost) | `am_earnings` on `payroll_consultant_entries` | `col D × commission_pct` = `hours × spread × commission_pct` |
| Agency Gross Profit | `gross_margin` on `payroll_consultant_entries` | `(hours × bill_rate) − am_earnings` |
| Agency Revenue | `revenue` on `payroll_consultant_entries` | `hours × bill_rate` |

---

_Last updated: 2026-03-25 — added commission_pct derivation, recomputeMargins rules, recruiter split clarification_
_Originally established as SSOT: 2026-03-23 by Raf_
