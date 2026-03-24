<?php

namespace Tests\Unit;

use App\Support\PayPeriodFormatter;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PayPeriodFormatterTest extends TestCase
{
    public function test_same_year_omits_year_on_start(): void
    {
        $s = PayPeriodFormatter::formatRange('2026-03-09', '2026-03-13');

        $this->assertSame('Mar 9 – Mar 13, 2026', $s);
    }

    #[DataProvider('spanningYearsProvider')]
    public function test_spanning_years_includes_both_years(string $start, string $end, string $expected): void
    {
        $this->assertSame($expected, PayPeriodFormatter::formatRange($start, $end));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function spanningYearsProvider(): array
    {
        return [
            'dec_to_jan' => ['2025-12-22', '2026-01-04', 'Dec 22, 2025 – Jan 4, 2026'],
        ];
    }

    public function test_accepts_carbon_instances(): void
    {
        $s = PayPeriodFormatter::formatRange(
            Carbon::parse('2026-03-09'),
            Carbon::parse('2026-03-13')
        );

        $this->assertSame('Mar 9 – Mar 13, 2026', $s);
    }

    public function test_empty_inputs_return_empty_string(): void
    {
        $this->assertSame('', PayPeriodFormatter::formatRange(null, '2026-01-01'));
        $this->assertSame('', PayPeriodFormatter::formatRange('2026-01-01', null));
        $this->assertSame('', PayPeriodFormatter::formatRange('', ''));
    }
}
