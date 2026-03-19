# DEVLOG — [Project Name]

> Append-only audit trail. One file. Three blocks per phase.
> Claude Code writes 🏗️ and ✅ blocks. Cursor writes 🔨 blocks.
> No other notes files are created anywhere in this repo.
> Every todo references its phase with a [Phase X] prefix.

---

## Phase 1 | [Phase Name]
_Opened: YYYY-MM-DD | Closed: YYYY-MM-DD_
_Mode: SEQUENTIAL | PARALLEL_

### 🏗️ [ARCHITECT — Claude Code]
**Goal:** [What this phase is building and why]
**Mode:** SEQUENTIAL | PARALLEL ([Phase 1a] + [Phase 1b])

**Dependency diagram:**
```
[Phase 1] → [Phase 2a]
           → [Phase 2b]
           → [Phase 3] (waits for 2a + 2b)
```

**Decisions made:**
- [Decision — reason]

**Risks flagged:**
- [Anything to watch for during build or in future phases]

**Files planned:**
- `/path/to/file.ts`
- `/path/to/other.ts`

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
