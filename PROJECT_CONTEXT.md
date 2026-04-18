<!-- DEVTEAM_TEMPLATE: PROJECT_CONTEXT v3 -->
# DevTeam Workflow -- Project Context
# Project Context -- IHRP (Internal HR Portal)

> Claude.ai authors this file during project bootstrap.
> After bootstrap, only factual corrections or Claude Code assisted template upgrades should change it.
> AGENTS READ THIS FILE ON STARTUP. Keep it accurate and complete.
>
> Session-by-session planning lives in `phase-N-plan.md`.
> The live Claude Code / Cursor bridge lives in `HANDOFF.md`.
> Deploy details live in `DEPLOY.md`.
> sync-workflow.ps1 never blind-overwrites this file.

---

## What We Are Building

A multi-user internal web HR application for Matchpointe Group, accessible at
`hr.matchpointegroup.com`. It migrates all features from the existing single-user Electron
payroll desktop app to a browser-based app, and adds:
- Account Manager role for placement management and payroll visibility
- Role-based access control across all features

## Why We Are Building It

The Electron app runs on one macOS machine and serves one user. The business needs multiple
employees to access payroll data, submit daily call reports, and manage placements from any
browser without being on that machine.

## Who Uses It

- **Admin** (boss/payroll manager): full access to all features
- **Account Manager**: manage placements, read own payroll data, submit and view call reports

_Note: An Employee role was initially planned but removed in Phase 4 (2026-03-20). All users are either admin or account_manager._

---

## Tech Stack

| Layer | Choice | Reason |
|---|---|---|
| Framework | Laravel 13 (PHP 8.3) | Auth, ORM, mail, and routing built in; runs natively on Bluehost. Planned as Laravel 11, but Composer resolved 13.x at scaffold time on 2026-03-19. |
| Frontend | Blade templates plus Alpine.js | Pure PHP, no build pipeline, deploys directly to Bluehost |
| Complex UI | Laravel Livewire | Supports timesheets wizard and real-time filtering without SPA complexity |
| Database | MySQL (Bluehost included) | Free, already provisioned, and cPanel managed |
| Auth | Laravel Breeze | Email/password, session-based, role middleware |
| PDF | barryvdh/laravel-dompdf | HTML to PDF, replaces pdfkit |
| Email | Laravel Mail plus SMTP | Replaces nodemailer while keeping the same SMTP credentials |
| File storage | `storage/app/uploads/` | Bluehost disk, symlinked to `public/storage` |
| Hosting | Bluehost Business Hosting | Already paid, includes disk and MySQL |
| Deploy | Bluehost cPanel Git Version Control plus `deploy.py` | Push to repo, then cPanel pull via UAPI or ssh-deploy fallback |

## Key Constraints

- Must run on Bluehost Business Hosting (PHP/Apache, no Node.js)
- MySQL only (no PostgreSQL)
- Internal use only; no public-facing registration
- All existing payroll features must be preserved from the Electron app
- OT calculation results must match the original Electron app exactly

## Integrations

- SMTP email (configured in Settings)
- Microsoft Graph API for email inbox sync (`AZURE_*` env vars)
- Bluehost MySQL (cPanel managed)
- Bluehost file storage (`storage/app/uploads`)

---

## Source App

| File | Lines | Notes |
|---|---|---|
| `Payroll/src/main/database.js` | 376 | SQLite schema to Laravel migration source |
| `Payroll/src/main/preload.js` | 136 | IPC API contract that maps to controller routes |
| `Payroll/src/main/ipc/overtime.js` | 606 | Must be rewritten as `OvertimeCalculator.php` |
| `Payroll/src/main/ipc/invoices.js` | 667 | Largest IPC handler; maps to `InvoiceController` |
| `Payroll/src/renderer/pages/Timesheets.jsx` | 1,445 | Hardest frontend area; target for Livewire component |

---

## Non-Negotiable Rules
> Agents enforce these on every task. No exceptions.

### Data And Money
- All money is stored as `DECIMAL(12,4)` in MySQL, never `FLOAT`
- All payroll, earnings, margin, and commission calculations use `bcmath`, never float arithmetic
- `am_earnings` is immutable upload-derived cost data; recompute flows never modify it

### Authorization
- Every controller action must call `$this->authorize()` or perform an explicit role or ownership check
- Role hierarchy is `admin > account_manager`, with AM data scoped to owned placements, payroll, and calls where applicable

### Audit And Integrity
- Every write path must call `AppService::auditLog()` with actor context (`user_id = Auth::id()`)
- Migrations are append-only; never modify an existing migration file after it has run
- Protected deploy files (`.env`, uploads, template XLSX, storage symlink) are never overwritten during deploy

### Domain Rules
- OT calculation always goes through `OvertimeCalculator`, never inline
- `BUSINESS_MODEL.md` is the SSOT for earnings, margins, payroll, and commissions
- `POST /payroll/recompute-margins` may recalculate `revenue`, `gross_margin`, and related projections, but never `am_earnings`

### Code Conventions
- Controllers live in `app/Http/Controllers/`, services in `app/Services/`, and Blade views mirror original app page names
- File uploads are stored in `storage/app/uploads/` and served through the storage symlink
- Tests run through `php artisan test`; the current repo baseline must pass with 0 failures before deploy

---

## Institutional Weirdness Log
> Intentional anti-patterns and legacy constraints. Do NOT "fix" anything listed here.
> Claude.ai or Claude Code should add an entry when a deliberate decision would confuse a future agent.

| What it is | Why it exists | Date noted |
|---|---|---|
| `placements.consultant_name` is free-text, not a foreign key | Placements must be creatable before the consultant record exists; the save flow can auto-create the consultant later | 2026-03-20 |
| `am_earnings` is immutable upload-derived cost data | Payroll uploads are treated as source-of-truth compensation records; recompute only updates revenue and margin fields | 2026-03-23 |
| Account managers are redirected to `/placements` and blocked from `/dashboard` | Their day-to-day workflow is placements and calls, not the admin dashboard | 2026-03-20 |

---

## Deploy
> Full runbook, server config, and known issues live in DEPLOY.md.
> deploy-agent reads DEPLOY.md as its single authority, not this section.

- **Hosting:** Bluehost Business Hosting shared PHP and Apache
- **Deploy script:** `python deploy.py`; full runbook in `DEPLOY.md`
- **Test gate:** `php artisan test`; the current repo baseline must pass with 0 failures before any deploy

---

## Out Of Scope

- Public-facing client portal
- Mobile app
- Real-time collaboration or websockets
- Payroll direct deposit or ACH integration

---

## Decisions Already Made

- PHP plus Laravel, not Next.js; manager decision that enables free Bluehost hosting
- Blade plus Alpine.js frontend, not React; pure PHP with no npm build pipeline on Bluehost
- Livewire for complex interactive pages such as Timesheets and Placements
- Railway is not used; Bluehost Business Hosting covers everything
- Domain is `hr.matchpointegroup.com` via A record in WordPress Plus cPanel

---

_Generated: 2026-03-19_
_Stack decisions made with: Claude.ai - manager confirmed PHP and Bluehost_
_Last corrected: 2026-04-04 - upgraded to workflow-project template v3 and aligned heading names_
