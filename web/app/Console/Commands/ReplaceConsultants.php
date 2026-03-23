<?php

namespace App\Console\Commands;

use App\Models\Consultant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReplaceConsultants extends Command
{
    protected $signature = 'consultants:replace
                            {--year=2026 : Keep only data from this year}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Purge all non-current-year data and reset the consultant list to the active 2026 roster.';

    private array $consultants = [
        'Arman Ghazanchyan',
        'Charles Hanley',
        'Saleem Shaik',
        'Torrance Mohammed',
        'Preeti Srivastava',
        'Tanseef Fahad',
        'Gopinadh Kumar',
        'Jagan Rao Alleni',
        'Dheeraj Bandaru',
        'Gabriela Ibarra',
        'Oleg Yevteyev',
        'Linda Tracey',
        'Randall Beck',
        'Daxes Desai',
        'Judith Legaspi',
        'Charlotte Baker',
        'Jacquline Bendt',
        'Alexis Mes',
        'Kenny Lee',
        'Benjamin Picciano',
    ];

    public function handle(): int
    {
        $year = (int) $this->option('year');

        if (! $this->option('force')) {
            $this->warn("This will DELETE all data NOT from {$year} across timesheets, invoices,");
            $this->warn('payroll records, call reports, placements, and audit logs.');
            $this->warn('The consultant list will be replaced with the active 2026 roster (20 names).');

            if (! $this->confirm('Continue?')) {
                $this->info('Aborted.');
                return self::SUCCESS;
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            // --- Timesheets + children ---
            $oldTimesheetIds = DB::table('timesheets')
                ->whereRaw('YEAR(pay_period_start) != ?', [$year])
                ->pluck('id');

            if ($oldTimesheetIds->isNotEmpty()) {
                DB::table('timesheet_daily_hours')->whereIn('timesheet_id', $oldTimesheetIds)->delete();
                DB::table('invoices')->whereIn('timesheet_id', $oldTimesheetIds)->update(['timesheet_id' => null]);
                DB::table('timesheets')->whereIn('id', $oldTimesheetIds)->delete();
                $this->line("  Deleted {$oldTimesheetIds->count()} timesheet(s) not from {$year}.");
            }

            // --- Invoices + children ---
            $oldInvoiceIds = DB::table('invoices')
                ->whereRaw('YEAR(invoice_date) != ?', [$year])
                ->pluck('id');

            if ($oldInvoiceIds->isNotEmpty()) {
                DB::table('invoice_line_items')->whereIn('invoice_id', $oldInvoiceIds)->delete();
                DB::table('invoices')->whereIn('id', $oldInvoiceIds)->delete();
                $this->line("  Deleted {$oldInvoiceIds->count()} invoice(s) not from {$year}.");
            }

            // --- Daily call reports ---
            $deleted = DB::table('daily_call_reports')
                ->whereRaw('YEAR(report_date) != ?', [$year])
                ->delete();
            $this->line("  Deleted {$deleted} call report(s) not from {$year}.");

            // --- Placements (start_date) ---
            $deleted = DB::table('placements')
                ->whereRaw('YEAR(start_date) != ?', [$year])
                ->delete();
            $this->line("  Deleted {$deleted} placement(s) not from {$year}.");

            // --- Payroll records ---
            $deleted = DB::table('payroll_records')
                ->whereRaw('YEAR(check_date) != ?', [$year])
                ->delete();
            $this->line("  Deleted {$deleted} payroll record(s) not from {$year}.");

            // --- Payroll consultant entries ---
            $deleted = DB::table('payroll_consultant_entries')
                ->where('year', '!=', $year)
                ->delete();
            $this->line("  Deleted {$deleted} payroll consultant entries not from {$year}.");

            // --- Payroll goals ---
            $deleted = DB::table('payroll_goals')
                ->where('year', '!=', $year)
                ->delete();
            $this->line("  Deleted {$deleted} payroll goal(s) not from {$year}.");

            // --- Audit log ---
            $deleted = DB::table('audit_log')
                ->whereRaw('YEAR(timestamp) != ?', [$year])
                ->delete();
            $this->line("  Deleted {$deleted} audit log entry/entries not from {$year}.");

            // --- Replace consultant list ---
            DB::table('consultant_onboarding_items')->truncate();
            DB::table('payroll_consultant_mappings')->update(['consultant_id' => null]);
            DB::table('payroll_consultant_entries')->update(['consultant_id' => null]);
            DB::table('consultants')->truncate();

            $now = now();
            $rows = array_map(fn ($name) => [
                'full_name'  => $name,
                'active'     => true,
                'w9_on_file' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ], $this->consultants);

            Consultant::insert($rows);
            $this->line('  Consultant list replaced with ' . count($this->consultants) . ' active names.');

        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->newLine();
        $this->info("✅ Done — database cleaned to {$year} data only.");

        return self::SUCCESS;
    }
}
