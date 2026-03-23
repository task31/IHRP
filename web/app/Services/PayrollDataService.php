<?php

namespace App\Services;

use App\Models\PayrollConsultantEntry;
use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PayrollDataService
{
    public function __construct()
    {
        bcscale(8);
    }

    /**
     * @return list<int>
     */
    public function getYears(int $userId): array
    {
        $q = PayrollRecord::query()->forOwner($userId);
        if (DB::connection()->getDriverName() === 'sqlite') {
            $years = $q
                ->selectRaw('DISTINCT CAST(strftime("%Y", check_date) AS INTEGER) as y')
                ->orderByDesc('y')
                ->pluck('y')
                ->all();
        } else {
            $years = $q
                ->selectRaw('DISTINCT YEAR(check_date) as y')
                ->orderByDesc('y')
                ->pluck('y')
                ->all();
        }

        return array_map('intval', $years);
    }

    /**
     * @return array{periods: list<array<string, mixed>>, prior_year_periods: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function getSummary(int $userId, int $year): array
    {
        $records = PayrollRecord::query()
            ->forOwner($userId)
            ->whereYear('check_date', $year)
            ->orderBy('check_date')
            ->get();

        $periods = [];
        $cumulative = '0.0000';
        foreach ($records as $row) {
            $cumulative = $this->bcAdd($cumulative, (string) $row->net_pay);
            $periods[] = [
                'date' => $row->check_date->format('Y-m-d'),
                'gross' => $this->money($row->gross_pay),
                'federal' => $this->money($row->federal_tax),
                'ss' => $this->money($row->social_security),
                'medicare' => $this->money($row->medicare),
                'state' => $this->money($row->state_tax),
                'disability' => $this->money($row->other_deductions),
                'retirement' => $this->money($row->retirement_401k),
                'net' => $this->money($row->net_pay),
                'cumulative_net' => $this->bcAdd($cumulative, '0'),
            ];
        }

        $prior = PayrollRecord::query()
            ->forOwner($userId)
            ->whereYear('check_date', $year - 1)
            ->orderBy('check_date')
            ->get();

        $prior_year_periods = [];
        $priorCum = '0.0000';
        foreach ($prior as $row) {
            $priorCum = $this->bcAdd($priorCum, (string) $row->net_pay);
            $prior_year_periods[] = [
                'date' => $row->check_date->format('Y-m-d'),
                'cumulative_net' => $this->bcAdd($priorCum, '0'),
            ];
        }

        $ytdNet = '0.0000';
        $ytdGross = '0.0000';
        $federal = '0.0000';
        $ss = '0.0000';
        $medicare = '0.0000';
        $state = '0.0000';
        $disability = '0.0000';
        $retirement = '0.0000';
        foreach ($records as $row) {
            $ytdNet = $this->bcAdd($ytdNet, (string) $row->net_pay);
            $ytdGross = $this->bcAdd($ytdGross, (string) $row->gross_pay);
            $federal = $this->bcAdd($federal, (string) $row->federal_tax);
            $ss = $this->bcAdd($ss, (string) $row->social_security);
            $medicare = $this->bcAdd($medicare, (string) $row->medicare);
            $state = $this->bcAdd($state, (string) $row->state_tax);
            $disability = $this->bcAdd($disability, (string) $row->other_deductions);
            $retirement = $this->bcAdd($retirement, (string) $row->retirement_401k);
        }
        $taxesPaid = $this->bcAdd($this->bcAdd($this->bcAdd($this->bcAdd($this->bcAdd($federal, $ss), $medicare), $state), $disability), '0');

        return [
            'periods' => $periods,
            'prior_year_periods' => $prior_year_periods,
            'totals' => [
                'ytd_net' => $this->bcAdd($ytdNet, '0'),
                'ytd_gross' => $this->bcAdd($ytdGross, '0'),
                'taxes_paid' => $this->bcAdd($taxesPaid, '0'),
                'retirement_total' => $this->bcAdd($retirement, '0'),
                'federal' => $this->bcAdd($federal, '0'),
                'ss' => $this->bcAdd($ss, '0'),
                'medicare' => $this->bcAdd($medicare, '0'),
                'state' => $this->bcAdd($state, '0'),
                'disability' => $this->bcAdd($disability, '0'),
            ],
        ];
    }

    /**
     * @return array{months: list<array<string, mixed>>}
     */
    public function getMonthly(int $userId, int $year): array
    {
        $records = PayrollRecord::query()
            ->forOwner($userId)
            ->whereYear('check_date', $year)
            ->orderBy('check_date')
            ->get();

        /** @var array<string, array<string, string>> $byMonth */
        $byMonth = [];
        foreach ($records as $row) {
            $key = $row->check_date->format('Y-m').'-01';
            if (! isset($byMonth[$key])) {
                $byMonth[$key] = [
                    'gross' => '0.0000',
                    'federal' => '0.0000',
                    'ss' => '0.0000',
                    'medicare' => '0.0000',
                    'state' => '0.0000',
                    'disability' => '0.0000',
                    'retirement' => '0.0000',
                    'net' => '0.0000',
                ];
            }
            $byMonth[$key]['gross'] = $this->bcAdd($byMonth[$key]['gross'], (string) $row->gross_pay);
            $byMonth[$key]['federal'] = $this->bcAdd($byMonth[$key]['federal'], (string) $row->federal_tax);
            $byMonth[$key]['ss'] = $this->bcAdd($byMonth[$key]['ss'], (string) $row->social_security);
            $byMonth[$key]['medicare'] = $this->bcAdd($byMonth[$key]['medicare'], (string) $row->medicare);
            $byMonth[$key]['state'] = $this->bcAdd($byMonth[$key]['state'], (string) $row->state_tax);
            $byMonth[$key]['disability'] = $this->bcAdd($byMonth[$key]['disability'], (string) $row->other_deductions);
            $byMonth[$key]['retirement'] = $this->bcAdd($byMonth[$key]['retirement'], (string) $row->retirement_401k);
            $byMonth[$key]['net'] = $this->bcAdd($byMonth[$key]['net'], (string) $row->net_pay);
        }

        ksort($byMonth);
        $months = [];
        foreach ($byMonth as $monthKey => $m) {
            $sum = $this->bcAdd($this->bcAdd($m['gross'], $m['net']), '0');
            if (bccomp($sum, '0', 4) <= 0) {
                continue;
            }
            $months[] = [
                'month' => $monthKey,
                'gross' => $m['gross'],
                'federal' => $m['federal'],
                'ss' => $m['ss'],
                'medicare' => $m['medicare'],
                'state' => $m['state'],
                'disability' => $m['disability'],
                'retirement' => $m['retirement'],
                'net' => $m['net'],
            ];
        }

        return ['months' => $months];
    }

    /**
     * @return array{years: list<array<string, mixed>>}
     */
    public function getAnnualTotals(int $userId): array
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $rows = PayrollRecord::query()
                ->forOwner($userId)
                ->selectRaw('CAST(strftime("%Y", check_date) AS INTEGER) as year')
                ->selectRaw('SUM(net_pay) as net')
                ->selectRaw('SUM(gross_pay) as gross')
                ->selectRaw('SUM(federal_tax) as federal')
                ->selectRaw('SUM(social_security) as ss')
                ->selectRaw('SUM(medicare) as medicare')
                ->selectRaw('SUM(state_tax) as state')
                ->selectRaw('SUM(other_deductions) as disability')
                ->groupBy(DB::raw('CAST(strftime("%Y", check_date) AS INTEGER)'))
                ->orderBy('year')
                ->get();
        } else {
            $rows = PayrollRecord::query()
                ->forOwner($userId)
                ->selectRaw('YEAR(check_date) as year')
                ->selectRaw('SUM(net_pay) as net')
                ->selectRaw('SUM(gross_pay) as gross')
                ->selectRaw('SUM(federal_tax) as federal')
                ->selectRaw('SUM(social_security) as ss')
                ->selectRaw('SUM(medicare) as medicare')
                ->selectRaw('SUM(state_tax) as state')
                ->selectRaw('SUM(other_deductions) as disability')
                ->groupBy(DB::raw('YEAR(check_date)'))
                ->orderBy('year')
                ->get();
        }

        $years = [];
        foreach ($rows as $r) {
            $taxes = $this->bcAdd(
                $this->bcAdd(
                    $this->bcAdd(
                        $this->bcAdd(
                            $this->bcAdd((string) $r->federal, (string) $r->ss),
                            (string) $r->medicare
                        ),
                        (string) $r->state
                    ),
                    (string) $r->disability
                ),
                '0'
            );
            $years[] = [
                'year' => (int) $r->year,
                'net' => $this->money($r->net),
                'gross' => $this->money($r->gross),
                'taxes_paid' => $taxes,
            ];
        }

        return ['years' => $years];
    }

    /**
     * @return array{consultants: list<array<string, mixed>>, total_periods: int, total_paid_out: string, top_earner: string}
     */
    public function getConsultants(int $userId, int $year): array
    {
        $rows = PayrollConsultantEntry::query()
            ->forOwner($userId)
            ->where('year', $year)
            ->orderByDesc('revenue')
            ->get();

        $periodCount = PayrollRecord::query()
            ->forOwner($userId)
            ->whereYear('check_date', $year)
            ->count();

        $grandTotal = '0.0000';
        foreach ($rows as $row) {
            $grandTotal = $this->bcAdd($grandTotal, (string) $row->revenue);
        }

        $topEarner = $rows->first()?->consultant_name ?? '';

        $consultants = [];
        foreach ($rows as $row) {
            $pct = round((float) $row->pct_of_total, 1);
            $tier = match (true) {
                $pct >= 25.0 => '50%',
                $pct >= 15.0 => '35%',
                $pct >= 10.0 => '20%',
                default      => '10%',
            };
            $consultants[] = [
                'name'           => $row->consultant_name,
                'consultant_id'  => $row->consultant_id,
                'total_gross'    => $this->money($row->revenue),
                'total_hours'    => null,
                'periods_active' => $periodCount,
                'tier'           => $tier,
                'pct_of_total'   => $pct,
            ];
        }

        return [
            'consultants'    => $consultants,
            'total_periods'  => $periodCount,
            'total_paid_out' => $this->bcAdd($grandTotal, '0'),
            'top_earner'     => $topEarner,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getProjection(int $userId, int $year): array
    {
        $records = PayrollRecord::query()
            ->forOwner($userId)
            ->whereYear('check_date', $year)
            ->orderBy('check_date')
            ->get();

        $periodsElapsed = $records->count();
        if ($periodsElapsed === 0) {
            return [
                'projectionSuppressed' => true,
                'reason' => 'no_data',
                'message' => 'No payroll data yet',
                'projectedAnnual' => null,
                'periodsElapsed' => 0,
                'priorYearNet' => $this->priorYearNetTotal($userId, $year),
                'avg_net_per_period' => '0.0000',
            ];
        }

        if ($periodsElapsed < 4) {
            $ytdNet = '0.0000';
            foreach ($records as $row) {
                $ytdNet = $this->bcAdd($ytdNet, (string) $row->net_pay);
            }

            return [
                'projectionSuppressed' => true,
                'reason' => 'too_early',
                'message' => 'Too early in year to project reliably',
                'projectedAnnual' => null,
                'periodsElapsed' => $periodsElapsed,
                'priorYearNet' => $this->priorYearNetTotal($userId, $year),
                'avg_net_per_period' => $this->bcDiv($ytdNet, (string) $periodsElapsed),
            ];
        }

        $ytdNet = '0.0000';
        foreach ($records as $row) {
            $ytdNet = $this->bcAdd($ytdNet, (string) $row->net_pay);
        }
        $avg = $this->bcDiv($ytdNet, (string) $periodsElapsed);
        $projected = bcmul($avg, '26', 4);

        return [
            'projectionSuppressed' => false,
            'reason' => null,
            'message' => '',
            'projectedAnnual' => $projected,
            'periodsElapsed' => $periodsElapsed,
            'priorYearNet' => $this->priorYearNetTotal($userId, $year),
            'avg_net_per_period' => $avg,
        ];
    }

    /**
     * @return array{ytd_net: string, ytd_gross: string, taxes_paid: string}
     */
    public function getAggregateSummary(int $year): array
    {
        $records = PayrollRecord::query()->whereYear('check_date', $year)->get();
        $ytdNet = '0.0000';
        $ytdGross = '0.0000';
        $federal = '0.0000';
        $ss = '0.0000';
        $medicare = '0.0000';
        $state = '0.0000';
        $disability = '0.0000';
        foreach ($records as $row) {
            $ytdNet = $this->bcAdd($ytdNet, (string) $row->net_pay);
            $ytdGross = $this->bcAdd($ytdGross, (string) $row->gross_pay);
            $federal = $this->bcAdd($federal, (string) $row->federal_tax);
            $ss = $this->bcAdd($ss, (string) $row->social_security);
            $medicare = $this->bcAdd($medicare, (string) $row->medicare);
            $state = $this->bcAdd($state, (string) $row->state_tax);
            $disability = $this->bcAdd($disability, (string) $row->other_deductions);
        }
        $taxesPaid = $this->bcAdd($this->bcAdd($this->bcAdd($this->bcAdd($this->bcAdd($federal, $ss), $medicare), $state), $disability), '0');

        return [
            'ytd_net' => $this->bcAdd($ytdNet, '0'),
            'ytd_gross' => $this->bcAdd($ytdGross, '0'),
            'taxes_paid' => $this->bcAdd($taxesPaid, '0'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPerAmBreakdown(int $year): array
    {
        $ams = User::query()->where('role', 'account_manager')->orderBy('name')->get();

        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $sums = PayrollRecord::query()
                ->select('user_id')
                ->selectRaw('SUM(net_pay) as net_pay')
                ->selectRaw('SUM(gross_pay) as gross_pay')
                ->selectRaw('SUM(federal_tax + social_security + medicare + state_tax + other_deductions) as taxes_paid')
                ->whereYear('check_date', $year)
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');
        } else {
            $sums = PayrollRecord::query()
                ->select('user_id')
                ->selectRaw('SUM(net_pay) as net_pay')
                ->selectRaw('SUM(gross_pay) as gross_pay')
                ->selectRaw('SUM(federal_tax + social_security + medicare + state_tax + other_deductions) as taxes_paid')
                ->whereYear('check_date', $year)
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');
        }

        $grandNet = '0.0000';
        foreach ($sums as $s) {
            $grandNet = $this->bcAdd($grandNet, (string) $s->net_pay);
        }

        $out = [];
        foreach ($ams as $am) {
            $s = $sums->get($am->id);
            $net = $s ? $this->money($s->net_pay) : '0.0000';
            $gross = $s ? $this->money($s->gross_pay) : '0.0000';
            $taxes = $s ? $this->money($s->taxes_paid) : '0.0000';
            $pct = '0.0000';
            if (bccomp($grandNet, '0', 4) > 0 && $s) {
                $pct = bcmul(bcdiv((string) $s->net_pay, $grandNet, 8), '100', 4);
            }
            $out[] = [
                'user_id' => $am->id,
                'name' => $am->name,
                'ytd_net' => $net,
                'ytd_gross' => $gross,
                'taxes_paid' => $taxes,
                'pct_of_total_net' => $pct,
            ];
        }

        return $out;
    }

    private function priorYearNetTotal(int $userId, int $year): string
    {
        $sum = PayrollRecord::query()
            ->forOwner($userId)
            ->whereYear('check_date', $year - 1)
            ->sum('net_pay');

        return $this->money($sum ?? 0);
    }

    private function money(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '0.0000';
        }

        return bcadd((string) $v, '0', 4);
    }

    private function bcAdd(string $a, string $b): string
    {
        return bcadd($a, $b, 4);
    }

    private function bcDiv(string $a, string $b): string
    {
        if (bccomp($b, '0', 4) === 0) {
            return '0.0000';
        }

        return bcdiv($a, $b, 4);
    }
}
