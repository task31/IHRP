# Phase 7b Plan — True Margin in Payroll Consultant Breakdown

_Status: In Progress_
_Architect: Chat Pane | Agent: backend-dev_

---

## Goal

Replace the incorrect "Gross Earned" (consultant pay ≈ cost) with real
Revenue / Cost / Margin using bill_rate from the consultants table.
The parse service already extracts `hours` per consultant per period — we just aren't
storing or using it. This fixes that.

---

## Formula

| Field | Formula | Source |
|---|---|---|
| Revenue | hours × consultant.bill_rate | parse hours + consultants table |
| Cost | gross_pay from payroll Excel | parse result (currently stored as `revenue`) |
| Margin | revenue − cost | computed |
| Fallback (no mapping or no bill_rate) | revenue = cost = gross_pay, margin = 0 | existing behavior |

pct_of_total is recomputed per year based on the new revenue values (not gross_pay).

---

## Todos

- [Phase 7b] Add `hours` column migration to payroll_consultant_entries
- [Phase 7b] Recompute revenue/cost/margin/pct_of_total in PayrollController::upload()
- [Phase 7b] Update PayrollDataService::getConsultants() to return revenue/cost/margin
- [Phase 7b] Update payroll blade — table + KPI cards + JS refs
- [Phase 7b] Run test suite (107 must pass)

---

## Implementation Notes

### [Phase 7b] Add `hours` column migration

Create `database/migrations/2026_03_23_100000_add_hours_to_payroll_consultant_entries.php`

```php
Schema::table('payroll_consultant_entries', function (Blueprint $table) {
    $table->decimal('hours', 12, 4)->default(0)->after('year');
});
```

down() drops the column.

---

### [Phase 7b] PayrollController::upload() — recompute rows

In `PayrollController.php`, the `upload()` method has a DB transaction block that loops
over `$result->consultantRows` and calls `PayrollConsultantEntry::query()->create(...)`.

**Before** the transaction, add this logic to build `$computedRows`:

```php
// Load bill_rates for all mapped consultants in this upload
$consultantBillRates = [];
foreach ($result->consultantRows as $row) {
    $mapping = PayrollConsultantMapping::query()
        ->where('raw_name', $row['name'])
        ->where('user_id', $targetUser->id)
        ->first();
    if ($mapping && $mapping->consultant_id) {
        $c = \App\Models\Consultant::query()->find($mapping->consultant_id);
        if ($c && $c->bill_rate !== null) {
            $consultantBillRates[$row['name']] = (string) $c->bill_rate;
        }
    }
}

// Compute true revenue / cost / margin per row, grouped by year for pct recalc
$rowsByYear = [];
foreach ($result->consultantRows as $row) {
    $hours   = $row['hours'] ?? '0.0000';
    $grossPay = $row['revenue']; // parse service stores gross_pay as 'revenue'
    $billRate = $consultantBillRates[$row['name']] ?? null;

    if ($billRate !== null && bccomp($hours, '0', 4) > 0) {
        $revenue = bcmul($hours, $billRate, 4);
        $cost    = $grossPay;
        $margin  = bcsub($revenue, $cost, 4);
    } else {
        $revenue = $grossPay;
        $cost    = $grossPay;
        $margin  = '0.0000';
    }

    $rowsByYear[(int) $row['year']][] = array_merge($row, [
        'hours'   => $hours,
        'revenue' => $revenue,
        'cost'    => $cost,
        'margin'  => $margin,
    ]);
}

// Recompute pct_of_total per year based on new revenue
$computedRows = [];
foreach ($rowsByYear as $yr => $rows) {
    $grandRevenue = array_reduce($rows, fn ($c, $r) => bcadd($c, $r['revenue'], 4), '0.0000');
    foreach ($rows as $r) {
        $pct = '0.0000';
        if (bccomp($grandRevenue, '0', 4) > 0) {
            $pct = bcmul(bcdiv($r['revenue'], $grandRevenue, 8), '100', 4);
        }
        $computedRows[] = array_merge($r, ['pct_of_total' => $pct]);
    }
}
```

**Inside** the transaction, change the loop from `$result->consultantRows` to `$computedRows`
and add `'hours' => $row['hours']` to the `create()` call:

```php
foreach ($computedRows as $row) {
    $mapping = PayrollConsultantMapping::query()
        ->where('raw_name', $row['name'])
        ->where('user_id', $targetUser->id)
        ->first();
    PayrollConsultantEntry::query()->create([
        'user_id'        => $targetUser->id,
        'consultant_name'=> $row['name'],
        'year'           => $row['year'],
        'hours'          => $row['hours'],
        'revenue'        => $row['revenue'],
        'cost'           => $row['cost'],
        'margin'         => $row['margin'],
        'pct_of_total'   => $row['pct_of_total'],
        'consultant_id'  => $mapping?->consultant_id,
    ]);
}
```

Note: the `PayrollConsultantMapping` lookup inside the transaction can be removed since
the mapping was already resolved above. But it's fine to keep it for the consultant_id.

---

### [Phase 7b] PayrollDataService::getConsultants()

Update the method to:
1. Compute `$totalMargin` alongside `$grandTotal`
2. Return `revenue`, `cost`, `margin`, `hours` per consultant (rename `total_gross` → `revenue`)
3. Add `total_margin` and `total_revenue` to the meta

Updated method:

```php
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

    $grandRevenue = '0.0000';
    $grandMargin  = '0.0000';
    foreach ($rows as $row) {
        $grandRevenue = $this->bcAdd($grandRevenue, (string) $row->revenue);
        $grandMargin  = $this->bcAdd($grandMargin,  (string) $row->margin);
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
        $hasMargin = bccomp((string) $row->hours, '0', 4) > 0;
        $consultants[] = [
            'name'           => $row->consultant_name,
            'consultant_id'  => $row->consultant_id,
            'revenue'        => $this->money($row->revenue),
            'cost'           => $this->money($row->cost),
            'margin'         => $hasMargin ? $this->money($row->margin) : null,
            'hours'          => $this->money($row->hours),
            'periods_active' => $periodCount,
            'tier'           => $tier,
            'pct_of_total'   => $pct,
        ];
    }

    return [
        'consultants'    => $consultants,
        'total_periods'  => $periodCount,
        'total_revenue'  => $this->bcAdd($grandRevenue, '0'),
        'total_margin'   => $this->bcAdd($grandMargin, '0'),
        'top_earner'     => $topEarner,
    ];
}
```

Note: `total_paid_out` is renamed to `total_revenue`. The blade JS must also be updated.

---

### [Phase 7b] Payroll blade — payroll/index.blade.php

**KPI cards** (currently 3 cards: Active Consultants, Total Paid Out, Top Earner):
Change to 4 cards, changing `grid-cols-3` to `grid-cols-4`:
- Active Consultants (keep)
- Total Revenue (was "Total Paid Out" — update label + `consultantMeta?.total_revenue`)
- Total Margin (new — `consultantMeta?.total_margin`)
- Top Earner (keep)

**Table headers** — change from:
`Consultant | Tier | Gross Earned | % of Total | Periods`
to:
`Consultant | Tier | Revenue | Cost | Margin | % of Total`
(drop Periods — it's the same number for every row)

**Table rows** — change the data cells:
- Remove the `total_gross` cell
- Add Revenue cell: `x-text="fmtMoney(c.revenue)"`
- Add Cost cell: `x-text="fmtMoney(c.cost)"`
- Add Margin cell: `x-text="c.margin !== null ? fmtMoney(c.margin) : '—'"` with color:
  - If margin is null: gray `#94a3b8`
  - If positive: green `#22c55e`
  - If zero or negative: default white

**JS** — in the Alpine `loadConsultants()` / fetch response handler (around line 682):
Change:
```js
this.consultantMeta = { total_paid_out: data.total_paid_out, top_earner: data.top_earner, total_periods: data.total_periods };
```
to:
```js
this.consultantMeta = { total_revenue: data.total_revenue, total_margin: data.total_margin, top_earner: data.top_earner, total_periods: data.total_periods };
```

---

### [Phase 7b] Tests

The main tests to update:

**PayrollDataServiceTest**: Any test calling `getConsultants()` that asserts on
`total_paid_out` — rename to `total_revenue`. Any test asserting on `total_gross`
in the consultants array — rename to `revenue`.

**PayrollControllerTest**: Any test checking the `apiConsultants` JSON response fields —
update field names accordingly. If any test checks that `cost` or `margin` are 0
(because no mapping), confirm the fallback logic is correct.

---

## Files to Create/Modify

| File | Action |
|---|---|
| `database/migrations/2026_03_23_100000_add_hours_to_payroll_consultant_entries.php` | CREATE |
| `app/Http/Controllers/PayrollController.php` | MODIFY — recompute rows in upload() |
| `app/Services/PayrollDataService.php` | MODIFY — getConsultants() return shape |
| `resources/views/payroll/index.blade.php` | MODIFY — KPI cards + table columns + JS refs |
| `tests/Unit/PayrollDataServiceTest.php` | MODIFY — field name updates |
| `tests/Feature/PayrollControllerTest.php` | MODIFY — field name updates |
