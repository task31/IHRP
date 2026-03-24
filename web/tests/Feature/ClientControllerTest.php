<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_manager_sees_account_manager_column_on_index(): void
    {
        $am = User::factory()->create(['role' => 'account_manager', 'name' => 'Pat AM']);
        $client = Client::query()->create([
            'name' => 'Managed LLC',
            'payment_terms' => 'Net 30',
            'total_budget' => 0,
            'active' => true,
            'account_manager_id' => $am->id,
        ]);

        $response = $this->actingAs($am)->get(route('clients.index'));

        $response->assertOk();
        $response->assertSee('Managed LLC', false);
        $response->assertSee('Pat AM', false);
        $response->assertSee('Account manager', false);
        $this->assertTrue(
            $response->viewData('clients')->pluck('id')->contains($client->id),
        );
    }

    public function test_admin_can_create_client_with_account_manager(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $am = User::factory()->create(['role' => 'account_manager']);

        $response = $this->actingAs($admin)->postJson(route('clients.store'), [
            'name' => 'New Client Inc',
            'email' => 'bill@example.com',
            'smtp_email' => 'smtp@example.com',
            'account_manager_id' => $am->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('account_manager_id', $am->id);
        $this->assertDatabaseHas('clients', [
            'name' => 'New Client Inc',
            'account_manager_id' => $am->id,
        ]);
    }

    public function test_admin_cannot_set_account_manager_to_admin_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson(route('clients.store'), [
            'name' => 'Bad AM',
            'email' => 'a@example.com',
            'smtp_email' => 'b@example.com',
            'account_manager_id' => $admin->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('account_manager_id');
    }

    public function test_admin_can_clear_account_manager_on_update(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $am = User::factory()->create(['role' => 'account_manager']);
        $client = Client::query()->create([
            'name' => 'To Clear',
            'payment_terms' => 'Net 30',
            'total_budget' => 0,
            'active' => true,
            'account_manager_id' => $am->id,
        ]);

        $response = $this->actingAs($admin)->putJson(route('clients.update', $client), [
            'name' => 'To Clear',
            'email' => null,
            'smtp_email' => null,
            'payment_terms' => 'Net 30',
            'total_budget' => 0,
            'account_manager_id' => null,
        ]);

        $response->assertOk();
        $this->assertNull($client->fresh()->account_manager_id);
    }
}
