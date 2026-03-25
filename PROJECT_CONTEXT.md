# Project Context — IHRP (Internal HR Portal)

> This file is written once and never edited. It is the permanent brief for this project.
> _Exception: factual corrections to stack/role decisions are noted inline with a dated note._

---

## What We're Building

A multi-user internal web HR application for Matchpointe Group, accessible at
`hr.matchpointegroup.com`. It migrates all features from the existing single-user Electron
payroll desktop app (19 sessions of work) to a browser-based app, and adds:
- Account Manager role for placement management and payroll visibility
- Role-based access control across all features

## Why We're Building It

The Electron app runs on one macOS machine and serves one user. The business needs multiple
employees to access payroll data, submit daily call reports, and manage placements from any
browser without being on that machine.

## Who Uses It

- **Admin** (boss/payroll manager): full access to all features
- **Account Manager**: manage placements, read own payroll data, submit + view call reports

_Note: An Employee role was initially planned but removed in Phase 4 (2026-03-20). All users are either admin or account_manager._

---

## Tech Stack

| Layer | Choice | Reason |
|---|---|---|
| Framework | Laravel 13 (PHP 8.3) | Auth, ORM, mail, routing all built-in; runs natively on Bluehost. _Note: planned as Laravel 11 — Composer resolved 13.x at scaffold time (2026-03-19)._ |
| Frontend | Blade templates + Alpine.js | Pure PHP, no build pipeline, deploys directly to Bluehost |
| Complex UI | Laravel Livewire | Timesheets wizard, real-time filtering — reactive without SPA complexity |
| Database | MySQL (Bluehost included) | Free, already provisioned, cPanel managed |
| Auth | Laravel Breeze | Email/password, session-based, role middleware |
| PDF | barryvdh/laravel-dompdf | HTML → PDF, replaces pdfkit |
| Email | Laravel Mail + SMTP | Replaces nodemailer, same SMTP credentials |
| File storage | storage/app/uploads/ | Bluehost disk, symlinked to public/storage |
| Hosting | Bluehost Business Hosting | Already paid — 50GB disk, MySQL included, $0 extra |
| Deploy | Bluehost cPanel Git Version Control + deploy.py | Push → cPanel pull via UAPI |

## Key Constraints

- Must run on Bluehost Business Hosting (PHP/Apache, no Node.js)
- MySQL only (no PostgreSQL)
- Internal use only — no public-facing registration
- All existing payroll features must be preserved (19 sessions of Electron app)
- OT calculation results must match the original Electron app exactly

## Integrations

- SMTP email (existing configuration, same credentials — configured in Settings)
- Microsoft Graph API (email inbox sync — `AZURE_*` env vars, see T026)
- Bluehost MySQL (cPanel managed)
- Bluehost file storage (storage/app/uploads)

---

## Source App

| File | Lines | Notes |
|---|---|---|
| `Payroll/src/main/database.js` | 376 | SQLite schema → Laravel migration source |
| `Payroll/src/main/preload.js` | 136 | IPC API contract → maps to every Controller route |
| `Payroll/src/main/ipc/overtime.js` | 606 | Must be rewritten as OvertimeCalculator.php |
| `Payroll/src/main/ipc/invoices.js` | 667 | Largest IPC handler → InvoiceController |
| `Payroll/src/renderer/pages/Timesheets.jsx` | 1,445 | Hardest frontend → Livewire component |

## Out of Scope

- Public-facing client portal
- Mobile app
- Real-time collaboration / websockets
- Payroll direct deposit / ACH integration

## Decisions Already Made

- PHP + Laravel (not Next.js) — manager decision, enables free Bluehost hosting
- Blade + Alpine.js frontend (not React) — pure PHP, no npm build pipeline on Bluehost
- Livewire for complex interactive pages (Timesheets, Placements)
- Railway is NOT used — Bluehost Business Hosting covers everything
- Domain: hr.matchpointegroup.com via A record (hr → 50.6.53.175) in WordPress Plus cPanel

---

_Generated: 2026-03-19_
_Stack decisions made with: Claude (claude.ai) — manager confirmed PHP/Bluehost_
_Last corrected: 2026-03-25 — Employee role removal noted; Laravel 13 runtime noted; stale session handoff removed_
