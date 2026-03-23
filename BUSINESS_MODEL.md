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

### Recruiter Role

- An AM can also be a **Recruiter** for other AMs' placements.
- Their payroll sheet includes BOTH their own placements AND consultants they recruited for other AMs.
- Higher tiers (e.g., 50%) = typically their own placements (they are the AM).
- Lower tiers (e.g., 10%, 20%, 35%) = they recruited the candidate for another AM.
- Example: Total spread = $10/hr. MPG keeps 50%, placing AM gets 35%, recruiter (Sibug) gets 15%.
- Each person's sheet only shows THEIR cut — the tier % in their sheet is their personal commission rate.

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
- `pay_rate` is NOT in the Excel. It can be derived: `pay_rate = bill_rate − col C` (requires bill_rate entered manually).
- All AM payroll sheets follow the same structure.

---

## Multi-AM Architecture

- IHRP supports **multiple AMs**.
- Each AM has their own `payroll_records` and `payroll_consultant_entries`.
- **Admins** see data for all AMs.
- **AMs** only see their own data (scoped by `user_id`).

---

## What This Means in Code

| Concept | DB Field | Formula |
|---|---|---|
| Client pays MPG | `bill_rate` | — |
| MPG pays consultant | `pay_rate` | — |
| Spread | derived | `bill_rate − pay_rate` |
| AM commission % | `commission_pct` on `payroll_consultant_entries` | varies per consultant |
| AM Earnings (cost) | `am_earnings` on `payroll_consultant_entries` | `col D × commission_pct` = `hours × spread × commission_pct` |
| Agency Gross Profit | `gross_margin` on `payroll_consultant_entries` | `(hours × bill_rate) − am_earnings` |
| Agency Revenue | `revenue` on `payroll_consultant_entries` | `hours × bill_rate` |

---

_Last updated: 2026-03-23 — established as SSOT by Raf_
