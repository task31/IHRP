<?php

namespace Tests\Unit;

use App\Models\PayrollConsultantEntry;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\PayrollDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollDataServiceTest extends TestCase
{
    use RefreshDatabase;

    private function svc(): PayrollDataService
    {
        return new PayrollDataService;
    }

    public function test_projection_suppressed_under_4_periods(): void
    {
        $u = User::factory()->create(['role' => 'account_manager']);
        foreach (['2026-01-01', '2026-01-15', '2026-02-01'] as $d) {
            PayrollRecord::query()->create([
                'user_id' => $u->id,
                'check_date' => $d,
                'gross_pay' => 1000,
                'net_pay' => 800,
                'federal_tax' => 0,
                'state_tax' => 0,
                'social_security' => 0,
                'medicare' => 0,
                'retirement_401k' => 0,
                'health_insurance' => 0,
                'other_deductions' => 0,
                'commission_subtotal' => 0,
                'salary_subtotal' => 0,
            ]);
        }
        $p = $this->svc()->getProjection($u->id, 2026);
        $this->assertTrue($p['projectionSuppressed']);
        $this->assertSame('too_early', $p['reason']);
    }

    public function test_projection_returns_no_data_for_empty_am(): void
    {
        $u = User::factory()->create(['role' => 'account_manager']);
        $p = $this->svc()->getProjection($u->id, 2026);
        $this->assertTrue($p['projectionSuppressed']);
        $this->assertSame('no_data', $p['reason']);
        $this->assertSame('No payroll data yet', $p['message']);
    }

    public function test_projection_linear_extrapolation_correct(): void
    {
        $u = User::factory()->create(['role' => 'account_manager']);
        foreach (['2026-01-01', '2026-01-15', '2026-02-01', '2026-02-15'] as $d) {
            PayrollRecord::query()->create([
                'user_id' => $u->id,
                'check_date' => $d,
                'gross_pay' => 1000,
                'net_pay' => 100,
                'federal_tax' => 0,
                'state_tax' => 0,
                'social_security' => 0,
                'medicare' => 0,
                'retirement_401k' => 0,
                'health_insurance' => 0,
                'other_deductions' => 0,
                'commission_subtotal' => 0,
                'salary_subtotal' => 0,
            ]);
        }
        $p = $this->svc()->getProjection($u->id, 2026);
        $this->assertFalse($p['projectionSuppressed']);
        $this->assertSame('2600.0000', $p['projectedAnnual']);
    }

    public function test_aggregate_sums_across_all_owners(): void
    {
        $a = User::factory()->create(['role' => 'account_manager']);
        $b = User::factory()->create(['role' => 'account_manager']);
        PayrollRecord::query()->create([
            'user_id' => $a->id,
            'check_date' => '2026-03-01',
            'gross_pay' => 1000,
            'net_pay' => 500,
            'federal_tax' => 10,
            'state_tax' => 0,
            'social_security' => 0,
            'medicare' => 0,
            'retirement_401k' => 0,
            'health_insurance' => 0,
            'other_deductions' => 0,
            'commission_subtotal' => 0,
            'salary_subtotal' => 0,
        ]);
        PayrollRecord::query()->create([
            'user_id' => $b->id,
            'check_date' => '2026-03-01',
            'gross_pay' => 2000,
            'net_pay' => 300,
            'federal_tax' => 20,
            'state_tax' => 0,
            'social_security' => 0,
            'medicare' => 0,
            'retirement_401k' => 0,
            'health_insurance' => 0,
            'other_deductions' => 0,
            'commission_subtotal' => 0,
            'salary_subtotal' => 0,
        ]);
        $agg = $this->svc()->getAggregateSummary(2026);
        $this->assertSame('800.0000', $agg['ytd_net']);
        $this->assertSame('3000.0000', $agg['ytd_gross']);
    }

    public function test_per_am_breakdown_includes_all_account_managers(): void
    {
        User::factory()->create(['role' => 'account_manager', 'name' => 'Zed']);
        User::factory()->create(['role' => 'account_manager', 'name' => 'Amy']);
        $rows = $this->svc()->getPerAmBreakdown(2026);
        $this->assertCount(2, $rows);
    }

    public function test_per_am_breakdown_shows_zero_for_empty_am(): void
    {
        $a = User::factory()->create(['role' => 'account_manager', 'name' => 'Has Data']);
        User::factory()->create(['role' => 'account_manager', 'name' => 'No Data']);
        PayrollRecord::query()->create([
            'user_id' => $a->id,
            'check_date' => '2026-01-01',
            'gross_pay' => 100,
            'net_pay' => 50,
            'federal_tax' => 0,
            'state_tax' => 0,
            'social_security' => 0,
            'medicare' => 0,
            'retirement_401k' => 0,
            'health_insurance' => 0,
            'other_deductions' => 0,
            'commission_subtotal' => 0,
            'salary_subtotal' => 0,
        ]);
        $rows = $this->svc()->getPerAmBreakdown(2026);
        $empty = collect($rows)->first(fn ($r) => $r['name'] === 'No Data');
        $this->assertNotNull($empty);
        $this->assertSame('0.0000', $empty['ytd_net']);
    }

    public function test_aggregate_handles_zero_total_without_division_error(): void
    {
        User::factory()->create(['role' => 'account_manager']);
        $rows = $this->svc()->getPerAmBreakdown(2026);
        $this->assertIsArray($rows);
        foreach ($rows as $r) {
            $this->assertArrayHasKey('pct_of_total_net', $r);
        }
    }

    public function test_consultant_pct_of_total_sums_to_100(): void
    {
        $u = User::factory()->create(['role' => 'account_manager']);
        PayrollConsultantEntry::query()->create([
            'user_id' => $u->id,
            'consultant_name' => 'A',
            'year' => 2026,
            'revenue' => 40,
            'cost' => 0,
            'margin' => 40,
            'pct_of_total' => 40,
            'consultant_id' => null,
        ]);
        PayrollConsultantEntry::query()->create([
            'user_id' => $u->id,
            'consultant_name' => 'B',
            'year' => 2026,
            'revenue' => 60,
            'cost' => 0,
            'margin' => 60,
            'pct_of_total' => 60,
            'consultant_id' => null,
        ]);
        $list = $this->svc()->getConsultants($u->id, 2026);
        $sum = array_sum(array_map(fn ($c) => (float) $c['pct_of_total'], $list));
        $this->assertEqualsWithDelta(100.0, $sum, 0.01);
    }
}
