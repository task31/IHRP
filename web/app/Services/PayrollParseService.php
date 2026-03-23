<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Raw XLSX extraction for payroll workbooks. No DB writes.
 */
final class PayrollParseService
{
    private const SKIP_SHEETS = ['Payroll Summary', 'Full Time Recon'];

    private const HEADER_SEARCH_MAX_ROW = 20;

    /** @var list<string> Required columns — upload fails if any are absent */
    private const SUMMARY_REQUIRED_HEADERS = [
        'Sub-Total Gross Income',
        'Federal Tax',
        'Social Security',
        'Medicare',
        'State Tax',
        'Disability',
        'Check Amount',
    ];

    /** @var list<string> Optional columns — default $0 if absent */
    private const SUMMARY_OPTIONAL_HEADERS = [
        '401k Contribution',
    ];

    /** @var list<string> All numeric headers (required + optional) */
    private const SUMMARY_NUMERIC_HEADERS = [
        'Sub-Total Gross Income',
        'Federal Tax',
        'Social Security',
        'Medicare',
        'State Tax',
        'Disability',
        '401k Contribution',
        'Check Amount',
    ];

    public function parse(UploadedFile $file, string $stopName): PayrollParseResult
    {
        ini_set('memory_limit', '256M');

        $path = $file->getRealPath();
        if ($path === false) {
            throw new \RuntimeException('Invalid upload path');
        }

        $mime = $file->getMimeType();
        if ($mime !== 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
            throw new \InvalidArgumentException('File must be an .xlsx spreadsheet.');
        }

        $spreadsheet = IOFactory::load($path);
        $warnings = [];

        $summary = $this->parseSummarySheet($spreadsheet, $stopName);
        $consultantRows = $this->parseConsultantSheets($spreadsheet, $stopName);

        return new PayrollParseResult(
            $summary['ownerName'],
            $summary['records'],
            $consultantRows,
            $warnings,
        );
    }

    /**
     * @return array{ownerName: ?string, records: list<array<string, string>>}
     */
    private function parseSummarySheet(Spreadsheet $wb, string $stopName): array
    {
        $sheet = $wb->getSheet(0);
        $ownerName = $this->trimString($sheet->getCell('A2')->getValue());
        $ownerName = $ownerName !== '' ? $ownerName : null;

        $headerRowIndex = null;
        /** @var array<string, int> $colMap trimmed header => 1-based column index */
        $colMap = [];

        for ($r = 1; $r <= self::HEADER_SEARCH_MAX_ROW; $r++) {
            $highestCol = $sheet->getHighestDataColumn($r);
            $hi = Coordinate::columnIndexFromString($highestCol);
            for ($c = 1; $c <= $hi; $c++) {
                $raw = $sheet->getCell($this->cell($c, $r))->getValue();
                if (is_string($raw) && trim($raw) === 'Check Date') {
                    $headerRowIndex = $r;
                    for ($c2 = 1; $c2 <= $hi; $c2++) {
                        $hv = $sheet->getCell($this->cell($c2, $r))->getValue();
                        $label = $this->trimString($hv);
                        if ($label !== '') {
                            $colMap[$label] = $c2;
                        }
                    }
                    break 2;
                }
            }
        }

        if ($headerRowIndex === null || ! isset($colMap['Check Date'])) {
            throw new \InvalidArgumentException('Spreadsheet must have a Check Date column in the first 20 rows of the first sheet.');
        }

        foreach (self::SUMMARY_REQUIRED_HEADERS as $h) {
            if (! isset($colMap[$h])) {
                throw new \InvalidArgumentException('Missing required column: '.$h);
            }
        }

        $dateCol = $colMap['Check Date'];
        /** @var array<string, array<string, string>> $grouped ISO date => accumulators */
        $grouped = [];

        $maxRow = (int) $sheet->getHighestDataRow();
        for ($r = $headerRowIndex + 1; $r <= $maxRow; $r++) {
            $cellA = $sheet->getCell($this->cell(1, $r));
            $firstCol = $this->trimString($cellA->getCalculatedValue());
            if ($firstCol !== '' && str_starts_with($firstCol, $stopName)) {
                break;
            }

            $dateCell = $sheet->getCell($this->cell($dateCol, $r));
            $iso = $this->parseDateCell($dateCell);
            if ($iso === null) {
                continue;
            }

            if (! isset($grouped[$iso])) {
                $grouped[$iso] = $this->emptyMoneyRow();
            }

            foreach (self::SUMMARY_NUMERIC_HEADERS as $header) {
                if (! isset($colMap[$header])) {
                    continue; // optional column absent in this file — stays $0
                }
                $col = $colMap[$header];
                $val = $this->decimalFromCell($sheet->getCell($this->cell($col, $r)));
                $key = $this->summaryHeaderToKey($header);
                $grouped[$iso][$key] = $this->bcAdd($grouped[$iso][$key], $val);
            }
        }

        $records = [];
        foreach ($grouped as $iso => $money) {
            $records[] = array_merge(['check_date' => $iso], $money);
        }

        usort($records, fn (array $a, array $b): int => strcmp($a['check_date'], $b['check_date']));

        return ['ownerName' => $ownerName, 'records' => $records];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseConsultantSheets(Spreadsheet $wb, string $stopName): array
    {
        /** @var array<int, array<string, array{am_earnings: string, hours: string, spread_per_hour: string, commission_pct: string}>> $byYear */
        $byYear = [];

        foreach ($wb->getWorksheetIterator() as $ws) {
            $title = $ws->getTitle();
            if (! $this->isPeriodSheet($title)) {
                continue;
            }
            $year = $this->getSheetYear($ws);
            if ($year === null) {
                continue;
            }
            $parsed = $this->parsePayCalc($ws, $stopName);
            foreach ($parsed as $row) {
                $name = $row['name'];
                if (! isset($byYear[$year][$name])) {
                    $byYear[$year][$name] = [
                        'am_earnings'     => '0.0000',
                        'hours'           => '0.0000',
                        'spread_per_hour' => '0.0000',
                        'commission_pct'  => '0.00000000',
                    ];
                }
                $byYear[$year][$name]['am_earnings'] = $this->bcAdd($byYear[$year][$name]['am_earnings'], $row['am_earnings']);
                $byYear[$year][$name]['hours'] = $this->bcAdd($byYear[$year][$name]['hours'], $row['hours']);
                // spread_per_hour and commission_pct are stable per consultant — keep last non-zero value
                if (bccomp($row['spread_per_hour'], '0', 4) > 0) {
                    $byYear[$year][$name]['spread_per_hour'] = $row['spread_per_hour'];
                }
                if (bccomp($row['commission_pct'], '0', 8) > 0) {
                    $byYear[$year][$name]['commission_pct'] = $row['commission_pct'];
                }
            }
        }

        $out = [];
        foreach ($byYear as $year => $names) {
            foreach ($names as $name => $entry) {
                $out[] = [
                    'year'            => $year,
                    'name'            => $name,
                    'am_earnings'     => $entry['am_earnings'],
                    'hours'           => $entry['hours'],
                    'spread_per_hour' => $entry['spread_per_hour'],
                    'commission_pct'  => $entry['commission_pct'],
                ];
            }
        }

        return $out;
    }

    private function isPeriodSheet(string $name): bool
    {
        if (in_array($name, self::SKIP_SHEETS, true)) {
            return false;
        }

        return str_contains($name, '_') && (str_contains($name, '.') || str_contains($name, '-'));
    }

    private function getSheetYear(Worksheet $ws): ?int
    {
        $highestCol = $ws->getHighestDataColumn(3);
        $hi = Coordinate::columnIndexFromString($highestCol);
        for ($c = 1; $c <= $hi; $c++) {
            $cell = $ws->getCell($this->cell($c, 3));
            $val = $cell->getValue();
            if ($val instanceof \DateTimeInterface) {
                return (int) $val->format('Y');
            }
            if (is_numeric($val) && ExcelDate::isDateTime($cell)) {
                try {
                    return (int) ExcelDate::excelToDateTimeObject((float) $val)->format('Y');
                } catch (\Throwable) {
                    // continue scanning row 3
                }
            }
            if (is_string($val) && trim($val) !== '') {
                $t = trim($val);
                $dt = \DateTime::createFromFormat('Y-m-d', $t);
                if ($dt instanceof \DateTime) {
                    return (int) $dt->format('Y');
                }
                $dt2 = \DateTime::createFromFormat('m/d/Y', $t);
                if ($dt2 instanceof \DateTime) {
                    return (int) $dt2->format('Y');
                }
            }
        }

        return null;
    }

    /**
     * @return list<array{name: string, am_earnings: string, hours: string, spread_per_hour: string, commission_pct: string}>
     */
    private function parsePayCalc(Worksheet $ws, string $stopName): array
    {
        $inPayCalc = false;
        /** @var list<array{name: string, spread_total: string, hours: string, spread_per_hour: string}> $buffer */
        $buffer = [];
        /** @var list<array{name: string, am_earnings: string, hours: string, spread_per_hour: string, commission_pct: string}> $results */
        $results = [];

        $maxRow = (int) $ws->getHighestDataRow();
        for ($r = 1; $r <= $maxRow; $r++) {
            $colE = $ws->getCell($this->cell(5, $r))->getValue();
            if (! $inPayCalc) {
                if (is_string($colE) && str_contains(strtoupper($colE), 'OT')) {
                    $inPayCalc = true;
                }

                continue;
            }

            $colA = $ws->getCell($this->cell(1, $r))->getCalculatedValue();
            if (! is_string($colA) || trim($colA) === '') {
                continue;
            }

            $colAStripped = trim($colA);

            if (str_starts_with($colAStripped, 'Total') || str_starts_with($colAStripped, $stopName)) {
                break;
            }

            if (str_starts_with($colAStripped, 'Commission') && (
                str_contains($colAStripped, 'Subtotal') || str_contains($colAStripped, 'Subttal')
            )) {
                $parts = preg_split('/\s+/', $colAStripped) ?: [];
                $tierStr = $parts[1] ?? '0%';
                // commission% applied to (hours × spread) gives the AM's actual earnings
                $commissionPct = $this->tierToPct($tierStr);
                foreach ($buffer as $entry) {
                    $results[] = [
                        'name'             => $entry['name'],
                        'am_earnings'      => bcmul($entry['spread_total'], $commissionPct, 4),
                        'hours'            => $entry['hours'],
                        'spread_per_hour'  => $entry['spread_per_hour'],
                        'commission_pct'   => $commissionPct,
                    ];
                }
                $buffer = [];

                continue;
            }

            if (str_contains(strtolower($colAStripped), 'of total')) {
                continue;
            }

            $colB = $ws->getCell($this->cell(2, $r))->getCalculatedValue();
            $colC = $ws->getCell($this->cell(3, $r))->getCalculatedValue();
            $colD = $ws->getCell($this->cell(4, $r))->getCalculatedValue();

            $hours          = is_numeric($colB) ? $this->formatMoney((string) $colB) : '0.0000';
            $spreadPerHour  = is_numeric($colC) ? $this->formatMoney((string) $colC) : '0.0000';
            // col D = hours × spread (total spread for this consultant this period)
            $spreadTotal = is_numeric($colD) ? (float) $colD : 0.0;
            if ($spreadTotal > 0) {
                $buffer[] = [
                    'name'            => $colAStripped,
                    'spread_total'    => $this->formatMoney((string) $spreadTotal),
                    'hours'           => $hours,
                    'spread_per_hour' => $spreadPerHour,
                ];
            }
        }

        return $results;
    }

    /**
     * Convert a tier label like "50%", "35%", "20%", "10%" to a bcmath-safe decimal fraction.
     */
    private function tierToPct(string $tier): string
    {
        $cleaned = rtrim(trim($tier), '%');
        if (is_numeric($cleaned) && (float) $cleaned > 0) {
            return bcdiv($cleaned, '100', 8);
        }

        return '0.00000000';
    }

    private function parseDateCell(Cell $cell): ?string
    {
        $v = $cell->getValue();

        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }

        if (is_numeric($v) && ExcelDate::isDateTime($cell)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $v)->format('Y-m-d');
            } catch (\Throwable) {
                // fall through
            }
        }

        if (is_string($v) && trim($v) !== '') {
            $dt = \DateTime::createFromFormat('m/d/Y', trim($v));
            if ($dt instanceof \DateTime) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }

    private function decimalFromCell(Cell $cell): string
    {
        $v = $cell->getCalculatedValue();
        if ($v === null || $v === '') {
            return '0.0000';
        }
        if (is_numeric($v)) {
            return $this->formatMoney((string) $v);
        }

        return '0.0000';
    }

    private function trimString(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_string($v)) {
            return trim($v);
        }
        if (is_numeric($v)) {
            return trim((string) $v);
        }

        return trim((string) $v);
    }

    /**
     * @return array<string, string>
     */
    private function emptyMoneyRow(): array
    {
        return [
            'gross_pay' => '0.0000',
            'federal_tax' => '0.0000',
            'social_security' => '0.0000',
            'medicare' => '0.0000',
            'state_tax' => '0.0000',
            'other_deductions' => '0.0000',
            'retirement_401k' => '0.0000',
            'net_pay' => '0.0000',
            'health_insurance' => '0.0000',
            'commission_subtotal' => '0.0000',
            'salary_subtotal' => '0.0000',
        ];
    }

    private function summaryHeaderToKey(string $header): string
    {
        return match ($header) {
            'Sub-Total Gross Income' => 'gross_pay',
            'Federal Tax' => 'federal_tax',
            'Social Security' => 'social_security',
            'Medicare' => 'medicare',
            'State Tax' => 'state_tax',
            'Disability' => 'other_deductions',
            '401k Contribution' => 'retirement_401k',
            'Check Amount' => 'net_pay',
            default => throw new \InvalidArgumentException('Unknown header: '.$header),
        };
    }

    private function bcAdd(string $a, string $b): string
    {
        return bcadd($a, $b, 4);
    }

    private function formatMoney(string $n): string
    {
        return bcadd($n, '0', 4);
    }

    private function cell(int $columnIndex1Based, int $row): string
    {
        return Coordinate::stringFromColumnIndex($columnIndex1Based).$row;
    }
}
