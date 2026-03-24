<?php

namespace App\Support;

/**
 * Display-only USD formatting for reports (PDF / tables). Not for payroll or invoice math.
 */
final class ReportMoney
{
    public static function usd(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '$0.00';
        }
        if (is_string($amount) && ! is_numeric($amount)) {
            return '$0.00';
        }

        return '$'.number_format((float) $amount, 2, '.', ',');
    }

    /**
     * Column keys that represent money in report row arrays.
     *
     * @return list<string>
     */
    public static function moneyColumnKeys(): array
    {
        return [
            'total_client_billable',
            'total_consultant_cost',
            'billed',
            'cost',
        ];
    }
}
