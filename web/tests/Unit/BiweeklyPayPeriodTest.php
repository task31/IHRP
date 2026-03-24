<?php

namespace Tests\Unit;

use App\Support\BiweeklyPayPeriod;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class BiweeklyPayPeriodTest extends TestCase
{
    public function test_anchor_week_period_zero(): void
    {
        [$start, $end] = BiweeklyPayPeriod::suggestedPeriodContaining('2020-01-06');

        $this->assertSame('2020-01-06', $start);
        $this->assertSame('2020-01-19', $end);
    }

    public function test_mid_period_same_window(): void
    {
        [$start, $end] = BiweeklyPayPeriod::suggestedPeriodContaining('2020-01-15');

        $this->assertSame('2020-01-06', $start);
        $this->assertSame('2020-01-19', $end);
    }

    public function test_next_period_after_boundary(): void
    {
        [$start, $end] = BiweeklyPayPeriod::suggestedPeriodContaining('2020-01-20');

        $this->assertSame('2020-01-20', $start);
        $this->assertSame('2020-02-02', $end);
    }

    public function test_accepts_carbon(): void
    {
        [$start, $end] = BiweeklyPayPeriod::suggestedPeriodContaining(Carbon::parse('2026-03-24'));

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $start);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $end);
        $this->assertEqualsWithDelta(13.0, (float) Carbon::parse($start)->diffInDays(Carbon::parse($end)), 0.001);
    }
}
