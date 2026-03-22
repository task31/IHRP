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

        $unresolvedNames = [];
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
                if ($consultant) {
                    $mapping->consultant_id = $consultant->id;
                }
            }
            $mapping->save();
            if ($mapping->consultant_id === null) {
                $unresolvedNames[] = $name;
            }
        }
        $unresolvedNames = array_values(array_unique($unresolvedNames));

        $affectedYears = array_values(array_unique(array_map(
            fn (array $r) => (int) $r['year'],
            $result->consultantRows
        )));

        DB::transaction(function () use ($result, $targetUser, $storedPath, $file, $stopName, $affectedYears, $unresolvedNames) {
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

            foreach ($result->consultantRows as $row) {
                $mapping = PayrollConsultantMapping::query()
                    ->where('raw_name', $row['name'])
                    ->where('user_id', $targetUser->id)
                    ->first();
                PayrollConsultantEntry::query()->create([
                    'user_id' => $targetUser->id,
                    'consultant_name' => $row['name'],
                    'year' => $row['year'],
                    'revenue' => $row['revenue'],
                    'cost' => $row['cost'],
                    'margin' => $row['margin'],
                    'pct_of_total' => $row['pct_of_total'],
                    'consultant_id' => $mapping?->consultant_id,
                ]);
            }

            PayrollUpload::query()->create([
                'user_id' => $targetUser->id,
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $storedPath,
                'stop_name' => $stopName,
                'record_count' => count($result->records),
                'warnings' => array_merge($result->warnings, array_map(fn ($n) => 'Unresolved consultant name: '.$n, $unresolvedNames)),
            ]);
        });

        AppService::auditLog('payroll_uploads', 0, 'PAYROLL_UPLOAD', [], [
            'target_am_id' => $targetUser->id,
            'filename' => $file->getClientOriginalName(),
            'stop_name' => $stopName,
            'record_count' => count($result->records),
            'years_affected' => $affectedYears,
            'unresolved_names' => $unresolvedNames,
            'warnings' => $result->warnings,
        ]);

        return response()->json([
            'success' => true,
            'recordCount' => count($result->records),
            'yearsAffected' => $affectedYears,
            'unresolvedNames' => $unresolvedNames,
            'warnings' => array_merge($result->warnings, $unresolvedNames),
        ]);
    }

    public function apiDashboard(Request $request, PayrollDataService $data): JsonResponse
    {
        $this->authorize('account_manager');
        $year = $request->integer('year', (int) now()->format('Y'));
        $ownerId = $this->getOwnerId($request);

        $goal = PayrollGoal::query()->forOwner($ownerId)->where('year', $year)->first();

        return response()->json([
            'years' => $data->getYears($ownerId),
            'summary' => $data->getSummary($ownerId, $year),
            'monthly' => $data->getMonthly($ownerId, $year),
            'annualTotals' => $data->getAnnualTotals($ownerId),
            'goal' => [
                'year' => $year,
                'amount' => $goal ? (string) $goal->goal_amount : '0.0000',
            ],
            'projection' => $data->getProjection($ownerId, $year),
        ]);
    }

    public function apiConsultants(Request $request, PayrollDataService $data): JsonResponse
    {
        $this->authorize('account_manager');
        $year = $request->integer('year');
        if ($year < 2000 || $year > 2100) {
            return response()->json(['message' => 'Valid year required'], 422);
        }
        $ownerId = $this->getOwnerId($request);

        return response()->json([
            'consultants' => $data->getConsultants($ownerId, $year),
        ]);
    }

    public function apiAggregate(Request $request, PayrollDataService $data): JsonResponse
    {
        $this->authorize('admin');
        $year = $request->integer('year', (int) now()->format('Y'));

        return response()->json([
            'year' => $year,
            'aggregate' => $data->getAggregateSummary($year),
            'perAm' => $data->getPerAmBreakdown($year),
        ]);
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
