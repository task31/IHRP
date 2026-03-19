<?php

namespace App\Http\Controllers;

use App\Services\LedgerQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LedgerController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $this->authorize('account_manager');

        $filters = $request->only(['consultantId', 'clientId', 'startDate', 'endDate', 'invoiceStatus']);

        $timesheets = LedgerQueryService::listTimesheets($filters);
        $summary = LedgerQueryService::summary($filters);

        if ($request->expectsJson()) {
            return response()->json([
                'timesheets' => $timesheets,
                'summary' => $summary,
                'consultantsInLedger' => LedgerQueryService::distinctConsultantsInTimesheets(),
                'clientsInLedger' => LedgerQueryService::distinctClientsInTimesheets(),
            ]);
        }

        $footer = $this->ledgerFooterTotals($timesheets);

        return view('ledger.index', [
            'timesheets' => $timesheets,
            'summary' => $summary,
            'consultantsInLedger' => LedgerQueryService::distinctConsultantsInTimesheets(),
            'clientsInLedger' => LedgerQueryService::distinctClientsInTimesheets(),
            'filters' => $filters,
            'footer' => $footer,
        ]);
    }

    /**
     * @param  list<object>  $rows
     * @return array{reg: float, ot: float, cost: float, billable: float, margin: float, margin_pct: float}
     */
    private function ledgerFooterTotals(array $rows): array
    {
        $reg = 0.0;
        $ot = 0.0;
        $cost = 0.0;
        $billable = 0.0;
        $margin = 0.0;

        foreach ($rows as $r) {
            $reg += (float) ($r->total_regular_hours ?? 0);
            $ot += (float) ($r->total_ot_hours ?? 0);
            $cost += (float) ($r->total_consultant_cost ?? 0);
            $billable += (float) ($r->total_client_billable ?? 0);
            $margin += (float) ($r->gross_margin_dollars ?? 0);
        }

        $marginPct = $billable > 0 ? ($margin / $billable) * 100 : 0.0;

        return [
            'reg' => $reg,
            'ot' => $ot,
            'cost' => $cost,
            'billable' => $billable,
            'margin' => $margin,
            'margin_pct' => $marginPct,
        ];
    }
}
