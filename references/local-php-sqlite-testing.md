# Local PHP: SQLite for PHPUnit (IHRP)

`web/phpunit.xml` sets `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` so **MySQL is not required** to run the test suite. PHP must load **`pdo_sqlite`**.

## Verify

From the `web/` directory:

```bash
php -r "echo extension_loaded('pdo_sqlite') ? 'pdo_sqlite: OK' : 'pdo_sqlite: MISSING'; echo PHP_EOL;"
php artisan test
```

## Windows (e.g. PHP from WinGet)

1. Find the active ini: `php --ini` → **Loaded Configuration File**
2. Open that `php.ini`
3. Ensure these lines are **uncommented** (no leading `;`):
   - `extension=pdo_sqlite`
4. If PHP reports it cannot load the DLL, set **`extension_dir`** to the `ext` folder next to `php.exe` (same directory as in the official PHP zip layout).
5. Open a **new** terminal and re-run the verify commands above.

## Linux (Debian/Ubuntu)

Install the SQLite PDO module for your PHP version, e.g.:

```bash
sudo apt install php8.3-sqlite3
```

(Package name may vary by distro and PHP version.)

## macOS (Homebrew)

```bash
brew install php
# If sqlite is disabled, check $(brew --prefix)/etc/php/X.X/php.ini for extension=pdo_sqlite
```
