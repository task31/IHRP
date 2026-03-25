# IHRP Master Task List
_Last updated: 2026-03-25_
_Source of truth for all remaining work. Check items off as completed. Append new items — never delete._
_Completed tasks → `references/tasklist-archive.md`_

---

## Production Status
**Production last verified: 2026-03-25 (Raf)** — SSH deploy + all 3 pending migrations applied.
All migrations current on prod. No pending migrations.

---

## 🧹 P4 — Cleanup + Docs

- [ ] **T025** — Consolidate all deploy knowledge (LiteSpeed handler notes, deploy process, known DB issues) — partially captured in `.cursor/rules/ihrp-deploy.mdc` and `references/`; ensure nothing is only in loose notes.

---

## 📋 How to Work This List

1. Pick the next unchecked item from the top (lowest P-level first).
2. Mark it `[ → in progress]` when starting.
3. Mark it `[x]` when confirmed done (not just coded — verified).
4. If a task reveals new work, append it as a new `T0XX` item at the bottom of the correct section.
5. **Production default path:** after **`git push`**, use **`python deploy.py --step ssh-deploy`** (cPanel UAPI deploy path is broken — `repository_root` param rejected). Then run `python deploy.py --step migrate-status` and `python deploy.py --step run-migrations` if migrations are pending. Migrations need **explicit Raf confirmation** before `--force`.
6. Deploy agent (`ihrp-deploy-expert`) owns execution of deploy/production steps; Architect reviews output and DEVLOG.
7. Backend agent (`ihrp-backend-expert`) handles all backend/migration/service items.
