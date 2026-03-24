# Project Context — IHRP (Internal HR Portal)

> This file is written once and never edited. It is the permanent brief for this project.

---

## What We're Building

A multi-user internal web HR application for Matchpointe Group, accessible at
`hr.matchpointegroup.com`. It migrates all features from the existing single-user Electron
payroll desktop app (19 sessions of work) to a browser-based app, and adds:
- Employee logins to log daily call activity
- Account Manager role for placement management
- Role-based access control across all features

## Why We're Building It

The Electron app runs on one macOS machine and serves one user. The business needs multiple
employees to access payroll data, submit daily call reports, and manage placements from any
browser without being on that machine.

## Who Uses It

- **Admin** (boss/payroll manager): full access to all features
- **Account Manager**: manage placements, read payroll data, submit + view call reports
- **Employee**: submit daily call reports, view own stats and placement info

---

## Tech Stack

| Layer | Choice | Reason |
|---|---|---|
| Framework | Laravel 11 (PHP 8.2+) | Auth, ORM, mail, routing all built-in; runs natively on Bluehost |
| Frontend | Blade templates + Alpine.js | Pure PHP, no build pipeline, deploys directly to Bluehost |
| Complex UI | Laravel Livewire | Timesheets wizard, real-time filtering — reactive without SPA complexity |
| Database | MySQL (Bluehost included) | Free, already provisioned, cPanel managed |
| Auth | Laravel Breeze | Email/password, session-based, role middleware |
| PDF | barryvdh/laravel-dompdf | HTML → PDF, replaces pdfkit |
| Email | Laravel Mail + SMTP | Replaces nodemailer, same SMTP credentials |
| File storage | storage/app/uploads/ | Bluehost disk, symlinked to public/storage |
| Hosting | Bluehost Business Hosting | Already paid — 50GB disk, MySQL included, $0 extra |
| Deploy | Bluehost cPanel Git Version Control | Push → auto pull |

## Key Constraints

- Must run on Bluehost Business Hosting (PHP/Apache, no Node.js)
- MySQL only (no PostgreSQL)
- Internal use only — no public-facing registration
- All existing payroll features must be preserved (19 sessions of Electron app)
- OT calculation results must match the original Electron app exactly

## Integrations

- SMTP email (existing configuration, same credentials — configured in Settings)
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

---

<!-- handoff:start -->
## SESSION HANDOFF — 2026-03-23 (~17:30)

**Phase:** 5 — Deploy (production live at hr.matchpointegroup.com)
**Branch:** master
**Last commit:** 2cb78c2 — feat: add call activity analytics section to admin dashboard

**What was fixed/built this session:**
1. Alpine scope bug fixed on clients, consultants, timesheets pages — buttons in `x-slot="header"` moved into `x-data` div
2. Placements `wire:click.self` (no method) fixed → `wire:click.self="cancelForm"`
3. Timesheet template regenerated with proper biweekly structure + `GenerateTimesheetTemplate` artisan command added to deploy hook
4. Parser `resolveDate()` added — accepts both Excel date serials and formatted date strings
5. Call Activity analytics section added to admin dashboard (period picker, Chart.js trend, leaderboard, AM breakdown table)

**To resume:**
- All fixes are live on production after next cPanel deploy push
- Call analytics section shows "No call data" until AMs log daily calls
- 107 tests passing, working tree clean

**Key files changed:**
- `web/resources/views/clients/index.blade.php` — Alpine scope fix
- `web/resources/views/consultants/index.blade.php` — Alpine scope fix
- `web/resources/views/timesheets/index.blade.php` — Alpine scope fix
- `web/resources/views/livewire/placement-manager.blade.php` — wire:click.self fix
- `web/app/Services/TimesheetParseService.php` — resolveDate()
- `web/app/Console/Commands/GenerateTimesheetTemplate.php` — new command
- `web/app/Http/Controllers/DashboardController.php` — callsStats() endpoint
- `web/resources/views/dashboard.blade.php` — call analytics section
- `.cpanel.yml` — timesheets:generate-template added to deploy hook
<!-- handoff:end -->
