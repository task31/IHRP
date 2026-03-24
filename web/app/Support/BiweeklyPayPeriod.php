<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Fixed Monday-start biweekly pay periods for timesheet template defaults.
 * Anchor is adjustable if payroll changes the schedule (see TASKLIST / business).
 */
final class BiweeklyPayPeriod
{
    /** Monday (Y-m-d) — first day of pay period index 0. */
    public const ANCHOR_MONDAY = '2020-01-06';

    /**
     * @return array{0: string, 1: string} Pay period [start, end] as Y-m-d (inclusive 14 days).
     */
    public static function suggestedPeriodContaining(CarbonInterface|\DateTimeInterface|string $date): array
    {
        $anchor = Carbon::parse(self::ANCHOR_MONDAY)->startOfDay();
        $d = $date instanceof CarbonInterface
            ? Carbon::instance($date)->copy()->startOfDay()
            : Carbon::parse($date)->startOfDay();

        if ($d->lt($anchor)) {
            $start = $anchor->copy();
        } else {
            $days = (int) $anchor->diffInDays($d, false);
            $periodIndex = intdiv($days, 14);
            $start = $anchor->copy()->addDays($periodIndex * 14);
        }

        $end = $start->copy()->addDays(13);

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }
}
