<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Port of Payroll Electron overtime.js — state overtime rules engine.
 */
final class OvertimeCalculator
{
    /** Nevada min wage as of 2025 (NRS §608.018) */
    public const NV_MIN_WAGE = 12.0;

    /** 1.5× state min wage — daily OT threshold for NV */
    public const NV_DAILY_OT_WAGE_CEILING = 18.0; // NV_MIN_WAGE * 1.5

    /**
     * 51 entries: 50 US states + PR (+ DC) = 52 keys total per listAllStateRules().
     *
     * @var array<string, array<string, mixed>>
     */
    private const STATE_RULES = [
        'AL' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'AZ' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'AR' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'CT' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'DE' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'FL' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'GA' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'HI' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'ID' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'IL' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => '820 ILCS 140'],
        'IN' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'IA' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'LA' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'ME' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => '26 MRS §663'],
        'MA' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'MGL c.151 §1A'],
        'MI' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'MCL §408.384'],
        'MS' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'MO' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'MT' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'NE' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'NH' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'RSA §279:21'],
        'NJ' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'NJSA §34:11-56a4'],
        'NM' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'NC' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'ND' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'OH' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'ORC §4111.03'],
        'OK' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'PA' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'PA MWA §4'],
        'SC' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'SD' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'TN' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'TX' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'UT' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'VT' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => '21 VSA §384'],
        'VA' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'VA Code §40.1-29.2'],
        'WV' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'WI' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'WY' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'FLSA'],
        'WA' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'RCW §49.46.130'],
        'DC' => ['category' => 'FEDERAL', 'weeklyThreshold' => 40, 'cite' => 'D.C. Code §32-1003(c)'],
        'KS' => [
            'category' => 'FEDERAL', 'weeklyThreshold' => 40, 'stateThreshold' => 46,
            'cite' => 'KSA §44-1204',
            'label' => 'FLSA Weekly Only (KS — FLSA overrides state 46-hr threshold)',
        ],
        'MN' => [
            'category' => 'FEDERAL', 'weeklyThreshold' => 40, 'stateThreshold' => 48,
            'cite' => 'MN Stat. §177.25',
            'label' => 'FLSA Weekly Only (MN — FLSA overrides state 48-hr threshold)',
        ],
        'AK' => ['category' => 'DAILY', 'weeklyThreshold' => 40, 'dailyThreshold' => 8, 'cite' => 'Alaska Stat. §23.10.060'],
        'CA' => ['category' => 'CA', 'weeklyThreshold' => 40, 'dailyThreshold' => 8, 'doubleTimeThreshold' => 12, 'cite' => 'CA Labor Code §510'],
        'CO' => ['category' => 'DAILY', 'weeklyThreshold' => 40, 'dailyThreshold' => 12, 'cite' => '7 CCR 1103-1'],
        'NV' => ['category' => 'DAILY_CONDITIONAL', 'weeklyThreshold' => 40, 'dailyThreshold' => 8, 'wageThreshold' => self::NV_DAILY_OT_WAGE_CEILING, 'cite' => 'NRS §608.018'],
        'PR' => ['category' => 'DAILY_PR', 'weeklyThreshold' => 40, 'dailyThreshold' => 8, 'hireDateCutoff' => '2017-01-26', 'cite' => 'PR Labor Relations Act'],
        'KY' => ['category' => 'SPECIAL_KY', 'weeklyThreshold' => 40, 'cite' => 'KRS §337.285'],
        'MD' => ['category' => 'SPECIAL_MD', 'weeklyThreshold' => 40, 'agWeeklyThreshold' => 60, 'cite' => 'MD Labor & Empl. §3-415'],
        'NY' => ['category' => 'SPECIAL_NY', 'weeklyThreshold' => 40, 'farmWeeklyThreshold' => 52, 'cite' => 'NY Labor Law §651'],
        'OR' => ['category' => 'SPECIAL_OR', 'weeklyThreshold' => 40, 'mfgDailyThreshold' => 10, 'timberDailyThreshold' => 8, 'cite' => 'ORS §652.020'],
        'RI' => ['category' => 'SPECIAL_RI', 'weeklyThreshold' => 40, 'mfgDailyThreshold' => 8, 'cite' => 'RIGL §28-12-4.1'],
    ];

    private static function r(float $n): float
    {
        return round($n, 2);
    }

    /**
     * @param  list<float|int>  $hoursPerDay
     * @return list<array{day: int, hours: float, regularHrs: float, otHrs: float, doubleTimeHrs: float}>
     */
    private static function buildBreakdown(array $hoursPerDay): array
    {
        $out = [];
        foreach ($hoursPerDay as $i => $hours) {
            $h = (float) $hours;
            $out[] = [
                'day' => (int) $i,
                'hours' => $h,
                'regularHrs' => 0.0,
                'otHrs' => 0.0,
                'doubleTimeHrs' => 0.0,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{day: int, hours: float, regularHrs: float, otHrs: float, doubleTimeHrs: float}>  $breakdown
     */
    private static function allocateWeeklyOT(array &$breakdown, float $count): void
    {
        $toAllocate = $count;
        for ($i = count($breakdown) - 1; $i >= 0 && $toAllocate > 0; $i--) {
            $transfer = min($breakdown[$i]['regularHrs'], $toAllocate);
            $breakdown[$i]['regularHrs'] = self::r($breakdown[$i]['regularHrs'] - $transfer);
            $breakdown[$i]['otHrs'] = self::r($breakdown[$i]['otHrs'] + $transfer);
            $toAllocate -= $transfer;
        }
    }

    /**
     * @param  list<float|int>  $hoursPerDay
     * @return array{regularHours: float, otHours: float, doubleTimeHours: float, breakdown: list<array<string, float|int>>}
     */
    private static function calcFederal(array $hoursPerDay): array
    {
        $weeklyTotal = array_sum($hoursPerDay);
        $regularHours = min($weeklyTotal, 40);
        $otHours = max(0, $weeklyTotal - 40);
        $breakdown = self::buildBreakdown($hoursPerDay);

        $regRemain = $regularHours;
        $otRemain = $otHours;
        foreach ($breakdown as &$day) {
            $h = $day['hours'];
            $dayReg = min($h, $regRemain);
            $dayOt = min($h - $dayReg, $otRemain);
            $day['regularHrs'] = self::r($dayReg);
            $day['otHrs'] = self::r($dayOt);
            $regRemain -= $dayReg;
            $otRemain -= $dayOt;
        }
        unset($day);

        return [
            'regularHours' => self::r($regularHours),
            'otHours' => self::r($otHours),
            'doubleTimeHours' => 0.0,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @param  list<float|int>  $hoursPerDay
     * @return array{regularHours: float, otHours: float, doubleTimeHours: float, breakdown: list<array<string, float|int>>}
     */
    private static function calcDailyNoDoubleTime(array $hoursPerDay, float $dailyThreshold): array
    {
        $weeklyTotal = array_sum($hoursPerDay);
        $weeklyOTCount = max(0, $weeklyTotal - 40);
        $weeklyOTStart = 40;
        $breakdown = self::buildBreakdown($hoursPerDay);

        $sumDailyOT = 0.0;
        $alreadyElevated = 0.0;
        $cumHour = 0.0;

        for ($i = 0; $i < count($hoursPerDay); $i++) {
            $h = (float) $hoursPerDay[$i];
            if ($h > 0) {
                $dayReg = min($h, $dailyThreshold);
                $dayOt = max(0, $h - $dailyThreshold);
                $breakdown[$i]['regularHrs'] = self::r($dayReg);
                $breakdown[$i]['otHrs'] = self::r($dayOt);
                $sumDailyOT += $dayOt;

                if ($dayOt > 0) {
                    $otStart = $cumHour + $dayReg;
                    $otEnd = $cumHour + $h;
                    $ovS = max($otStart, $weeklyOTStart);
                    $ovE = min($otEnd, $weeklyTotal);
                    if ($ovE > $ovS) {
                        $alreadyElevated += $ovE - $ovS;
                    }
                }
            }
            $cumHour += $h;
        }

        $additionalWeeklyOT = $weeklyOTCount - $alreadyElevated;
        if ($additionalWeeklyOT > 0) {
            self::allocateWeeklyOT($breakdown, $additionalWeeklyOT);
        }

        $totalOT = $sumDailyOT + $additionalWeeklyOT;
        $regularHours = $weeklyTotal - $totalOT;

        return [
            'regularHours' => self::r($regularHours),
            'otHours' => self::r($totalOT),
            'doubleTimeHours' => 0.0,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @param  list<float|int>  $hoursPerDay
     * @return array{regularHours: float, otHours: float, doubleTimeHours: float, breakdown: list<array<string, float|int>>}
     */
    private static function calcCalifornia(array $hoursPerDay): array
    {
        $allDaysWorked = count($hoursPerDay) === 7 && ! in_array(false, array_map(fn ($h) => (float) $h > 0, $hoursPerDay), true);
        $weeklyTotal = array_sum($hoursPerDay);
        $weeklyOTCount = max(0, $weeklyTotal - 40);
        $weeklyOTStart = 40;
        $breakdown = self::buildBreakdown($hoursPerDay);

        $totalDailyOT = 0.0;
        $totalDailyDT = 0.0;
        $alreadyElevated = 0.0;
        $cumHour = 0.0;

        for ($i = 0; $i < count($hoursPerDay); $i++) {
            $h = (float) $hoursPerDay[$i];
            $isSeventhDay = ($i === 6) && $allDaysWorked;

            if ($h === 0.0) {
                $cumHour += $h;

                continue;
            }

            if ($isSeventhDay) {
                $reg = 0.0;
                $ot = min($h, 8);
                $dt = max(0, $h - 8);
            } else {
                $reg = min($h, 8);
                $ot = min(max(0, $h - 8), 4);
                $dt = max(0, $h - 12);
            }

            $breakdown[$i]['regularHrs'] = self::r($reg);
            $breakdown[$i]['otHrs'] = self::r($ot);
            $breakdown[$i]['doubleTimeHrs'] = self::r($dt);
            $totalDailyOT += $ot;
            $totalDailyDT += $dt;

            if ($ot > 0) {
                $otStart = $cumHour + $reg;
                $otEnd = $otStart + $ot;
                $ovS = max($otStart, $weeklyOTStart);
                $ovE = min($otEnd, $weeklyTotal);
                if ($ovE > $ovS) {
                    $alreadyElevated += $ovE - $ovS;
                }
            }
            if ($dt > 0) {
                $dtStart = $cumHour + $reg + $ot;
                $dtEnd = $dtStart + $dt;
                $ovS = max($dtStart, $weeklyOTStart);
                $ovE = min($dtEnd, $weeklyTotal);
                if ($ovE > $ovS) {
                    $alreadyElevated += $ovE - $ovS;
                }
            }

            $cumHour += $h;
        }

        $additionalWeeklyOT = $weeklyOTCount - $alreadyElevated;
        if ($additionalWeeklyOT > 0) {
            self::allocateWeeklyOT($breakdown, $additionalWeeklyOT);
        }
        $totalDailyOT += $additionalWeeklyOT;

        $regularHours = $weeklyTotal - $totalDailyOT - $totalDailyDT;

        return [
            'regularHours' => self::r($regularHours),
            'otHours' => self::r($totalDailyOT),
            'doubleTimeHours' => self::r($totalDailyDT),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @param  list<float|int>  $hoursPerDay
     * @return array{regularHours: float, otHours: float, doubleTimeHours: float, breakdown: list<array<string, float|int>>, nevadaDailyOTEligible?: bool}
     */
    private static function calcNevada(array $hoursPerDay, float $regularRate): array
    {
        if ($regularRate >= self::NV_DAILY_OT_WAGE_CEILING) {
            $f = self::calcFederal($hoursPerDay);
            $f['nevadaDailyOTEligible'] = false;

            return $f;
        }
        $d = self::calcDailyNoDoubleTime($hoursPerDay, 8);
        $d['nevadaDailyOTEligible'] = true;

        return $d;
    }

    /**
     * @param  list<float|int>  $hoursPerDay
     * @return array{regularHours: float, otHours: float, doubleTimeHours: float, breakdown: list<array<string, float|int>>}
     */
    private static function calcKentucky(array $hoursPerDay): array
    {
        $allDaysWorked = count($hoursPerDay) === 7 && ! in_array(false, array_map(fn ($h) => (float) $h > 0, $hoursPerDay), true);
        $weeklyTotal = array_sum($hoursPerDay);
        $f = self::calcFederal($hoursPerDay);
        $regularHours = $f['regularHours'];
        $otHours = $f['otHours'];
        $breakdown = $f['breakdown'];

        if ($allDaysWorked && (float) $hoursPerDay[6] > 0) {
            $seventhDayHours = (float) $hoursPerDay[6];
            $weeklyExcess = max(0, $weeklyTotal - 40);
            $sevenDayInRegular = max(0, $seventhDayHours - $weeklyExcess);

            if ($sevenDayInRegular > 0) {
                $regularHours = self::r($regularHours - $sevenDayInRegular);
                $otHours = self::r($otHours + $sevenDayInRegular);
                $day6 = &$breakdown[6];
                $transfer = min($day6['regularHrs'], $sevenDayInRegular);
                $day6['regularHrs'] = self::r($day6['regularHrs'] - $transfer);
                $day6['otHrs'] = self::r($day6['otHrs'] + $transfer);
                unset($day6);
            }
        }

        return [
            'regularHours' => $regularHours,
            'otHours' => $otHours,
            'doubleTimeHours' => 0.0,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @param  list<float|int>  $hoursPerDay
     * @return array{regularHours: float, otHours: float, doubleTimeHours: float, breakdown: list<array<string, float|int>>}
     */
    private static function calcOregon(array $hoursPerDay, string $industry): array
    {
        $n = strtolower($industry);
        if (in_array($n, ['mill', 'factory', 'manufacturing'], true)) {
            return self::calcDailyNoDoubleTime($hoursPerDay, 10);
        }
        if ($n === 'timber') {
            return self::calcDailyNoDoubleTime($hoursPerDay, 8);
        }

        return self::calcFederal($hoursPerDay);
    }

    /**
     * @param  list<float|int>  $hoursPerDay
     * @return array{regularHours: float, otHours: float, doubleTimeHours: float, breakdown: list<array<string, float|int>>}
     */
    private static function calcRhodeIsland(array $hoursPerDay, string $industry): array
    {
        $n = strtolower($industry);
        if ($n === 'manufacturing') {
            return self::calcDailyNoDoubleTime($hoursPerDay, 8);
        }

        return self::calcFederal($hoursPerDay);
    }

    /**
     * @param  list<float|int>  $hoursPerDay
     * @return array{regularHours: float, otHours: float, doubleTimeHours: float, breakdown: list<array<string, float|int>>}
     */
    private static function calcMaryland(array $hoursPerDay, string $industry): array
    {
        $n = strtolower($industry);
        $threshold = $n === 'agriculture' ? 60 : 40;
        $weeklyTotal = array_sum($hoursPerDay);
        $regularHours = min($weeklyTotal, $threshold);
        $otHours = max(0, $weeklyTotal - $threshold);
        $breakdown = self::buildBreakdown($hoursPerDay);

        $regRemain = $regularHours;
        $otRemain = $otHours;
        foreach ($breakdown as &$day) {
            $h = $day['hours'];
            $dayReg = min($h, $regRemain);
            $dayOt = min($h - $dayReg, $otRemain);
            $day['regularHrs'] = self::r($dayReg);
            $day['otHrs'] = self::r($dayOt);
            $regRemain -= $dayReg;
            $otRemain -= $dayOt;
        }
        unset($day);

        return [
            'regularHours' => self::r($regularHours),
            'otHours' => self::r($otHours),
            'doubleTimeHours' => 0.0,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @param  list<float|int>  $hoursPerDay
     * @return array{regularHours: float, otHours: float, doubleTimeHours: float, breakdown: list<array<string, float|int>>}
     */
    private static function calcNewYork(array $hoursPerDay, string $industry): array
    {
        $n = strtolower($industry);
        $threshold = ($n === 'farm' || $n === 'agriculture') ? 52 : 40;
        $weeklyTotal = array_sum($hoursPerDay);
        $regularHours = min($weeklyTotal, $threshold);
        $otHours = max(0, $weeklyTotal - $threshold);
        $breakdown = self::buildBreakdown($hoursPerDay);

        $regRemain = $regularHours;
        $otRemain = $otHours;
        foreach ($breakdown as &$day) {
            $h = $day['hours'];
            $dayReg = min($h, $regRemain);
            $dayOt = min($h - $dayReg, $otRemain);
            $day['regularHrs'] = self::r($dayReg);
            $day['otHrs'] = self::r($dayOt);
            $regRemain -= $dayReg;
            $otRemain -= $dayOt;
        }
        unset($day);

        return [
            'regularHours' => self::r($regularHours),
            'otHours' => self::r($otHours),
            'doubleTimeHours' => 0.0,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @param  list<float|int>  $hoursPerDay
     * @return array{regularHours: float, otHours: float, doubleTimeHours: float, breakdown: list<array<string, float|int>>, flagForReview?: bool, reviewReason?: string}
     */
    private static function calcPuertoRico(array $hoursPerDay, ?string $hireDate): array
    {
        $cutoff = strtotime('2017-01-26');
        if ($hireDate !== null && $hireDate !== '' && strtotime($hireDate) < $cutoff) {
            $f = self::calcFederal($hoursPerDay);
            $f['flagForReview'] = true;
            $f['reviewReason'] = 'PR pre-2017 hire — verify applicable pre-reform rules manually';

            return $f;
        }

        return self::calcDailyNoDoubleTime($hoursPerDay, 8);
    }

    private static function getLabel(
        string $stateUpper,
        string $category,
        string $industry,
        ?string $hireDate,
        ?bool $nevadaEligible
    ): string {
        $rule = self::STATE_RULES[$stateUpper] ?? null;
        if ($rule !== null && isset($rule['label'])) {
            return (string) $rule['label'];
        }

        $n = strtolower($industry);
        switch ($category) {
            case 'FEDERAL':
                return 'FLSA Weekly Only';
            case 'DAILY':
                return "Daily + Weekly OT ({$stateUpper})";
            case 'CA':
                return 'CA Daily + Weekly + Double-Time (CA Labor Code §510)';
            case 'DAILY_CONDITIONAL':
                return $nevadaEligible
                    ? 'NV Daily + Weekly (rate below $'.self::NV_DAILY_OT_WAGE_CEILING.'/hr threshold)'
                    : 'NV Weekly Only (rate at/above $'.self::NV_DAILY_OT_WAGE_CEILING.'/hr threshold)';
            case 'DAILY_PR':
                if ($hireDate !== null && $hireDate !== '' && strtotime($hireDate) < strtotime('2017-01-26')) {
                    return 'PR Legacy Hire — Flag for Review';
                }

                return $hireDate
                    ? 'PR Daily + Weekly (post-Jan-2017 hire)'
                    : 'PR Daily + Weekly (hire date unknown — defaulting to post-2017)';
            case 'SPECIAL_KY':
                return 'FLSA Weekly + KY 7th Day Rule';
            case 'SPECIAL_MD':
                return $n === 'agriculture' ? 'MD Agriculture 60-hr Weekly' : 'FLSA Weekly Only (MD)';
            case 'SPECIAL_NY':
                return ($n === 'farm' || $n === 'agriculture') ? 'NY Farm Workers 52-hr Weekly' : 'FLSA Weekly Only (NY)';
            case 'SPECIAL_OR':
                if (in_array($n, ['mill', 'factory', 'manufacturing'], true)) {
                    return 'OR Daily + Weekly (manufacturing/mill/factory)';
                }
                if ($n === 'timber') {
                    return 'OR Daily + Weekly (timber)';
                }

                return 'FLSA Weekly Only (OR)';
            case 'SPECIAL_RI':
                return $n === 'manufacturing' ? 'RI Daily + Weekly (manufacturing)' : 'FLSA Weekly Only (RI)';
            default:
                return "OT Rule ({$stateUpper})";
        }
    }

    /**
     * @param  array{state: mixed, hoursPerDay: mixed, regularRate: mixed}  $params
     */
    private static function validate(array $params): void
    {
        $state = $params['state'] ?? null;
        $hoursPerDay = $params['hoursPerDay'] ?? null;
        $regularRate = $params['regularRate'] ?? null;

        if (! is_string($state) || $state === '') {
            throw new InvalidArgumentException('state is required (2-letter abbreviation)');
        }
        $stateUpper = strtoupper($state);
        if (! isset(self::STATE_RULES[$stateUpper])) {
            throw new InvalidArgumentException('Unknown state code: "'.$state.'". Must be a 2-letter US state abbreviation or PR.');
        }
        if (! is_array($hoursPerDay) || count($hoursPerDay) !== 7) {
            throw new InvalidArgumentException('hoursPerDay must be an array of exactly 7 numbers');
        }
        foreach ($hoursPerDay as $h) {
            if (((! is_int($h)) && (! is_float($h))) || ! is_finite((float) $h) || (float) $h < 0) {
                throw new InvalidArgumentException('Each value in hoursPerDay must be a non-negative finite number');
            }
        }
        if (((! is_int($regularRate)) && (! is_float($regularRate))) || ! is_finite((float) $regularRate) || (float) $regularRate <= 0) {
            throw new InvalidArgumentException('regularRate must be a positive number');
        }
    }

    /**
     * @param  array{
     *     state: string,
     *     industry?: string,
     *     hoursPerDay: list<float|int>,
     *     regularRate: float|int,
     *     billRate?: float|int|null,
     *     hireDate?: string|null
     * }  $params
     * @return array<string, mixed>
     */
    public static function calculateOvertimePay(array $params): array
    {
        self::validate($params);

        $stateUpper = strtoupper($params['state']);
        $industry = isset($params['industry']) ? (string) $params['industry'] : 'general';
        $hoursPerDay = array_map(fn ($h) => (float) $h, $params['hoursPerDay']);
        $regularRate = (float) $params['regularRate'];
        $billRate = $params['billRate'] ?? null;
        $hireDate = array_key_exists('hireDate', $params) ? $params['hireDate'] : null;
        if ($hireDate !== null && ! is_string($hireDate)) {
            $hireDate = null;
        }

        $rule = self::STATE_RULES[$stateUpper];
        $category = (string) $rule['category'];

        $calc = null;
        $nevadaEligible = null;

        switch ($category) {
            case 'FEDERAL':
                $calc = self::calcFederal($hoursPerDay);
                break;
            case 'DAILY':
                $calc = self::calcDailyNoDoubleTime($hoursPerDay, (float) $rule['dailyThreshold']);
                break;
            case 'CA':
                $calc = self::calcCalifornia($hoursPerDay);
                break;
            case 'DAILY_CONDITIONAL':
                $calc = self::calcNevada($hoursPerDay, $regularRate);
                $nevadaEligible = $calc['nevadaDailyOTEligible'] ?? null;
                break;
            case 'DAILY_PR':
                $calc = self::calcPuertoRico($hoursPerDay, $hireDate);
                break;
            case 'SPECIAL_KY':
                $calc = self::calcKentucky($hoursPerDay);
                break;
            case 'SPECIAL_MD':
                $calc = self::calcMaryland($hoursPerDay, $industry);
                break;
            case 'SPECIAL_NY':
                $calc = self::calcNewYork($hoursPerDay, $industry);
                break;
            case 'SPECIAL_OR':
                $calc = self::calcOregon($hoursPerDay, $industry);
                break;
            case 'SPECIAL_RI':
                $calc = self::calcRhodeIsland($hoursPerDay, $industry);
                break;
            default:
                $calc = self::calcFederal($hoursPerDay);
        }

        $regularHours = $calc['regularHours'];
        $otHours = $calc['otHours'];
        $doubleTimeHours = $calc['doubleTimeHours'];
        $breakdown = $calc['breakdown'];
        $flagForReview = $calc['flagForReview'] ?? false;
        $reviewReason = $calc['reviewReason'] ?? null;

        $regularPay = self::r($regularHours * $regularRate);
        $otPay = self::r($otHours * $regularRate * 1.5);
        $doubleTimePay = self::r($doubleTimeHours * $regularRate * 2.0);
        $totalConsultantCost = self::r($regularPay + $otPay + $doubleTimePay);

        $effectiveBillRate = (is_numeric($billRate) && (float) $billRate > 0) ? (float) $billRate : $regularRate;
        $regularBillable = self::r($regularHours * $effectiveBillRate);
        $otBillable = self::r($otHours * $effectiveBillRate * 1.5);
        $doubleTimeBillable = self::r($doubleTimeHours * $effectiveBillRate * 2.0);
        $totalClientBillable = self::r($regularBillable + $otBillable + $doubleTimeBillable);

        $result = [
            'regularHours' => $regularHours,
            'otHours' => $otHours,
            'doubleTimeHours' => $doubleTimeHours,
            'regularPay' => $regularPay,
            'otPay' => $otPay,
            'doubleTimePay' => $doubleTimePay,
            'totalConsultantCost' => $totalConsultantCost,
            'regularBillable' => $regularBillable,
            'otBillable' => $otBillable,
            'doubleTimeBillable' => $doubleTimeBillable,
            'totalClientBillable' => $totalClientBillable,
            'otRuleApplied' => self::getLabel($stateUpper, $category, $industry, $hireDate, $nevadaEligible),
            'nevadaDailyOTEligible' => $nevadaEligible,
            'breakdown' => $breakdown,
        ];

        if ($flagForReview) {
            $result['flagForReview'] = true;
            $result['reviewReason'] = $reviewReason;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getStateRule(?string $state): ?array
    {
        if ($state === null || $state === '') {
            return null;
        }
        $upper = strtoupper($state);

        return self::STATE_RULES[$upper] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listAllStateRules(): array
    {
        $out = [];
        foreach (self::STATE_RULES as $state => $rule) {
            $out[] = array_merge(['state' => $state], $rule);
        }

        return $out;
    }
}
