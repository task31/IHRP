<?php

namespace App\Http\Controllers;

use App\Models\Consultant;
use App\Models\PayrollConsultantEntry;
use App\Models\PayrollConsultantMapping;
use App\Models\PayrollGoal;
use App\Models\PayrollRecord;
use App\Models\PayrollUpload;
use App\Models\User;
use App\Services\AppService;
use App\Services\PayrollDataService;
use App\Services\PayrollParseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(): View
    {
        $this->authorize('account_manager');

        $accountManagers = User::query()
            ->where('role', 'account_manager')
            ->orderBy('name')
            ->get(['id', 'name']);

        $consultants = Consultant::query()
            ->where('active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name']);

        return view('payroll.index', [
            'accountManagers' => $accountManagers,
            'consultants' => $consultants,
        ]);
    }

    public function upload(Request $request, PayrollParseService $parser): JsonResponse
    {
        $this->authorize('admin');
        $data = $request->validate([
            'file' => ['required', 'file', 'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'max:51200'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'stop_name' => ['required', 'string', 'max:255'],
        ]);

        $targetUser = User::query()->findOrFail($data['user_id']);
        if ($targetUser->role !== 'account_manager') {
            return response()->json(['message' => 'Target user must be an account manager'], 422);
        }

        $stopName = trim($data['stop_name']);
        if ($stopName === '') {
            return response()->json(['message' => 'stop_name is required'], 422);
        }

        $file = $request->file('file');
        $result = $parser->parse($file, $stopName);

        $storedPath = $file->storeAs(
            'uploads/payroll',
            now()->format('Ymd_His').'_'.$file->getClientOriginalName(),
            'local'
        );

        $newConsultants = [];
        foreach ($result->consultantRows as $row) {
            $name = $row['name'];
            $mapping = PayrollConsultantMapping::query()->firstOrNew([
                'raw_name' => $name,
                'user_id' => $targetUser->id,
            ]);
            if (! $mapping->exists) {
                $mapping->created_by = Auth::id();
            }
            if ($mapping->consultant_id === null) {
                $consultant = Consultant::query()
                    ->whereRaw('LOWER(full_name) = ?', [mb_strtolower($name)])
                    ->first();
                if (! $consultant) {
                    $consultant = Consultant::query()->create([
                        'full_name' => $name,
                        'active' => true,
                    ]);
                    $newConsultants[] = $name;
                }
                $mapping->consultant_id = $consultant->id;
            }
            $mapping->save();
        }
        $newConsultants = array_values(array_unique($newConsultants));

        $affectedYears = array_values(array_unique(array_map(
            fn (array $r) => (int) $r['year'],
            $result->consultantRows
        )));

        // Load bill_rate for all mapped consultants in this upload
        $consultantBillRates = [];
        foreach ($result->consultantRows as $row) {
            $mapping = PayrollConsultantMapping::query()
                ->where('raw_name', $row['name'])
                ->where('user_id', $targetUser->id)
                ->first();
            if ($mapping && $mapping->consultant_id) {
                $c = Consultant::query()->find($mapping->consultant_id);
                if ($c && $c->bill_rate !== null) {
                    $consultantBillRates[$row['name']] = (string) $c->bill_rate;
                }
            }
        }

        // Col C = spread per hour (bill_rate − pay_rate = markup per hour)
        // Col D = hours × spread = total markup
        // AM Earnings         = col D × commission%  (this AM's cut of the spread)
        // Agency Revenue      = hours × bill_rate (requires bill_rate on consultant record)
        // Agency Gross Profit = Revenue − AM Earnings
        //
        // pay_rate can be derived: bill_rate − spread_per_hour (requires bill_rate manually entered)
        $rowsByYear = [];
        foreach ($result->consultantRows as $row) {
            $hours          = $row['hours'] ?? '0.0000';
            $amEarnings     = $row['am_earnings'];
            $spreadPerHour  = $row['spread_per_hour'] ?? '0.0000';
            $billRate       = $consultantBillRates[$row['name']] ?? null;

            if ($billRate !== null && bccomp($hours, '0', 4) > 0) {
                $revenue = bcmul($hours, $billRate, 4);
                $margin  = bcsub($revenue, $amEarnings, 4);

                // Derive pay_rate = bill_rate − spread when both are known
                if (bccomp($spreadPerHour, '0', 4) > 0) {
                    $payRate = bcsub($billRate, $spreadPerHour, 4);
                    $mapping = PayrollConsultantMapping::query()
                        ->where('raw_name', $row['name'])
                        ->where('user_id', $targetUser->id)
                        ->first();
                    if ($mapping?->consultant_id) {
                        Consultant::query()
                            ->where('id', $mapping->consultant_id)
                            ->whereNull('pay_rate')
                            ->update(['pay_rate' => $payRate]);
                    }
                }
            } else {
                $revenue = $amEarnings;
                $margin  = '0.0000';
            }

            $rowsByYear[(int) $row['year']][] = array_merge($row, [
                'hours'       => $hours,
                'am_earnings' => $amEarnings,
                'revenue'     => $revenue,
                'cost'        => $amEarnings,
                'margin'      => $margin,
            ]);
        }

        // Recompute pct_of_total per year based on new revenue
        $computedRows = [];
        foreach ($rowsByYear as $yr => $rows) {
            $grandRevenue = array_reduce($rows, fn ($c, $r) => bcadd($c, $r['revenue'], 4), '0.0000');
            foreach ($rows as $r) {
                $pct = '0.0000';
                if (bccomp($grandRevenue, '0', 4) > 0) {
                    $pct = bcmul(bcdiv($r['revenue'], $grandRevenue, 8), '100', 4);
                }
                $computedRows[] = array_merge($r, ['pct_of_total' => $pct]);
            }
        }

        DB::transaction(function () use ($result, $computedRows, $targetUser, $storedPath, $file, $stopName, $affectedYears, $newConsultants) {
            foreach ($result->records as $rec) {
                PayrollRecord::query()->updateOrCreate(
                    [
                        'user_id' => $targetUser->id,
                        'check_date' => $rec['check_date'],
                    ],
                    [
                        'gross_pay' => $rec['gross_pay'],
                        'net_pay' => $rec['net_pay'],
                        'federal_tax' => $rec['federal_tax'],
                        'state_tax' => $rec['state_tax'],
                        'social_security' => $rec['social_security'],
                        'medicare' => $rec['medicare'],
                        'retirement_401k' => $rec['retirement_401k'],
                        'health_insurance' => $rec['health_insurance'],
                        'other_deductions' => $rec['other_deductions'],
                        'commission_subtotal' => $rec['commission_subtotal'],
                        'salary_subtotal' => $rec['salary_subtotal'],
                    ]
                );
            }

            if ($affectedYears !== []) {
                PayrollConsultantEntry::query()
                    ->where('user_id', $targetUser->id)
                    ->whereIn('year', $affectedYears)
                    ->delete();
            }

            foreach ($computedRows as $row) {
                $mapping = PayrollConsultantMapping::query()
                    ->where('raw_name', $row['name'])
                    ->where('user_id', $targetUser->id)
                    ->first();
                PayrollConsultantEntry::query()->updateOrCreate(
                    [
                        'user_id'         => $targetUser->id,
                        'consultant_name' => $row['name'],
                        'year'            => $row['year'],
                    ],
                    [
                        'hours'           => $row['hours'],
                        'spread_per_hour' => $row['spread_per_hour'] ?? '0.0000',
                        'commission_pct'  => $row['commission_pct'] ?? '0.00000000',
                        'am_earnings'     => $row['am_earnings'],
                        'revenue'         => $row['revenue'],
                        'cost'            => $row['cost'],
                        'margin'          => $row['margin'],
                        'pct_of_total'    => $row['pct_of_total'],
                        'consultant_id'   => $mapping?->consultant_id,
                    ]
                );
            }

            PayrollUpload::query()->create([
                'user_id' => $targetUser->id,
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $storedPath,
                'stop_name' => $stopName,
                'record_count' => count($result->records),
                'warnings' => array_merge($result->warnings, array_map(fn ($n) => 'Auto-created consultant: '.$n, $newConsultants)),
            ]);
        });

        AppService::auditLog('payroll_uploads', 0, 'PAYROLL_UPLOAD', [], [
            'target_am_id' => $targetUser->id,
            'filename' => $file->getClientOriginalName(),
            'stop_name' => $stopName,
            'record_count' => count($result->records),
            'years_affected' => $affectedYears,
            'new_consultants' => $newConsultants,
            'warnings' => $result->warnings,
        ]);

        $ownerId = $targetUser->id;
        foreach ($affectedYears as $yr) {
            Cache::forget("payroll_dashboard_{$ownerId}_{$yr}");
            Cache::forget("payroll_aggregate_{$yr}");
        }

        return response()->json([
            'success' => true,
            'recordCount' => count($result->records),
            'yearsAffected' => $affectedYears,
            'newConsultants' => $newConsultants,
            'warnings' => $result->warnings,
        ]);
    }

    public function apiDashboard(Request $request, PayrollDataService $data): JsonResponse
    {
        $this->authorize('account_manager');
        $year = $request->integer('year', (int) now()->format('Y'));
        $ownerId = $this->getOwnerId($request);

        $cacheKey = "payroll_dashboard_{$ownerId}_{$year}";
        $payload = Cache::remember($cacheKey, 3600, function () use ($data, $ownerId, $year) {
            $goal = PayrollGoal::query()->forOwner($ownerId)->where('year', $year)->first();
            return [
                'years'       => $data->getYears($ownerId),
                'summary'     => $data->getSummary($ownerId, $year),
                'monthly'     => $data->getMonthly($ownerId, $year),
                'annualTotals'=> $data->getAnnualTotals($ownerId),
                'goal'        => [
                    'year'   => $year,
                    'amount' => $goal ? (string) $goal->goal_amount : '0.0000',
                ],
                'projection'  => $data->getProjection($ownerId, $year),
            ];
        });

        return response()->json($payload);
    }

    public function apiConsultants(Request $request, PayrollDataService $data): JsonResponse
    {
        $this->authorize('account_manager');
        $year = $request->integer('year');
        if ($year < 2000 || $year > 2100) {
            return response()->json(['message' => 'Valid year required'], 422);
        }
        $ownerId = $this->getOwnerId($request);

        return response()->json($data->getConsultants($ownerId, $year));
    }

    public function apiAggregate(Request $request, PayrollDataService $data): JsonResponse
    {
        $this->authorize('admin');
        $year = $request->integer('year', (int) now()->format('Y'));

        $cacheKey = "payroll_aggregate_{$year}";
        $payload = Cache::remember($cacheKey, 3600, function () use ($data, $year) {
            return [
                'year'      => $year,
                'aggregate' => $data->getAggregateSummary($year),
                'perAm'     => $data->getPerAmBreakdown($year),
            ];
        });

        return response()->json($payload);
    }

    public function apiGoalSet(Request $request): JsonResponse
    {
        $this->authorize('admin');
        $payload = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'goal_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $target = User::query()->findOrFail($payload['user_id']);
        if ($target->role !== 'account_manager') {
            return response()->json(['message' => 'Target user must be an account manager'], 422);
        }

        $goal = PayrollGoal::query()->updateOrCreate(
            [
                'user_id' => $target->id,
                'year' => $payload['year'],
            ],
            [
                'goal_amount' => bcadd((string) $payload['goal_amount'], '0', 4),
            ]
        );

        $ownerId = $target->id;
        $year = $payload['year'];
        Cache::forget("payroll_dashboard_{$ownerId}_{$year}");

        return response()->json([
            'success' => true,
            'year' => (int) $goal->year,
            'user_id' => $goal->user_id,
            'goal_amount' => (string) $goal->goal_amount,
        ]);
    }

    public function apiMappings(Request $request): JsonResponse
    {
        $this->authorize('admin');
        $rows = PayrollConsultantMapping::query()
            ->with('user:id,name')
            ->whereNull('consultant_id')
            ->orderBy('user_id')
            ->orderBy('raw_name')
            ->get(['id', 'raw_name', 'user_id']);

        return response()->json([
            'mappings' => $rows->map(fn ($m) => [
                'id' => $m->id,
                'raw_name' => $m->raw_name,
                'user_id' => $m->user_id,
                'user_name' => $m->user?->name,
            ])->values()->all(),
        ]);
    }

    public function apiMappingsUpdate(Request $request): JsonResponse
    {
        $this->authorize('admin');
        $payload = $request->validate([
            'raw_name' => ['required', 'string', 'max:255'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'consultant_id' => ['required', 'integer', 'exists:consultants,id'],
        ]);

        $target = User::query()->findOrFail($payload['user_id']);
        if ($target->role !== 'account_manager') {
            return response()->json(['message' => 'Target user must be an account manager'], 422);
        }

        PayrollConsultantMapping::query()->updateOrCreate(
            [
                'raw_name' => $payload['raw_name'],
                'user_id' => $payload['user_id'],
            ],
            [
                'consultant_id' => $payload['consultant_id'],
                'created_by' => Auth::id(),
            ]
        );

        return response()->json(['success' => true]);
    }

    public function recomputeMargins(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $target = User::query()->findOrFail($data['user_id']);
        if ($target->role !== 'account_manager') {
            return response()->json(['message' => 'Target user must be an account manager'], 422);
        }

        $entries = PayrollConsultantEntry::query()
            ->where('user_id', $target->id)
            ->get();

        // Load bill_rate map: consultant_id → bill_rate
        $billRatesById = [];
        $consultantIds = $entries->pluck('consultant_id')->filter()->unique()->values();
        if ($consultantIds->isNotEmpty()) {
            Consultant::query()
                ->whereIn('id', $consultantIds)
                ->whereNotNull('bill_rate')
                ->get(['id', 'bill_rate'])
                ->each(function ($c) use (&$billRatesById) {
                    $billRatesById[$c->id] = (string) $c->bill_rate;
                });
        }

        // Group entries by year to recompute pct_of_total
        $byYear = [];
        foreach ($entries as $entry) {
            $byYear[$entry->year][] = $entry;
        }

        $updated = 0;
        DB::transaction(function () use ($byYear, $billRatesById, &$updated) {
            foreach ($byYear as $yr => $yearEntries) {
                $computed = [];
                foreach ($yearEntries as $entry) {
                    $hours          = (string) $entry->hours;
                    $amEarnings     = (string) $entry->am_earnings; // never modified — comes from Excel upload only
                    $spreadPerHour  = (string) $entry->spread_per_hour;
                    $billRate       = isset($entry->consultant_id) ? ($billRatesById[$entry->consultant_id] ?? null) : null;

                    // Agency Gross Profit = (hours × bill_rate) − AM Earnings
                    // Pay Rate            = Bill Rate − spread_per_hour
                    if ($billRate !== null && bccomp($hours, '0', 4) > 0) {
                        $revenue = bcmul($hours, $billRate, 4);
                        $margin  = bccomp($amEarnings, '0', 4) > 0
                            ? bcsub($revenue, $amEarnings, 4)
                            : '0.0000';

                        // Derive pay_rate = bill_rate − spread when both are known
                        if (bccomp($spreadPerHour, '0', 4) > 0 && $entry->consultant_id) {
                            $payRate = bcsub($billRate, $spreadPerHour, 4);
                            Consultant::query()
                                ->where('id', $entry->consultant_id)
                                ->whereNull('pay_rate')
                                ->update(['pay_rate' => $payRate]);
                        }
                    } else {
                        $revenue = $amEarnings;
                        $margin  = '0.0000';
                    }

                    $computed[] = ['entry' => $entry, 'revenue' => $revenue, 'margin' => $margin];
                }

                // Recompute pct_of_total based on new revenues
                $grandRevenue = array_reduce($computed, fn ($c, $r) => bcadd($c, $r['revenue'], 4), '0.0000');
                foreach ($computed as $item) {
                    $pct = '0.0000';
                    if (bccomp($grandRevenue, '0', 4) > 0) {
                        $pct = bcmul(bcdiv($item['revenue'], $grandRevenue, 8), '100', 4);
                    }
                    $item['entry']->update([
                        'revenue'      => $item['revenue'],
                        'margin'       => $item['margin'],
                        'pct_of_total' => $pct,
                    ]);
                    $updated++;
                }
            }
        });

        // Bust dashboard cache for all years this AM has data
        $years = $entries->pluck('year')->unique()->values();
        foreach ($years as $yr) {
            Cache::forget("payroll_dashboard_{$target->id}_{$yr}");
            Cache::forget("payroll_aggregate_{$yr}");
        }

        AppService::auditLog('payroll_consultant_entries', 0, 'RECOMPUTE_MARGINS', [], [
            'target_am_id' => $target->id,
            'entries_updated' => $updated,
        ]);

        return response()->json(['success' => true, 'updated' => $updated]);
    }

    private function getOwnerId(Request $request): int
    {
        /** @var User $user */
        $user = Auth::user();
        if ($user->isAdmin()) {
            abort_unless($request->filled('user_id'), 422, 'user_id is required for admin payroll views');
            $target = User::query()->findOrFail($request->integer('user_id'));
            abort_if($target->role !== 'account_manager', 422, 'Target user must be an account manager');

            return $target->id;
        }

        return (int) $user->id;
    }
}
