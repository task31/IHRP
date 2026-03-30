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
        $titleFound = false;
        $dateHeaderFound = false;
        foreach (array_slice($rows, 0, 20) as $r) {
            if (! is_array($r)) {
                continue;
            }

            $rowHasDate = false;
            $rowHasHours = false;

            foreach ($r as $cell) {
                $s = strtoupper(trim((string) $cell));
                if ($s === '') {
                    continue;
                }

                $norm = preg_replace('/[^A-Z]/', '', $s) ?? '';
                if (str_contains($norm, 'BIWEEKLY') && str_contains($norm, 'TIMESHEET')) {
                    $titleFound = true;
                }

                if ($s === 'DATE') {
                    $rowHasDate = true;
                }

                if (str_contains($s, 'HOURS')) {
                    $rowHasHours = true;
                }
            }

            if ($rowHasDate && $rowHasHours) {
                $dateHeaderFound = true;
            }
        }

        if ($titleFound && $dateHeaderFound) {
            return 'biweekly-template';
        }

        $r0 = $rows[0] ?? [];
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
     * Resolve a cell value to an ISO date string.
     * Accepts either an Excel date serial (float/int) or a formatted date string.
     */
    public static function resolveDate(mixed $value): string
    {
        if (is_numeric($value) && (float) $value > 1) {
            return self::serialToISO((float) $value);
        }
        if (is_string($value)) {
            $ts = strtotime($value);
            if ($ts !== false) {
                return gmdate('Y-m-d', $ts);
            }

            foreach (['m-d-y', 'm/d/y', 'm-d-Y', 'm/d/Y'] as $fmt) {
                $dt = \DateTime::createFromFormat($fmt, $value);
                if ($dt instanceof \DateTime) {
                    return $dt->format('Y-m-d');
                }
            }
        }
        throw new \InvalidArgumentException("Cannot parse date value: {$value}");
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @return array{consultantName: string, payPeriodStart: string, payPeriodEnd: string, week1Hours: list<float>, week2Hours: list<float>, totalHours: float}
     */
    public static function parseTemplate(array $rows): array
    {
        $toHours = fn ($val): float => (is_float($val) || is_int($val)) ? (float) $val : 0.0;

        // Prefer current official template layout (generator: GenerateTimesheetTemplate).
        try {
            $startVal = $rows[10][0] ?? null; // A11
            $endVal   = $rows[23][0] ?? null; // A24
            if ($startVal === null || $startVal === '' || $endVal === null || $endVal === '') {
                throw new \InvalidArgumentException('Missing date values in template (A11/A24)');
            }

            $consultantName = trim((string) ($rows[2][1] ?? '')); // B3
            $payPeriodStart = self::resolveDate($startVal);
            $payPeriodEnd   = self::resolveDate($endVal);

            $week1Hours = [];
            foreach ([10, 11, 12, 13, 14, 15, 16] as $i) {
                $week1Hours[] = $toHours($rows[$i][6] ?? 0);
            }
            $week2Hours = [];
            foreach ([17, 18, 19, 20, 21, 22, 23] as $i) {
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
        } catch (\Throwable) {
            // Fall back to legacy official template layout (older template positions).
        }

        $startVal = $rows[5][5] ?? null; // F6
        $endVal   = $rows[6][5] ?? null; // F7
        if ($startVal === null || $startVal === '' || $endVal === null || $endVal === '') {
            throw new \InvalidArgumentException('Missing date values in template (F6/F7)');
        }

        $consultantName = trim((string) ($rows[1][1] ?? '')); // B2
        $payPeriodStart = self::resolveDate($startVal);
        $payPeriodEnd   = self::resolveDate($endVal);

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
        if ($format !== 'biweekly-template') {
            try {
                $parsedRows = [self::parseTemplate($allRows)];
                $format = 'biweekly-template';
            } catch (\Throwable) {
                // ignore
            }
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
