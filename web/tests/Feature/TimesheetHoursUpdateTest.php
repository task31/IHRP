<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Consultant;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimesheetHoursUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function seedTimesheetFixture(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::query()->create([
            'name' => 'Acme Corp',
            'payment_terms' => 'Net 30',
            'active' => true,
        ]);
        $consultant = Consultant::query()->create([
            'full_name' => 'Jane Consultant',
            'pay_rate' => 50,
            'bill_rate' => 100,
            'state' => 'TX',
            'industry_type' => 'other',
            'client_id' => $client->id,
            'active' => true,
        ]);

        $this->actingAs($admin)->postJson(route('timesheets.save'), [
            'rows' => [[
                'consultantId' => $consultant->id,
                'week1Hours' => array_fill(0, 7, 8),
                'week2Hours' => array_fill(0, 7, 8),
                'payPeriodStart' => '2026-01-05',
                'payPeriodEnd' => '2026-01-18',
                'overwrite' => false,
            ]],
        ])->assertOk();

        $ts = Timesheet::query()->where('consultant_id', $consultant->id)->firstOrFail();

        return [$admin, $ts];
    }

    public function test_account_manager_cannot_patch_hours(): void
    {
        $am = User::factory()->create(['role' => 'account_manager']);
        [, $ts] = $this->seedTimesheetFixture();

        $this->actingAs($am)->patchJson(route('timesheets.update-hours', $ts->id), [
            'week1' => array_fill(0, 7, 4),
            'week2' => array_fill(0, 7, 4),
        ])->assertForbidden();
    }

    public function test_admin_can_patch_hours_and_totals_change(): void
    {
        [$admin, $ts] = $this->seedTimesheetFixture();
        $beforeReg = (float) $ts->total_regular_hours;

        $response = $this->actingAs($admin)->patchJson(route('timesheets.update-hours', $ts->id), [
            'week1' => [0, 0, 0, 0, 0, 0, 40],
            'week2' => array_fill(0, 7, 0),
        ]);

        $response->assertOk();
        $response->assertJsonPath('consultant_name', 'Jane Consultant');
        $this->assertNotSame($beforeReg, (float) $response->json('total_regular_hours'));

        $ts->refresh();
        $this->assertSame((float) $response->json('total_regular_hours'), (float) $ts->total_regular_hours);
        $this->assertCount(14, Timesheet::query()->find($ts->id)->dailyHours);
    }

    public function test_blocked_when_invoice_linked(): void
    {
        [$admin, $ts] = $this->seedTimesheetFixture();
        $ts->update(['invoice_id' => 999]);

        $this->actingAs($admin)->patchJson(route('timesheets.update-hours', $ts->id), [
            'week1' => array_fill(0, 7, 1),
            'week2' => array_fill(0, 7, 1),
        ])->assertStatus(422);
    }
}
