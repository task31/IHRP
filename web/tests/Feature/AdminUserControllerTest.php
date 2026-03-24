<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Consultant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_redirected_from_admin_users_index(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect();
    }

    public function test_account_manager_forbidden_from_admin_users_index(): void
    {
        $am = User::factory()->create(['role' => 'account_manager']);
        $this->actingAs($am)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_admin_can_set_consultant_link_for_account_manager(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $am = User::factory()->create(['role' => 'account_manager', 'consultant_id' => null]);
        $client = Client::query()->create(['name' => 'C1', 'active' => true]);
        $consultant = Consultant::query()->create([
            'full_name' => 'Linked Consultant',
            'pay_rate' => 50,
            'bill_rate' => 80,
            'state' => 'CA',
            'industry_type' => 'other',
            'client_id' => $client->id,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $am), [
                'name' => $am->name,
                'email' => $am->email,
                'password' => '',
                'role' => 'account_manager',
                'consultant_id' => (string) $consultant->id,
                'active' => '1',
            ])
            ->assertRedirect(route('admin.users.index'));

        $am->refresh();
        $this->assertSame((int) $consultant->id, (int) $am->consultant_id);
    }

    public function test_admin_role_clears_consultant_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::query()->create(['name' => 'C2', 'active' => true]);
        $consultant = Consultant::query()->create([
            'full_name' => 'Other',
            'pay_rate' => 40,
            'bill_rate' => 70,
            'state' => 'NY',
            'industry_type' => 'other',
            'client_id' => $client->id,
            'active' => true,
        ]);
        $target = User::factory()->create([
            'role' => 'account_manager',
            'consultant_id' => $consultant->id,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'password' => '',
                'role' => 'admin',
                'consultant_id' => (string) $consultant->id,
                'active' => '1',
            ])
            ->assertRedirect(route('admin.users.index'));

        $target->refresh();
        $this->assertNull($target->consultant_id);
    }
}
