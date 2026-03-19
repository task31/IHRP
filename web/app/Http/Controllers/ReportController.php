<?php

namespace App\Http\Controllers;

use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('account_manager');

        return response()->json([
            'endpoints' => [
                'monthly' => 'GET /reports/monthly',
                'year_end' => 'GET /reports/year-end',
                'quickbooks' => 'GET /reports/quickbooks',
            ],
        ]);
    }

    public function monthly(Request $request): JsonResponse
    {
        $this->authorize('account_manager');
        $request->validate(['month' => ['nullable', 'string', 'size:7']]);
        $month = $request->input('month', now()->format('Y-m'));

        $rows = DB::select('
            SELECT t.pay_period_start, t.pay_period_end, c.full_name AS consultant, cl.name AS client,
                   t.total_client_billable, t.total_consultant_cost
            FROM timesheets t
            JOIN consultants c ON c.id = t.consultant_id
            JOIN clients cl ON cl.id = t.client_id
            WHERE DATE_FORMAT(t.pay_period_start, "%Y-%m") = ?
            ORDER BY t.pay_period_start
        ', [$month]);

        return response()->json(['month' => $month, 'rows' => $rows]);
    }

    public function yearEnd(Request $request): JsonResponse
    {
        $this->authorize('account_manager');
        $year = (int) $request->query('year', now()->year);
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

        return response()->json(['year' => $year, 'rows' => $rows]);
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

    public function saveCsv(Request $request): StreamedResponse
    {
        $this->authorize('account_manager');
        $data = $request->validate([
            'filename' => ['nullable', 'string', 'max:120'],
            'rows' => ['required', 'array'],
        ]);

        $name = ($data['filename'] ?? 'export').'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            $rows = $data['rows'];
            if ($rows !== []) {
                fputcsv($out, array_keys((array) $rows[0]));
                foreach ($rows as $row) {
                    fputcsv($out, array_values((array) $row));
                }
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }
}
