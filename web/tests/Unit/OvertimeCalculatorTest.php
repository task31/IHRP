<?php

namespace Tests\Unit;

use App\Services\OvertimeCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Port of Payroll src/main/ipc/overtime.test.js
 */
class OvertimeCalculatorTest extends TestCase
{
    private function assertMoney(string $label, float $actual, float $expected, float $tol = 0.01): void
    {
        $this->assertEqualsWithDelta($expected, $actual, $tol, $label);
    }

    // ─── FEDERAL ─────────────────────────────────────────────────────────────

    public function test_federal_pure_40_hrs_tx_25(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'TX',
            'hoursPerDay' => [8, 8, 8, 8, 8, 0, 0],
            'regularRate' => 25,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 40);
        $this->assertMoney('otHours', $r['otHours'], 0);
        $this->assertMoney('totalConsultantCost', $r['totalConsultantCost'], 1000);
    }

    public function test_federal_5_hrs_ot_fl_20(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'FL',
            'hoursPerDay' => [9, 9, 9, 9, 9, 0, 0],
            'regularRate' => 20,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 40);
        $this->assertMoney('otHours', $r['otHours'], 5);
        $this->assertMoney('totalConsultantCost', $r['totalConsultantCost'], 950);
    }

    public function test_federal_ks_treated_as_40_not_46(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'KS',
            'hoursPerDay' => [9, 9, 9, 9, 5, 0, 0],
            'regularRate' => 30,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 40);
        $this->assertMoney('otHours', $r['otHours'], 1);
        $this->assertStringContainsString('KS', $r['otRuleApplied'], 'label contains KS');
    }

    public function test_federal_mn_treated_as_40_not_48(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'MN',
            'hoursPerDay' => [8, 8, 8, 8, 8, 0, 0],
            'regularRate' => 25,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 40);
        $this->assertMoney('otHours', $r['otHours'], 0);
        $this->assertStringContainsString('MN', $r['otRuleApplied'], 'label contains MN');
    }

    // ─── ALASKA ──────────────────────────────────────────────────────────────

    public function test_ak_daily_ot_10_8_8_8_6(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'AK',
            'hoursPerDay' => [10, 8, 8, 8, 6, 0, 0],
            'regularRate' => 30,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 38);
        $this->assertMoney('otHours', $r['otHours'], 2);
        $this->assertMoney('totalConsultantCost', $r['totalConsultantCost'], 1230);
    }

    // ─── COLORADO ────────────────────────────────────────────────────────────

    public function test_co_daily_gt_12_plus_weekly_ot_no_double_count(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'CO',
            'hoursPerDay' => [13, 8, 8, 8, 8, 0, 0],
            'regularRate' => 25,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 39);
        $this->assertMoney('otHours', $r['otHours'], 6);
    }

    public function test_co_no_double_time_14_hrs_single_day(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'CO',
            'hoursPerDay' => [14, 0, 0, 0, 0, 0, 0],
            'regularRate' => 25,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 12);
        $this->assertMoney('otHours', $r['otHours'], 2);
        $this->assertMoney('doubleTimeHours', $r['doubleTimeHours'], 0);
    }

    // ─── CALIFORNIA ───────────────────────────────────────────────────────────

    public function test_ca_daily_10_plus_2_extra_weekly(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'CA',
            'hoursPerDay' => [10, 8, 8, 8, 8, 0, 0],
            'regularRate' => 30,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 38);
        $this->assertMoney('otHours', $r['otHours'], 4);
        $this->assertMoney('doubleTimeHours', $r['doubleTimeHours'], 0);
        $this->assertMoney('totalConsultantCost', $r['totalConsultantCost'], 1320);
    }

    public function test_ca_daily_gt_12_dt(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'CA',
            'hoursPerDay' => [14, 8, 8, 8, 8, 0, 0],
            'regularRate' => 30,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 34);
        $this->assertMoney('otHours', $r['otHours'], 10);
        $this->assertMoney('doubleTimeHours', $r['doubleTimeHours'], 2);
        $this->assertMoney('total hrs', $r['regularHours'] + $r['otHours'] + $r['doubleTimeHours'], 46);
    }

    public function test_ca_7th_day_rule_lt_8_all_6s(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'CA',
            'hoursPerDay' => [6, 6, 6, 6, 6, 6, 6],
            'regularRate' => 25,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 36);
        $this->assertMoney('otHours', $r['otHours'], 6);
        $this->assertMoney('doubleTimeHours', $r['doubleTimeHours'], 0);
    }

    public function test_ca_7th_day_rule_gt_8(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'CA',
            'hoursPerDay' => [8, 6, 6, 6, 6, 6, 10],
            'regularRate' => 20,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 38);
        $this->assertMoney('otHours', $r['otHours'], 8);
        $this->assertMoney('doubleTimeHours', $r['doubleTimeHours'], 2);
        $this->assertMoney('total hrs', $r['regularHours'] + $r['otHours'] + $r['doubleTimeHours'], 48);
    }

    public function test_ca_7th_day_does_not_fire_when_one_day_zero(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'CA',
            'hoursPerDay' => [8, 8, 8, 8, 8, 0, 8],
            'regularRate' => 20,
        ]);
        $this->assertMoney('doubleTimeHours', $r['doubleTimeHours'], 0);
    }

    // ─── NEVADA ──────────────────────────────────────────────────────────────

    public function test_nv_rate_gte_18_federal_only(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'NV',
            'hoursPerDay' => [10, 8, 8, 8, 8, 0, 0],
            'regularRate' => 20,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 40);
        $this->assertMoney('otHours', $r['otHours'], 2);
        $this->assertFalse($r['nevadaDailyOTEligible'], 'nevadaDailyOTEligible');
    }

    public function test_nv_rate_lt_18_daily_ot_fires(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'NV',
            'hoursPerDay' => [10, 8, 8, 8, 8, 0, 0],
            'regularRate' => 15,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 38);
        $this->assertMoney('otHours', $r['otHours'], 4);
        $this->assertTrue($r['nevadaDailyOTEligible'], 'nevadaDailyOTEligible');
    }

    // ─── KENTUCKY ────────────────────────────────────────────────────────────

    public function test_ky_7th_day_wk_lte_40(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'KY',
            'hoursPerDay' => [6, 6, 6, 6, 6, 6, 6],
            'regularRate' => 25,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 36);
        $this->assertMoney('otHours', $r['otHours'], 6);
        $this->assertMoney('doubleTimeHours', $r['doubleTimeHours'], 0);
    }

    public function test_ky_7th_day_wk_gt_40(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'KY',
            'hoursPerDay' => [7, 7, 7, 7, 7, 7, 7],
            'regularRate' => 25,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 40);
        $this->assertMoney('otHours', $r['otHours'], 9);
    }

    public function test_ky_7th_day_does_not_fire_day6_zero(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'KY',
            'hoursPerDay' => [8, 8, 8, 8, 8, 0, 8],
            'regularRate' => 25,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 40);
        $this->assertMoney('otHours', $r['otHours'], 8);
    }

    // ─── OREGON ──────────────────────────────────────────────────────────────

    public function test_or_general_flsa(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'OR',
            'industry' => 'general',
            'hoursPerDay' => [11, 8, 8, 8, 8, 0, 0],
            'regularRate' => 22,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 40);
        $this->assertMoney('otHours', $r['otHours'], 3);
    }

    public function test_or_manufacturing_threshold_10(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'OR',
            'industry' => 'manufacturing',
            'hoursPerDay' => [11, 8, 8, 8, 8, 0, 0],
            'regularRate' => 22,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 39);
        $this->assertMoney('otHours', $r['otHours'], 4);
    }

    public function test_or_timber_threshold_8(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'OR',
            'industry' => 'timber',
            'hoursPerDay' => [10, 8, 8, 8, 8, 0, 0],
            'regularRate' => 22,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 38);
        $this->assertMoney('otHours', $r['otHours'], 4);
    }

    public function test_or_factory_same_as_manufacturing(): void
    {
        $a = OvertimeCalculator::calculateOvertimePay([
            'state' => 'OR',
            'industry' => 'factory',
            'hoursPerDay' => [11, 8, 8, 8, 8, 0, 0],
            'regularRate' => 22,
        ]);
        $b = OvertimeCalculator::calculateOvertimePay([
            'state' => 'OR',
            'industry' => 'manufacturing',
            'hoursPerDay' => [11, 8, 8, 8, 8, 0, 0],
            'regularRate' => 22,
        ]);
        $this->assertEquals($b['otHours'], $a['otHours'], 'factory === manufacturing otHours');
    }

    // ─── RHODE ISLAND ────────────────────────────────────────────────────────

    public function test_ri_general_flsa(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'RI',
            'industry' => 'general',
            'hoursPerDay' => [10, 8, 8, 8, 8, 0, 0],
            'regularRate' => 20,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 40);
        $this->assertMoney('otHours', $r['otHours'], 2);
    }

    public function test_ri_manufacturing_threshold_8(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'RI',
            'industry' => 'manufacturing',
            'hoursPerDay' => [10, 8, 8, 8, 8, 0, 0],
            'regularRate' => 20,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 38);
        $this->assertMoney('otHours', $r['otHours'], 4);
    }

    // ─── MARYLAND ────────────────────────────────────────────────────────────

    public function test_md_standard(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'MD',
            'industry' => 'general',
            'hoursPerDay' => [9, 9, 9, 9, 9, 0, 0],
            'regularRate' => 25,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 40);
        $this->assertMoney('otHours', $r['otHours'], 5);
    }

    public function test_md_agriculture_60_hr(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'MD',
            'industry' => 'agriculture',
            'hoursPerDay' => [10, 10, 10, 10, 10, 10, 10],
            'regularRate' => 20,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 60);
        $this->assertMoney('otHours', $r['otHours'], 10);
    }

    // ─── NEW YORK ────────────────────────────────────────────────────────────

    public function test_ny_standard(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'NY',
            'industry' => 'general',
            'hoursPerDay' => [9, 9, 9, 9, 9, 0, 0],
            'regularRate' => 28,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 40);
        $this->assertMoney('otHours', $r['otHours'], 5);
    }

    public function test_ny_farm_52_hr(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'NY',
            'industry' => 'farm',
            'hoursPerDay' => [10, 10, 10, 10, 10, 10, 0],
            'regularRate' => 18,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 52);
        $this->assertMoney('otHours', $r['otHours'], 8);
    }

    public function test_ny_agriculture_same_as_farm(): void
    {
        $a = OvertimeCalculator::calculateOvertimePay([
            'state' => 'NY',
            'industry' => 'agriculture',
            'hoursPerDay' => [10, 10, 10, 10, 10, 10, 0],
            'regularRate' => 18,
        ]);
        $this->assertMoney('regularHours', $a['regularHours'], 52);
    }

    // ─── PUERTO RICO ─────────────────────────────────────────────────────────

    public function test_pr_post_2017_hire(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'PR',
            'hoursPerDay' => [10, 8, 8, 8, 8, 0, 0],
            'regularRate' => 18,
            'hireDate' => '2020-01-15',
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 38);
        $this->assertMoney('otHours', $r['otHours'], 4);
    }

    public function test_pr_null_hire_date_defaults_post_cutoff(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'PR',
            'hoursPerDay' => [10, 8, 8, 8, 8, 0, 0],
            'regularRate' => 18,
            'hireDate' => null,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 38);
        $this->assertMoney('otHours', $r['otHours'], 4);
        $this->assertArrayNotHasKey('flagForReview', $r, 'no flagForReview');
    }

    public function test_pr_pre_2017_flsa_fallback_flag(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'PR',
            'hoursPerDay' => [10, 8, 8, 8, 8, 0, 0],
            'regularRate' => 18,
            'hireDate' => '2010-06-01',
        ]);
        $this->assertMoney('regularHours (FLSA fallback)', $r['regularHours'], 40);
        $this->assertMoney('otHours', $r['otHours'], 2);
        $this->assertTrue($r['flagForReview'], 'flagForReview');
        $this->assertIsString($r['reviewReason'], 'reviewReason set');
    }

    // ─── PAY CALCULATIONS ────────────────────────────────────────────────────

    public function test_pay_calc_flsa_5_ot_bill_rate(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'TX',
            'hoursPerDay' => [9, 9, 9, 9, 9, 0, 0],
            'regularRate' => 20,
            'billRate' => 35,
        ]);
        $this->assertMoney('regularPay', $r['regularPay'], 800);
        $this->assertMoney('otPay', $r['otPay'], 150);
        $this->assertMoney('totalConsultantCost', $r['totalConsultantCost'], 950);
        $this->assertMoney('regularBillable', $r['regularBillable'], 1400);
        $this->assertMoney('otBillable', $r['otBillable'], 262.50);
        $this->assertMoney('totalClientBillable', $r['totalClientBillable'], 1662.50);
    }

    public function test_pay_calc_ca_dt(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'CA',
            'hoursPerDay' => [14, 0, 0, 0, 0, 0, 0],
            'regularRate' => 30,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 8);
        $this->assertMoney('otHours', $r['otHours'], 4);
        $this->assertMoney('doubleTimeHours', $r['doubleTimeHours'], 2);
        $this->assertMoney('regularPay', $r['regularPay'], 240);
        $this->assertMoney('otPay', $r['otPay'], 180);
        $this->assertMoney('doubleTimePay', $r['doubleTimePay'], 120);
        $this->assertMoney('totalConsultantCost', $r['totalConsultantCost'], 540);
    }

    // ─── BREAKDOWN ARRAY ───────────────────────────────────────────────────

    public function test_breakdown_has_7_entries(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'TX',
            'hoursPerDay' => [8, 8, 8, 8, 8, 0, 0],
            'regularRate' => 25,
        ]);
        $this->assertCount(7, $r['breakdown'], 'breakdown length');
        $this->assertEquals(0, $r['breakdown'][5]['hours'], 'breakdown[5].hours');
    }

    public function test_breakdown_hours_sum_matches_input(): void
    {
        $hoursPerDay = [9, 8, 7, 10, 6, 0, 0];
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'CA',
            'hoursPerDay' => $hoursPerDay,
            'regularRate' => 25,
        ]);
        $breakdownTotal = 0.0;
        foreach ($r['breakdown'] as $d) {
            $breakdownTotal += $d['regularHrs'] + $d['otHrs'] + $d['doubleTimeHrs'];
        }
        $this->assertMoney('breakdown sum == weeklyTotal', $breakdownTotal, 40);
    }

    // ─── INPUT VALIDATION ───────────────────────────────────────────────────

    public function test_validation_unknown_state_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OvertimeCalculator::calculateOvertimePay([
            'state' => 'XX',
            'hoursPerDay' => [8, 8, 8, 8, 8, 0, 0],
            'regularRate' => 20,
        ]);
    }

    public function test_validation_wrong_hours_length_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OvertimeCalculator::calculateOvertimePay([
            'state' => 'TX',
            'hoursPerDay' => [8, 8, 8],
            'regularRate' => 20,
        ]);
    }

    public function test_validation_negative_hours_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OvertimeCalculator::calculateOvertimePay([
            'state' => 'TX',
            'hoursPerDay' => [8, -1, 8, 8, 8, 0, 0],
            'regularRate' => 20,
        ]);
    }

    // ─── getStateRule & listAllStateRules ────────────────────────────────────

    public function test_get_state_rule_returns_correct_object(): void
    {
        $rule = OvertimeCalculator::getStateRule('CA');
        $this->assertNotNull($rule);
        $this->assertEquals('CA', $rule['category'], 'CA category');
        $this->assertEquals(8, $rule['dailyThreshold'], 'CA dailyThreshold');
        $this->assertEquals(12, $rule['doubleTimeThreshold'], 'CA doubleTimeThreshold');
    }

    public function test_get_state_rule_case_insensitive(): void
    {
        $r1 = OvertimeCalculator::getStateRule('tx');
        $r2 = OvertimeCalculator::getStateRule('TX');
        $this->assertNotNull($r1);
        $this->assertNotNull($r2);
        $this->assertEquals($r2['category'], $r1['category'], 'lower === upper');
    }

    public function test_get_state_rule_unknown_returns_null(): void
    {
        $this->assertNull(OvertimeCalculator::getStateRule('ZZ'), 'null for bad state');
    }

    public function test_list_all_state_rules_count_and_keys(): void
    {
        $rules = OvertimeCalculator::listAllStateRules();
        $this->assertCount(52, $rules, 'count');
        $states = array_column($rules, 'state');
        $this->assertContains('PR', $states, 'includes PR');
        $this->assertContains('CA', $states, 'includes CA');
        $this->assertContains('TX', $states, 'includes TX');
        $this->assertContains('DC', $states, 'includes DC');
    }

    public function test_dc_weekly_ot_only(): void
    {
        $r = OvertimeCalculator::calculateOvertimePay([
            'state' => 'DC',
            'hoursPerDay' => [10, 8, 8, 8, 8, 0, 0],
            'regularRate' => 25,
        ]);
        $this->assertMoney('regularHours', $r['regularHours'], 40);
        $this->assertMoney('otHours', $r['otHours'], 2);
        $this->assertMoney('doubleTimeHours', $r['doubleTimeHours'], 0);
        $this->assertMoney('totalConsultantCost', $r['totalConsultantCost'], 1075);
        $dc = OvertimeCalculator::getStateRule('DC');
        $this->assertNotNull($dc);
        $this->assertEquals('D.C. Code §32-1003(c)', $dc['cite'], 'cite');
    }

    // ─── BI-WEEKLY SPLIT ─────────────────────────────────────────────────────

    public function test_biweekly_split_tx(): void
    {
        $w1 = OvertimeCalculator::calculateOvertimePay([
            'state' => 'TX',
            'hoursPerDay' => [9, 9, 9, 9, 9, 0, 0],
            'regularRate' => 20,
        ]);
        $w2 = OvertimeCalculator::calculateOvertimePay([
            'state' => 'TX',
            'hoursPerDay' => [7, 7, 7, 7, 7, 0, 0],
            'regularRate' => 20,
        ]);
        $this->assertMoney('w1 otHours', $w1['otHours'], 5);
        $this->assertMoney('w2 otHours', $w2['otHours'], 0);
        $totalOT = $w1['otHours'] + $w2['otHours'];
        $this->assertMoney('total OT = 5 (not 5 from combined 80-hr pool)', $totalOT, 5);
        $this->assertMoney('combined pay', $w1['totalConsultantCost'] + $w2['totalConsultantCost'], 1650);
    }
}
