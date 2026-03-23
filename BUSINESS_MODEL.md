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
4. **Spread** = Bill Rate − Pay Rate. This is MPG's gross revenue on that consultant.
5. The **AM who placed that consultant earns a % of the Spread** as their commission.
6. MPG keeps what remains of the Spread after paying the AM.

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

---

## Payroll Excel Structure

- Each AM has **one payroll sheet**.
- Rows = consultants the AM placed, grouped by commission % tier.
- Columns include: consultant name, hours, gross pay (to the AM), check date.
- AM Earnings for a consultant = the value in column D (AM commission per consultant row).
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
| AM Earnings (cost) | `am_earnings` on `payroll_consultant_entries` | `hours × (bill_rate − pay_rate) × commission_pct` |
| Agency Gross Profit | `gross_margin` on `payroll_consultant_entries` | `(hours × bill_rate) − am_earnings` |
| Agency Revenue | `revenue` on `payroll_consultant_entries` | `hours × bill_rate` |

---

_Last updated: 2026-03-23 — established as SSOT by Raf_
