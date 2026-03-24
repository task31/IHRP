# IHRP deploy / production — known issues (index)

Full narrative and fixes: **`DEPLOY.md`** (Issues 1–12).

Quick pointers:

| # | Topic | Where |
|---|--------|--------|
| 12 | cPanel deploy API needs `repository_root`; use `ssh-deploy` fallback | `DEPLOY.md` Issue 12 |
| SSH / cPanel auth | Key + `Authorization: cpanel user:secret` | `DEPLOY.md`, `deploy-learning-log.md` |
| Smoke `/dashboard` false negative | urllib follows 302 → 200 | `references/deploy-preflight-checks.md` |
