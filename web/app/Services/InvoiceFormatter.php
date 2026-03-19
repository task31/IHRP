<?php

namespace App\Services;

/**
 * Pure helpers ported from invoices.js
 */
final class InvoiceFormatter
{
    public static function formatInvoiceNumber(string $prefix, int $number): string
    {
        $padded = str_pad((string) $number, 6, '0', STR_PAD_LEFT);

        return $prefix !== '' ? "{$prefix}-{$padded}" : $padded;
    }

    public static function calcDueDate(string $invoiceDate, ?string $paymentTerms): string
    {
        $term = strtolower(trim((string) $paymentTerms));
        if ($term === 'due on receipt') {
            return $invoiceDate;
        }
        preg_match('/(\d+)/', $term, $m);
        $days = isset($m[1]) ? (int) $m[1] : 30;

        $dt = new \DateTimeImmutable($invoiceDate);

        return $dt->modify("+{$days} days")->format('Y-m-d');
    }

    public static function fmtDate(?string $dateStr): string
    {
        if ($dateStr === null || $dateStr === '') {
            return '';
        }
        $parts = explode('-', (string) $dateStr);
        if (count($parts) !== 3) {
            return $dateStr;
        }

        return str_pad($parts[1], 2, '0', STR_PAD_LEFT).'/'.str_pad($parts[2], 2, '0', STR_PAD_LEFT).'/'.$parts[0];
    }

    /**
     * @param  object|array<string, mixed>  $ts
     * @return list<array<string, mixed>>
     */
    public static function buildLineItems(object|array $ts): array
    {
        $t = is_array($ts) ? (object) $ts : $ts;
        $br = (float) ($t->bill_rate_snapshot ?? 0);
        $start = self::dateStr($t->pay_period_start ?? null);
        $end = self::dateStr($t->pay_period_end ?? null);
        $range = self::fmtDate($start).' – '.self::fmtDate($end);
        $items = [];
        $sortOrder = 1;

        $regHours = (float) ($t->week1_regular_hours ?? 0) + (float) ($t->week2_regular_hours ?? 0);
        $regAmount = (float) ($t->week1_regular_billable ?? 0) + (float) ($t->week2_regular_billable ?? 0);
        if ($regHours > 0) {
            $items[] = [
                'week_number' => 1,
                'sort_order' => $sortOrder++,
                'description' => "Staffing Services  {$range}",
                'hours' => $regHours,
                'rate' => $br,
                'multiplier' => 1.0,
                'amount' => $regAmount,
            ];
        }

        $otHours = (float) ($t->week1_ot_hours ?? 0) + (float) ($t->week2_ot_hours ?? 0);
        $otAmount = (float) ($t->week1_ot_billable ?? 0) + (float) ($t->week2_ot_billable ?? 0);
        if ($otHours > 0) {
            $items[] = [
                'week_number' => 1,
                'sort_order' => $sortOrder++,
                'description' => "Overtime Hours  {$range}",
                'hours' => $otHours,
                'rate' => $br * 1.5,
                'multiplier' => 1.5,
                'amount' => $otAmount,
            ];
        }

        $dtHours = (float) ($t->week1_dt_hours ?? 0) + (float) ($t->week2_dt_hours ?? 0);
        $dtAmount = (float) ($t->week1_dt_billable ?? 0) + (float) ($t->week2_dt_billable ?? 0);
        if ($dtHours > 0) {
            $items[] = [
                'week_number' => 1,
                'sort_order' => $sortOrder++,
                'description' => "Double-Time Hours  {$range}",
                'hours' => $dtHours,
                'rate' => $br * 2.0,
                'multiplier' => 2.0,
                'amount' => $dtAmount,
            ];
        }

        return $items;
    }

    private static function dateStr(mixed $d): string
    {
        if ($d === null) {
            return '';
        }
        if ($d instanceof \DateTimeInterface) {
            return $d->format('Y-m-d');
        }

        return substr((string) $d, 0, 10);
    }
}
