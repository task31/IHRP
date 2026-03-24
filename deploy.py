"""
IHRP Production Deploy Script
==============================
Run from the IHRP project root: python deploy.py

Usage:
  python deploy.py                     # Full deploy flow
  python deploy.py --step migrate-status
  python deploy.py --step verify-env
  python deploy.py --step tail-log
  python deploy.py --step smoke
  python deploy.py --step clear-cache
  python deploy.py --step storage-link

Requirements:
  pip install paramiko requests

Credentials (.deploy.env — gitignored):
  BLUEHOST_SSH_KEY=C:/Users/zobel/Downloads/id_rsa
  BLUEHOST_SSH_PASSWORD=Vape13578!
  # SSH key is used for SSH connections (Bluehost disables password SSH auth)
  # Password is used for cPanel UAPI (port 2083) only
"""

import argparse
import os
import sys
import time
import urllib.request
import urllib3

# Force UTF-8 output on Windows to handle emoji in print statements
if sys.platform == "win32":
    import io
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8", errors="replace")
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding="utf-8", errors="replace")

try:
    import paramiko
    import requests
except ImportError:
    print("Missing dependencies. Run: pip install paramiko requests")
    sys.exit(1)

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# ─── Load local credentials from .deploy.env (gitignored) ───────────────────

def _load_deploy_env() -> None:
    env_file = os.path.join(os.path.dirname(os.path.abspath(__file__)), ".deploy.env")
    if os.path.exists(env_file):
        with open(env_file) as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith("#") and "=" in line:
                    key, _, val = line.partition("=")
                    os.environ.setdefault(key.strip(), val.strip())

_load_deploy_env()

# ─── Server Constants ────────────────────────────────────────────────────────

HOST     = "sh00858.bluehost.com"
USER     = "rbjwhhmy"
PHP      = "/opt/cpanel/ea-php83/root/usr/bin/php"
ARTISAN  = "/home2/rbjwhhmy/public_html/hr/artisan"
APP_DIR  = "/home2/rbjwhhmy/public_html/hr"
REPO_DIR = "/home2/rbjwhhmy/repositories/IHRP"
APP_URL  = "https://hr.matchpointegroup.com"

# SSH uses key auth (Bluehost has password SSH auth disabled)
SSH_KEY  = os.environ.get(
    "BLUEHOST_SSH_KEY",
    os.path.expanduser("C:/Users/zobel/Downloads/id_rsa"),
)

# cPanel UAPI password (separate from SSH — used only for port 2083 API calls)
CPANEL_PASS = os.environ.get("BLUEHOST_SSH_PASSWORD", "")

# ─── SSH Helpers ─────────────────────────────────────────────────────────────

def get_ssh() -> paramiko.SSHClient:
    """Open an SSH connection to Bluehost using key authentication."""
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

    # Normalize key path (handles forward/back slashes on Windows)
    key_path = os.path.normpath(SSH_KEY)

    if not os.path.exists(key_path):
        print(f"❌ SSH key not found: {key_path}")
        print(f"   Set BLUEHOST_SSH_KEY in .deploy.env to the correct path.")
        sys.exit(1)

    print(f"Connecting to {HOST} with key: {key_path}")
    try:
        ssh.connect(
            hostname=HOST,
            username=USER,
            key_filename=key_path,
            passphrase=CPANEL_PASS,  # key was generated in cPanel; passphrase = cPanel password
            port=22,
            timeout=30,
            look_for_keys=False,   # only use the specified key
            allow_agent=False,     # don't try ssh-agent
        )
        print("✅ SSH connected (key auth)\n")
        return ssh
    except paramiko.AuthenticationException:
        print("❌ SSH key authentication rejected by server.")
        print("   Verify the public key is registered in Bluehost SSH Access panel.")
        print(f"   Key used: {key_path}")
        sys.exit(1)
    except paramiko.SSHException as e:
        print(f"❌ SSH error: {e}")
        sys.exit(1)


def run(ssh: paramiko.SSHClient, cmd: str, timeout: int = 120):
    """Run a command via SSH. Returns (stdout, stderr, exit_code) as strings."""
    transport = ssh.get_transport()
    channel = transport.open_session()
    channel.settimeout(timeout)
    channel.exec_command(cmd)
    stdout = channel.makefile("r").read()
    stderr = channel.makefile_stderr("r").read()
    exit_code = channel.recv_exit_status()
    # Decode bytes → str if paramiko returns bytes (Python 3 SSH channels)
    if isinstance(stdout, (bytes, bytearray)):
        stdout = stdout.decode("utf-8", errors="replace")
    if isinstance(stderr, (bytes, bytearray)):
        stderr = stderr.decode("utf-8", errors="replace")
    return stdout, stderr, exit_code


def artisan(ssh: paramiko.SSHClient, cmd: str, timeout: int = 120):
    """Run an Artisan command on the server."""
    return run(ssh, f"{PHP} {ARTISAN} {cmd}", timeout=timeout)

def resolve_composer_command(ssh: paramiko.SSHClient) -> str | None:
    """
    Resolve a Composer command that works on Bluehost jailshell.
    Returns a shell-safe command string or None when not found.
    """
    candidates = [
        "composer",
        "/opt/cpanel/composer/bin/composer",
        f"{PHP} /opt/cpanel/composer/bin/composer",
        f"{PHP} /home2/{USER}/composer.phar",
        "php /opt/cpanel/composer/bin/composer",
        "php ~/composer.phar",
    ]

    for candidate in candidates:
        out, _, code = run(
            ssh,
            f"{candidate} --version >/dev/null 2>&1 && echo OK || echo NO",
            timeout=30,
        )
        if code == 0 and "OK" in out:
            return candidate

    return None


# ─── cPanel UAPI ─────────────────────────────────────────────────────────────

def _cpanel_request(method: str, url: str, **kwargs) -> dict:
    """
    Make a cPanel UAPI request using the correct Authorization header format.
    cPanel UAPI requires:  Authorization: cpanel username:password
    NOT HTTP Basic auth (which is what requests.get(..., auth=(user, pass)) sends).
    """
    if not CPANEL_PASS:
        print("⚠️  BLUEHOST_SSH_PASSWORD not set in .deploy.env — cPanel API calls will fail.")
        return {"status": 0, "errors": ["no password"]}

    headers = {
        "Authorization": f"cpanel {USER}:{CPANEL_PASS}",
    }
    try:
        r = requests.request(method, url, headers=headers, verify=False, timeout=30, **kwargs)
        r.raise_for_status()
        return r.json()
    except requests.exceptions.HTTPError as e:
        return {"status": 0, "errors": [str(e)], "http_status": r.status_code}
    except Exception as e:
        return {"status": 0, "errors": [str(e)]}


# ─── Individual Steps ─────────────────────────────────────────────────────────

def step_migrate_status(ssh):
    """Step 2: Always check migration status before any deploy."""
    print("=" * 60)
    print("STEP 2 — Migration Status")
    print("=" * 60)
    out, err, code = artisan(ssh, "migrate:status")
    print(out)
    if err:
        print("STDERR:", err)

    pending = [l for l in out.splitlines() if "Pending" in l]
    if pending:
        print(f"\n⚠️  {len(pending)} pending migration(s) found:")
        for p in pending:
            print(f"   {p.strip()}")
        print("\nThese must be run manually after deploy.")
        print("Confirm with Raf before running: python deploy.py --step run-migrations\n")
    else:
        print("✅ No pending migrations.\n")
    return pending


def step_cpanel_deploy(ssh):
    """Step 4: Trigger cPanel Git pull + .cpanel.yml deployment."""
    print("=" * 60)
    print("STEP 4 — cPanel Git Deploy")
    print("=" * 60)

    # Git retrieve (pull latest from GitHub into cPanel's git clone)
    pull_url = f"https://{HOST}:2083/execute/VersionControl/retrieve"
    print("Triggering git retrieve...")
    result = _cpanel_request("GET", pull_url)
    if result.get("status") == 1:
        print("✅ Git retrieve successful")
    else:
        print(f"⚠️  Git retrieve response: {result}")
        print("Manual fallback: cPanel → Git Version Control → IHRP → Deploy HEAD Commit")
        _cpanel_manual_hint()
        return

    # Deploy (runs .cpanel.yml tasks)
    deploy_url = f"https://{HOST}:2083/execute/VersionControlDeployment/create"
    print("Triggering deployment (.cpanel.yml tasks)...")
    result = _cpanel_request("POST", deploy_url, data={"repository": REPO_DIR})
    if result.get("status") == 1:
        print("✅ Deploy triggered successfully")
    else:
        print(f"⚠️  Deploy response: {result}")
        _cpanel_manual_hint()
        return

    print("\nWaiting 90 seconds for .cpanel.yml tasks to complete...")
    print("  (cp -R web/ → public_html/hr/, composer install, config:cache, route:cache, view:cache)\n")
    for i in range(9):
        time.sleep(10)
        print(f"  {(i + 1) * 10}s elapsed...")
    print()


def _cpanel_manual_hint():
    print("\n  Manual deploy steps:")
    print("  1. Open: https://sh00858.bluehost.com:2083")
    print("  2. Git Version Control → IHRP → Manage → Deploy HEAD Commit")
    print()


def step_ssh_deploy(ssh):
    """
    SSH-based deploy fallback — mirrors .cpanel.yml exactly.
    Used when cPanel UAPI VersionControl endpoints return 403.

    Steps:
      1. Backup .env (protected — cp -R would overwrite it)
      2. git fetch + reset --hard in server repo
      3. cp -R web/. public_html/hr/
      4. Restore .env from backup
      5. composer install --no-dev --optimize-autoloader
      6. php artisan config:cache + route:cache + view:cache + timesheets:generate-template
    """
    print("=" * 60)
    print("STEP 4 — SSH Deploy (cPanel UAPI unavailable; SSH fallback)")
    print("=" * 60)

    # --- 1. Backup .env ---
    print("Backing up .env to /tmp/ihrp_env_backup ...")
    out, err, code = run(ssh, f"cp {APP_DIR}/.env /tmp/ihrp_env_backup && echo BACKED_UP")
    if "BACKED_UP" not in out:
        print(f"🚨 .env backup FAILED — aborting deploy to protect production .env")
        print(f"   stdout: {out}  stderr: {err}")
        sys.exit(1)
    print("✅ .env backed up\n")

    # --- 2. Git fetch + reset in server repo ---
    print("Pulling latest code from GitHub into server repo ...")
    out, err, code = run(
        ssh,
        f"cd {REPO_DIR} && git fetch origin master 2>&1 && git reset --hard origin/master 2>&1",
        timeout=120,
    )
    print(out.strip())
    if code != 0:
        print(f"🚨 git fetch/reset failed (exit {code})")
        print(err)
        sys.exit(1)
    print("✅ Server repo updated\n")

    # --- 3. cp -R web/ → public_html/hr/ ---
    print("Copying web/ to public_html/hr/ ...")
    out, err, code = run(
        ssh,
        f"cp -R {REPO_DIR}/web/. {APP_DIR}/ 2>&1",
        timeout=120,
    )
    if code != 0:
        print(f"🚨 cp -R failed (exit {code}): {err}")
        # Restore .env even if cp fails
        run(ssh, f"cp /tmp/ihrp_env_backup {APP_DIR}/.env")
        sys.exit(1)
    print("✅ Files copied\n")

    # --- 4. Restore .env (CRITICAL — cp -R overwrites it) ---
    print("Restoring .env from backup ...")
    out, err, code = run(ssh, f"cp /tmp/ihrp_env_backup {APP_DIR}/.env && echo RESTORED")
    if "RESTORED" not in out:
        print(f"🚨 .env RESTORE FAILED — production .env may be corrupted!")
        print(f"   stdout: {out}  stderr: {err}")
        sys.exit(1)
    print("✅ .env restored\n")

    # --- 5. Composer install ---
    print("Resolving composer command ...")
    composer = resolve_composer_command(ssh)
    if not composer:
        print("🚨 Composer not found in SSH shell.")
        print("   Checked common locations and PATH, but none worked.")
        print("   Add composer to PATH, or place composer.phar at ~/composer.phar.")
        print("   Aborting SSH deploy to avoid partial/unsafe dependency state.")
        sys.exit(1)

    print(f"Using composer command: {composer}")
    print("Running composer install --no-dev --optimize-autoloader ...")
    out, err, code = run(
        ssh,
        f"cd {APP_DIR} && {composer} install --no-dev --optimize-autoloader 2>&1",
        timeout=300,
    )
    last_lines = "\n".join(out.strip().splitlines()[-5:]) if out.strip() else err.strip()[:200]
    print(last_lines)
    if code != 0:
        print(f"⚠️  Composer returned exit {code} — check output above")
    else:
        print("✅ Composer install complete\n")

    # --- 6. Artisan post-deploy commands ---
    print("Running artisan post-deploy commands ...")
    for cmd in ["config:cache", "route:cache", "view:cache", "timesheets:generate-template"]:
        out, err, code = artisan(ssh, cmd)
        icon = "✅" if code == 0 else "❌"
        msg = (out or err).strip().replace("\n", " ")[:80]
        print(f"  {icon} {cmd}: {msg}")
    print()
    print("✅ SSH deploy complete\n")


def step_verify_env(ssh):
    """Step 5: Verify .env survived the cp -R deploy."""
    print("=" * 60)
    print("STEP 5 — Verify .env Integrity")
    print("=" * 60)
    out, _, _ = run(ssh, f"head -6 {APP_DIR}/.env")
    print(out)

    checks = {
        "APP_ENV=production": "APP_ENV is production",
        "APP_DEBUG=false": "APP_DEBUG is false",
        "APP_NAME": "APP_NAME present",
    }
    all_ok = True
    for key, label in checks.items():
        ok = key in out
        icon = "✅" if ok else "🚨"
        print(f"  {icon} {label}")
        if not ok:
            all_ok = False

    if not all_ok:
        print("\n🚨 .env is MISSING or CORRUPTED. Stop and restore .env before proceeding.")
        print(f"   Expected at: {APP_DIR}/.env")
        print("   Restore from .env.production.example then SFTP to server.")
        sys.exit(1)
    print("\n✅ .env intact\n")


def step_storage_link(ssh):
    """Step 5b: Verify or recreate public/storage symlink."""
    print("=" * 60)
    print("STEP 5b — Storage Symlink")
    print("=" * 60)
    out, err, _ = run(ssh, f"ls -la {APP_DIR}/public/storage")
    print(out)
    if "->" not in out:
        print("⚠️  Symlink missing. Recreating...")
        out2, err2, code = artisan(ssh, "storage:link")
        print(out2 or err2)
        if code == 0:
            print("✅ Symlink created\n")
        else:
            print("🚨 Failed to create symlink\n")
    else:
        print("✅ Symlink intact\n")


def step_safety_checks(ssh):
    """Step 5c: Check PHP handler, @vite, @livewireScripts."""
    print("=" * 60)
    print("STEP 5c — Safety Checks")
    print("=" * 60)

    # PHP handler
    out, _, _ = run(ssh, f"grep AddHandler {APP_DIR}/public/.htaccess")
    if "___lsphp" in out:
        print("✅ PHP handler: application/x-httpd-ea-php83___lsphp (correct)")
    else:
        print(f"🚨 PHP handler wrong or missing! Found: {out.strip()}")
        print("   Expected: AddHandler application/x-httpd-ea-php83___lsphp .php")

    # @vite check
    out, _, _ = run(ssh, f"grep -r '@vite' {APP_DIR}/resources/views/layouts/ 2>/dev/null")
    if out.strip():
        print(f"🚨 @vite found in layouts — will cause 500!\n   {out.strip()}")
    else:
        print("✅ No @vite directives in layouts")

    # @livewireScripts check (must be @livewireScriptConfig)
    out, _, _ = run(
        ssh,
        f"grep -rn 'livewireScripts[^C]' {APP_DIR}/resources/views/layouts/ 2>/dev/null",
    )
    if out.strip():
        print(f"🚨 @livewireScripts found — must be @livewireScriptConfig!\n   {out.strip()}")
    else:
        print("✅ Livewire script tag is @livewireScriptConfig (correct)\n")


def step_run_migrations(ssh):
    """Step 6: Run pending migrations (with confirmation gate)."""
    print("=" * 60)
    print("STEP 6 — Run Migrations")
    print("=" * 60)

    out, _, _ = artisan(ssh, "migrate:status")
    pending = [l for l in out.splitlines() if "Pending" in l]

    if not pending:
        print("✅ No pending migrations — nothing to run.\n")
        return

    print(f"⚠️  {len(pending)} pending migration(s):")
    for p in pending:
        print(f"   {p.strip()}")

    confirm = input("\nConfirm: run these migrations on PRODUCTION? (yes/no): ").strip().lower()
    if confirm != "yes":
        print("⏭️  Migration skipped. Run manually when ready.\n")
        return

    print("\nRunning migrations...")
    out, err, code = artisan(ssh, "migrate --force", timeout=180)
    print(out)
    if code != 0:
        print("🚨 Migration FAILED:")
        print(err)
        return

    out2, _, _ = artisan(ssh, "migrate:status")
    remaining = [l for l in out2.splitlines() if "Pending" in l]
    if remaining:
        print(f"⚠️  {len(remaining)} still pending after migrate run")
    else:
        print("✅ All migrations ran successfully\n")


def step_clear_cache(ssh):
    """Step 7: Clear and rebuild Laravel caches."""
    print("=" * 60)
    print("STEP 7 — Rebuild Caches")
    print("=" * 60)
    for cmd in ["config:clear", "config:cache", "route:cache", "view:cache"]:
        out, err, code = artisan(ssh, cmd)
        icon = "✅" if code == 0 else "❌"
        msg = (out or err).strip().replace("\n", " ")[:80]
        print(f"  {icon} {cmd}: {msg}")
    print()


def step_tail_log(ssh):
    """Step 8: Tail Laravel log for errors."""
    print("=" * 60)
    print("STEP 8 — Laravel Log Check")
    print("=" * 60)
    out, _, _ = run(ssh, f"tail -50 {APP_DIR}/storage/logs/laravel.log")
    print(out)
    error_indicators = ["ERROR", "CRITICAL", "Exception", "Stack trace"]
    if any(x in out for x in error_indicators):
        print("⚠️  Errors found in log — investigate before smoke test\n")
    else:
        print("✅ No recent errors in Laravel log\n")


def step_smoke(ssh=None):
    """Step 9: HTTP smoke test."""
    print("=" * 60)
    print("STEP 9 — HTTP Smoke Test")
    print("=" * 60)

    def check(url, expected_status=200, check_text=None):
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "ihrp-deploy/1.0"})
            with urllib.request.urlopen(req, timeout=15) as resp:
                body = resp.read().decode("utf-8", errors="ignore")
                status = resp.getcode()
                ok = status == expected_status
                if check_text:
                    ok = ok and check_text in body
                icon = "✅" if ok else "❌"
                print(f"  {icon} {url} → {status}")
                return ok
        except urllib.error.HTTPError as e:
            ok = e.code == expected_status
            icon = "✅" if ok else "❌"
            print(f"  {icon} {url} → {e.code}")
            return ok
        except Exception as e:
            print(f"  ❌ {url} → ERROR: {e}")
            return False

    check(f"{APP_URL}/login", check_text="IHRP")
    check(f"{APP_URL}/dashboard", expected_status=302)

    print()
    print("Manual verification needed (log in and check):")
    print("  • Admin dashboard: 4 stat cards render")
    print("  • Payroll page: charts render (not blank)")
    print("  • AM login: redirects to /placements")
    print()


def step_diagnose():
    """Diagnose SSH and cPanel connectivity without a full deploy."""
    print("=" * 60)
    print("DIAGNOSE — Connectivity Check")
    print("=" * 60)

    # 1. Check key file
    key_path = os.path.normpath(SSH_KEY)
    print(f"SSH key path: {key_path}")
    if os.path.exists(key_path):
        print("✅ Key file exists")
    else:
        print("❌ Key file NOT found")
        return

    # 2. Attempt SSH
    print(f"\nAttempting SSH to {HOST}...")
    try:
        ssh = get_ssh()
        out, _, _ = run(ssh, "echo 'SSH OK' && whoami")
        print(f"✅ SSH works: {out.strip()}")
        ssh.close()
    except SystemExit:
        print("❌ SSH failed (see error above)")
        return

    # 3. cPanel API
    print(f"\nTesting cPanel UAPI at https://{HOST}:2083...")
    if not CPANEL_PASS:
        print("⚠️  BLUEHOST_SSH_PASSWORD not in .deploy.env — skipping cPanel test")
    else:
        result = _cpanel_request("GET", f"https://{HOST}:2083/execute/Stats/get_bandwidth")
        if result.get("status") == 1:
            print("✅ cPanel UAPI auth works")
        else:
            print(f"❌ cPanel UAPI auth failed: {result.get('errors', result)}")
            print("   Possible fix: generate a cPanel API Token in cPanel → Manage API Tokens")
            print("   Then set BLUEHOST_CPANEL_TOKEN=<token> in .deploy.env and use it as auth")

    print()


# ─── Full Deploy Flow ─────────────────────────────────────────────────────────

def full_deploy():
    print("\n" + "=" * 60)
    print("IHRP PRODUCTION DEPLOY")
    print("=" * 60 + "\n")

    ssh = get_ssh()

    try:
        print("STEP 1 — Pre-Flight Reminder")
        print("Before proceeding, confirm:")
        print("  a) New .env keys? → update production .env via SFTP first")
        print("  b) composer.json changed? → vendor/ rebuilt and committed?")
        print("  c) Code pushed to GitHub? → git push origin master")
        print()
        input("Press Enter when ready to continue...")
        print()

        pending = step_migrate_status(ssh)
        step_cpanel_deploy(ssh)
        step_verify_env(ssh)
        step_storage_link(ssh)
        step_safety_checks(ssh)

        if pending:
            step_run_migrations(ssh)
            step_clear_cache(ssh)
        else:
            print("STEP 6 — Migrations: None pending, skipped.\n")

        step_tail_log(ssh)
        step_smoke(ssh)

    finally:
        ssh.close()
        print("SSH connection closed.")
        print("\n✅ Deploy sequence complete.\n")


# ─── CLI ──────────────────────────────────────────────────────────────────────

STEP_MAP = {
    "diagnose":        lambda ssh: step_diagnose(),
    "migrate-status":  lambda ssh: step_migrate_status(ssh),
    "run-migrations":  lambda ssh: step_run_migrations(ssh),
    "verify-env":      lambda ssh: step_verify_env(ssh),
    "storage-link":    lambda ssh: step_storage_link(ssh),
    "safety-checks":   lambda ssh: step_safety_checks(ssh),
    "clear-cache":     lambda ssh: step_clear_cache(ssh),
    "tail-log":        lambda ssh: step_tail_log(ssh),
    "smoke":           lambda ssh: step_smoke(ssh),
    "deploy":          lambda ssh: step_cpanel_deploy(ssh),
    "ssh-deploy":      lambda ssh: step_ssh_deploy(ssh),
}

# Steps that don't need an SSH connection
NO_SSH_STEPS = {"diagnose", "smoke"}


def main():
    parser = argparse.ArgumentParser(description="IHRP Production Deploy Script")
    parser.add_argument(
        "--step",
        choices=list(STEP_MAP.keys()),
        help="Run a single step instead of the full deploy flow",
    )
    args = parser.parse_args()

    if args.step:
        if args.step in NO_SSH_STEPS:
            STEP_MAP[args.step](None)
        else:
            ssh = get_ssh()
            try:
                STEP_MAP[args.step](ssh)
            finally:
                ssh.close()
    else:
        full_deploy()


if __name__ == "__main__":
    main()
