# DEVLOG — [Project Name]

> Append-only audit trail. One file. Three blocks per phase.
> Claude Code writes 🏗️ and ✅ blocks. Cursor writes 🔨 blocks.
> No other notes files are created anywhere in this repo.
> Every todo references its phase with a [Phase X] prefix.

---

## Phase 0 | Scaffold + Auth
_Opened: 2026-03-19 | Closed: —_
_Mode: SEQUENTIAL_

### 🏗️ [ARCHITECT — Claude Code]
**Goal:** Create running Laravel 11 app with MySQL migrations (14 tables), role-based auth,
login page, and admin user management. Deployable skeleton — no Electron features ported yet.
**Mode:** SEQUENTIAL

**Dependency diagram:**
```
[Phase 0] → [Phase 1] → [Phase 2] → [Phase 3] → [Phase 4] → [Phase 5]
```

**Decisions made:**
- PHP + Laravel (not Next.js) — manager decision, enables free Bluehost Business Hosting
- Blade + Alpine.js frontend (not React) — pure PHP, no npm build pipeline
- Livewire for complex interactive pages (Timesheets, Placements)
- MySQL (Bluehost included) instead of PostgreSQL — Prisma not used
- No Railway needed — Bluehost covers everything at $0 extra
- Laravel Breeze for auth scaffolding (fastest path to working auth)
- dompdf for PDF generation (replaces pdfkit)

**Risks flagged:**
- Bluehost `.htaccess` / AllowOverride: Apache may ignore .htaccess on shared hosting → routes 404. Verify or contact Bluehost support before Phase 5.
- PHP version: confirm PHP 8.2+ available in cPanel before starting Phase 0.
- OT engine is a full rewrite (not a port) — highest regression risk. 116 PHPUnit tests are the safety net.
- dompdf produces different layout than pdfkit — PDF templates need visual comparison against original invoices.

**Files planned:**
- `app/Http/Controllers/AdminUserController.php`
- `app/Http/Middleware/RequireRole.php`
- `database/migrations/[14 files]`
- `database/seeders/DatabaseSeeder.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/admin/users/*.blade.php`
- `routes/web.php`

---

### 🔨 [BUILD — Cursor]
**Assigned workstream:** [Phase 1] | [Phase 1a] | [Phase 1b]

**Todos completed:**
- [x] [Phase 1] Create /src/auth/login.ts
- [x] [Phase 1] Build token refresh logic
- [ ] [Phase 1] Write auth tests — skipped (see deviations)

**Deviations from plan:**
- [What changed from the architect's plan and why — or "None"]

**Unplanned additions:**
- [Anything added that wasn't in phase-N-plan.md — or "None"]

**Files actually created/modified:**
- `/path/to/file.ts` ✅ (as planned)
- `/path/to/other.ts` ✅ (modified from plan)
- `/path/to/new.ts` ➕ (unplanned addition)
- `/path/to/skipped.ts` ❌ (skipped — reason)

---

### ✅ [REVIEW — Claude Code]
**Test results:** [X passed, X failed, X skipped]

**Issues found:**
- [Any problems — severity and description — or "None"]

**PHASES.md updated:** ✅ [Phase 1] marked complete

**Carry forward to Phase 2:**
- [ ] [Specific action item for next architect session to pick up]
- [ ] [Another item]

---

<!--
  Copy the block below for each new phase.
  Replace N with the phase number.
  Do not delete completed phases — this is a permanent record.
-->

<!--
## Phase N | [Phase Name]
_Opened: YYYY-MM-DD | Closed: YYYY-MM-DD_
_Mode: SEQUENTIAL | PARALLEL_

### 🏗️ [ARCHITECT — Claude Code]
**Goal:**
**Mode:** SEQUENTIAL | PARALLEL

**Dependency diagram:**
```
[Phase N] → [Phase N+1]
```

**Decisions made:**
-

**Risks flagged:**
-

**Files planned:**
-

---

### 🔨 [BUILD — Cursor]
**Assigned workstream:** [Phase N]

**Todos completed:**
- [x] [Phase N] ...
- [ ] [Phase N] ... (skipped — reason)

**Deviations from plan:**
-

**Unplanned additions:**
-

**Files actually created/modified:**
-

---

### ✅ [REVIEW — Claude Code]
**Test results:**

**Issues found:**
-

**PHASES.md updated:**

**Carry forward to Phase N+1:**
- [ ]

---
-->
