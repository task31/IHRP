<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class PayPeriodFormatter
{
    /**
     * Human-readable pay period, e.g. "Mar 9 – Mar 13, 2026" (same year) or
     * "Dec 22, 2025 – Jan 4, 2026" (spanning years).
     */
    public static function formatRange(mixed $start, mixed $end): string
    {
        if ($start === null || $end === null || $start === '' || $end === '') {
            return '';
        }

        $s = $start instanceof CarbonInterface
            ? Carbon::instance($start)->startOfDay()
            : Carbon::parse((string) $start)->startOfDay();
        $e = $end instanceof CarbonInterface
            ? Carbon::instance($end)->startOfDay()
            : Carbon::parse((string) $end)->startOfDay();

        $sep = ' – ';

        if ($s->year === $e->year) {
            return $s->format('M j').$sep.$e->format('M j, Y');
        }

        return $s->format('M j, Y').$sep.$e->format('M j, Y');
    }
}
