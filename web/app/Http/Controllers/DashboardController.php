<?php

namespace App\Http\Controllers;

use App\Models\DailyCallReport;
use App\Models\Placement;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function page(): View
    {
        $user = auth()->user();

        if ($user->role === 'employee') {
            $placement = null;
            if ($user->consultant_id !== null) {
                $placement = Placement::query()
                    ->with(['consultant', 'client'])
                    ->where('consultant_id', $user->consultant_id)
                    ->where('status', 'active')
                    ->orderByDesc('start_date')
                    ->first();
            }

            $recentCalls = DailyCallReport::query()
                ->where('user_id', $user->id)
                ->where('report_date', '>=', now()->subDays(6)->toDateString())
                ->orderByDesc('report_date')
                ->get();

            return view('dashboard', compact('placement', 'recentCalls'));
        }

        return view('dashboard');
    }

    public function index(): JsonResponse
    {
        $role = auth()->user()?->role;
        abort_unless(in_array($role, ['admin', 'account_manager', 'employee'], true), 403);

        $mtdMonth = now()->format('Y-m');

        if ($role === 'account_manager') {
            $userId = auth()->id();

            $consultantIds = DB::table('placements')
                ->where('placed_by', $userId)
                ->whereNotNull('consultant_id')
                ->distinct()
                ->pluck('consultant_id');

            $clientIds = DB::table('placements')
                ->where('placed_by', $userId)
                ->distinct()
                ->pluck('client_id');

            $activeConsultants = DB::table('consultants')
                ->where('active', 1)
                ->whereIn('id', $consultantIds)
                ->count();

            $activeClients = DB::table('clients')
                ->where('active', 1)
                ->whereIn('id', $clientIds)
                ->count();

            $pending = DB::table('invoices')
                ->whereIn('status', ['pending', 'sent'])
                ->whereIn('consultant_id', $consultantIds)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount_due), 0) as total')
                ->first();

            $mtdRevenue = (float) DB::table('timesheets')
                ->whereIn('consultant_id', $consultantIds)
                ->whereRaw("DATE_FORMAT(pay_period_start, '%Y-%m') = ?", [$mtdMonth])
                ->sum('total_client_billable');
        } else {
            $activeConsultants = (int) DB::table('consultants')->where('active', 1)->count();
            $activeClients = (int) DB::table('clients')->where('active', 1)->count();

            $pending = DB::table('invoices')
                ->whereIn('status', ['pending', 'sent'])
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount_due), 0) as total')
                ->first();

            $mtdRevenue = (float) DB::table('timesheets')
                ->whereRaw("DATE_FORMAT(pay_period_start, '%Y-%m') = ?", [$mtdMonth])
                ->sum('total_client_billable');
        }

        return response()->json([
            'activeConsultants' => (int) $activeConsultants,
            'activeClients' => (int) $activeClients,
            'pendingInvoicesCount' => (int) ($pending->count ?? 0),
            'pendingInvoicesAmount' => (float) ($pending->total ?? 0),
            'mtdRevenue' => $mtdRevenue,
        ]);
    }
}
