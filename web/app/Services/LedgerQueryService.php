<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class LedgerQueryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{where: string, bindings: list<mixed>}
     */
    public static function buildWhereClause(array $filters): array
    {
        $conditions = ['1=1'];
        $params = [];

        if (isset($filters['consultantId']) && $filters['consultantId'] !== null && $filters['consultantId'] !== '') {
            $conditions[] = 't.consultant_id = ?';
            $params[] = $filters['consultantId'];
        }
        if (isset($filters['clientId']) && $filters['clientId'] !== null && $filters['clientId'] !== '') {
            $conditions[] = 't.client_id = ?';
            $params[] = $filters['clientId'];
        }
        if (! empty($filters['startDate'])) {
            $conditions[] = 't.pay_period_start >= ?';
            $params[] = $filters['startDate'];
        }
        if (! empty($filters['endDate'])) {
            $conditions[] = 't.pay_period_end <= ?';
            $params[] = $filters['endDate'];
        }
        if (isset($filters['invoiceStatus']) && $filters['invoiceStatus'] !== null && $filters['invoiceStatus'] !== '') {
            $conditions[] = 't.invoice_status = ?';
            $params[] = $filters['invoiceStatus'];
        }

        return [
            'where' => implode(' AND ', $conditions),
            'bindings' => $params,
        ];
    }

    private const FROM_JOINS = <<<'SQL'
FROM timesheets t
JOIN consultants c ON c.id = t.consultant_id
JOIN clients cl ON cl.id = t.client_id
SQL;

    private const MARGIN_EXPR = 'SUM(t.gross_margin_dollars) / NULLIF(SUM(t.total_client_billable), 0) * 100 AS blended_margin_percent';

    /**
     * @param  array<string, mixed>  $filters
     * @return list<object>
     */
    public static function listTimesheets(array $filters): array
    {
        $q = self::buildWhereClause($filters);
        $sql = '
            SELECT t.*, c.full_name AS consultant_name, cl.name AS client_name
            '.self::FROM_JOINS."
            WHERE {$q['where']}
            ORDER BY t.pay_period_start DESC, c.full_name ASC
        ";

        return DB::select($sql, $q['bindings']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{byPeriod: list<object>, byConsultant: list<object>, byClient: list<object>, totals: object|null}
     */
    public static function summary(array $filters): array
    {
        $q = self::buildWhereClause($filters);
        $w = $q['where'];
        $b = $q['bindings'];
        $margin = self::MARGIN_EXPR;

        $byPeriod = DB::select("
            SELECT t.pay_period_start, t.pay_period_end,
                   COUNT(*) AS row_count,
                   SUM(t.total_consultant_cost) AS total_consultant_cost,
                   SUM(t.total_client_billable) AS total_client_billable,
                   SUM(t.gross_margin_dollars) AS gross_margin_dollars,
                   {$margin}
            ".self::FROM_JOINS."
            WHERE {$w}
            GROUP BY t.pay_period_start, t.pay_period_end
            ORDER BY t.pay_period_start DESC
        ", $b);

        $byConsultant = DB::select("
            SELECT t.consultant_id, c.full_name AS consultant_name,
                   COUNT(DISTINCT t.client_id) AS client_count,
                   COUNT(*) AS row_count,
                   SUM(t.total_consultant_cost) AS total_consultant_cost,
                   SUM(t.total_client_billable) AS total_client_billable,
                   SUM(t.gross_margin_dollars) AS gross_margin_dollars,
                   {$margin}
            ".self::FROM_JOINS."
            WHERE {$w}
            GROUP BY t.consultant_id, c.full_name
            ORDER BY c.full_name ASC
        ", $b);

        $byClient = DB::select("
            SELECT t.client_id, cl.name AS client_name,
                   COUNT(DISTINCT t.consultant_id) AS consultant_count,
                   COUNT(*) AS row_count,
                   SUM(t.total_consultant_cost) AS total_consultant_cost,
                   SUM(t.total_client_billable) AS total_client_billable,
                   SUM(t.gross_margin_dollars) AS gross_margin_dollars,
                   {$margin}
            ".self::FROM_JOINS."
            WHERE {$w}
            GROUP BY t.client_id, cl.name
            ORDER BY cl.name ASC
        ", $b);

        $totals = DB::selectOne("
            SELECT COUNT(*) AS row_count,
                   SUM(t.total_consultant_cost) AS total_consultant_cost,
                   SUM(t.total_client_billable) AS total_client_billable,
                   SUM(t.gross_margin_dollars) AS gross_margin_dollars,
                   {$margin}
            ".self::FROM_JOINS."
            WHERE {$w}
        ", $b);

        return [
            'byPeriod' => $byPeriod,
            'byConsultant' => $byConsultant,
            'byClient' => $byClient,
            'totals' => $totals,
        ];
    }

    /**
     * @return list<object>
     */
    public static function distinctConsultantsInTimesheets(): array
    {
        return DB::select('
            SELECT DISTINCT t.consultant_id AS id, c.full_name
            FROM timesheets t
            JOIN consultants c ON c.id = t.consultant_id
            ORDER BY c.full_name ASC
        ');
    }

    /**
     * @return list<object>
     */
    public static function distinctClientsInTimesheets(): array
    {
        return DB::select('
            SELECT DISTINCT t.client_id AS id, cl.name
            FROM timesheets t
            JOIN clients cl ON cl.id = t.client_id
            ORDER BY cl.name ASC
        ');
    }
}
