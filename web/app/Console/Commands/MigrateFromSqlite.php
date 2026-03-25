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
        $this->migrateTimesheets();           // pass 1: invoice_id = NULL
        $this->migrateTimesheetDailyHours();
        $this->migrateInvoices();
        $this->migrateInvoiceLineItems();
        $this->migrateInvoiceSequence();
        $this->patchTimesheetInvoiceIds();    // pass 2: fill invoice_id
        $this->migrateAuditLog();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->table(
            ['Table', 'Rows migrated'],
            collect($this->counts)->map(fn ($v, $k) => [$k, $v])->values()->toArray()
        );

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

    private function dec(mixed $v): ?string
    {
        return $v !== null ? (string) round((float) $v, 4) : null;
    }

    private function bool(mixed $v): bool { return (bool) $v; }

    /** Normalize any date/datetime string to YYYY-MM-DD for MySQL DATE columns. */
    private function dateOnly(mixed $v): ?string
    {
        if (empty($v)) return null;
        // Handle ISO 8601 (2026-03-18T13:19:51.652Z) and plain dates (2026-03-18)
        return substr(str_replace('T', ' ', $v), 0, 10);
    }

    private function record(string $table, int $n): void
    {
        $this->counts[$table] = $n;
    }

    // ── Table migrators ────────────────────────────────────────────────────────

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
                    'id'                         => $r['id'],
                    'name'                       => $r['name'],
                    'billing_contact_name'       => $r['billing_contact_name'],
                    'billing_address'            => $r['billing_address'],
                    'email'                      => $r['email'],
                    'smtp_email'                 => $r['smtp_email'],
                    'payment_terms'              => $r['payment_terms'] ?? 'Net 30',
                    'total_budget'               => $this->dec($r['total_budget']),
                    'budget_alert_warning_sent'  => $this->bool($warningSent),
                    'budget_alert_critical_sent' => $this->bool($criticalSent),
                    'active'                     => $this->bool($r['active']),
                    'created_at'                 => $r['created_at'],
                    'updated_at'                 => $r['updated_at'],
                ],
                ['id'],
                ['name', 'billing_contact_name', 'billing_address', 'email', 'smtp_email',
                 'payment_terms', 'total_budget', 'budget_alert_warning_sent',
                 'budget_alert_critical_sent', 'active', 'updated_at']
            );
            $n++;
        }
        $this->record('clients', $n);
    }

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
                ['full_name', 'pay_rate', 'bill_rate', 'state', 'industry_type', 'client_id',
                 'project_start_date', 'project_end_date', 'w9_on_file', 'w9_file_path',
                 'active', 'updated_at']
            );
            $n++;
        }
        $this->record('consultants', $n);
    }

    private function migrateConsultantOnboardingItems(): void
    {
        $this->info('  → consultant_onboarding_items');
        $rows = $this->sq('SELECT * FROM consultant_onboarding_items');
        $n = 0;

        foreach ($rows as $r) {
            if ($this->dryRun) { $n++; continue; }

            // SQLite: column = 'item', completed_at exists
            // MySQL:  column = 'item_key', uses standard timestamps() — no completed_at
            DB::table('consultant_onboarding_items')->upsert(
                [
                    'id'            => $r['id'],
                    'consultant_id' => $r['consultant_id'],
                    'item_key'      => $r['item'],
                    'completed'     => $this->bool($r['completed']),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
                ['consultant_id', 'item_key'],
                ['completed', 'updated_at']
            );
            $n++;
        }
        $this->record('consultant_onboarding_items', $n);
    }

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
                    'invoice_id'             => null,   // patched in pass 2
                    'invoice_status'         => $r['invoice_status'] ?? 'pending',
                    'source_file_path'       => $r['source_file_path'] ?? null,
                    'created_at'             => $r['created_at'],
                    'updated_at'             => $r['updated_at'],
                ],
                ['id'],
                ['consultant_id', 'client_id', 'pay_period_start', 'pay_period_end',
                 'pay_rate_snapshot', 'bill_rate_snapshot', 'state_snapshot',
                 'industry_type_snapshot', 'ot_rule_applied',
                 'week1_regular_hours', 'week1_ot_hours', 'week1_dt_hours',
                 'week1_regular_pay', 'week1_ot_pay', 'week1_dt_pay',
                 'week1_regular_billable', 'week1_ot_billable', 'week1_dt_billable',
                 'week2_regular_hours', 'week2_ot_hours', 'week2_dt_hours',
                 'week2_regular_pay', 'week2_ot_pay', 'week2_dt_pay',
                 'week2_regular_billable', 'week2_ot_billable', 'week2_dt_billable',
                 'total_regular_hours', 'total_ot_hours', 'total_dt_hours',
                 'total_consultant_cost', 'total_client_billable',
                 'gross_revenue', 'gross_margin_dollars', 'gross_margin_percent',
                 'invoice_status', 'source_file_path', 'updated_at']
            );
            $n++;
        }
        $this->record('timesheets', $n);
    }

    private function migrateTimesheetDailyHours(): void
    {
        $this->info('  → timesheet_daily_hours');
        $rows = $this->sq('SELECT * FROM timesheet_daily_hours');
        $n = 0;

        // SQLite: day_index integer 0=Mon…6=Sun
        // MySQL:  day_of_week string (no timestamps on this table)
        $dayNames = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];

        foreach ($rows as $r) {
            if ($this->dryRun) { $n++; continue; }

            $dayOfWeek = $dayNames[(int) $r['day_index']] ?? 'monday';

            DB::table('timesheet_daily_hours')->upsert(
                [
                    'id'           => $r['id'],
                    'timesheet_id' => $r['timesheet_id'],
                    'week_number'  => $r['week_number'],
                    'day_of_week'  => $dayOfWeek,
                    'hours'        => $this->dec($r['hours']),
                ],
                ['timesheet_id', 'week_number', 'day_of_week'],
                ['hours']
            );
            $n++;
        }
        $this->record('timesheet_daily_hours', $n);
    }

    private function migrateInvoices(): void
    {
        $this->info('  → invoices');
        $rows = $this->sq('SELECT * FROM invoices');
        $n = 0;

        foreach ($rows as $r) {
            if ($this->dryRun) { $n++; continue; }

            // SQLite stores absolute pdf_path; MySQL expects relative: invoices/<basename>
            $pdfPath = null;
            if (! empty($r['pdf_path'])) {
                $basename = basename(str_replace('\\', '/', $r['pdf_path']));
                $pdfPath  = 'invoices/' . $basename;
            }

            DB::table('invoices')->upsert(
                [
                    'id'               => $r['id'],
                    'invoice_number'   => $r['invoice_number'],
                    'invoice_date'     => $this->dateOnly($r['invoice_date']),
                    'due_date'         => $this->dateOnly($r['due_date']),
                    'consultant_id'    => $r['consultant_id'],
                    'client_id'        => $r['client_id'],
                    'timesheet_id'     => $r['timesheet_id'],
                    'bill_to_name'     => $r['bill_to_name'],
                    'bill_to_contact'  => $r['bill_to_contact'],
                    'bill_to_address'  => $r['bill_to_address'],
                    'payment_terms'    => $r['payment_terms'] ?? 'Net 30',
                    'po_number'        => $r['po_number'] ?? null,
                    'notes'            => $r['notes'],
                    'subtotal'         => $this->dec($r['subtotal']),
                    'total_amount_due' => $this->dec($r['total_amount_due']),
                    'status'           => $r['status'] ?? 'pending',
                    'sent_date'        => $this->dateOnly($r['sent_date']),
                    'paid_date'        => $this->dateOnly($r['paid_date']),
                    'pdf_path'         => $pdfPath,
                    'created_at'       => $r['created_at'],
                    'updated_at'       => $r['updated_at'],
                ],
                ['id'],
                ['invoice_number', 'invoice_date', 'due_date', 'consultant_id', 'client_id',
                 'timesheet_id', 'bill_to_name', 'bill_to_contact', 'bill_to_address',
                 'payment_terms', 'po_number', 'notes', 'subtotal', 'total_amount_due',
                 'status', 'sent_date', 'paid_date', 'pdf_path', 'updated_at']
            );
            $n++;
        }
        $this->record('invoices', $n);
    }

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
                ['invoice_id', 'week_number', 'description', 'hours', 'rate',
                 'multiplier', 'amount', 'sort_order']
            );
            $n++;
        }
        $this->record('invoice_line_items', $n);
    }

    private function migrateInvoiceSequence(): void
    {
        $this->info('  → invoice_sequence');
        $rows = $this->sq('SELECT * FROM invoice_sequence WHERE id = 1');
        $row  = $rows[0] ?? null;

        if (! $row) {
            $this->record('invoice_sequence', 0);
            return;
        }

        if (! $this->dryRun) {
            // SQLite: current_number (last used) → MySQL: next_number (next to use)
            DB::table('invoice_sequence')->upsert(
                [
                    'id'          => 1,
                    'prefix'      => $row['prefix'] ?? '',
                    'next_number' => ($row['current_number'] ?? 1),
                ],
                ['id'],
                ['prefix', 'next_number']
            );
        }
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

    private function migrateAuditLog(): void
    {
        $this->info('  → audit_log');
        $rows = $this->sq('SELECT * FROM audit_log');
        $n = 0;

        foreach ($rows as $r) {
            if ($this->dryRun) { $n++; continue; }

            // audit_log has no unique constraint — use id to detect duplicates on re-run
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
                'user_id'       => null,   // pre-multiuser era — no actor to attribute
            ]);
            $n++;
        }
        $this->record('audit_log', $n);
    }
}
