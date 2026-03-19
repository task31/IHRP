<?php

namespace App\Http\Controllers;

use App\Models\Consultant;
use App\Models\Timesheet;
use App\Models\TimesheetDailyHour;
use App\Services\AppService;
use App\Services\OvertimeCalculator;
use App\Services\TimesheetParseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TimesheetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('account_manager');

        $q = Timesheet::query()
            ->with(['consultant:id,full_name', 'client:id,name'])
            ->orderByDesc('pay_period_start');

        if ($request->filled('consultantId')) {
            $q->where('consultant_id', $request->integer('consultantId'));
        }
        if ($request->filled('startDate')) {
            $q->where('pay_period_start', '>=', $request->date('startDate'));
        }
        if ($request->filled('endDate')) {
            $q->where('pay_period_end', '<=', $request->date('endDate'));
        }

        $rows = $q->get()->map(function (Timesheet $t) {
            $a = $t->toArray();
            $a['consultant_name'] = $t->consultant?->full_name;
            $a['client_name'] = $t->client?->name;

            return $a;
        });

        return response()->json($rows);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorize('account_manager');
        $t = Timesheet::query()->find($id);
        if (! $t) {
            return response()->json(null, 404);
        }

        $daily = TimesheetDailyHour::query()
            ->where('timesheet_id', $id)
            ->orderBy('week_number')
            ->orderBy('day_of_week')
            ->get();

        return response()->json(array_merge($t->toArray(), ['dailyHours' => $daily]));
    }

    public function upload(Request $request, TimesheetParseService $parser): JsonResponse
    {
        $this->authorize('admin');
        $request->validate(['timesheet' => ['required', 'file']]);

        return response()->json($parser->parse($request->file('timesheet')));
    }

    public function save(Request $request): JsonResponse
    {
        $this->authorize('admin');
        $payload = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.consultantId' => ['required', 'integer', 'exists:consultants,id'],
            'rows.*.clientId' => ['nullable', 'integer', 'exists:clients,id'],
            'rows.*.week1Hours' => ['required', 'array', 'size:7'],
            'rows.*.week2Hours' => ['required', 'array', 'size:7'],
            'rows.*.payPeriodStart' => ['required', 'date'],
            'rows.*.payPeriodEnd' => ['required', 'date'],
            'rows.*.overwrite' => ['boolean'],
            'saveMapping' => ['boolean'],
            'mapping' => ['nullable', 'array'],
        ]);

        if (! empty($payload['saveMapping']) && isset($payload['mapping'])) {
            AppService::setSetting('timesheet_import_column_mapping', json_encode($payload['mapping']));
        }

        $saved = 0;
        $overwrote = 0;
        $errors = [];

        foreach ($payload['rows'] as $row) {
            try {
                $r = $this->persistTimesheetRow($row, $errors);
                $saved += $r['saved'];
                $overwrote += $r['overwrote'];
            } catch (\Throwable $e) {
                $errors[] = 'Row error: '.$e->getMessage();
            }
        }

        return response()->json(['saved' => $saved, 'overwrote' => $overwrote, 'errors' => $errors]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $errors
     * @return array{saved: int, overwrote: int}
     */
    private function persistTimesheetRow(array $row, array &$errors): array
    {
        $consultant = Consultant::query()->find($row['consultantId']);
        if (! $consultant) {
            $errors[] = "Consultant id {$row['consultantId']} not found — skipped";

            return ['saved' => 0, 'overwrote' => 0];
        }

        $clientId = $row['clientId'] ?? $consultant->client_id;
        if (! $clientId) {
            $errors[] = "{$consultant->full_name}: no client assigned — skipped";

            return ['saved' => 0, 'overwrote' => 0];
        }

        $industry = $consultant->industry_type === 'other' ? 'general' : $consultant->industry_type;
        $hireDate = $consultant->project_start_date?->format('Y-m-d');

        $payRate = (float) $consultant->pay_rate;
        $billRate = (float) $consultant->bill_rate;

        $week1Hours = array_map(fn ($h) => (float) $h, $row['week1Hours']);
        $week2Hours = array_map(fn ($h) => (float) $h, $row['week2Hours']);

        $week1Result = OvertimeCalculator::calculateOvertimePay([
            'state' => $consultant->state,
            'industry' => $industry,
            'hoursPerDay' => $week1Hours,
            'regularRate' => $payRate,
            'billRate' => $billRate,
            'hireDate' => $hireDate,
        ]);
        $week2Result = OvertimeCalculator::calculateOvertimePay([
            'state' => $consultant->state,
            'industry' => $industry,
            'hoursPerDay' => $week2Hours,
            'regularRate' => $payRate,
            'billRate' => $billRate,
            'hireDate' => $hireDate,
        ]);

        $totalConsultantCost = (float) $week1Result['totalConsultantCost'] + (float) $week2Result['totalConsultantCost'];
        $totalClientBillable = (float) $week1Result['totalClientBillable'] + (float) $week2Result['totalClientBillable'];
        $grossMarginDollars = $totalClientBillable - $totalConsultantCost;
        $grossMarginPercent = $totalClientBillable > 0 ? ($grossMarginDollars / $totalClientBillable) * 100 : 0;
        $otRuleApplied = (string) $week1Result['otRuleApplied'];

        $existing = Timesheet::query()
            ->where('consultant_id', $consultant->id)
            ->whereDate('pay_period_start', $row['payPeriodStart'])
            ->whereDate('pay_period_end', $row['payPeriodEnd'])
            ->first();

        return DB::transaction(function () use (
            $existing, $row, $consultant, $clientId, $payRate, $billRate, $week1Result, $week2Result,
            $totalConsultantCost, $totalClientBillable, $grossMarginDollars, $grossMarginPercent, $otRuleApplied,
            $week1Hours, $week2Hours, &$errors
        ) {
            $attrs = [
                'client_id' => $clientId,
                'pay_rate_snapshot' => $payRate,
                'bill_rate_snapshot' => $billRate,
                'state_snapshot' => $consultant->state,
                'industry_type_snapshot' => $consultant->industry_type,
                'ot_rule_applied' => $otRuleApplied,
                'week1_regular_hours' => $week1Result['regularHours'],
                'week1_ot_hours' => $week1Result['otHours'],
                'week1_dt_hours' => $week1Result['doubleTimeHours'],
                'week1_regular_pay' => $week1Result['regularPay'],
                'week1_ot_pay' => $week1Result['otPay'],
                'week1_dt_pay' => $week1Result['doubleTimePay'],
                'week1_regular_billable' => $week1Result['regularBillable'],
                'week1_ot_billable' => $week1Result['otBillable'],
                'week1_dt_billable' => $week1Result['doubleTimeBillable'],
                'week2_regular_hours' => $week2Result['regularHours'],
                'week2_ot_hours' => $week2Result['otHours'],
                'week2_dt_hours' => $week2Result['doubleTimeHours'],
                'week2_regular_pay' => $week2Result['regularPay'],
                'week2_ot_pay' => $week2Result['otPay'],
                'week2_dt_pay' => $week2Result['doubleTimePay'],
                'week2_regular_billable' => $week2Result['regularBillable'],
                'week2_ot_billable' => $week2Result['otBillable'],
                'week2_dt_billable' => $week2Result['doubleTimeBillable'],
                'total_regular_hours' => $week1Result['regularHours'] + $week2Result['regularHours'],
                'total_ot_hours' => $week1Result['otHours'] + $week2Result['otHours'],
                'total_dt_hours' => $week1Result['doubleTimeHours'] + $week2Result['doubleTimeHours'],
                'total_consultant_cost' => round($totalConsultantCost, 4),
                'total_client_billable' => round($totalClientBillable, 4),
                'gross_revenue' => round($totalClientBillable, 4),
                'gross_margin_dollars' => round($grossMarginDollars, 4),
                'gross_margin_percent' => round($grossMarginPercent, 4),
            ];

            if ($existing && ! empty($row['overwrite'])) {
                TimesheetDailyHour::query()->where('timesheet_id', $existing->id)->delete();
                $existing->update($attrs);
                $this->insertDailyHours((int) $existing->id, $week1Hours, $week2Hours);
                AppService::auditLog('timesheets', (int) $existing->id, 'TIMESHEET_OVERWRITE', [], ['confirm' => true]);

                return ['saved' => 0, 'overwrote' => 1];
            }
            if (! $existing) {
                $ts = Timesheet::query()->create(array_merge($attrs, [
                    'consultant_id' => $consultant->id,
                    'pay_period_start' => $row['payPeriodStart'],
                    'pay_period_end' => $row['payPeriodEnd'],
                ]));
                $this->insertDailyHours((int) $ts->id, $week1Hours, $week2Hours);
                AppService::auditLog('timesheets', (int) $ts->id, 'TIMESHEET_IMPORT', [], [
                    'consultant' => $consultant->full_name,
                    'period' => $row['payPeriodStart'].'–'.$row['payPeriodEnd'],
                ]);

                return ['saved' => 1, 'overwrote' => 0];
            }

            $errors[] = "Duplicate skipped: {$consultant->full_name} {$row['payPeriodStart']}–{$row['payPeriodEnd']}";

            return ['saved' => 0, 'overwrote' => 0];
        });
    }

    /**
     * @param  list<float>  $week1Hours
     * @param  list<float>  $week2Hours
     */
    private function insertDailyHours(int $timesheetId, array $week1Hours, array $week2Hours): void
    {
        for ($i = 0; $i < 7; $i++) {
            TimesheetDailyHour::query()->create([
                'timesheet_id' => $timesheetId,
                'week_number' => 1,
                'day_of_week' => (string) $i,
                'hours' => $week1Hours[$i] ?? 0,
            ]);
        }
        for ($i = 0; $i < 7; $i++) {
            TimesheetDailyHour::query()->create([
                'timesheet_id' => $timesheetId,
                'week_number' => 2,
                'day_of_week' => (string) $i,
                'hours' => $week2Hours[$i] ?? 0,
            ]);
        }
    }

    public function checkDuplicate(Request $request): JsonResponse
    {
        $this->authorize('account_manager');
        $data = $request->validate([
            'consultantId' => ['required', 'integer'],
            'payPeriodStart' => ['required', 'date'],
            'payPeriodEnd' => ['required', 'date'],
        ]);

        $row = Timesheet::query()
            ->where('consultant_id', $data['consultantId'])
            ->whereDate('pay_period_start', $data['payPeriodStart'])
            ->whereDate('pay_period_end', $data['payPeriodEnd'])
            ->first(['id', 'total_regular_hours', 'total_ot_hours', 'created_at']);

        return response()->json($row);
    }

    public function downloadTemplate(): BinaryFileResponse|JsonResponse
    {
        $this->authorize('admin');
        $path = storage_path('app/templates/timesheet_template.xlsx');
        if (! is_file($path)) {
            return response()->json(['error' => 'Template not installed at storage/app/templates/timesheet_template.xlsx'], 404);
        }

        return response()->download($path, 'timesheet_template.xlsx');
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request);
    }

    public function update(Request $request, string $timesheet): JsonResponse
    {
        $this->authorize('admin');

        return response()->json(['error' => 'Use POST /timesheets/save for batch import'], 405);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorize('admin');

        return response()->json(['error' => 'Timesheet delete not implemented'], 405);
    }
}
