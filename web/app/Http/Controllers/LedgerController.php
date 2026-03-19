<?php

namespace App\Http\Controllers;

use App\Services\LedgerQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('account_manager');

        $filters = $request->only(['consultantId', 'clientId', 'startDate', 'endDate', 'invoiceStatus']);

        return response()->json([
            'timesheets' => LedgerQueryService::listTimesheets($filters),
            'summary' => LedgerQueryService::summary($filters),
            'consultantsInLedger' => LedgerQueryService::distinctConsultantsInTimesheets(),
            'clientsInLedger' => LedgerQueryService::distinctClientsInTimesheets(),
        ]);
    }
}
