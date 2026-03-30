<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTemplateDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_manager_can_download_invoice_ot_template(): void
    {
        $path = storage_path('app/templates/invoice_template_ot.xlsx');
        if (! is_file($path)) {
            $this->markTestSkipped('storage/app/templates/invoice_template_ot.xlsx not available');
        }

        $user = User::factory()->create(['role' => 'account_manager']);

        $response = $this->actingAs($user)->get(route('invoices.template'));

        $response->assertOk();
        $response->assertDownload('invoice_template_ot.xlsx');
    }

    public function test_guest_cannot_download_invoice_ot_template(): void
    {
        $response = $this->get(route('invoices.template'));

        $response->assertRedirect(route('login'));
    }
}
