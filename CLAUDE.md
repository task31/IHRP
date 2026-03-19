# CLAUDE.md — IHRP (Internal HR Portal)

> Lean summary of completed phases. Append-only — phases are never removed.
> Full decision + build history lives in DEVLOG.md.
> Read this at the start of every session to understand project state.

---

## Project Overview

Laravel 11 (PHP) web app migrating the Matchpointe Electron payroll desktop app to
`hr.matchpointegroup.com`. Multi-user with role-based access (admin, account_manager,
employee). Hosted on Bluehost Business Hosting (PHP/MySQL/Apache, $0 extra).

See `PROJECT_CONTEXT.md` for full brief, stack decisions, and constraints.

---

## Project Location

All projects live at: `C:\Users\zobel\Claude-Workspace\projects\`

This project is at: `C:\Users\zobel\Claude-Workspace\projects\IHRP\`

Source (Electron app): `C:\Users\zobel\Claude-Workspace\projects\Payroll\`

---

## Completed Phases

_None yet — phases will be summarized here as they close._

---

## Active Phase

**Phase 0** — Scaffold + Auth — IN PROGRESS

---

## Conventions for This Project

- All Controllers in `app/Http/Controllers/` — one controller per IPC module
- Blade views in `resources/views/` — mirroring page names from original app
- Services in `app/Services/` — OvertimeCalculator, PdfService, etc.
- Every controller method must call `$this->authorize()` or check role explicitly
- Money fields stored as `DECIMAL(12,4)` in MySQL — never FLOAT
- OT calculation always goes through `OvertimeCalculator` — never inline
- File uploads stored in `storage/app/uploads/` — served via storage symlink
- Audit log entries must include `user_id` = `Auth::id()`

---

## SSOT (Single Source of Truth)

| Info type | Lives in |
|---|---|
| What we're building | `PROJECT_CONTEXT.md` |
| Full decision + build history | `DEVLOG.md` |
| Phase summaries (completed) | This file (`CLAUDE.md`) |
| Current phase plan | `phase-N-plan.md` |
| Phase map + status | `PHASES.md` |
