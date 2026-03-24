<?php

namespace App\Console\Commands;

use App\Support\BiweeklyPayPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GenerateTimesheetTemplate extends Command
{
    protected $signature   = 'timesheets:generate-template';
    protected $description = 'Regenerate storage/app/templates/timesheet_template.xlsx';

    public function handle(): int
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Worksheet');

        // ── Row 1: Title ──────────────────────────────────────────────
        $sheet->setCellValue('A1', 'BI-WEEKLY TIMESHEET');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        // ── Row 2: Consultant name ────────────────────────────────────
        $sheet->setCellValue('A2', 'Consultant:');
        $sheet->setCellValue('B2', '');  // user fills this in
        $sheet->getStyle('A2')->getFont()->setBold(true);

        // ── Rows 3-4: blank ───────────────────────────────────────────

        [$payStart, $payEnd] = BiweeklyPayPeriod::suggestedPeriodContaining(Carbon::now());
        $periodStart = Carbon::parse($payStart)->startOfDay();

        // ── Row 6: Pay Period Start ───────────────────────────────────
        $sheet->setCellValue('A6', 'Pay Period Start:');
        $sheet->setCellValue('F6', $payStart);
        $sheet->getStyle('A6')->getFont()->setBold(true);
        $sheet->getStyle('F6')->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // ── Row 7: Pay Period End ─────────────────────────────────────
        $sheet->setCellValue('A7', 'Pay Period End:');
        $sheet->setCellValue('F7', $payEnd);
        $sheet->getStyle('A7')->getFont()->setBold(true);
        $sheet->getStyle('F7')->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // ── Row 8: blank ──────────────────────────────────────────────

        // ── Row 9: Column headers ─────────────────────────────────────
        $sheet->setCellValue('A9', 'DATE');
        $sheet->setCellValue('B9', 'DAY');
        $sheet->setCellValue('C9', 'DESCRIPTION');
        $sheet->setCellValue('G9', 'HOURS');
        $sheet->getStyle('A9:G9')->getFont()->setBold(true);
        $sheet->getStyle('A9:G9')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E1F2');

        // ── Rows 10–16: Week 1 (Mon–Sun) ─────────────────────────────
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $dateFmt = NumberFormat::FORMAT_DATE_YYYYMMDDSLASH;
        for ($i = 0; $i < 7; $i++) {
            $row = 10 + $i;
            $d = $periodStart->copy()->addDays($i);
            $sheet->setCellValue("A{$row}", ExcelDate::PHPToExcel($d->toDateTime()));
            $sheet->getStyle("A{$row}")->getNumberFormat()->setFormatCode($dateFmt);
            $sheet->setCellValue("B{$row}", $days[$i]);
            $sheet->setCellValue("G{$row}", 0);
            $sheet->getStyle("G{$row}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
        }

        // ── Rows 17–23: Week 2 (Mon–Sun) ─────────────────────────────
        for ($i = 0; $i < 7; $i++) {
            $row = 17 + $i;
            $d = $periodStart->copy()->addDays(7 + $i);
            $sheet->setCellValue("A{$row}", ExcelDate::PHPToExcel($d->toDateTime()));
            $sheet->getStyle("A{$row}")->getNumberFormat()->setFormatCode($dateFmt);
            $sheet->setCellValue("B{$row}", $days[$i]);
            $sheet->setCellValue("G{$row}", 0);
            $sheet->getStyle("G{$row}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
        }

        // ── Row 25: Total ─────────────────────────────────────────────
        $sheet->setCellValue('F25', 'TOTAL HOURS:');
        $sheet->setCellValue('G25', '=SUM(G10:G23)');
        $sheet->getStyle('F25:G25')->getFont()->setBold(true);

        // ── Column widths ─────────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('G')->setWidth(12);

        // ── Save ──────────────────────────────────────────────────────
        $dir  = storage_path('app/templates');
        $path = $dir . '/timesheet_template.xlsx';

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        $this->info("Template written to {$path}");

        return self::SUCCESS;
    }
}
