<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Consultant;
use App\Models\EmailInboxAttachment;
use App\Models\EmailInboxMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailInboxApplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_w9_forbidden_for_non_admin(): void
    {
        $am = User::factory()->create(['role' => 'account_manager']);
        $client = Client::query()->create(['name' => 'Acme', 'active' => true]);
        $consultant = Consultant::query()->create([
            'full_name' => 'Test User',
            'pay_rate' => 50,
            'bill_rate' => 100,
            'state' => 'TX',
            'industry_type' => 'general',
            'client_id' => $client->id,
            'active' => true,
        ]);

        $msg = $this->makeMessage();
        Storage::disk('local')->put('inbox/w9.pdf', '%PDF-1.4 test');
        $att = EmailInboxAttachment::query()->create([
            'email_inbox_message_id' => $msg->id,
            'graph_attachment_id' => null,
            'filename' => 'w9.pdf',
            'content_type' => 'application/pdf',
            'size_bytes' => 20,
            'storage_path' => 'inbox/w9.pdf',
        ]);

        $this->actingAs($am)->postJson(route('admin.inbox.attachments.apply-w9', $att), [
            'consultant_id' => $consultant->id,
        ])->assertForbidden();
    }

    public function test_apply_w9_rejects_non_pdf_attachment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::query()->create(['name' => 'Acme', 'active' => true]);
        $consultant = Consultant::query()->create([
            'full_name' => 'Test User',
            'pay_rate' => 50,
            'bill_rate' => 100,
            'state' => 'TX',
            'industry_type' => 'general',
            'client_id' => $client->id,
            'active' => true,
        ]);

        $msg = $this->makeMessage();
        Storage::disk('local')->put('inbox/note.txt', 'hello');
        $att = EmailInboxAttachment::query()->create([
            'email_inbox_message_id' => $msg->id,
            'graph_attachment_id' => null,
            'filename' => 'note.txt',
            'content_type' => 'text/plain',
            'size_bytes' => 5,
            'storage_path' => 'inbox/note.txt',
        ]);

        $this->actingAs($admin)->postJson(route('admin.inbox.attachments.apply-w9', $att), [
            'consultant_id' => $consultant->id,
        ])->assertUnprocessable();
    }

    public function test_apply_contract_copies_pdf_to_consultant(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::query()->create(['name' => 'Acme', 'active' => true]);
        $consultant = Consultant::query()->create([
            'full_name' => 'Contract User',
            'pay_rate' => 50,
            'bill_rate' => 100,
            'state' => 'TX',
            'industry_type' => 'general',
            'client_id' => $client->id,
            'active' => true,
        ]);

        $msg = $this->makeMessage();
        $pdf = '%PDF-1.4 inbox msa';
        Storage::disk('local')->put('inbox/msa.pdf', $pdf);
        $att = EmailInboxAttachment::query()->create([
            'email_inbox_message_id' => $msg->id,
            'graph_attachment_id' => null,
            'filename' => 'msa.pdf',
            'content_type' => 'application/pdf',
            'size_bytes' => strlen($pdf),
            'storage_path' => 'inbox/msa.pdf',
        ]);

        $this->actingAs($admin)->postJson(route('admin.inbox.attachments.apply-contract', $att), [
            'consultant_id' => $consultant->id,
        ])->assertOk()->assertJsonPath('ok', true);

        $consultant->refresh();
        $this->assertSame('consultant_'.$consultant->id.'.pdf', $consultant->contract_file_path);
        $this->assertTrue($consultant->contract_on_file);
        $this->assertSame($pdf, Storage::disk('local')->get('uploads/contracts/consultant_'.$consultant->id.'.pdf'));
    }

    public function test_apply_w9_copies_pdf_to_consultant(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::query()->create(['name' => 'Acme', 'active' => true]);
        $consultant = Consultant::query()->create([
            'full_name' => 'Test User',
            'pay_rate' => 50,
            'bill_rate' => 100,
            'state' => 'TX',
            'industry_type' => 'general',
            'client_id' => $client->id,
            'active' => true,
        ]);

        $msg = $this->makeMessage();
        $pdf = '%PDF-1.4 inbox w9';
        Storage::disk('local')->put('inbox/w9.pdf', $pdf);
        $att = EmailInboxAttachment::query()->create([
            'email_inbox_message_id' => $msg->id,
            'graph_attachment_id' => null,
            'filename' => 'w9.pdf',
            'content_type' => 'application/pdf',
            'size_bytes' => strlen($pdf),
            'storage_path' => 'inbox/w9.pdf',
        ]);

        $this->actingAs($admin)->postJson(route('admin.inbox.attachments.apply-w9', $att), [
            'consultant_id' => $consultant->id,
        ])->assertOk()->assertJsonPath('ok', true);

        $consultant->refresh();
        $this->assertSame('consultant_'.$consultant->id.'.pdf', $consultant->w9_file_path);
        $this->assertTrue($consultant->w9_on_file);
        $this->assertSame($pdf, Storage::disk('local')->get('uploads/w9s/consultant_'.$consultant->id.'.pdf'));
    }

    public function test_apply_timesheet_rejects_flat_csv_from_inbox(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::query()->create(['name' => 'Acme', 'active' => true]);
        $consultant = Consultant::query()->create([
            'full_name' => 'Test User',
            'pay_rate' => 50,
            'bill_rate' => 100,
            'state' => 'TX',
            'industry_type' => 'general',
            'client_id' => $client->id,
            'active' => true,
        ]);

        $msg = $this->makeMessage();
        Storage::disk('local')->put('inbox/data.csv', "Name,Hours\nTest,40\n");
        $att = EmailInboxAttachment::query()->create([
            'email_inbox_message_id' => $msg->id,
            'graph_attachment_id' => null,
            'filename' => 'data.csv',
            'content_type' => 'text/csv',
            'size_bytes' => 20,
            'storage_path' => 'inbox/data.csv',
        ]);

        $this->actingAs($admin)->postJson(route('admin.inbox.attachments.apply-timesheet', $att), [
            'consultant_id' => $consultant->id,
            'overwrite' => false,
        ])->assertUnprocessable();
    }

    public function test_apply_timesheet_imports_official_template_for_selected_consultant(): void
    {
        $templatePath = base_path('storage/app/templates/timesheet_template.xlsx');
        if (! is_readable($templatePath)) {
            $this->markTestSkipped('storage/app/templates/timesheet_template.xlsx not available');
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::query()->create(['name' => 'Acme', 'active' => true]);
        $consultant = Consultant::query()->create([
            'full_name' => 'Jane Template',
            'pay_rate' => 50,
            'bill_rate' => 100,
            'state' => 'TX',
            'industry_type' => 'general',
            'client_id' => $client->id,
            'active' => true,
        ]);

        $msg = $this->makeMessage();
        $bytes = file_get_contents($templatePath);
        $this->assertNotFalse($bytes);
        Storage::disk('local')->put('inbox/ts.xlsx', $bytes);
        $att = EmailInboxAttachment::query()->create([
            'email_inbox_message_id' => $msg->id,
            'graph_attachment_id' => null,
            'filename' => 'timesheet.xlsx',
            'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size_bytes' => strlen($bytes),
            'storage_path' => 'inbox/ts.xlsx',
        ]);

        $this->actingAs($admin)->postJson(route('admin.inbox.attachments.apply-timesheet', $att), [
            'consultant_id' => $consultant->id,
            'overwrite' => false,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('timesheets', [
            'consultant_id' => $consultant->id,
        ]);
    }

    private function makeMessage(): EmailInboxMessage
    {
        return EmailInboxMessage::query()->create([
            'graph_message_id' => 'apply-msg-'.uniqid(),
            'internet_message_id' => null,
            'mailbox_upn' => 'inbox@test.local',
            'from_name' => 'HR',
            'from_email' => 'hr@test.local',
            'subject' => 'Docs',
            'received_at' => now(),
            'has_attachments' => true,
            'status' => 'new',
            'body_preview' => 'x',
            'body_plain' => 'x',
            'body_html' => null,
            'body_content_type' => 'text',
        ]);
    }
}
