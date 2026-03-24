<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Consultant;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class InvoicePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_streams_stored_pdf_without_dompdf_when_file_exists(): void
    {
        $user = User::factory()->create(['role' => 'account_manager']);
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
        $invoice = Invoice::query()->create([
            'invoice_number' => 'TST-000001',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'consultant_id' => $consultant->id,
            'client_id' => $client->id,
            'timesheet_id' => null,
            'bill_to_name' => 'Acme Corp',
            'payment_terms' => 'Net 30',
            'subtotal' => 100,
            'total_amount_due' => 100,
            'status' => 'pending',
            'pdf_path' => 'invoices/TST-000001.pdf',
        ]);

        $pdfBytes = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";
        Storage::disk('local')->put('invoices/TST-000001.pdf', $pdfBytes);

        $response = $this->actingAs($user)->get(route('invoices.preview', $invoice->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $base = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $base);
        $this->assertSame($pdfBytes, (string) file_get_contents($base->getFile()->getPathname()));
    }
}
