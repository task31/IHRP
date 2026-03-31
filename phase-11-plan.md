# Phase 11 Plan — Payroll Semantics: Fix Missing Bill-Rate Revenue Fallback
_Created: 2026-03-30_
_Mode: SEQUENTIAL_

> **For agentic workers:** Read CLAUDE.md and BUSINESS_MODEL.md before writing any code.

**Goal:** Fix two identical semantics bugs where a missing `bill_rate` causes `revenue` to be set to `am_earnings` instead of `0.0000`, making the payroll dashboard silently overstate revenue.

**Architecture:** One-line fix in each of two methods in `PayrollController.php`, followed by two feature tests that assert the corrected behavior. No schema changes. No new routes. No model changes.

**Tech Stack:** Laravel 13, PHP 8.3, PHPUnit, PhpSpreadsheet (for test fixture)

---

## Context

`BUSINESS_MODEL.md` defines:
- `revenue` = `hours × bill_rate` — requires `bill_rate` on the consultant record
- When `bill_rate` is missing: `revenue = 0.0000`, `margin = 0.0000`

Both `PayrollController::upload()` and `PayrollController::recomputeMargins()` have an
`else` branch that runs when `bill_rate` is null:

```php
// CURRENT — WRONG
} else {
    $revenue = $amEarnings;   // am_earnings is cost, not revenue
    $margin  = '0.0000';
}

// CORRECT
} else {
    $revenue = '0.0000';
    $margin  = '0.0000';
}
```

`am_earnings` is the AM's cut of the spread — it is a cost to MPG, not revenue. Setting
`revenue = am_earnings` makes the dashboard show a plausible-looking number that is
semantically wrong. Entries without a bill_rate should surface as `revenue = 0` so the
missing rate is visible, not hidden.

## Dependency
- Requires Phase 10 complete ✅
- Unlocks: clean foundation for any future payroll semantics work

---

## To-Dos

### Task 1 — Write the two failing tests first

File: `web/tests/Feature/PayrollControllerTest.php`

Add two new test methods at the bottom of the class (before the closing `}`).

- [Phase 11] **Write** `test_upload_missing_bill_rate_stores_zero_revenue()`:

```php
public function test_upload_missing_bill_rate_stores_zero_revenue(): void
{
    // Consultant exists but has no bill_rate
    $consultant = Consultant::query()->create([
        'full_name' => 'Alice Adams',
        'active'    => true,
        'bill_rate' => null,
        'pay_rate'  => null,
    ]);

    $admin = User::factory()->create(['role' => 'admin']);
    $am    = User::factory()->create(['role' => 'account_manager']);

    // Pre-create mapping so the upload finds the consultant
    PayrollConsultantMapping::query()->create([
        'raw_name'       => 'Alice Adams',
        'user_id'        => $am->id,
        'consultant_id'  => $consultant->id,
        'created_by'     => $admin->id,
    ]);

    $file = $this->uploadedPayrollFile(withAlice: true);
    $this->actingAs($admin)
        ->post(route('payroll.upload'), [
            'file'      => $file,
            'user_id'   => $am->id,
            'stop_name' => 'Rafael',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $entry = PayrollConsultantEntry::query()
        ->where('user_id', $am->id)
        ->where('consultant_name', 'Alice Adams')
        ->first();

    $this->assertNotNull($entry, 'Consultant entry should exist after upload');
    $this->assertSame('0.0000', $entry->revenue,
        'revenue must be 0.0000 when bill_rate is missing — not am_earnings');
    $this->assertSame('0.0000', $entry->margin,
        'margin must be 0.0000 when bill_rate is missing');
}
```

- [Phase 11] **Write** `test_recompute_margins_missing_bill_rate_leaves_revenue_zero()`:

```php
public function test_recompute_margins_missing_bill_rate_leaves_revenue_zero(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $am    = User::factory()->create(['role' => 'account_manager']);

    // Consultant with no bill_rate
    $consultant = Consultant::query()->create([
        'full_name' => 'Bob Builder',
        'active'    => true,
        'bill_rate' => null,
        'pay_rate'  => null,
    ]);

    // Entry pre-seeded with wrong revenue (simulates old corrupted state)
    PayrollConsultantEntry::query()->create([
        'user_id'         => $am->id,
        'consultant_name' => 'Bob Builder',
        'consultant_id'   => $consultant->id,
        'year'            => 2026,
        'hours'           => '10.0000',
        'spread_per_hour' => '5.0000',
        'commission_pct'  => '0.50000000',
        'am_earnings'     => '250.0000',
        'revenue'         => '250.0000',   // wrong — was set to am_earnings
        'cost'            => '250.0000',
        'margin'          => '0.0000',
        'pct_of_total'    => '100.0000',
    ]);

    $this->actingAs($admin)
        ->postJson(route('payroll.recompute.margins'), ['user_id' => $am->id])
        ->assertOk()
        ->assertJsonPath('success', true);

    $entry = PayrollConsultantEntry::query()
        ->where('user_id', $am->id)
        ->where('consultant_name', 'Bob Builder')
        ->first();

    $this->assertNotNull($entry);
    $this->assertSame('0.0000', $entry->revenue,
        'revenue must be 0.0000 after recompute when bill_rate is still missing');
    $this->assertSame('0.0000', $entry->margin,
        'margin must be 0.0000 after recompute when bill_rate is still missing');
}
```

- [Phase 11] **Run** `php artisan test --filter "test_upload_missing_bill_rate_stores_zero_revenue|test_recompute_margins_missing_bill_rate_leaves_revenue_zero"` from `web/`
  - Expected: **2 FAILED** (revenue is currently set to am_earnings, not 0.0000)
  - If they pass at this point → stop and re-check the test assertions match the bug

---

### Task 2 — Fix `upload()` fallback

File: `web/app/Http/Controllers/PayrollController.php`
Method: `upload()`, around line 151

- [Phase 11] **Find** this exact block in `upload()`:

```php
            } else {
                $revenue = $amEarnings;
                $margin  = '0.0000';
            }
```

- [Phase 11] **Replace** with:

```php
            } else {
                $revenue = '0.0000';
                $margin  = '0.0000';
            }
```

That is the only change in this method. Do not touch any other line.

---

### Task 3 — Fix `recomputeMargins()` fallback

File: `web/app/Http/Controllers/PayrollController.php`
Method: `recomputeMargins()`, around line 488

- [Phase 11] **Find** this exact block in `recomputeMargins()`:

```php
                    } else {
                        $revenue = $amEarnings;
                        $margin  = '0.0000';
                    }
```

- [Phase 11] **Replace** with:

```php
                    } else {
                        $revenue = '0.0000';
                        $margin  = '0.0000';
                    }
```

That is the only change in this method. Do not touch any other line.

---

### Task 4 — Run full test suite

- [Phase 11] Run `php artisan test` from `web/`
  - Expected: **162 passed** (160 existing + 2 new), **0 failures**
  - If only 160 pass → the two new tests are still failing; re-check that the edits landed in the correct methods
  - If any previously-passing test breaks → the wrong line was changed; revert and re-read the context

---

### Task 5 — Commit

- [Phase 11] Stage and commit only the two changed files:

```bash
git add web/app/Http/Controllers/PayrollController.php \
        web/tests/Feature/PayrollControllerTest.php
git commit -m "fix(payroll): missing bill_rate yields revenue=0 not am_earnings (Phase 11)

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```

- [Phase 11] Write a `[BUILD — Cursor]` block to `DEVLOG.md` in the standard format.

---

## Acceptance Criteria

- [ ] `PayrollController::upload()` `else` branch sets `$revenue = '0.0000'`
- [ ] `PayrollController::recomputeMargins()` `else` branch sets `$revenue = '0.0000'`
- [ ] `test_upload_missing_bill_rate_stores_zero_revenue` passes: entry.revenue = '0.0000'
- [ ] `test_recompute_margins_missing_bill_rate_leaves_revenue_zero` passes: entry.revenue = '0.0000'
- [ ] `php artisan test` — **162 passed**, 0 failures

## Files Planned

- `web/app/Http/Controllers/PayrollController.php` (2 one-line edits)
- `web/tests/Feature/PayrollControllerTest.php` (2 new test methods appended)
