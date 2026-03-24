<?php

namespace Tests\Unit;

use App\Support\ReportMoney;
use PHPUnit\Framework\TestCase;

class ReportMoneyTest extends TestCase
{
    public function test_formats_with_commas_and_cents(): void
    {
        $this->assertSame('$2,565.00', ReportMoney::usd('2565'));
        $this->assertSame('$2,565.00', ReportMoney::usd('2565.0000'));
        $this->assertSame('$2,565.01', ReportMoney::usd('2565.005'));
    }

    public function test_null_and_empty_are_zero(): void
    {
        $this->assertSame('$0.00', ReportMoney::usd(null));
        $this->assertSame('$0.00', ReportMoney::usd(''));
    }

    public function test_non_numeric_string_is_zero(): void
    {
        $this->assertSame('$0.00', ReportMoney::usd('n/a'));
    }
}
