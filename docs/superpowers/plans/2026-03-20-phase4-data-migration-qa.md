# Phase 4: Data Migration + QA Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate all live data from the Electron app's SQLite database into Laravel's MySQL database, copy associated files, and verify data integrity before Phase 5 deployment.

**Architecture:** A single idempotent Artisan command (`migrate:from-sqlite`) reads the SQLite file directly using PDO, inserts rows into MySQL in FK-safe order (two-pass for the `timesheets ↔ invoices` circular reference), and validates with row counts + money checksums. File migration is a separate script that copies invoice PDFs and timesheet XLSXs into Laravel storage.

**Tech Stack:** PHP 8.3, Laravel Artisan commands, PDO (pdo_sqlite), MySQL, `Storage` facade

---

## Source / Target Reference

| Item | Path |
|---|---|
| Live SQLite DB | `C:/Users/zobel/AppData/Roaming/payroll-app/payroll.db` |
| Invoice PDFs (source) | `C:/Users/zobel/AppData/Roaming/payroll-app/invoices/` |
| Timesheet XLSXs (source) | `C:/Users/zobel/AppData/Roaming/payroll-app/timesheets/` |
| W-9s (source) | `C:/Users/zobel/AppData/Roaming/payroll-app/w9s/` |
| Laravel invoice PDF target | `storage/app/invoices/<invoice_number>.pdf` |
| Laravel timesheet target | `storage/app/uploads/timesheets/<filename>` |
| Laravel W-9 target | `storage/app/uploads/w9s/<filename>` |

## Key Column Differences (SQLite → MySQL)

| SQLite column | MySQL column | Note |
|---|---|---|
| `clients.budget_alert_80_sent` | `budget_alert_warning_sent` | SQLite ran `RENAME COLUMN` migration; both names may exist depending on DB age |
| `clients.budget_alert_100_sent` | `budget_alert_critical_sent` | Same as above |
| `invoices.pdf_path` | `invoices.pdf_path` | SQLite = absolute path; MySQL = relative (`invoices/<number>.pdf`) |
| `consultants.w9_file_path` | `consultants.w9_file_path` | SQLite = basename only; MySQL = basename only (same) |
| `timesheets.invoice_id` | `timesheets.invoice_id` | Circular FK — migrate as NULL first, UPDATE after invoices are inserted |
| `audit_log.user_id` | `audit_log.user_id` | SQLite had no users — set NULL for all migrated rows |
| `backups.*` | (skip) | Electron-specific file paths; useless in web context |
| `daily_call_reports` | (skip) | New table, no source data in Electron |
| `placements` | (skip) | New table, no source data in Electron |

## Migration Insert Order (FK-safe)

1. `settings`
2. `clients`
3. `consultants`
4. `consultant_onboarding_items`
5. `timesheets` (first pass — `invoice_id = NULL`)
6. `timesheet_daily_hours`
7. `invoices`
8. `invoice_line_items`
9. `invoice_sequence`
10. UPDATE `timesheets.invoice_id` (second pass)
11. `audit_log`

---

## Task 1: Enable pdo_sqlite

**Files:**
- Modify: `C:/Users/zobel/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe/php.ini`

- [ ] **Step 1: Enable the extension in php.ini**

  Open the php.ini file. Find the section with other `extension=` lines (look for `extension=pdo_mysql`).
  Add this line immediately after it:
  ```ini
  extension=pdo_sqlite
  ```

- [ ] **Step 2: Verify the extension loads**

  Run:
  ```bash
  php -m | grep -i sqlite
  ```
  Expected: `pdo_sqlite` (and optionally `sqlite3`) appear in the list.

- [ ] **Step 3: Smoke test PDO connection from Laravel**

  ```bash
  cd web && php -r "
  \$db = new PDO('sqlite:C:/Users/zobel/AppData/Roaming/payroll-app/payroll.db');
  \$row = \$db->query('SELECT COUNT(*) as c FROM clients')->fetch();
  echo 'clients: ' . \$row['c'] . PHP_EOL;
  "
  ```
  Expected: `clients: <number>` with no exception.

- [ ] **Step 4: Commit**

  ```bash
  git add -p   # only the php.ini change if tracked; otherwise skip
  git commit -m "chore: enable pdo_sqlite extension for migration command"
  ```
  > Note: php.ini lives outside the repo. This commit is just for the Artisan command files below. No git action needed if php.ini isn't tracked.

---

## Task 2: Scaffold the Artisan Migration Command

**Files:**
- Create: `web/app/Console/Commands/MigrateFromSqlite.php`

- [ ] **Step 1: Generate the command stub**

  ```bash
  cd web && php artisan make:command MigrateFromSqlite
  ```

- [ ] **Step 2: Replace the generated file with the full scaffold**

  Replace `web/app/Console/Commands/MigrateFromSqlite.php` with:

  ```php
  <?php

  namespace App\Console\Commands;

  use Illuminate\Console\Command;
  use Illuminate\Support\Facades\DB;
  use PDO;

  class MigrateFromSqlite extends Command
  {
      protected $signature   = 'migrate:from-sqlite
                                  {--dry-run : Print counts only, do not write}
                                  {--db= : Path to SQLite file (default: payroll-app userData)}';
      protected $description = 'Migrate live SQLite data into MySQL. Idempotent — safe to re-run.';

      private PDO   $sqlite;
      private bool  $dryRun;
      private array $counts = [];

      public function handle(): int
      {
          $this->dryRun = (bool) $this->option('dry-run');
          $dbPath = $this->option('db')
              ?? 'C:/Users/zobel/AppData/Roaming/payroll-app/payroll.db';

          if (! file_exists($dbPath)) {
              $this->error("SQLite file not found: {$dbPath}");
              return 1;
          }

          $this->sqlite = new PDO("sqlite:{$dbPath}");
          $this->sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

          $this->info($this->dryRun ? '[DRY RUN] No data will be written.' : 'Starting migration...');

          DB::statement('SET FOREIGN_KEY_CHECKS=0');

          $this->migrateSettings();
          $this->migrateClients();
          $this->migrateConsultants();
          $this->migrateConsultantOnboardingItems();
          $this->migrateTimesheets();          // pass 1: invoice_id = NULL
          $this->migrateTimesheetDailyHours();
          $this->migrateInvoices();
          $this->migrateInvoiceLineItems();
          $this->migrateInvoiceSequence();
          $this->patchTimesheetInvoiceIds();   // pass 2: fill invoice_id
          $this->migrateAuditLog();

          DB::statement('SET FOREIGN_KEY_CHECKS=1');

          $this->table(['Table', 'Rows migrated'], collect($this->counts)
              ->map(fn ($v, $k) => [$k, $v])->values()->toArray());

          $this->info('Done.');
          return 0;
      }

      // ── Helpers ────────────────────────────────────────────────────────────────

      private function sq(string $sql, array $params = []): array
      {
          $stmt = $this->sqlite->prepare($sql);
          $stmt->execute($params);
          return $stmt->fetchAll();
      }

      private function dec(?float $v): ?string
      {
          return $v !== null ? (string) round($v, 4) : null;
      }

      private function bool(mixed $v): bool { return (bool) $v; }

      private function record(string $table, int $n): void
      {
          $this->counts[$table] = $n;
      }
  }
  ```

- [ ] **Step 3: Verify the command is registered**

  ```bash
  cd web && php artisan list | grep migrate:from
  ```
  Expected: `migrate:from-sqlite` appears.

- [ ] **Step 4: Commit**

  ```bash
  git add web/app/Console/Commands/MigrateFromSqlite.php
  git commit -m "feat: scaffold MigrateFromSqlite Artisan command"
  ```

---

## Task 3: Migrate Settings + Clients

**Files:**
- Modify: `web/app/Console/Commands/MigrateFromSqlite.php`

- [ ] **Step 1: Add `migrateSettings()` method**

  Add to `MigrateFromSqlite.php` (inside the class, after the helpers):

  ```php
  private function migrateSettings(): void
  {
      $this->info('  → settings');
      $rows = $this->sq('SELECT * FROM settings');
      $n = 0;

      foreach ($rows as $r) {
          if ($this->dryRun) { $n++; continue; }
          DB::table('settings')->upsert(
              [
                  'key'        => $r['key'],
                  'value'      => $r['value'],
                  'updated_at' => $r['updated_at'],
              ],
              ['key'],
              ['value', 'updated_at']
          );
          $n++;
      }
      $this->record('settings', $n);
  }
  ```

- [ ] **Step 2: Add `migrateClients()` method**

  ```php
  private function migrateClients(): void
  {
      $this->info('  → clients');
      $rows = $this->sq('SELECT * FROM clients');
      $n = 0;

      foreach ($rows as $r) {
          if ($this->dryRun) { $n++; continue; }

          // Column was renamed in a SQLite migration — handle both names
          $warningSent  = $r['budget_alert_warning_sent']  ?? $r['budget_alert_80_sent']  ?? 0;
          $criticalSent = $r['budget_alert_critical_sent'] ?? $r['budget_alert_100_sent'] ?? 0;

          DB::table('clients')->upsert(
              [
                  'id'                          => $r['id'],
                  'name'                        => $r['name'],
                  'billing_contact_name'        => $r['billing_contact_name'],
                  'billing_address'             => $r['billing_address'],
                  'email'                       => $r['email'],
                  'smtp_email'                  => $r['smtp_email'],
                  'payment_terms'               => $r['payment_terms'] ?? 'Net 30',
                  'total_budget'                => $this->dec($r['total_budget']),
                  'budget_alert_warning_sent'   => $this->bool($warningSent),
                  'budget_alert_critical_sent'  => $this->bool($criticalSent),
                  'po_number'                   => $r['po_number'] ?? null,
                  'active'                      => $this->bool($r['active']),
                  'created_at'                  => $r['created_at'],
                  'updated_at'                  => $r['updated_at'],
              ],
              ['id'],
              ['name','billing_contact_name','billing_address','email','smtp_email',
               'payment_terms','total_budget','budget_alert_warning_sent',
               'budget_alert_critical_sent','po_number','active','updated_at']
          );
          $n++;
      }
      $this->record('clients', $n);
  }
  ```

- [ ] **Step 3: Run dry-run to verify row counts are detected**

  ```bash
  cd web && php artisan migrate:from-sqlite --dry-run
  ```
  Expected: table prints `settings: N`, `clients: N` (other tables 0 until implemented).

- [ ] **Step 4: Run live migration for just these two tables**

  ```bash
  cd web && php artisan migrate:from-sqlite
  ```
  Then verify in MySQL:
  ```bash
  php artisan tinker --execute="echo DB::table('clients')->count();"
  ```
  Expected: matches SQLite count.

- [ ] **Step 5: Commit**

  ```bash
  git add web/app/Console/Commands/MigrateFromSqlite.php
  git commit -m "feat: migrate settings and clients from SQLite"
  ```

---

## Task 4: Migrate Consultants + Onboarding Items

**Files:**
- Modify: `web/app/Console/Commands/MigrateFromSqlite.php`

- [ ] **Step 1: Add `migrateConsultants()` method**

  ```php
  private function migrateConsultants(): void
  {
      $this->info('  → consultants');
      $rows = $this->sq('SELECT * FROM consultants');
      $n = 0;

      foreach ($rows as $r) {
          if ($this->dryRun) { $n++; continue; }

          // client_id is NOT NULL in MySQL — skip orphaned rows
          if (empty($r['client_id'])) {
              $this->warn("  Skipping consultant id={$r['id']} ({$r['full_name']}): no client_id");
              continue;
          }

          DB::table('consultants')->upsert(
              [
                  'id'                 => $r['id'],
                  'full_name'          => $r['full_name'],
                  'pay_rate'           => $this->dec($r['pay_rate']),
                  'bill_rate'          => $this->dec($r['bill_rate']),
                  'state'              => $r['state'],
                  'industry_type'      => $r['industry_type'] ?? 'other',
                  'client_id'          => $r['client_id'],
                  'project_start_date' => $r['project_start_date'],
                  'project_end_date'   => $r['project_end_date'],
                  'w9_on_file'         => $this->bool($r['w9_on_file']),
                  'w9_file_path'       => $r['w9_file_path'] ?? null,
                  'active'             => $this->bool($r['active']),
                  'created_at'         => $r['created_at'],
                  'updated_at'         => $r['updated_at'],
              ],
              ['id'],
              ['full_name','pay_rate','bill_rate','state','industry_type','client_id',
               'project_start_date','project_end_date','w9_on_file','w9_file_path',
               'active','updated_at']
          );
          $n++;
      }
      $this->record('consultants', $n);
  }
  ```

- [ ] **Step 2: Add `migrateConsultantOnboardingItems()` method**

  ```php
  private function migrateConsultantOnboardingItems(): void
  {
      $this->info('  → consultant_onboarding_items');
      $rows = $this->sq('SELECT * FROM consultant_onboarding_items');
      $n = 0;

      foreach ($rows as $r) {
          if ($this->dryRun) { $n++; continue; }

          DB::table('consultant_onboarding_items')->upsert(
              [
                  'id'            => $r['id'],
                  'consultant_id' => $r['consultant_id'],
                  'item'          => $r['item'],
                  'completed'     => $this->bool($r['completed']),
                  'completed_at'  => $r['completed_at'],
              ],
              ['consultant_id', 'item'],
              ['completed', 'completed_at']
          );
          $n++;
      }
      $this->record('consultant_onboarding_items', $n);
  }
  ```

- [ ] **Step 3: Run and verify**

  ```bash
  cd web && php artisan migrate:from-sqlite
  php artisan tinker --execute="echo DB::table('consultants')->count();"
  ```

- [ ] **Step 4: Commit**

  ```bash
  git add web/app/Console/Commands/MigrateFromSqlite.php
  git commit -m "feat: migrate consultants and onboarding items from SQLite"
  ```

---

## Task 5: Migrate Timesheets + Daily Hours (Pass 1)

**Files:**
- Modify: `web/app/Console/Commands/MigrateFromSqlite.php`

- [ ] **Step 1: Add `migrateTimesheets()` method (invoice_id = NULL on first pass)**

  ```php
  private function migrateTimesheets(): void
  {
      $this->info('  → timesheets (pass 1 — invoice_id deferred)');
      $rows = $this->sq('SELECT * FROM timesheets');
      $n = 0;

      foreach ($rows as $r) {
          if ($this->dryRun) { $n++; continue; }

          DB::table('timesheets')->upsert(
              [
                  'id'                     => $r['id'],
                  'consultant_id'          => $r['consultant_id'],
                  'client_id'              => $r['client_id'],
                  'pay_period_start'       => $r['pay_period_start'],
                  'pay_period_end'         => $r['pay_period_end'],
                  'pay_rate_snapshot'      => $this->dec($r['pay_rate_snapshot']),
                  'bill_rate_snapshot'     => $this->dec($r['bill_rate_snapshot']),
                  'state_snapshot'         => $r['state_snapshot'],
                  'industry_type_snapshot' => $r['industry_type_snapshot'] ?? 'other',
                  'ot_rule_applied'        => $r['ot_rule_applied'] ?? 'FLSA Weekly Only',
                  'week1_regular_hours'    => $this->dec($r['week1_regular_hours']),
                  'week1_ot_hours'         => $this->dec($r['week1_ot_hours']),
                  'week1_dt_hours'         => $this->dec($r['week1_dt_hours']),
                  'week1_regular_pay'      => $this->dec($r['week1_regular_pay']),
                  'week1_ot_pay'           => $this->dec($r['week1_ot_pay']),
                  'week1_dt_pay'           => $this->dec($r['week1_dt_pay']),
                  'week1_regular_billable' => $this->dec($r['week1_regular_billable']),
                  'week1_ot_billable'      => $this->dec($r['week1_ot_billable']),
                  'week1_dt_billable'      => $this->dec($r['week1_dt_billable']),
                  'week2_regular_hours'    => $this->dec($r['week2_regular_hours']),
                  'week2_ot_hours'         => $this->dec($r['week2_ot_hours']),
                  'week2_dt_hours'         => $this->dec($r['week2_dt_hours']),
                  'week2_regular_pay'      => $this->dec($r['week2_regular_pay']),
                  'week2_ot_pay'           => $this->dec($r['week2_ot_pay']),
                  'week2_dt_pay'           => $this->dec($r['week2_dt_pay']),
                  'week2_regular_billable' => $this->dec($r['week2_regular_billable']),
                  'week2_ot_billable'      => $this->dec($r['week2_ot_billable']),
                  'week2_dt_billable'      => $this->dec($r['week2_dt_billable']),
                  'total_regular_hours'    => $this->dec($r['total_regular_hours']),
                  'total_ot_hours'         => $this->dec($r['total_ot_hours']),
                  'total_dt_hours'         => $this->dec($r['total_dt_hours']),
                  'total_consultant_cost'  => $this->dec($r['total_consultant_cost']),
                  'total_client_billable'  => $this->dec($r['total_client_billable']),
                  'gross_revenue'          => $this->dec($r['gross_revenue']),
                  'gross_margin_dollars'   => $this->dec($r['gross_margin_dollars']),
                  'gross_margin_percent'   => $this->dec($r['gross_margin_percent']),
                  'invoice_id'             => null,  // patched in pass 2
                  'invoice_status'         => $r['invoice_status'] ?? 'pending',
                  'source_file_path'       => $r['source_file_path'] ?? null,
                  'created_at'             => $r['created_at'],
                  'updated_at'             => $r['updated_at'],
              ],
              ['id'],
              ['consultant_id','client_id','pay_period_start','pay_period_end',
               'pay_rate_snapshot','bill_rate_snapshot','state_snapshot',
               'industry_type_snapshot','ot_rule_applied',
               'week1_regular_hours','week1_ot_hours','week1_dt_hours',
               'week1_regular_pay','week1_ot_pay','week1_dt_pay',
               'week1_regular_billable','week1_ot_billable','week1_dt_billable',
               'week2_regular_hours','week2_ot_hours','week2_dt_hours',
               'week2_regular_pay','week2_ot_pay','week2_dt_pay',
               'week2_regular_billable','week2_ot_billable','week2_dt_billable',
               'total_regular_hours','total_ot_hours','total_dt_hours',
               'total_consultant_cost','total_client_billable',
               'gross_revenue','gross_margin_dollars','gross_margin_percent',
               'invoice_status','source_file_path','updated_at']
          );
          $n++;
      }
      $this->record('timesheets', $n);
  }
  ```

- [ ] **Step 2: Add `migrateTimesheetDailyHours()` method**

  ```php
  private function migrateTimesheetDailyHours(): void
  {
      $this->info('  → timesheet_daily_hours');
      $rows = $this->sq('SELECT * FROM timesheet_daily_hours');
      $n = 0;

      foreach ($rows as $r) {
          if ($this->dryRun) { $n++; continue; }

          DB::table('timesheet_daily_hours')->upsert(
              [
                  'id'           => $r['id'],
                  'timesheet_id' => $r['timesheet_id'],
                  'week_number'  => $r['week_number'],
                  'day_index'    => $r['day_index'],
                  'hours'        => $this->dec($r['hours']),
              ],
              ['timesheet_id', 'week_number', 'day_index'],
              ['hours']
          );
          $n++;
      }
      $this->record('timesheet_daily_hours', $n);
  }
  ```

- [ ] **Step 3: Run and verify timesheet counts**

  ```bash
  cd web && php artisan migrate:from-sqlite
  php artisan tinker --execute="echo DB::table('timesheets')->count() . ' / ' . DB::table('timesheet_daily_hours')->count();"
  ```

- [ ] **Step 4: Commit**

  ```bash
  git add web/app/Console/Commands/MigrateFromSqlite.php
  git commit -m "feat: migrate timesheets and daily hours from SQLite (invoice_id deferred)"
  ```

---

## Task 6: Migrate Invoices + Line Items + Sequence

**Files:**
- Modify: `web/app/Console/Commands/MigrateFromSqlite.php`

- [ ] **Step 1: Add `migrateInvoices()` method**

  Note: SQLite stores `pdf_path` as an absolute path (e.g. `C:\Users\...\invoices\000001.pdf`).
  MySQL expects relative: `invoices/000001.pdf`. Extract the basename.

  ```php
  private function migrateInvoices(): void
  {
      $this->info('  → invoices');
      $rows = $this->sq('SELECT * FROM invoices');
      $n = 0;

      foreach ($rows as $r) {
          if ($this->dryRun) { $n++; continue; }

          // Convert absolute pdf_path to relative Laravel storage path
          $pdfPath = null;
          if (! empty($r['pdf_path'])) {
              $basename = basename(str_replace('\\', '/', $r['pdf_path']));
              $pdfPath  = 'invoices/' . $basename;
          }

          DB::table('invoices')->upsert(
              [
                  'id'              => $r['id'],
                  'invoice_number'  => $r['invoice_number'],
                  'invoice_date'    => $r['invoice_date'],
                  'due_date'        => $r['due_date'],
                  'consultant_id'   => $r['consultant_id'],
                  'client_id'       => $r['client_id'],
                  'timesheet_id'    => $r['timesheet_id'],
                  'bill_to_name'    => $r['bill_to_name'],
                  'bill_to_contact' => $r['bill_to_contact'],
                  'bill_to_address' => $r['bill_to_address'],
                  'payment_terms'   => $r['payment_terms'] ?? 'Net 30',
                  'po_number'       => $r['po_number'] ?? null,
                  'notes'           => $r['notes'],
                  'subtotal'        => $this->dec($r['subtotal']),
                  'total_amount_due'=> $this->dec($r['total_amount_due']),
                  'status'          => $r['status'] ?? 'pending',
                  'sent_date'       => $r['sent_date'],
                  'paid_date'       => $r['paid_date'],
                  'pdf_path'        => $pdfPath,
                  'created_at'      => $r['created_at'],
                  'updated_at'      => $r['updated_at'],
              ],
              ['id'],
              ['invoice_number','invoice_date','due_date','consultant_id','client_id',
               'timesheet_id','bill_to_name','bill_to_contact','bill_to_address',
               'payment_terms','po_number','notes','subtotal','total_amount_due',
               'status','sent_date','paid_date','pdf_path','updated_at']
          );
          $n++;
      }
      $this->record('invoices', $n);
  }
  ```

- [ ] **Step 2: Add `migrateInvoiceLineItems()` method**

  ```php
  private function migrateInvoiceLineItems(): void
  {
      $this->info('  → invoice_line_items');
      $rows = $this->sq('SELECT * FROM invoice_line_items');
      $n = 0;

      foreach ($rows as $r) {
          if ($this->dryRun) { $n++; continue; }

          DB::table('invoice_line_items')->upsert(
              [
                  'id'          => $r['id'],
                  'invoice_id'  => $r['invoice_id'],
                  'week_number' => $r['week_number'],
                  'description' => $r['description'],
                  'hours'       => $this->dec($r['hours']),
                  'rate'        => $this->dec($r['rate']),
                  'multiplier'  => $this->dec($r['multiplier'] ?? 1.0),
                  'amount'      => $this->dec($r['amount']),
                  'sort_order'  => $r['sort_order'] ?? 0,
              ],
              ['id'],
              ['invoice_id','week_number','description','hours','rate','multiplier',
               'amount','sort_order']
          );
          $n++;
      }
      $this->record('invoice_line_items', $n);
  }
  ```

- [ ] **Step 3: Add `migrateInvoiceSequence()` and `patchTimesheetInvoiceIds()`**

  ```php
  private function migrateInvoiceSequence(): void
  {
      $this->info('  → invoice_sequence');
      $row = $this->sq('SELECT * FROM invoice_sequence WHERE id = 1')[0] ?? null;
      if (! $row || $this->dryRun) {
          $this->record('invoice_sequence', $row ? 1 : 0);
          return;
      }

      DB::table('invoice_sequence')->upsert(
          [
              'id'             => 1,
              'prefix'         => $row['prefix'] ?? '',
              'current_number' => $row['current_number'] ?? 1,
          ],
          ['id'],
          ['prefix', 'current_number']
      );
      $this->record('invoice_sequence', 1);
  }

  private function patchTimesheetInvoiceIds(): void
  {
      $this->info('  → timesheets.invoice_id (pass 2 — patching)');
      $rows = $this->sq('SELECT id, invoice_id FROM timesheets WHERE invoice_id IS NOT NULL');
      $n = 0;

      foreach ($rows as $r) {
          if ($this->dryRun) { $n++; continue; }
          DB::table('timesheets')
              ->where('id', $r['id'])
              ->update(['invoice_id' => $r['invoice_id']]);
          $n++;
      }
      $this->record('timesheets.invoice_id patch', $n);
  }
  ```

- [ ] **Step 4: Run and verify invoice counts**

  ```bash
  cd web && php artisan migrate:from-sqlite
  php artisan tinker --execute="echo DB::table('invoices')->count() . ' invoices, ' . DB::table('invoice_line_items')->count() . ' line items';"
  ```

- [ ] **Step 5: Commit**

  ```bash
  git add web/app/Console/Commands/MigrateFromSqlite.php
  git commit -m "feat: migrate invoices, line items, sequence, and patch timesheet invoice_ids"
  ```

---

## Task 7: Migrate Audit Log

**Files:**
- Modify: `web/app/Console/Commands/MigrateFromSqlite.php`

- [ ] **Step 1: Add `migrateAuditLog()` method**

  All historical audit log rows get `user_id = NULL` — the Electron app had no multi-user concept.

  ```php
  private function migrateAuditLog(): void
  {
      $this->info('  → audit_log');
      $rows = $this->sq('SELECT * FROM audit_log');
      $n = 0;

      foreach ($rows as $r) {
          if ($this->dryRun) { $n++; continue; }

          // Check for duplicate before inserting (audit_log has no unique key)
          $exists = DB::table('audit_log')->where('id', $r['id'])->exists();
          if ($exists) { $n++; continue; }

          DB::table('audit_log')->insert([
              'id'            => $r['id'],
              'timestamp'     => $r['timestamp'],
              'table_name'    => $r['table_name'],
              'record_id'     => $r['record_id'],
              'action_type'   => $r['action_type'],
              'field_changed' => $r['field_changed'],
              'old_value'     => $r['old_value'],
              'new_value'     => $r['new_value'],
              'description'   => $r['description'],
              'user_id'       => null,  // pre-multiuser era
          ]);
          $n++;
      }
      $this->record('audit_log', $n);
  }
  ```

- [ ] **Step 2: Run full migration and check totals**

  ```bash
  cd web && php artisan migrate:from-sqlite
  ```
  Expected output: full table of row counts, all non-zero (except `daily_call_reports` and `placements` which are new).

- [ ] **Step 3: Commit**

  ```bash
  git add web/app/Console/Commands/MigrateFromSqlite.php
  git commit -m "feat: migrate audit log from SQLite (user_id=NULL for pre-multiuser rows)"
  ```

---

## Task 8: Validation — Row Counts + Money Checksums

**Files:**
- Create: `web/app/Console/Commands/ValidateMigration.php`

- [ ] **Step 1: Generate the command**

  ```bash
  cd web && php artisan make:command ValidateMigration
  ```

- [ ] **Step 2: Implement the validation command**

  Replace `web/app/Console/Commands/ValidateMigration.php` with:

  ```php
  <?php

  namespace App\Console\Commands;

  use Illuminate\Console\Command;
  use Illuminate\Support\Facades\DB;
  use PDO;

  class ValidateMigration extends Command
  {
      protected $signature   = 'migrate:validate {--db= : Path to SQLite file}';
      protected $description = 'Compare SQLite row counts and money totals against MySQL.';

      public function handle(): int
      {
          $dbPath = $this->option('db')
              ?? 'C:/Users/zobel/AppData/Roaming/payroll-app/payroll.db';

          $sqlite = new PDO("sqlite:{$dbPath}");
          $sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

          $checks = [
              // [label, sqlite_sql, mysql_table_or_sql]
              ['clients (count)',       'SELECT COUNT(*) as v FROM clients',       'clients'],
              ['consultants (count)',   'SELECT COUNT(*) as v FROM consultants',   'consultants'],
              ['timesheets (count)',    'SELECT COUNT(*) as v FROM timesheets',     'timesheets'],
              ['invoices (count)',      'SELECT COUNT(*) as v FROM invoices',       'invoices'],
              ['invoice_line_items',    'SELECT COUNT(*) as v FROM invoice_line_items', 'invoice_line_items'],
              ['timesheet_daily_hours', 'SELECT COUNT(*) as v FROM timesheet_daily_hours', 'timesheet_daily_hours'],
              ['audit_log (count)',     'SELECT COUNT(*) as v FROM audit_log',     'audit_log'],
          ];

          // Money checksum checks
          $moneyChecks = [
              [
                  'total_client_billable SUM',
                  'SELECT ROUND(SUM(total_client_billable),2) as v FROM timesheets',
                  'SELECT ROUND(SUM(total_client_billable),2) as v FROM timesheets',
              ],
              [
                  'total_amount_due SUM (invoices)',
                  'SELECT ROUND(SUM(total_amount_due),2) as v FROM invoices',
                  'SELECT ROUND(SUM(total_amount_due),2) as v FROM invoices',
              ],
          ];

          $rows   = [];
          $passed = 0;
          $failed = 0;

          foreach ($checks as [$label, $sqSql, $myTable]) {
              $sqVal = $sqlite->query($sqSql)->fetch()['v'];
              $myVal = DB::table($myTable)->count();
              $ok    = (string)$sqVal === (string)$myVal;
              $ok ? $passed++ : $failed++;
              $rows[] = [$label, $sqVal, $myVal, $ok ? '✅' : '❌'];
          }

          foreach ($moneyChecks as [$label, $sqSql, $mySql]) {
              $sqVal = $sqlite->query($sqSql)->fetch()['v'];
              $myVal = DB::selectOne($mySql)->v;
              $ok    = abs((float)$sqVal - (float)$myVal) < 0.02; // allow $0.02 rounding tolerance
              $ok ? $passed++ : $failed++;
              $rows[] = [$label, $sqVal, $myVal, $ok ? '✅' : '❌'];
          }

          $this->table(['Check', 'SQLite', 'MySQL', 'Result'], $rows);
          $this->info("Passed: {$passed} | Failed: {$failed}");

          return $failed > 0 ? 1 : 0;
      }
  }
  ```

- [ ] **Step 3: Run the validator**

  ```bash
  cd web && php artisan migrate:validate
  ```
  Expected: all rows show ✅. Any ❌ means re-run `migrate:from-sqlite` and investigate.

- [ ] **Step 4: Commit**

  ```bash
  git add web/app/Console/Commands/ValidateMigration.php
  git commit -m "feat: add ValidateMigration command with row-count and money checksums"
  ```

---

## Task 9: File Migration Script

**Files:**
- Create: `web/app/Console/Commands/MigrateFiles.php`

- [ ] **Step 1: Generate the command**

  ```bash
  cd web && php artisan make:command MigrateFiles
  ```

- [ ] **Step 2: Implement the file copy command**

  ```php
  <?php

  namespace App\Console\Commands;

  use Illuminate\Console\Command;
  use Illuminate\Support\Facades\Storage;

  class MigrateFiles extends Command
  {
      protected $signature   = 'migrate:files
                                  {--source= : Base userData path (default: payroll-app path)}
                                  {--dry-run : Show what would be copied without copying}';
      protected $description = 'Copy invoice PDFs, timesheet XLSXs, and W-9s from Electron userData to Laravel storage.';

      public function handle(): int
      {
          $source  = rtrim(
              $this->option('source') ?? 'C:/Users/zobel/AppData/Roaming/payroll-app',
              '/\\'
          );
          $dryRun  = (bool) $this->option('dry-run');

          $jobs = [
              [
                  'label'  => 'Invoice PDFs',
                  'srcDir' => $source . '/invoices',
                  'dstDir' => 'invoices',
                  'ext'    => 'pdf',
              ],
              [
                  'label'  => 'Timesheet XLSXs',
                  'srcDir' => $source . '/timesheets',
                  'dstDir' => 'uploads/timesheets',
                  'ext'    => ['xlsx','xls','csv'],
              ],
              [
                  'label'  => 'W-9 files',
                  'srcDir' => $source . '/w9s',
                  'dstDir' => 'uploads/w9s',
                  'ext'    => null,  // any extension
              ],
          ];

          foreach ($jobs as $job) {
              $this->info("  → {$job['label']}");

              if (! is_dir($job['srcDir'])) {
                  $this->warn("    Source dir not found: {$job['srcDir']} — skipping");
                  continue;
              }

              $files = array_diff(scandir($job['srcDir']), ['.', '..']);
              $n = 0;

              foreach ($files as $filename) {
                  $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                  if ($job['ext'] !== null) {
                      $allowed = is_array($job['ext']) ? $job['ext'] : [$job['ext']];
                      if (! in_array($ext, $allowed)) continue;
                  }

                  $srcPath = $job['srcDir'] . '/' . $filename;
                  $dstPath = $job['dstDir'] . '/' . $filename;

                  if ($dryRun) {
                      $this->line("    [dry] {$srcPath} → {$dstPath}");
                  } else {
                      Storage::disk('local')->put($dstPath, file_get_contents($srcPath));
                      $this->line("    ✅ {$filename}");
                  }
                  $n++;
              }

              $this->info("    {$n} files " . ($dryRun ? 'found' : 'copied'));
          }

          return 0;
      }
  }
  ```

- [ ] **Step 3: Dry-run first**

  ```bash
  cd web && php artisan migrate:files --dry-run
  ```
  Expected: lists all files to be copied without writing anything.

- [ ] **Step 4: Run live copy**

  ```bash
  cd web && php artisan migrate:files
  ```

- [ ] **Step 5: Verify files landed correctly**

  ```bash
  ls storage/app/invoices/
  ls storage/app/uploads/timesheets/
  ```
  Expected: same filenames as in the Electron userData directories.

- [ ] **Step 6: Spot-check one invoice PDF renders in-app**

  Log in as admin → Invoices → open an invoice → click "Preview PDF".
  Expected: PDF loads correctly (not a 404 or blank).

- [ ] **Step 7: Commit**

  ```bash
  git add web/app/Console/Commands/MigrateFiles.php
  git commit -m "feat: add MigrateFiles command to copy PDFs, XLSXs, and W-9s from Electron userData"
  ```

---

## Task 10: Admin UI — Link Users to Consultants

**Files:**
- Modify: `web/app/Http/Controllers/AdminUserController.php`
- Modify: `web/resources/views/admin/users/edit.blade.php` (or the user edit modal)

This resolves the Phase 3 carry-forward: `users.consultant_id` needs to be set from the admin panel so employee users can see their own placement and call data.

- [ ] **Step 1: Read the current AdminUserController edit/update methods**

  ```bash
  grep -n "edit\|update\|consultant" web/app/Http/Controllers/AdminUserController.php
  ```

- [ ] **Step 2: Pass consultants to the edit view**

  In `AdminUserController::edit()`, add to the data passed to the view:
  ```php
  'consultants' => \App\Models\Consultant::orderBy('full_name')->get(['id','full_name']),
  ```

- [ ] **Step 3: Add `consultant_id` to the update method**

  In `AdminUserController::update()`, include `consultant_id` in the validated fields:
  ```php
  $validated = $request->validate([
      'name'          => 'required|string|max:255',
      'email'         => 'required|email|unique:users,email,' . $user->id,
      'role'          => 'required|in:admin,account_manager,employee',
      'active'        => 'boolean',
      'consultant_id' => 'nullable|exists:consultants,id',
  ]);
  $user->update($validated);
  ```

- [ ] **Step 4: Add the consultant dropdown to the edit view**

  In `resources/views/admin/users/` (edit form or modal), add after the role field:

  ```blade
  <div>
      <label class="block text-sm font-medium text-gray-700">Linked Consultant</label>
      <select name="consultant_id"
              class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
          <option value="">— None —</option>
          @foreach ($consultants as $c)
              <option value="{{ $c->id }}"
                  {{ old('consultant_id', $user->consultant_id) == $c->id ? 'selected' : '' }}>
                  {{ $c->full_name }}
              </option>
          @endforeach
      </select>
      <p class="mt-1 text-xs text-gray-500">Links this user to their consultant record for dashboard data.</p>
  </div>
  ```

- [ ] **Step 5: Test the UI**

  - Log in as admin → `/admin/users` → edit an employee user
  - Select a consultant from the dropdown → save
  - Verify `users.consultant_id` is updated in the DB:
    ```bash
    php artisan tinker --execute="dump(App\Models\User::where('role','employee')->first()?->consultant_id);"
    ```

- [ ] **Step 6: Commit**

  ```bash
  git add web/app/Http/Controllers/AdminUserController.php
  git add web/resources/views/admin/users/
  git commit -m "feat: admin UI to link user accounts to consultant records"
  ```

---

## Task 11: Cleanup — Remove smoke_*.py Files

**Files:**
- Delete: any `smoke_*.py` files in the project root or `web/` directory

- [ ] **Step 1: Find them**

  ```bash
  find C:/Users/zobel/Claude-Workspace/projects/IHRP -name "smoke_*.py" 2>/dev/null
  ```

- [ ] **Step 2: Delete them**

  Delete each file found. They were debugging scripts from Phase 3 build sessions.

- [ ] **Step 3: Commit**

  ```bash
  git add -u
  git commit -m "chore: remove smoke test Python scripts (replaced by Artisan validation)"
  ```

---

## Task 12: Full Regression Smoke Test

This is the QA gate before Phase 5 (deploy). Run manually and mark each item.

- [ ] **Role: admin**
  - [ ] Login works
  - [ ] Dashboard loads stat cards with real data (not all zeros)
  - [ ] Clients: list loads, CRUD works on an existing migrated client
  - [ ] Consultants: list loads, existing consultant shows correct name + rates
  - [ ] Invoices: list loads, click an existing invoice → PDF previews correctly
  - [ ] Timesheets: list loads, existing timesheet shows correct hours + OT breakdown
  - [ ] Reports: year-end PDF generates without error; monthly CSV downloads
  - [ ] Settings: loads all 6 tabs, existing settings values are present
  - [ ] Placements: list loads, admin can create/edit/delete
  - [ ] Calls: submit a report, view own history
  - [ ] Calls Report: aggregate table shows employee totals

- [ ] **Role: account_manager**
  - [ ] Dashboard loads
  - [ ] Placements: full access
  - [ ] Calls Report: accessible

- [ ] **Role: employee**
  - [ ] Employee dashboard: My Placement card shows data (if consultant_id is set)
  - [ ] Placements: read-only scoped to own consultant
  - [ ] Calls: submit and view own history only
  - [ ] Calls Report: 403 as expected

- [ ] **Validation command passes**

  ```bash
  cd web && php artisan migrate:validate
  ```
  Expected: all ✅

- [ ] **OT tests still pass**

  ```bash
  cd web && php artisan test --filter=OvertimeCalculatorTest
  ```
  Expected: 44 tests, 120 assertions, 0 failures

- [ ] **Commit final status to DEVLOG.md** (append `✅ [REVIEW — Claude Code] — Phase 4 Complete`)

---

## Acceptance Criteria

- [ ] `php artisan migrate:from-sqlite` completes without errors
- [ ] `php artisan migrate:validate` shows all ✅
- [ ] Invoice PDF preview works for at least one migrated invoice
- [ ] OT tests: 44 passed, 120 assertions, 0 failures
- [ ] `users.consultant_id` settable from admin UI
- [ ] All 3 roles pass the regression smoke checklist
- [ ] `smoke_*.py` files removed from repo

## Files Created/Modified

| File | Action |
|---|---|
| `web/app/Console/Commands/MigrateFromSqlite.php` | Create |
| `web/app/Console/Commands/ValidateMigration.php` | Create |
| `web/app/Console/Commands/MigrateFiles.php` | Create |
| `web/app/Http/Controllers/AdminUserController.php` | Modify |
| `web/resources/views/admin/users/` (edit view) | Modify |
| `smoke_*.py` files | Delete |
| `php.ini` (system-level) | Modify (enable pdo_sqlite) |
