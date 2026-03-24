<?php

namespace App\Http\Controllers;

use App\Models\DailyCallReport;
use App\Models\User;
use App\Services\AppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DailyCallReportController extends Controller
{
    private const HISTORY_PER_PAGE = 50;

    /** @var list<string> */
    private const AUDIT_FIELDS = [
        'user_id',
        'report_date',
        'calls_made',
        'contacts_reached',
        'submittals',
        'interviews_scheduled',
        'notes',
    ];

    public function index(Request $request): JsonResponse|View
    {
        $this->authorize('viewAny', DailyCallReport::class);

        $user = Auth::user();

        $filters = $request->validate([
            'period' => ['sometimes', 'string', Rule::in(['30', '90', '365', 'all'])],
        ]);
        $period = $filters['period'] ?? '30';

        $query = DailyCallReport::query()->with('user')->orderByDesc('report_date')->orderByDesc('id');
        [$rangeStart, $rangeEnd] = $this->historyPeriodBounds($period);
        if ($rangeStart !== null && $rangeEnd !== null) {
            $query
                ->whereDate('report_date', '>=', $rangeStart->toDateString())
                ->whereDate('report_date', '<=', $rangeEnd->toDateString());
        }

        $reports = $query->paginate(self::HISTORY_PER_PAGE)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json($reports);
        }

        $myReports = DailyCallReport::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn (DailyCallReport $r) => $r->report_date->format('Y-m-d'));

        $myReportsPayload = $myReports->map(
            fn (DailyCallReport $r) => [
                'calls_made' => $r->calls_made,
                'contacts_reached' => $r->contacts_reached,
                'submittals' => $r->submittals,
                'interviews_scheduled' => $r->interviews_scheduled,
                'notes' => $r->notes ?? '',
            ],
        )->all();

        $monthlyStats = DailyCallReport::query()
            ->where('user_id', Auth::id())
            ->whereYear('report_date', now()->year)
            ->whereMonth('report_date', now()->month)
            ->selectRaw('
                COALESCE(SUM(calls_made), 0) as calls_made,
                COALESCE(SUM(contacts_reached), 0) as contacts_reached,
                COALESCE(SUM(submittals), 0) as submittals,
                COALESCE(SUM(interviews_scheduled), 0) as interviews_scheduled
            ')
            ->first();

        $historyRangeLabel = $rangeStart !== null && $rangeEnd !== null
            ? $rangeStart->format('M j, Y').' – '.$rangeEnd->format('M j, Y')
            : 'All dates';

        return view('calls.index', [
            'reports' => $reports,
            'historyPeriod' => $period,
            'historyRangeLabel' => $historyRangeLabel,
            'myReportsByDate' => $myReportsPayload,
            'todayDate' => now()->toDateString(),
            'showEmployeeColumn' => true,
            'monthlyStats' => $monthlyStats,
        ]);
    }

    /**
     * @return array{0: \Illuminate\Support\Carbon|null, 1: \Illuminate\Support\Carbon|null}
     */
    private function historyPeriodBounds(string $period): array
    {
        if ($period === 'all') {
            return [null, null];
        }

        $days = match ($period) {
            '90' => 90,
            '365' => 365,
            default => 30,
        };

        $end = now()->startOfDay();
        $start = $end->copy()->subDays($days - 1);

        return [$start, $end];
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', DailyCallReport::class);

        $data = $request->validate([
            'report_date' => ['required', 'date', 'before_or_equal:today'],
            'calls_made' => ['required', 'integer', 'min:0'],
            'contacts_reached' => ['required', 'integer', 'min:0'],
            'submittals' => ['required', 'integer', 'min:0'],
            'interviews_scheduled' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $key = [
            'user_id' => Auth::id(),
            'report_date' => $data['report_date'],
        ];

        $existing = DailyCallReport::query()->where($key)->first();

        $payload = [
            'calls_made' => $data['calls_made'],
            'contacts_reached' => $data['contacts_reached'],
            'submittals' => $data['submittals'],
            'interviews_scheduled' => $data['interviews_scheduled'],
            'notes' => $data['notes'] ?? null,
        ];

        $report = DailyCallReport::query()->updateOrCreate($key, $payload);

        AppService::auditLog(
            'daily_call_reports',
            (int) $report->id,
            $existing ? 'UPDATE' : 'INSERT',
            $existing ? $existing->only(self::AUDIT_FIELDS) : [],
            $report->fresh()->only(self::AUDIT_FIELDS),
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $report->load('user')]);
        }

        return back()->with('toast', 'Call report saved.');
    }

    public function aggregate(Request $request): JsonResponse|View
    {
        $this->authorize('admin');

        $filters = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        if (($filters['date_from'] ?? null) !== null && ($filters['date_to'] ?? null) !== null) {
            Validator::make($filters, [
                'date_to' => ['after_or_equal:date_from'],
            ])->validate();
        }

        $summary = DailyCallReport::query()
            ->from('daily_call_reports')
            ->join('users', 'users.id', '=', 'daily_call_reports.user_id')
            ->when(
                isset($filters['user_id']),
                fn ($q) => $q->where('daily_call_reports.user_id', (int) $filters['user_id']),
            )
            ->when(
                isset($filters['date_from']),
                fn ($q) => $q->whereDate('daily_call_reports.report_date', '>=', $filters['date_from']),
            )
            ->when(
                isset($filters['date_to']),
                fn ($q) => $q->whereDate('daily_call_reports.report_date', '<=', $filters['date_to']),
            )
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderBy('users.name')
            ->select([
                'users.id as user_id',
                'users.name as user_name',
                'users.email as user_email',
            ])
            ->selectRaw('COUNT(*) as total_days')
            ->selectRaw('SUM(daily_call_reports.calls_made) as total_calls')
            ->selectRaw('SUM(daily_call_reports.contacts_reached) as total_contacts')
            ->selectRaw('SUM(daily_call_reports.submittals) as total_submittals')
            ->selectRaw('SUM(daily_call_reports.interviews_scheduled) as total_interviews')
            ->selectRaw('AVG(daily_call_reports.calls_made) as avg_calls_per_day')
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'summary' => $summary,
                'filters' => $filters,
            ]);
        }

        return view('calls.report', [
            'summary' => $summary,
            'filters' => $filters,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
