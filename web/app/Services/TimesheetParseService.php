<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Port of timesheets-parse.js + row extraction from timesheets.js upload handler.
 */
final class TimesheetParseService
{
    public static function serialToISO(float $n): string
    {
        $ts = (int) round(($n - 25569) * 86400 * 1000 / 1000);

        return gmdate('Y-m-d', $ts);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    public static function detectFormat(array $rows): string
    {
        $r0 = $rows[0] ?? [];
        $r8 = $rows[8] ?? [];
        $cell8_6 = isset($r8[6]) ? strtoupper((string) $r8[6]) : '';

        if (($r0[0] ?? null) === 'BI-WEEKLY TIMESHEET'
            && ($r8[0] ?? null) === 'DATE'
            && str_contains($cell8_6, 'HOURS')
        ) {
            return 'biweekly-template';
        }

        if (is_array($r0) && count($r0) > 0) {
            foreach ($r0 as $cell) {
                if (is_string($cell) && trim($cell) !== '') {
                    return 'flat-csv';
                }
            }
        }

        return 'unknown';
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @return array{consultantName: string, payPeriodStart: string, payPeriodEnd: string, week1Hours: list<float>, week2Hours: list<float>, totalHours: float}
     */
    public static function parseTemplate(array $rows): array
    {
        $startSerial = $rows[5][5] ?? null;
        $endSerial = $rows[6][5] ?? null;
        if (! is_numeric($startSerial) || ! is_numeric($endSerial)) {
            throw new \InvalidArgumentException('Invalid date serials in template');
        }

        $toHours = fn ($val): float => (is_float($val) || is_int($val)) ? (float) $val : 0.0;

        $consultantName = trim((string) ($rows[1][1] ?? ''));
        $payPeriodStart = self::serialToISO((float) $startSerial);
        $payPeriodEnd = self::serialToISO((float) $endSerial);

        $week1Hours = [];
        foreach ([9, 10, 11, 12, 13, 14, 15] as $i) {
            $week1Hours[] = $toHours($rows[$i][6] ?? 0);
        }
        $week2Hours = [];
        foreach ([16, 17, 18, 19, 20, 21, 22] as $i) {
            $week2Hours[] = $toHours($rows[$i][6] ?? 0);
        }

        $totalHours = array_sum($week1Hours) + array_sum($week2Hours);

        return [
            'consultantName' => $consultantName,
            'payPeriodStart' => $payPeriodStart,
            'payPeriodEnd' => $payPeriodEnd,
            'week1Hours' => $week1Hours,
            'week2Hours' => $week2Hours,
            'totalHours' => $totalHours,
        ];
    }

    /**
     * @return array{
     *     format: string,
     *     columns: list<string>,
     *     rows: list<array<string, mixed>>,
     *     totalRows: int,
     *     parsedRows: list<array<string, mixed>>|null,
     *     savedMapping: mixed
     * }
     */
    public function parse(UploadedFile $file): array
    {
        ini_set('memory_limit', '256M');

        $path = $file->getRealPath();
        if ($path === false) {
            throw new \RuntimeException('Invalid upload path');
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $allRows = $sheet->toArray(null, true, true, false);

        if ($allRows === [] || (count($allRows) === 1 && $allRows[0] === [])) {
            return [
                'format' => 'unknown',
                'columns' => [],
                'rows' => [],
                'totalRows' => 0,
                'parsedRows' => null,
                'savedMapping' => null,
            ];
        }

        $format = 'unknown';
        $parsedRows = null;
        try {
            $format = self::detectFormat($allRows);
            if ($format === 'biweekly-template') {
                $parsedRows = [self::parseTemplate($allRows)];
            }
        } catch (\Throwable) {
            $format = 'unknown';
        }

        $columns = $format === 'flat-csv' ? array_map('strval', $allRows[0]) : [];
        $dataRows = $format === 'flat-csv'
            ? array_values(array_filter(array_slice($allRows, 1), fn ($r) => is_array($r) && ! empty(array_filter($r, fn ($c) => $c !== '' && $c !== null))))
            : [];

        $rows = [];
        foreach ($dataRows as $r) {
            $row = [];
            foreach ($columns as $i => $col) {
                $row[$col] = $r[$i] ?? '';
            }
            $rows[] = $row;
        }

        $raw = AppService::getSetting('timesheet_import_column_mapping');
        $savedMapping = $raw ? json_decode((string) $raw, true) : null;

        return [
            'format' => $format,
            'columns' => $columns,
            'rows' => $rows,
            'totalRows' => count($rows),
            'parsedRows' => $parsedRows,
            'savedMapping' => $savedMapping,
        ];
    }
}
