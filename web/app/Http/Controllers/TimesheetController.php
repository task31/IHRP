<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Consultant;
use App\Models\Timesheet;
use App\Models\TimesheetDailyHour;
use App\Services\AppService;
use App\Services\OvertimeCalculator;
use App\Services\TimesheetParseService;
use App\Support\PayPeriodFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TimesheetController extends Controller
{
    public function index(Request $request): JsonResponse|View
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
            $a['pay_period_label'] = PayPeriodFormatter::formatRange($t->pay_period_start, $t->pay_period_end);

            return $a;
        });

        if ($request->expectsJson()) {
            return response()->json($rows);
        }

        return view('timesheets.index', [
            'timesheets' => $rows,
            'consultants' => Consultant::query()->where('active', true)->orderBy('full_name')->get(['id', 'full_name', 'client_id', 'state']),
            'clients' => Client::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
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

        return response()->json(array_merge($t->toArray(), [
            'consultant_name' => $t->consultant?->full_name,
            'client_name'     => $t->client?->name,
            'pay_period_label' => PayPeriodFormatter::formatRange($t->pay_period_start, $t->pay_period_end),
            'dailyHours'      => $daily,
            'locked_for_hour_edit' => $t->invoice_id !== null,
        ]));
    }

    public function upload(Request $request, TimesheetParseService $parser): JsonResponse
    {
        $this->authorize('admin');
        $request->validate(['timesheet' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240']]);

        $file = $request->file('timesheet');
        $result = $parser->parse($file);
        $storedPath = $file->storeAs(
            'uploads/timesheets',
            now()->format('Ymd_His').'_'.$file->getClientOriginalName(),
            'local'
        );
        $result['storedPath'] = $storedPath;

        return response()->json($result);
    }

    /**
     * Batch import timesheets (used by POST /timesheets/save and Livewire wizard).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{saved: int, overwrote: int, errors: list<string>}
     */
    public function saveBatch(array $rows, ?string $sourceFilePath = null, bool $saveMapping = false, ?array $mapping = null): array
    {
        if ($saveMapping && $mapping !== null) {
            AppService::setSetting('timesheet_import_column_mapping', json_encode($mapping));
        }

        $saved = 0;
        $overwrote = 0;
        $errors = [];

        foreach ($rows as $row) {
            try {
                $r = $this->persistTimesheetRow($row, $errors, $sourceFilePath);
                $saved += $r['saved'];
                $overwrote += $r['overwrote'];
            } catch (\Throwable $e) {
                $errors[] = 'Row error: '.$e->getMessage();
            }
        }

        return ['saved' => $saved, 'overwrote' => $overwrote, 'errors' => $errors];
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
            'rows.*.state' => ['nullable', 'string', 'size:2'],
            'sourceFilePath' => ['nullable', 'string', 'max:500'],
            'saveMapping' => ['boolean'],
            'mapping' => ['nullable', 'array'],
        ]);

        $out = $this->saveBatch(
            $payload['rows'],
            $payload['sourceFilePath'] ?? null,
            ! empty($payload['saveMapping']),
            $payload['mapping'] ?? null
        );

        return response()->json($out);
    }

    public function previewOt(Request $request): JsonResponse
    {
        $this->authorize('account_manager');
        $data = $request->validate([
            'state' => ['required', 'string', 'size:2'],
            'week1Hours' => ['required', 'array', 'size:7'],
            'week2Hours' => ['required', 'array', 'size:7'],
            'payRate' => ['required', 'numeric', 'min:0'],
        ]);

        $week1Hours = array_map(fn ($h) => (float) $h, $data['week1Hours']);
        $week2Hours = array_map(fn ($h) => (float) $h, $data['week2Hours']);
        $payRate = (float) $data['payRate'];
        $state = strtoupper($data['state']);

        $w1 = OvertimeCalculator::calculateOvertimePay([
            'state' => $state,
            'industry' => 'general',
            'hoursPerDay' => $week1Hours,
            'regularRate' => $payRate,
            'billRate' => $payRate,
            'hireDate' => null,
        ]);
        $w2 = OvertimeCalculator::calculateOvertimePay([
            'state' => $state,
            'industry' => 'general',
            'hoursPerDay' => $week2Hours,
            'regularRate' => $payRate,
            'billRate' => $payRate,
            'hireDate' => null,
        ]);

        return response()->json([
            'week1' => [
                'regularHours' => $w1['regularHours'],
                'otHours' => $w1['otHours'],
                'doubleTimeHours' => $w1['doubleTimeHours'],
            ],
            'week2' => [
                'regularHours' => $w2['regularHours'],
                'otHours' => $w2['otHours'],
                'doubleTimeHours' => $w2['doubleTimeHours'],
            ],
            'totals' => [
                'regularHours' => (float) $w1['regularHours'] + (float) $w2['regularHours'],
                'otHours' => (float) $w1['otHours'] + (float) $w2['otHours'],
                'doubleTimeHours' => (float) $w1['doubleTimeHours'] + (float) $w2['doubleTimeHours'],
            ],
            'otRuleApplied' => (string) $w1['otRuleApplied'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $errors
     * @return array{saved: int, overwrote: int}
     */
    private function persistTimesheetRow(array $row, array &$errors, ?string $sourceFilePath = null): array
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

        $state = (isset($row['state']) && is_string($row['state']) && strlen($row['state']) === 2)
            ? strtoupper($row['state'])
            : (string) $consultant->state;

        $hireDate = $consultant->project_start_date?->format('Y-m-d');

        $payRate = (float) $consultant->pay_rate;
        $billRate = (float) $consultant->bill_rate;

        $week1Hours = array_map(fn ($h) => (float) $h, $row['week1Hours']);
        $week2Hours = array_map(fn ($h) => (float) $h, $row['week2Hours']);

        $computed = $this->computeTimesheetAggregates(
            $state,
            $consultant->industry_type,
            $hireDate,
            $payRate,
            $billRate,
            $week1Hours,
            $week2Hours,
        );

        $existing = Timesheet::query()
            ->where('consultant_id', $consultant->id)
            ->whereDate('pay_period_start', $row['payPeriodStart'])
            ->whereDate('pay_period_end', $row['payPeriodEnd'])
            ->first();

        return DB::transaction(function () use (
            $existing, $row, $consultant, $clientId, $payRate, $billRate, $computed,
            $week1Hours, $week2Hours, &$errors, $state, $sourceFilePath
        ) {
            $attrs = array_merge([
                'client_id' => $clientId,
                'pay_rate_snapshot' => $payRate,
                'bill_rate_snapshot' => $billRate,
                'state_snapshot' => $state,
                'industry_type_snapshot' => $consultant->industry_type,
            ], $computed);

            if ($existing && ! empty($row['overwrite'])) {
                TimesheetDailyHour::query()->where('timesheet_id', $existing->id)->delete();
                $existing->update($attrs);
                $this->insertDailyHours((int) $existing->id, $week1Hours, $week2Hours);
                AppService::auditLog('timesheets', (int) $existing->id, 'TIMESHEET_OVERWRITE', [], ['confirm' => true]);

                return ['saved' => 0, 'overwrote' => 1];
            }
            if (! $existing) {
                $create = array_merge($attrs, [
                    'consultant_id' => $consultant->id,
                    'pay_period_start' => $row['payPeriodStart'],
                    'pay_period_end' => $row['payPeriodEnd'],
                ]);
                if ($sourceFilePath !== null && $sourceFilePath !== '') {
                    $create['source_file_path'] = $sourceFilePath;
                }
                $ts = Timesheet::query()->create($create);
                $this->insertDailyHours((int) $ts->id, $week1Hours, $week2Hours);
                AppService::auditLog('timesheets', (int) $ts->id, 'TIMESHEET_IMPORT', [], [
                    'consultant' => $consultant->full_name,
                    'period' => PayPeriodFormatter::formatRange($row['payPeriodStart'], $row['payPeriodEnd'])
                        ?: ($row['payPeriodStart'].' – '.$row['payPeriodEnd']),
                ]);

                return ['saved' => 1, 'overwrote' => 0];
            }

            $periodLabel = PayPeriodFormatter::formatRange($row['payPeriodStart'], $row['payPeriodEnd'])
                ?: "{$row['payPeriodStart']} – {$row['payPeriodEnd']}";
            $errors[] = "Duplicate skipped: {$consultant->full_name} {$periodLabel}";

            return ['saved' => 0, 'overwrote' => 0];
        });
    }

    /**
     * @param  list<float>  $week1Hours
     * @param  list<float>  $week2Hours
     * @return array<string, mixed>
     */
    private function computeTimesheetAggregates(
        string $state,
        ?string $industryTypeSnapshot,
        ?string $hireDate,
        float $payRate,
        float $billRate,
        array $week1Hours,
        array $week2Hours,
    ): array {
        $industry = ($industryTypeSnapshot === null || $industryTypeSnapshot === '' || $industryTypeSnapshot === 'other')
            ? 'general'
            : (string) $industryTypeSnapshot;

        $week1Result = OvertimeCalculator::calculateOvertimePay([
            'state' => $state,
            'industry' => $industry,
            'hoursPerDay' => $week1Hours,
            'regularRate' => $payRate,
            'billRate' => $billRate,
            'hireDate' => $hireDate,
        ]);
        $week2Result = OvertimeCalculator::calculateOvertimePay([
            'state' => $state,
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

        return [
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

    public function storeManual(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('admin');
        $data = $request->validate([
            'consultant_id' => ['required', 'integer', 'exists:consultants,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'pay_period_start' => ['required', 'date'],
            'pay_period_end' => ['required', 'date', 'after_or_equal:pay_period_start'],
            'week1' => ['required', 'array', 'size:7'],
            'week2' => ['required', 'array', 'size:7'],
            'week1.*' => ['numeric', 'min:0'],
            'week2.*' => ['numeric', 'min:0'],
            'state' => ['nullable', 'string', 'size:2'],
        ]);

        $row = [
            'consultantId' => (int) $data['consultant_id'],
            'clientId' => isset($data['client_id']) ? (int) $data['client_id'] : null,
            'payPeriodStart' => $data['pay_period_start'],
            'payPeriodEnd' => $data['pay_period_end'],
            'week1Hours' => array_values($data['week1']),
            'week2Hours' => array_values($data['week2']),
            'overwrite' => false,
            'state' => $data['state'] ?? null,
        ];

        $out = $this->saveBatch([$row], null, false, null);

        if ($request->expectsJson()) {
            return response()->json($out);
        }

        $msg = $out['saved'] > 0
            ? 'Timesheet saved.'
            : ($out['errors'][0] ?? 'Could not save timesheet.');

        return redirect()
            ->route('timesheets.index')
            ->with($out['saved'] > 0 ? 'success' : 'error', $msg);
    }

    public function downloadTemplate(): BinaryFileResponse|JsonResponse
    {
        $this->authorize('account_manager');
        $path = storage_path('app/templates/timesheet_template.xlsx');
        if (! is_file($path)) {
            return response()->json(['error' => 'Template not installed at storage/app/templates/timesheet_template.xlsx'], 404);
        }

        return response()->download($path, 'timesheet_template.xlsx');
    }

    public function updateHours(Request $request, string $timesheet): JsonResponse
    {
        $this->authorize('admin');
        $data = $request->validate([
            'week1' => ['required', 'array', 'size:7'],
            'week2' => ['required', 'array', 'size:7'],
            'week1.*' => ['numeric', 'min:0'],
            'week2.*' => ['numeric', 'min:0'],
        ]);

        $ts = Timesheet::query()->with(['consultant', 'client'])->find($timesheet);
        if (! $ts) {
            return response()->json(['error' => 'Timesheet not found'], 404);
        }
        if ($ts->invoice_id !== null) {
            return response()->json(['error' => 'Cannot edit hours after an invoice is linked to this timesheet.'], 422);
        }

        $week1 = array_map(fn ($h) => (float) $h, array_values($data['week1']));
        $week2 = array_map(fn ($h) => (float) $h, array_values($data['week2']));
        $hireDate = $ts->consultant?->project_start_date?->format('Y-m-d');

        $computed = $this->computeTimesheetAggregates(
            (string) $ts->state_snapshot,
            $ts->industry_type_snapshot,
            $hireDate,
            (float) $ts->pay_rate_snapshot,
            (float) $ts->bill_rate_snapshot,
            $week1,
            $week2,
        );

        $beforeSnapshot = TimesheetDailyHour::query()
            ->where('timesheet_id', $ts->id)
            ->orderBy('week_number')
            ->orderBy('day_of_week')
            ->get(['week_number', 'day_of_week', 'hours'])
            ->map(fn ($r) => $r->toArray())
            ->all();

        DB::transaction(function () use ($ts, $computed, $week1, $week2, $beforeSnapshot) {
            TimesheetDailyHour::query()->where('timesheet_id', $ts->id)->delete();
            $this->insertDailyHours((int) $ts->id, $week1, $week2);
            $ts->update($computed);
            AppService::auditLog(
                'timesheets',
                (int) $ts->id,
                'TIMESHEET_HOURS_EDIT',
                ['daily_hours' => $beforeSnapshot],
                [
                    'week1' => $week1,
                    'week2' => $week2,
                    'total_regular_hours' => $computed['total_regular_hours'],
                    'total_ot_hours' => $computed['total_ot_hours'],
                    'total_client_billable' => $computed['total_client_billable'],
                ],
                'Daily hours revised; aggregates recomputed from stored rate/state snapshots.',
            );
        });

        $ts->refresh();
        $daily = TimesheetDailyHour::query()
            ->where('timesheet_id', $ts->id)
            ->orderBy('week_number')
            ->orderBy('day_of_week')
            ->get();

        return response()->json(array_merge($ts->toArray(), [
            'consultant_name' => $ts->consultant?->full_name,
            'client_name' => $ts->client?->name,
            'pay_period_label' => PayPeriodFormatter::formatRange($ts->pay_period_start, $ts->pay_period_end),
            'dailyHours' => $daily,
            'locked_for_hour_edit' => $ts->invoice_id !== null,
        ]));
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorize('admin');

        return response()->json(['error' => 'Timesheet delete not implemented'], 405);
    }
}
