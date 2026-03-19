# PHASES — IHRP (Internal HR Portal)
_Last updated: 2026-03-19_

## Phase Map

[Phase 0] — Scaffold + Auth | SEQUENTIAL
  └─ Create Laravel 11 project
  └─ MySQL migrations (11 existing + 3 new tables)
  └─ Laravel Breeze auth + role middleware
  └─ Login page + Admin user management
  └─ Deploy to Bluehost (Phase 0 verify)

[Phase 1] — Backend Port | SEQUENTIAL
  └─ OvertimeCalculator.php (rewrite overtime.js) + 116 PHPUnit tests
  └─ All 13 Controllers ported from IPC handlers
  └─ Role guards on every controller method
  └─ dompdf PDF generation (invoice + reports)
  └─ Laravel Mail (replaces nodemailer)

[Phase 2] — Frontend Port | SEQUENTIAL
  └─ Blade + Alpine.js for all 8 pages
  └─ Timesheets → Livewire component (file upload wizard)
  └─ Sidebar with @can role-based nav
  └─ PDF preview + download via Response::download()

[Phase 3] — New Features | SEQUENTIAL
  └─ Employee call reporting (/calls)
  └─ Call report aggregate view (/calls/report)
  └─ Placement management (/placements) — Livewire
  └─ Employee dashboard (own stats + placement)

[Phase 4] — Data Migration + QA | SEQUENTIAL
  └─ PHP migration script (SQLite → MySQL)
  └─ Row-count + money checksum validation
  └─ File migration (W-9s + invoice PDFs)
  └─ Full regression pass on migrated data

[Phase 5] — Deploy | SEQUENTIAL
  └─ Production MySQL on Bluehost
  └─ hr.matchpointegroup.com subdomain + DNS
  └─ Bluehost Git deploy hook
  └─ Run migration on production
  └─ Smoke test all features

## Dependency Rules

- [Phase 1] requires [Phase 0] complete (auth + DB must exist)
- [Phase 2] requires [Phase 1] complete (Controllers must exist for Blade to call)
- [Phase 3] requires [Phase 0] complete (auth/roles needed; can run alongside Phase 2 if needed)
- [Phase 4] requires [Phase 1] + [Phase 2] complete (full feature parity before migration)
- [Phase 5] requires [Phase 4] complete (QA gate before production deploy)

## Status

- [Phase 0] 🔨 In Progress
- [Phase 1] ⏳ Pending
- [Phase 2] ⏳ Pending
- [Phase 3] ⏳ Pending
- [Phase 4] ⏳ Pending
- [Phase 5] ⏳ Pending
