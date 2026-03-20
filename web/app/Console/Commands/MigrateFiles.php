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
        $source = rtrim(
            $this->option('source') ?? 'C:/Users/zobel/AppData/Roaming/payroll-app',
            '/\\'
        );
        $dryRun = (bool) $this->option('dry-run');

        $jobs = [
            [
                'label'   => 'Invoice PDFs',
                'srcDir'  => $source . '/invoices',
                'dstDir'  => 'invoices',
                'allowed' => ['pdf'],
            ],
            [
                'label'   => 'Timesheet XLSXs',
                'srcDir'  => $source . '/timesheets',
                'dstDir'  => 'uploads/timesheets',
                'allowed' => ['xlsx', 'xls', 'csv'],
            ],
            [
                'label'   => 'W-9 files',
                'srcDir'  => $source . '/w9s',
                'dstDir'  => 'uploads/w9s',
                'allowed' => null,   // any extension
            ],
        ];

        $totalCopied = 0;

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

                if ($job['allowed'] !== null && ! in_array($ext, $job['allowed'])) {
                    continue;
                }

                $srcPath = $job['srcDir'] . '/' . $filename;
                $dstPath = $job['dstDir'] . '/' . $filename;

                if ($dryRun) {
                    $this->line("    [dry] {$filename} → storage/app/{$dstPath}");
                } else {
                    Storage::disk('local')->put($dstPath, file_get_contents($srcPath));
                    $this->line("    ✅ {$filename}");
                }
                $n++;
            }

            $this->info("    {$n} file(s) " . ($dryRun ? 'found' : 'copied'));
            $totalCopied += $n;
        }

        $this->info($dryRun
            ? "Dry run complete. {$totalCopied} file(s) would be copied."
            : "File migration complete. {$totalCopied} file(s) copied."
        );

        return 0;
    }
}
