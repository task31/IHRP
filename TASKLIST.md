# IHRP Master Task List
_Last updated: 2026-03-25_
_Source of truth for all remaining work. Check items off as completed. Append new items — never delete._
_Completed tasks → `references/tasklist-archive.md`_

---

## Production Status
**Production last verified: 2026-03-24 (Raf)** — admin + AM smoke complete. Phase 5 close criteria met (T001–T008 all `[x]`).
**Pending:** `2026_03_25_210000_drop_po_number_from_clients_table` applied locally ✅ — run on prod via `python deploy.py --step deploy` (T022).

---

## 🔵 P3 — Tech Debt / Infrastructure

- [ ] **T022** — Fully wire and test `.cpanel.yml` end-to-end with GitHub push → auto-deploy (T005 was the prod fix; this is the verification step). **Next:** commit T021 changes, run `python deploy.py --step deploy`, confirm `.cpanel.yml` tasks execute + migration runs on prod. Optional follow-up: GitHub Action for push-triggered deploy.

---

## 🧹 P4 — Cleanup + Docs

- [ ] **T025** — Consolidate all deploy knowledge (LiteSpeed handler notes, deploy process, known DB issues) — partially captured in `.cursor/rules/ihrp-deploy.mdc` and `references/`; ensure nothing is only in loose notes.

---

## 📋 How to Work This List

1. Pick the next unchecked item from the top (lowest P-level first).
2. Mark it `[ → in progress]` when starting.
3. Mark it `[x]` when confirmed done (not just coded — verified).
4. If a task reveals new work, append it as a new `T0XX` item at the bottom of the correct section.
5. **Production default path:** after **`git push`** (or when shipping), the **Architect** delegates to the **`ihrp-deploy-expert`** subagent (Cursor **Task** tool) to run **`deploy.py`** / the runbook in **`.cursor/rules/ihrp-deploy.mdc`** — not ad hoc shell from Chat. Migrations still need **explicit Raf confirmation** before `--force`.
6. Deploy agent (`ihrp-deploy-expert`) owns execution of P0 deploy/production steps; Architect reviews output and DEVLOG.
7. Backend agent (`ihrp-backend-expert`) handles all backend/migration/service items.
