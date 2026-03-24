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
}

