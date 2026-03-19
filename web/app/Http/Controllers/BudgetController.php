<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\AppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    /**
     * @return array{budget: float, spent: float, remaining: float, pct: float}
     */
    public static function computeBudgetStats(float $budget, float $spent): array
    {
        $b = $budget;
        $s = $spent;

        return [
            'budget' => $b,
            'spent' => $s,
            'remaining' => $b - $s,
            'pct' => $b > 0 ? ($s / $b * 100) : 0,
        ];
    }

    public function index(): JsonResponse
    {
        $this->authorize('account_manager');

        $rows = DB::select('
            SELECT c.id AS client_id, c.name AS client_name, c.total_budget,
                   c.budget_alert_warning_sent, c.budget_alert_critical_sent,
                   COALESCE(SUM(t.total_client_billable), 0) AS total_billed
            FROM clients c
            LEFT JOIN timesheets t ON t.client_id = c.id
            WHERE c.active = 1 AND c.total_budget > 0
            GROUP BY c.id, c.name, c.total_budget, c.budget_alert_warning_sent, c.budget_alert_critical_sent
            ORDER BY (COALESCE(SUM(t.total_client_billable), 0) / c.total_budget) DESC
        ');

        $out = array_map(function ($row) {
            $arr = (array) $row;
            $tb = (float) ($arr['total_budget'] ?? 0);
            $billed = (float) ($arr['total_billed'] ?? 0);
            $pct = $tb > 0 ? ($billed / $tb * 100) : 0.0;

            return [
                'client_id' => (int) ($arr['client_id'] ?? 0),
                'client_name' => (string) ($arr['client_name'] ?? ''),
                'budget' => $tb,
                'spent' => $billed,
                'pct' => $pct,
                'remaining' => $tb - $billed,
                'budget_alert_warning_sent' => (bool) ($arr['budget_alert_warning_sent'] ?? false),
                'budget_alert_critical_sent' => (bool) ($arr['budget_alert_critical_sent'] ?? false),
            ];
        }, $rows);

        return response()->json($out);
    }

    public function show(Request $request, string $year): JsonResponse
    {
        $this->authorize('account_manager');
        $y = (int) $year;
        $startDate = sprintf('%d-01-01', $y);
        $endDate = sprintf('%d-12-31', $y);

        $bbClientId = AppService::getSetting('bridgebio_client_id');
        $bbBudget = (float) AppService::getSetting("budget_bridgebio_{$y}", 0);
        $otherBudget = (float) AppService::getSetting("budget_other_{$y}", 0);

        $bbClientName = null;
        $bbSpent = 0.0;
        $otherSpent = 0.0;

        if ($bbClientId) {
            $bbClientName = Client::query()->where('id', $bbClientId)->value('name');
            $bbSpent = (float) DB::table('timesheets')
                ->where('client_id', $bbClientId)
                ->whereBetween('pay_period_start', [$startDate, $endDate])
                ->sum('total_consultant_cost');
            $otherSpent = (float) DB::table('timesheets')
                ->where('client_id', '!=', $bbClientId)
                ->whereBetween('pay_period_start', [$startDate, $endDate])
                ->sum('total_consultant_cost');
        } else {
            $otherSpent = (float) DB::table('timesheets')
                ->whereBetween('pay_period_start', [$startDate, $endDate])
                ->sum('total_consultant_cost');
        }

        return response()->json([
            'bridgebio' => array_merge(
                ['clientId' => $bbClientId, 'clientName' => $bbClientName],
                self::computeBudgetStats($bbBudget, $bbSpent)
            ),
            'other' => self::computeBudgetStats($otherBudget, $otherSpent),
        ]);
    }

    public function update(Request $request, string $year): JsonResponse
    {
        $this->authorize('admin');
        $y = (int) $year;
        $data = $request->validate([
            'bridgebioClientId' => ['required', 'string'],
            'bridgebioBudget' => ['required', 'numeric', 'min:0'],
            'otherBudget' => ['required', 'numeric', 'min:0'],
        ]);

        if (! Client::query()->where('id', $data['bridgebioClientId'])->exists()) {
            return response()->json(['error' => 'Invalid BridgeBio client ID'], 422);
        }

        AppService::setSetting('bridgebio_client_id', $data['bridgebioClientId']);
        AppService::setSetting("budget_bridgebio_{$y}", (string) $data['bridgebioBudget']);
        AppService::setSetting("budget_other_{$y}", (string) $data['otherBudget']);

        AppService::auditLog('settings', 0, 'BUDGET_SET', [], ['year' => $y]);

        return response()->json(['ok' => true]);
    }

    public function alerts(): JsonResponse
    {
        $this->authorize('admin');

        $warnThreshold = (float) AppService::getSetting('budget_alert_threshold_warning', 80);
        $critThreshold = (float) AppService::getSetting('budget_alert_threshold_critical', 100);

        $rows = DB::select('
            SELECT c.id AS client_id, c.name AS client_name, c.total_budget,
                   c.budget_alert_warning_sent, c.budget_alert_critical_sent,
                   COALESCE(SUM(t.total_client_billable), 0) AS total_billed
            FROM clients c
            LEFT JOIN timesheets t ON t.client_id = c.id
            WHERE c.active = 1 AND c.total_budget > 0
            GROUP BY c.id, c.name, c.total_budget, c.budget_alert_warning_sent, c.budget_alert_critical_sent
        ');

        $newAlerts = [];

        foreach ($rows as $row) {
            $r = (array) $row;
            $tb = (float) ($r['total_budget'] ?? 0);
            $billed = (float) ($r['total_billed'] ?? 0);
            $pct = $tb > 0 ? ($billed / $tb * 100) : 0;
            $cid = (int) $r['client_id'];

            if ($pct >= $critThreshold && ! ($r['budget_alert_critical_sent'] ?? false)) {
                Client::query()->where('id', $cid)->update(['budget_alert_critical_sent' => true]);
                AppService::auditLog('clients', $cid, 'BUDGET_ALERT', [], [
                    'type' => 'critical',
                    'pct' => $pct,
                ]);
                $newAlerts[] = [
                    'client_id' => $cid,
                    'client_name' => $r['client_name'],
                    'threshold' => $critThreshold,
                    'utilization_pct' => $pct,
                    'total_budget' => $tb,
                ];
            } elseif ($pct >= $warnThreshold && ! ($r['budget_alert_warning_sent'] ?? false)) {
                Client::query()->where('id', $cid)->update(['budget_alert_warning_sent' => true]);
                AppService::auditLog('clients', $cid, 'BUDGET_ALERT', [], [
                    'type' => 'warning',
                    'pct' => $pct,
                ]);
                $newAlerts[] = [
                    'client_id' => $cid,
                    'client_name' => $r['client_name'],
                    'threshold' => $warnThreshold,
                    'utilization_pct' => $pct,
                    'total_budget' => $tb,
                ];
            }

            if ($pct < $warnThreshold && (($r['budget_alert_warning_sent'] ?? false) || ($r['budget_alert_critical_sent'] ?? false))) {
                Client::query()->where('id', $cid)->update([
                    'budget_alert_warning_sent' => false,
                    'budget_alert_critical_sent' => false,
                ]);
            } elseif ($pct >= $warnThreshold && $pct < $critThreshold && ($r['budget_alert_critical_sent'] ?? false)) {
                Client::query()->where('id', $cid)->update(['budget_alert_critical_sent' => false]);
            }
        }

        return response()->json(['newAlerts' => $newAlerts]);
    }
}
