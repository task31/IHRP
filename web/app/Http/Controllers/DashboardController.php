<?php

namespace App\Http\Controllers;

use App\Models\DailyCallReport;
use App\Models\Placement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function page(): View
    {
        $user = auth()->user();

        abort_if($user->role === 'account_manager', 403);

        return view('dashboard');
    }

    public function index(): JsonResponse
    {
        $role = auth()->user()?->role;
        abort_unless($role === 'admin', 403);

        $mtdStart = now()->startOfMonth()->toDateString();
        $mtdEnd = now()->endOfMonth()->toDateString();

        $activeConsultants = (int) DB::table('consultants')->where('active', 1)->count();
        $activeClients = (int) DB::table('clients')->where('active', 1)->count();

        $pending = DB::table('invoices')
            ->whereIn('status', ['pending', 'sent'])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount_due), 0) as total')
            ->first();

        $mtdRevenue = (float) DB::table('timesheets')
            ->whereBetween('pay_period_start', [$mtdStart, $mtdEnd])
            ->sum('total_client_billable');

        return response()->json([
            'activeConsultants' => (int) $activeConsultants,
            'activeClients' => (int) $activeClients,
            'pendingInvoicesCount' => (int) ($pending->count ?? 0),
            'pendingInvoicesAmount' => (float) ($pending->total ?? 0),
            'mtdRevenue' => $mtdRevenue,
        ]);
    }

    public function callsStats(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $period = in_array($request->get('period'), ['week', 'month', 'quarter', 'year'], true)
            ? $request->get('period')
            : 'month';

        $start = match ($period) {
            'week'    => now()->startOfWeek(),
            'month'   => now()->startOfMonth(),
            'quarter' => now()->startOfQuarter(),
            'year'    => now()->startOfYear(),
        };
        $end = now()->toDateString();

        // ── Team aggregate ────────────────────────────────────────────
        $team = DB::table('daily_call_reports')
            ->whereBetween('report_date', [$start->toDateString(), $end])
            ->selectRaw('
                COALESCE(SUM(calls_made), 0)           AS total_dials,
                COALESCE(SUM(contacts_reached), 0)     AS total_connects,
                COALESCE(SUM(submittals), 0)           AS total_submittals,
                COALESCE(SUM(interviews_scheduled), 0) AS total_interviews,
                COUNT(DISTINCT report_date)            AS days_with_data,
                COUNT(DISTINCT user_id)                AS active_ams
            ')
            ->first();

        $totalDials    = (int) $team->total_dials;
        $totalConnects = (int) $team->total_connects;
        $daysWithData  = (int) $team->days_with_data;

        // ── Per-AM breakdown ──────────────────────────────────────────
        $byAm = DB::table('daily_call_reports')
            ->join('users', 'daily_call_reports.user_id', '=', 'users.id')
            ->whereBetween('report_date', [$start->toDateString(), $end])
            ->groupBy('daily_call_reports.user_id', 'users.name')
            ->selectRaw('
                users.name,
                COALESCE(SUM(calls_made), 0)               AS dials,
                COALESCE(SUM(contacts_reached), 0)         AS connects,
                COALESCE(SUM(submittals), 0)               AS submittals,
                COALESCE(SUM(interviews_scheduled), 0)     AS interviews,
                COUNT(DISTINCT report_date)                AS days_reported,
                ROUND(AVG(calls_made), 1)                  AS avg_dials_per_day,
                ROUND(AVG(contacts_reached), 1)            AS avg_connects_per_day
            ')
            ->orderByRaw('SUM(calls_made) DESC')
            ->get()
            ->map(fn ($r) => [
                'name'              => $r->name,
                'dials'             => (int) $r->dials,
                'connects'          => (int) $r->connects,
                'connect_rate'      => $r->dials > 0 ? round($r->connects / $r->dials * 100, 1) : 0.0,
                'submittals'        => (int) $r->submittals,
                'interviews'        => (int) $r->interviews,
                'days_reported'     => (int) $r->days_reported,
                'avg_dials_per_day' => (float) $r->avg_dials_per_day,
            ])
            ->values()
            ->all();

        // ── Daily trend ───────────────────────────────────────────────
        $trend = DB::table('daily_call_reports')
            ->whereBetween('report_date', [$start->toDateString(), $end])
            ->groupBy('report_date')
            ->selectRaw('
                report_date,
                SUM(calls_made)       AS dials,
                SUM(contacts_reached) AS connects
            ')
            ->orderBy('report_date')
            ->get()
            ->map(fn ($r) => [
                'date'     => $r->report_date,
                'dials'    => (int) $r->dials,
                'connects' => (int) $r->connects,
            ])
            ->values()
            ->all();

        return response()->json([
            'period'  => $period,
            'team'    => [
                'total_dials'       => $totalDials,
                'total_connects'    => $totalConnects,
                'connect_rate'      => $totalDials > 0 ? round($totalConnects / $totalDials * 100, 1) : 0.0,
                'total_submittals'  => (int) $team->total_submittals,
                'total_interviews'  => (int) $team->total_interviews,
                'days_with_data'    => $daysWithData,
                'active_ams'        => (int) $team->active_ams,
                'avg_dials_per_day' => $daysWithData > 0 ? round($totalDials / $daysWithData, 1) : 0.0,
            ],
            'by_am'   => $byAm,
            'trend'   => $trend,
        ]);
    }
}
