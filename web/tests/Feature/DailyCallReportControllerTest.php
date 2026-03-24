<?php

namespace Tests\Feature;

use App\Models\DailyCallReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyCallReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_index_defaults_to_last_30_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24 12:00:00', 'UTC'));

        $user = User::factory()->create(['role' => 'account_manager']);
        DailyCallReport::query()->create([
            'user_id' => $user->id,
            'report_date' => '2026-02-10',
            'calls_made' => 1,
            'contacts_reached' => 0,
            'submittals' => 0,
            'interviews_scheduled' => 0,
            'notes' => null,
        ]);
        DailyCallReport::query()->create([
            'user_id' => $user->id,
            'report_date' => '2026-02-24',
            'calls_made' => 2,
            'contacts_reached' => 0,
            'submittals' => 0,
            'interviews_scheduled' => 0,
            'notes' => null,
        ]);

        $response = $this->actingAs($user)->get(route('calls.index'));

        $response->assertOk();
        $response->assertViewHas('historyPeriod', '30');
        $reports = $response->viewData('reports');
        $this->assertCount(1, $reports);
        $this->assertSame(2, (int) $reports->first()->calls_made);
    }

    public function test_index_period_all_includes_outside_default_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24 12:00:00', 'UTC'));

        $user = User::factory()->create(['role' => 'account_manager']);
        DailyCallReport::query()->create([
            'user_id' => $user->id,
            'report_date' => '2026-01-01',
            'calls_made' => 9,
            'contacts_reached' => 0,
            'submittals' => 0,
            'interviews_scheduled' => 0,
            'notes' => null,
        ]);

        $response = $this->actingAs($user)->get(route('calls.index', ['period' => 'all']));

        $response->assertOk();
        $response->assertViewHas('historyPeriod', 'all');
        $reports = $response->viewData('reports');
        $this->assertCount(1, $reports);
        $this->assertSame(9, (int) $reports->first()->calls_made);
    }

    public function test_index_paginates_history(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24 12:00:00', 'UTC'));

        $u1 = User::factory()->create(['role' => 'account_manager']);
        $u2 = User::factory()->create(['role' => 'account_manager']);

        for ($i = 0; $i < 26; $i++) {
            $d = Carbon::parse('2026-03-24')->subDays($i)->toDateString();
            DailyCallReport::query()->create([
                'user_id' => $u1->id,
                'report_date' => $d,
                'calls_made' => 1,
                'contacts_reached' => 0,
                'submittals' => 0,
                'interviews_scheduled' => 0,
                'notes' => null,
            ]);
            DailyCallReport::query()->create([
                'user_id' => $u2->id,
                'report_date' => $d,
                'calls_made' => 1,
                'contacts_reached' => 0,
                'submittals' => 0,
                'interviews_scheduled' => 0,
                'notes' => null,
            ]);
        }

        $response = $this->actingAs($u1)->get(route('calls.index'));

        $response->assertOk();
        $reports = $response->viewData('reports');
        $this->assertSame(52, $reports->total());
        $this->assertCount(50, $reports);

        $page2 = $this->actingAs($u1)->get(route('calls.index', ['page' => 2]));
        $page2->assertOk();
        $p2 = $page2->viewData('reports');
        $this->assertCount(2, $p2);
    }

    public function test_index_rejects_invalid_period(): void
    {
        $user = User::factory()->create(['role' => 'account_manager']);

        $response = $this->actingAs($user)->get(route('calls.index', ['period' => 'invalid']));

        $response->assertSessionHasErrors('period');
    }

    public function test_report_monthly_forbidden_for_account_manager(): void
    {
        $am = User::factory()->create(['role' => 'account_manager']);

        $this->actingAs($am)->get(route('calls.report.monthly'))->assertForbidden();
    }

    public function test_report_yearly_forbidden_for_account_manager(): void
    {
        $am = User::factory()->create(['role' => 'account_manager']);

        $this->actingAs($am)->get(route('calls.report.yearly'))->assertForbidden();
    }

    public function test_report_monthly_admin_sees_twelve_months_and_totals(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', 'UTC'));

        $admin = User::factory()->create(['role' => 'admin']);
        $am = User::factory()->create(['role' => 'account_manager']);

        $row = [
            'contacts_reached' => 0,
            'submittals' => 0,
            'interviews_scheduled' => 0,
            'notes' => null,
        ];
        DailyCallReport::query()->create(array_merge($row, [
            'user_id' => $am->id,
            'report_date' => '2026-03-10',
            'calls_made' => 10,
        ]));
        DailyCallReport::query()->create(array_merge($row, [
            'user_id' => $am->id,
            'report_date' => '2026-03-20',
            'calls_made' => 5,
        ]));
        DailyCallReport::query()->create(array_merge($row, [
            'user_id' => $am->id,
            'report_date' => '2026-04-01',
            'calls_made' => 3,
        ]));

        $response = $this->actingAs($admin)->get(route('calls.report.monthly', ['year' => 2026]));

        $response->assertOk();
        $rows = $response->viewData('monthlyRows');
        $this->assertCount(12, $rows);
        $march = $rows->firstWhere('ym', '2026-03');
        $this->assertNotNull($march);
        $this->assertSame(15, $march->total_calls);
        $this->assertSame(2, $march->total_days);
        $april = $rows->firstWhere('ym', '2026-04');
        $this->assertNotNull($april);
        $this->assertSame(3, $april->total_calls);
        $jan = $rows->firstWhere('ym', '2026-01');
        $this->assertNotNull($jan);
        $this->assertSame(0, $jan->total_calls);
    }

    public function test_report_yearly_admin_groups_by_calendar_year(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $am = User::factory()->create(['role' => 'account_manager']);

        $row = [
            'contacts_reached' => 0,
            'submittals' => 0,
            'interviews_scheduled' => 0,
            'notes' => null,
        ];
        DailyCallReport::query()->create(array_merge($row, [
            'user_id' => $am->id,
            'report_date' => '2024-06-01',
            'calls_made' => 40,
        ]));
        DailyCallReport::query()->create(array_merge($row, [
            'user_id' => $am->id,
            'report_date' => '2025-01-01',
            'calls_made' => 11,
        ]));

        $response = $this->actingAs($admin)->get(route('calls.report.yearly'));

        $response->assertOk();
        $rows = $response->viewData('yearlyRows');
        $this->assertCount(2, $rows);
        $this->assertSame(2025, (int) $rows->get(0)->y);
        $this->assertSame(11, (int) $rows->get(0)->total_calls);
        $this->assertSame(2024, (int) $rows->get(1)->y);
        $this->assertSame(40, (int) $rows->get(1)->total_calls);
    }

    public function test_report_monthly_json_shape(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-10 12:00:00', 'UTC'));

        $admin = User::factory()->create(['role' => 'admin']);
        $am = User::factory()->create(['role' => 'account_manager']);
        DailyCallReport::query()->create([
            'user_id' => $am->id,
            'report_date' => '2026-01-05',
            'calls_made' => 7,
            'contacts_reached' => 0,
            'submittals' => 0,
            'interviews_scheduled' => 0,
            'notes' => null,
        ]);

        $response = $this->actingAs($admin)->getJson(route('calls.report.monthly', ['year' => 2026]));

        $response->assertOk();
        $response->assertJsonPath('year', 2026);
        $response->assertJsonCount(12, 'months');
        $response->assertJsonPath('months.0.ym', '2026-01');
        $response->assertJsonPath('months.0.total_calls', 7);
    }
}
