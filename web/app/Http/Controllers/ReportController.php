<?php

namespace App\Http\Controllers;

use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $this->authorize('account_manager');

        if ($request->expectsJson()) {
            return response()->json([
                'endpoints' => [
                    'monthly' => 'GET /reports/monthly',
                    'year_end' => 'GET /reports/year-end',
                    'quickbooks' => 'GET /reports/quickbooks',
                    'monthly_csv' => 'GET /reports/monthly-csv',
                ],
            ]);
        }

        return view('reports.index');
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolveYearMonth(Request $request): array
    {
        $m = $request->query('month');
        if (is_string($m) && preg_match('/^(\d{4})-(\d{2})$/', $m, $matches)) {
            return [(int) $matches[1], (int) $matches[2]];
        }

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        return [$year, max(1, min(12, $month))];
    }

    private function wantsPdfResponse(Request $request): bool
    {
        $accept = (string) $request->header('Accept', '');

        return str_contains($accept, 'application/pdf') || $request->boolean('preview');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchMonthlyRows(int $year, int $month): array
    {
        $rows = DB::table('timesheets as t')
            ->join('consultants as c', 'c.id', '=', 't.consultant_id')
            ->join('clients as cl', 'cl.id', '=', 't.client_id')
            ->whereYear('t.pay_period_start', $year)
            ->whereMonth('t.pay_period_start', $month)
            ->orderBy('t.pay_period_start')
            ->select([
                't.pay_period_start',
                't.pay_period_end',
                DB::raw('c.full_name as consultant'),
                DB::raw('cl.name as client'),
                't.total_client_billable',
                't.total_consultant_cost',
            ])
            ->get();

        return $rows->map(function ($r) {
            $a = (array) $r;
            foreach (['pay_period_start', 'pay_period_end'] as $k) {
                if (isset($a[$k]) && $a[$k] instanceof \DateTimeInterface) {
                    $a[$k] = $a[$k]->format('Y-m-d');
                }
            }

            return $a;
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchYearEndRows(int $year): array
    {
        $start = sprintf('%d-01-01', $year);
        $end = sprintf('%d-12-31', $year);

        $rows = DB::select('
            SELECT c.full_name AS consultant, cl.name AS client,
                   SUM(t.total_client_billable) AS billed,
                   SUM(t.total_consultant_cost) AS cost
            FROM timesheets t
            JOIN consultants c ON c.id = t.consultant_id
            JOIN clients cl ON cl.id = t.client_id
            WHERE t.pay_period_start >= ? AND t.pay_period_end <= ?
            GROUP BY c.id, c.full_name, cl.id, cl.name
            ORDER BY c.full_name, cl.name
        ', [$start, $end]);

        return array_map(fn ($r) => (array) $r, $rows);
    }

    public function monthly(Request $request): JsonResponse|Response
    {
        $this->authorize('account_manager');
        $request->validate([
            'year' => ['sometimes', 'integer'],
            'month' => ['sometimes'],
            'preview' => ['sometimes', 'boolean'],
        ]);

        [$year, $month] = $this->resolveYearMonth($request);
        $rows = $this->fetchMonthlyRows($year, $month);
        $monthTag = sprintf('%04d-%02d', $year, $month);

        if ($this->wantsPdfResponse($request)) {
            $pdf = (new PdfService)->generateMonthlyReport([
                'title' => "Monthly report {$monthTag}",
                'rows' => $rows,
            ]);

            return response($pdf, 200)->header('Content-Type', 'application/pdf');
        }

        return response()->json(['month' => $monthTag, 'rows' => $rows]);
    }

    public function yearEnd(Request $request): JsonResponse|Response
    {
        $this->authorize('account_manager');
        $year = (int) $request->query('year', now()->year);
        $rows = $this->fetchYearEndRows($year);

        if ($this->wantsPdfResponse($request)) {
            $pdf = (new PdfService)->generateYearEndReport([
                'year' => $year,
                'rows' => $rows,
            ]);

            return response($pdf, 200)->header('Content-Type', 'application/pdf');
        }

        return response()->json(['year' => $year, 'rows' => $rows]);
    }

    public function downloadMonthlyCsv(Request $request): StreamedResponse
    {
        $this->authorize('account_manager');
        $data = $request->validate([
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $rows = $this->fetchMonthlyRows((int) $data['year'], (int) $data['month']);
        $filename = sprintf('monthly_%d_%02d.csv', (int) $data['year'], (int) $data['month']);

        $headers = $rows !== [] ? array_keys($rows[0]) : [
            'pay_period_start', 'pay_period_end', 'consultant', 'client', 'total_client_billable', 'total_consultant_cost',
        ];

        return response()->streamDownload(function () use ($rows, $headers) {
            $f = fopen('php://output', 'w');
            fputcsv($f, $headers);
            foreach ($rows as $r) {
                $line = [];
                foreach ($headers as $h) {
                    $line[] = $r[$h] ?? '';
                }
                fputcsv($f, $line);
            }
            fclose($f);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function quickbooks(Request $request): StreamedResponse
    {
        $this->authorize('account_manager');
        $year = (int) $request->query('year', now()->year);
        $start = sprintf('%d-01-01', $year);
        $end = sprintf('%d-12-31', $year);

        $rows = DB::select('
            SELECT i.invoice_number, i.invoice_date, i.total_amount_due, i.status,
                   cl.name AS client_name
            FROM invoices i
            JOIN clients cl ON cl.id = i.client_id
            WHERE i.invoice_date >= ? AND i.invoice_date <= ?
            ORDER BY i.invoice_date
        ', [$start, $end]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['invoice_number', 'invoice_date', 'client', 'amount', 'status']);
            foreach ($rows as $r) {
                $a = (array) $r;
                fputcsv($out, [
                    $a['invoice_number'] ?? '',
                    $a['invoice_date'] ?? '',
                    $a['client_name'] ?? '',
                    $a['total_amount_due'] ?? '',
                    $a['status'] ?? '',
                ]);
            }
            fclose($out);
        }, 'quickbooks_'.$year.'.csv', ['Content-Type' => 'text/csv']);
    }

    public function savePdf(Request $request): Response
    {
        $this->authorize('admin');
        $data = $request->validate([
            'type' => ['required', 'in:monthly,year_end'],
            'payload' => ['required', 'array'],
        ]);

        $pdf = $data['type'] === 'monthly'
            ? (new PdfService)->generateMonthlyReport($data['payload'])
            : (new PdfService)->generateYearEndReport($data['payload']);

        return response($pdf, 200)->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="report.pdf"');
    }
}
