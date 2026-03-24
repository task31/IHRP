# Deploy Preflight Checks

Purpose: reusable checks to run before deployment. Append-only.

Primary source of truth remains `.cursor/rules/ihrp-deploy.mdc`.
This file captures additional preventive checks discovered over time.

## Baseline Checks

- Confirm deploy scope (migrations, env keys, composer changes, Blade/Livewire changes, PayrollParseService changes).
- Review `references/known-issues.md` for pattern matches.
- If migrations exist, plan explicit migration confirmation gate.
- If `.env` keys changed, plan secure production update before deploy trigger.
- If `composer.json` changed, ensure vendor build expectations are met before deploy.
- For Blade/Livewire layout changes, include safety checks for `@vite()` and `@livewireScriptConfig`.
- For PayrollParseService changes, include AM file-format verification after deploy.

## Added Checks

- **SSH credential verification (added 2026-03-24):** Before triggering any deploy step, confirm SSH authentication works. Always run `python deploy.py --step diagnose` first. If auth fails, check `.deploy.env` has `BLUEHOST_SSH_KEY` pointing to the key file and `BLUEHOST_SSH_PASSWORD` set to the cPanel account password (this is also the key passphrase).
- **SSH key passphrase (added 2026-03-24):** The Bluehost SSH key at `C:/Users/zobel/Downloads/id_rsa` is passphrase-encrypted with the cPanel password `Vape13578!`. `deploy.py` must pass `passphrase=CPANEL_PASS` to `ssh.connect()`. Without it, paramiko silently fails to load the key and auth is rejected.
- **Use ssh-deploy not deploy (added 2026-03-24):** cPanel UAPI `VersionControl/retrieve` returns 403 for this account. Always use `python deploy.py --step ssh-deploy` for the code push step, not `--step deploy`.
- **API token unblocks UAPI (added 2026-03-24):** With `BLUEHOST_CPANEL_TOKEN` in `.deploy.env`, `VersionControl/retrieve` may return OK — then `python deploy.py --step deploy` may work for the **pull** step. If `VersionControlDeployment/create` fails with **repository_root** (see `DEPLOY.md` Issue 12), use `python deploy.py --step ssh-deploy` to run `.cpanel.yml`-equivalent steps. If token is unset or `retrieve` returns 403, use `ssh-deploy`.
- **Smoke test /dashboard false negative (added 2026-03-24):** `urllib.request` follows redirects, so `/dashboard` (expected 302) lands on `/login` (200). The smoke check showing ❌ for `/dashboard → 200` is a false negative — not a production issue.
