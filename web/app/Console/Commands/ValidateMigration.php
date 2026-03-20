<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class ValidateMigration extends Command
{
    protected $signature   = 'migrate:validate
                                {--db= : Path to SQLite file (default: payroll-app userData)}';
    protected $description = 'Compare SQLite row counts and money totals against MySQL.';

    public function handle(): int
    {
        $dbPath = $this->option('db')
            ?? 'C:/Users/zobel/AppData/Roaming/payroll-app/payroll.db';

        if (! file_exists($dbPath)) {
            $this->error("SQLite file not found: {$dbPath}");
            return 1;
        }

        $sqlite = new PDO("sqlite:{$dbPath}");
        $sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $rows   = [];
        $passed = 0;
        $failed = 0;

        // ── Row count checks ───────────────────────────────────────────────────
        $countChecks = [
            ['clients',                  'clients'],
            ['consultants',              'consultants'],
            ['consultant_onboarding_items', 'consultant_onboarding_items'],
            ['timesheets',               'timesheets'],
            ['timesheet_daily_hours',    'timesheet_daily_hours'],
            ['invoices',                 'invoices'],
            ['invoice_line_items',       'invoice_line_items'],
            ['audit_log',                'audit_log'],
        ];

        foreach ($countChecks as [$table, $mysqlTable]) {
            $sqVal = $sqlite->query("SELECT COUNT(*) as v FROM {$table}")->fetch()['v'];
            $myVal = DB::table($mysqlTable)->count();
            $ok    = (string) $sqVal === (string) $myVal;
            $ok ? $passed++ : $failed++;
            $rows[] = ["{$table} (count)", $sqVal, $myVal, $ok ? '✅' : '❌'];
        }

        // ── Money checksum checks ──────────────────────────────────────────────
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
            [
                'total_consultant_cost SUM',
                'SELECT ROUND(SUM(total_consultant_cost),2) as v FROM timesheets',
                'SELECT ROUND(SUM(total_consultant_cost),2) as v FROM timesheets',
            ],
        ];

        foreach ($moneyChecks as [$label, $sqSql, $mySql]) {
            $sqVal = $sqlite->query($sqSql)->fetch()['v'];
            $myVal = DB::selectOne($mySql)->v;
            // Allow $0.02 rounding tolerance for REAL→DECIMAL conversion
            $ok    = abs((float) $sqVal - (float) $myVal) < 0.02;
            $ok ? $passed++ : $failed++;
            $rows[] = [$label, $sqVal, $myVal, $ok ? '✅' : '❌'];
        }

        // ── Invoice sequence check ─────────────────────────────────────────────
        // SQLite: current_number | MySQL: next_number
        $sqSeq = $sqlite->query('SELECT current_number FROM invoice_sequence WHERE id = 1')->fetch();
        $mySeq = DB::table('invoice_sequence')->where('id', 1)->value('next_number');
        $ok    = (string) ($sqSeq['current_number'] ?? '') === (string) $mySeq;
        $ok ? $passed++ : $failed++;
        $rows[] = ['invoice_sequence (current→next)', $sqSeq['current_number'] ?? '—', $mySeq, $ok ? '✅' : '❌'];

        $this->table(['Check', 'SQLite', 'MySQL', 'Result'], $rows);
        $this->info("Passed: {$passed} | Failed: {$failed}");

        return $failed > 0 ? 1 : 0;
    }
}
