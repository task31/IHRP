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
}
