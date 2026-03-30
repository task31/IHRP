<?php

namespace App\Console\Commands;

use App\Models\Consultant;
use App\Models\PayrollConsultantEntry;
use App\Models\User;
use App\Services\AppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Ops/maintenance CLI — mirrors PayrollController::recomputeMargins() without HTTP session.
 * Bypasses web authorize('admin'); run only in trusted environments (SSH/scheduled ops).
 */
class RecomputeAmMargins extends Command
{
    protected $signature = 'payroll:recompute-am {user_id : users.id of the account manager}';

    protected $description = 'Recompute payroll revenue, margin, and pct_of_total for one AM (and derive consultant pay_rate where null).';

    public function handle(): int
    {
        $userId = (int) $this->argument('user_id');
        $target = User::query()->find($userId);

        if ($target === null) {
            $this->error("User {$userId} not found.");

            return self::FAILURE;
        }

        if ($target->role !== 'account_manager') {
            $this->error('Target user must be an account manager.');

            return self::FAILURE;
        }

        $entries = PayrollConsultantEntry::query()
            ->where('user_id', $target->id)
            ->get();

        $billRatesById = [];
        $consultantIds = $entries->pluck('consultant_id')->filter()->unique()->values();
        if ($consultantIds->isNotEmpty()) {
            Consultant::query()
                ->whereIn('id', $consultantIds)
                ->whereNotNull('bill_rate')
                ->get(['id', 'bill_rate'])
                ->each(function ($c) use (&$billRatesById) {
                    $billRatesById[$c->id] = (string) $c->bill_rate;
                });
        }

        $byYear = [];
        foreach ($entries as $entry) {
            $byYear[$entry->year][] = $entry;
        }

        $updated = 0;
        DB::transaction(function () use ($byYear, $billRatesById, &$updated) {
            foreach ($byYear as $yearEntries) {
                $computed = [];
                foreach ($yearEntries as $entry) {
                    $hours         = (string) $entry->hours;
                    $amEarnings    = (string) $entry->am_earnings;
                    $spreadPerHour = (string) $entry->spread_per_hour;
                    $billRate      = isset($entry->consultant_id) ? ($billRatesById[$entry->consultant_id] ?? null) : null;

                    if ($billRate !== null && bccomp($hours, '0', 4) > 0) {
                        $revenue = bcmul($hours, $billRate, 4);
                        $margin  = bccomp($amEarnings, '0', 4) > 0
                            ? bcsub($revenue, $amEarnings, 4)
                            : '0.0000';

                        if (bccomp($spreadPerHour, '0', 4) > 0 && $entry->consultant_id) {
                            $payRate = bcsub($billRate, $spreadPerHour, 4);
                            Consultant::query()
                                ->where('id', $entry->consultant_id)
                                ->whereNull('pay_rate')
                                ->update(['pay_rate' => $payRate]);
                        }
                    } else {
                        $revenue = $amEarnings;
                        $margin  = '0.0000';
                    }

                    $computed[] = ['entry' => $entry, 'revenue' => $revenue, 'margin' => $margin];
                }

                $grandRevenue = array_reduce($computed, fn ($c, $r) => bcadd($c, $r['revenue'], 4), '0.0000');
                foreach ($computed as $item) {
                    $pct = '0.0000';
                    if (bccomp($grandRevenue, '0', 4) > 0) {
                        $pct = bcmul(bcdiv($item['revenue'], $grandRevenue, 8), '100', 4);
                    }
                    $item['entry']->update([
                        'revenue'      => $item['revenue'],
                        'margin'       => $item['margin'],
                        'pct_of_total' => $pct,
                    ]);
                    $updated++;
                }
            }
        });

        $years = $entries->pluck('year')->unique()->values();
        foreach ($years as $yr) {
            Cache::forget("payroll_dashboard_{$target->id}_{$yr}");
            Cache::forget("payroll_aggregate_{$yr}");
        }

        $recomputeDescription = sprintf(
            'Recompute margins for %s (user_id=%d): %d consultant entry row(s) updated (revenue/margin/pct)',
            $target->name,
            $target->id,
            $updated,
        );

        AppService::auditLog('payroll_consultant_entries', 0, 'RECOMPUTE_MARGINS', [], [
            'target_am_id' => $target->id,
            'entries_updated' => $updated,
        ], $recomputeDescription);

        $this->info("Updated {$updated} entries for {$target->name}");

        return self::SUCCESS;
    }
}
