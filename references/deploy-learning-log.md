# IHRP Deploy Learning Log

> Append-only. One entry per deploy attempt or incident.
> Format: trigger → diagnosis → fix → prevention.
> Agent reads this at start of every deploy session to reuse proven fixes.

---

## 2026-03-24 — T001 SSH Auth + cPanel 401 (Attempt 2 Failure)

**Trigger:** Agent ran `python deploy.py` and received `paramiko.AuthenticationException` on SSH connect. cPanel UAPI calls returned HTTP 401. All deploy steps blocked.

**Diagnosis:**
- SSH failure: `deploy.py` was calling `ssh.connect(password=PASS)`. Bluehost Business Hosting (`sh00858.bluehost.com`) has password-based SSH auth disabled at the server level. The password `Vape13578!` is valid but the auth method is blocked — the rejection happens before the password is even evaluated.
- cPanel 401: `requests.get(url, auth=(USER, PASS))` sends `Authorization: Basic base64(user:pass)`. cPanel UAPI requires `Authorization: cpanel rbjwhhmy:password`. Wrong header format → 401 regardless of correct credentials.
- Secondary issue: `!` in password `Vape13578!` was being stripped when `.deploy.env` was written via bash heredoc (shell history expansion on `!`). Diagnosed from screenshot evidence showing password loaded as `Vape13578` (missing `!`).

**Fix applied:**
1. `deploy.py` `get_ssh()` changed to `ssh.connect(key_filename=key_path, look_for_keys=False, allow_agent=False)`. Key: `C:/Users/zobel/Downloads/id_rsa`.
2. New `_cpanel_request()` helper added — sets `Authorization: cpanel rbjwhhmy:{password}` header on all cPanel UAPI calls.
3. `.deploy.env` rewritten via Python `Filesystem:write_file` (not bash) to preserve `!`.
4. New `--step diagnose` added to `deploy.py` — tests SSH key + cPanel auth independently before any deploy attempt.
5. `BLUEHOST_SSH_KEY` added to `.deploy.env` pointing to `C:/Users/zobel/Downloads/id_rsa`.

**Prevention:**
- Agent must always use `key_filename` for SSH — never `password`. Rule added to agent safeguards.
- Agent must always use `_cpanel_request()` for cPanel API calls — never raw `requests`.
- Run `python deploy.py --step diagnose` before any new deploy on a new machine or after credential changes.
- Never write `.deploy.env` via shell heredoc/echo — always via Python file write.

**Files changed:**
- `deploy.py` — `get_ssh()`, `_cpanel_request()`, `step_diagnose()`, `STEP_MAP`, `NO_SSH_STEPS`
- `.deploy.env` — added `BLUEHOST_SSH_KEY`, rewrote to preserve `!`
- `.claude/agents/ihrp-deploy-expert.md` — added SSH/cPanel auth knowledge, known resolved issues table, example triggers for AuthenticationException and 401

---

## 2026-03-24 — T001 Placement Fix Deployed ✅ SUCCESS

**Trigger:** T001 bug — "Add Placement" button not working on production. Fix: `wire:click.self="cancelForm"` on placement modal backdrop (commit `e0a1c70`). Blade-only change.

**Diagnosis (new issues discovered during this attempt):**
1. `deploy.py` passed no passphrase to `ssh.connect()` — the SSH private key at `C:/Users/zobel/Downloads/id_rsa` is passphrase-encrypted with the cPanel password `Vape13578!`. Paramiko silently fails to load it → auth rejected.
2. Windows cp1252 console crashes on emoji chars (✅ ⚠️) in print statements → `UnicodeEncodeError`.
3. SSH `run()` returns `bytes` not `str` → `"Pending" in line` raises `TypeError` in `step_migrate_status`.
4. cPanel UAPI `VersionControl/retrieve` returns 403 (account-level permission restriction) — the `Authorization: cpanel user:pass` header format is correct but git operations are blocked for this API path. cPanel git deploy is effectively unavailable via UAPI.

**Fix applied:**
1. Added `passphrase=CPANEL_PASS` to `ssh.connect()` in `get_ssh()`.
2. Added `io.TextIOWrapper` UTF-8 wrapper for `sys.stdout/stderr` at `deploy.py` startup (Windows only).
3. Added `isinstance(stdout, bytes)` decode guard in `run()`.
4. Added `step_ssh_deploy()` — SSH-based deploy that mirrors `.cpanel.yml` exactly: backs up `.env` → `git fetch + reset --hard` → `cp -R web/. public_html/hr/` → restores `.env` → `composer install` (not on PATH, skipped — vendor in git) → artisan caches.
5. Registered `--step ssh-deploy` in `STEP_MAP`.

**Deploy executed (all steps passed):**
- `--step diagnose`: SSH ✅ | cPanel UAPI stats 403 (expected — stats endpoint restricted)
- `--step migrate-status`: 33/33 Ran, 0 Pending ✅
- `--step ssh-deploy`: .env backed up ✅ | git pull `395be19→7fb8f1e` ✅ | files copied ✅ | .env restored ✅ | config:cache ✅ | route:cache ✅ | view:cache ✅ | timesheets:generate-template ✅
- `--step verify-env`: APP_ENV=production ✅ | APP_DEBUG=false ✅ | APP_NAME present ✅
- `--step clear-cache`: config:clear + config:cache + route:cache + view:cache ✅ (clean rebuild)
- `--step smoke`: /login → 200 ✅ | /dashboard → 200 (false negative — urllib follows 302)
- `--step tail-log`: Last log entry = old migration stack trace from pre-f2f0de0. No new errors ✅

**Production verification:** `wire:click.self="cancelForm"` confirmed in deployed file. 76 compiled view cache files confirmed.

**Prevention:**
- Always pass `passphrase=CPANEL_PASS` in `ssh.connect()` — key is encrypted with cPanel password.
- Use `--step ssh-deploy` instead of `--step deploy` — cPanel UAPI VersionControl is 403 blocked.
- The `/dashboard` smoke check false negative is a known urllib redirect-follow issue — not a real failure.

**Files changed:**
- `deploy.py` — passphrase fix, UTF-8 fix, bytes decode fix, `step_ssh_deploy()`, `STEP_MAP` updated

---

## 2026-03-24 — T001 User Verification Override (Still Failing)

**Trigger:** After deploy marked success, user manually tested production and reported Add Placement is still not working.

**Status:** Treat T001 as **OPEN / NOT FIXED** until re-tested with live browser interaction and console error capture.

**Next action (scheduled):**
1. Reproduce on production with browser automation/manual session.
2. Capture frontend console errors and Livewire network responses when clicking Add Placement.
3. Confirm whether issue is role-specific, cache-specific, or JS/runtime-specific.
4. Keep T001 unchecked in `TASKLIST.md` until verified working by user.

---

## 2026-03-24 — Production deploy 609f94c (T012–T014): UAPI deploy create failed; ssh-deploy OK

**Trigger:** Raf requested production deploy for `master` at `609f94c` (client `account_manager_id` migration, test bootstrap, consultant checklist). Ran full preflight + deploy path from Windows with `.deploy.env`.

**Diagnosis:**
1. `python deploy.py --step diagnose`: SSH key auth OK; cPanel UAPI OK (`Stats/get_bandwidth`); `VersionControl/retrieve` OK (API token auth).
2. `python deploy.py --step deploy`: `VersionControl/retrieve` succeeded, but `VersionControlDeployment/create` returned `status: 0` with error **Provide the "repository_root" argument** — script currently POSTs only `repository` (path to clone), not `repository_root` / equivalent required by this cPanel version.
3. **Code sync:** `python deploy.py --step ssh-deploy` succeeded: server repo `57201ec..609f94c`, `cp -R web/.` to `public_html/hr/`, `.env` backup/restore, `composer install --no-dev`, `config:cache` / `route:cache` / `view:cache` / `timesheets:generate-template` all OK.
4. **Migrations:** Intentionally **not** run — `migrate:status` after deploy shows **1 Pending:** `2026_03_24_120000_add_account_manager_id_to_clients_table` (as required for Raf confirmation before `migrate --force`).
5. `tail-log`: tail shows **historical** stack traces from an older failed migration (`Unknown column 'hours'` in `add_spread_to_payroll_consultant_entries`) — not a new post-deploy web error; `migrate:status` shows those migrations as Ran.
6. **Smoke:** `/login` → 200; `/dashboard` reported ❌ because checker expects 302 without following redirect — known false negative per `references/deploy-preflight-checks.md`.

**Fix applied:** Used `--step ssh-deploy` after UAPI deploy trigger failed.

**Prevention:** Update `deploy.py` `step_cpanel_deploy` to pass cPanel-required `repository_root` (or use ssh-deploy when create fails). Document pattern in `DEPLOY.md` / `references/known-issues.md`.

**Files changed (this session):** `references/deploy-learning-log.md` (this entry only).
