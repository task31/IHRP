<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlacementPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_manager_sees_add_placement_button_with_livewire_assets(): void
    {
        $am = User::factory()->create(['role' => 'account_manager']);

        $this->actingAs($am)
            ->get(route('placements.index'))
            ->assertOk()
            ->assertSee('Add Placement', false)
            ->assertSee('wire:click="openCreate"', false)
            ->assertSee('livewire.js', false);
    }

    public function test_am_json_index_returns_only_own_placements(): void
    {
        $am1 = User::factory()->create(['role' => 'account_manager']);
        $am2 = User::factory()->create(['role' => 'account_manager']);

        $consultant = \App\Models\Consultant::factory()->create();
        $client = \App\Models\Client::factory()->create();

        \App\Models\Placement::factory()->create([
            'placed_by' => $am1->id,
            'consultant_id' => $consultant->id,
            'client_id' => $client->id,
        ]);
        \App\Models\Placement::factory()->create([
            'placed_by' => $am2->id,
            'consultant_id' => $consultant->id,
            'client_id' => $client->id,
        ]);

        $response = $this->actingAs($am1)
            ->getJson(route('placements.index'));

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals($am1->id, (int) $data[0]['placed_by']);
    }

    public function test_am_cannot_update_another_ams_placement(): void
    {
        $am1 = User::factory()->create(['role' => 'account_manager']);
        $am2 = User::factory()->create(['role' => 'account_manager']);

        $consultant = \App\Models\Consultant::factory()->create();
        $client = \App\Models\Client::factory()->create();

        $placement = \App\Models\Placement::factory()->create([
            'placed_by' => $am2->id,
            'consultant_id' => $consultant->id,
            'client_id' => $client->id,
            'status' => 'active',
        ]);

        $this->actingAs($am1)
            ->putJson(route('placements.update', $placement), [
                'consultant_id' => $consultant->id,
                'client_id' => $client->id,
                'job_title' => 'Hacked',
                'start_date' => '2026-01-01',
                'pay_rate' => '50.00',
                'bill_rate' => '60.00',
                'status' => 'active',
            ])
            ->assertForbidden();
    }
}

