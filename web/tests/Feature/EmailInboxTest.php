<?php

namespace Tests\Feature;

use App\Models\EmailInboxAttachment;
use App\Models\EmailInboxMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_manager_cannot_load_inbox_json(): void
    {
        $am = User::factory()->create(['role' => 'account_manager']);
        $msg = EmailInboxMessage::query()->create([
            'graph_message_id' => 'graph-1',
            'internet_message_id' => null,
            'mailbox_upn' => 'inbox@test.local',
            'from_name' => 'Sender',
            'from_email' => 'sender@test.local',
            'subject' => 'Hello',
            'received_at' => now(),
            'has_attachments' => false,
            'status' => 'new',
            'body_preview' => 'Hi',
            'body_plain' => 'Hi there',
            'body_html' => null,
            'body_content_type' => 'text',
        ]);

        $this->actingAs($am)->getJson(route('admin.inbox.message.json', $msg))
            ->assertForbidden();
    }

    public function test_admin_json_includes_body_and_attachments(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        $msg = EmailInboxMessage::query()->create([
            'graph_message_id' => 'graph-2',
            'internet_message_id' => null,
            'mailbox_upn' => 'inbox@test.local',
            'from_name' => 'Jane',
            'from_email' => 'jane@test.local',
            'subject' => 'Report',
            'received_at' => now(),
            'has_attachments' => true,
            'status' => 'new',
            'body_preview' => 'See attached',
            'body_plain' => 'See attached',
            'body_html' => null,
            'body_content_type' => 'text',
        ]);

        $path = 'uploads/inbound/'.$msg->id.'/doc.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 fake');

        $att = EmailInboxAttachment::query()->create([
            'email_inbox_message_id' => $msg->id,
            'graph_attachment_id' => 'att-1',
            'filename' => 'doc.pdf',
            'content_type' => 'application/pdf',
            'size_bytes' => 12,
            'storage_path' => $path,
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.inbox.message.json', $msg));

        $response->assertOk();
        $response->assertJsonPath('subject', 'Report');
        $response->assertJsonPath('status', 'read');
        $response->assertJsonPath('body_plain', 'See attached');
        $response->assertJsonPath('attachments.0.filename', 'doc.pdf');
        $response->assertJsonPath('attachments.0.download_url', route('admin.inbox.attachments.download', $att));

        $this->assertDatabaseHas('email_inbox_messages', [
            'id' => $msg->id,
            'status' => 'read',
        ]);

        $this->actingAs($admin)->getJson(route('admin.inbox.message.json', $msg))
            ->assertOk()
            ->assertJsonPath('status', 'read');
    }

    public function test_admin_can_download_attachment(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        $msg = EmailInboxMessage::query()->create([
            'graph_message_id' => 'graph-3',
            'internet_message_id' => null,
            'mailbox_upn' => 'inbox@test.local',
            'from_name' => null,
            'from_email' => 'a@b.c',
            'subject' => 'S',
            'received_at' => now(),
            'has_attachments' => true,
            'status' => 'new',
            'body_preview' => '',
            'body_plain' => '',
            'body_html' => null,
            'body_content_type' => null,
        ]);

        $path = 'uploads/inbound/'.$msg->id.'/x.txt';
        Storage::disk('local')->put($path, 'hello');

        $att = EmailInboxAttachment::query()->create([
            'email_inbox_message_id' => $msg->id,
            'graph_attachment_id' => null,
            'filename' => 'x.txt',
            'content_type' => 'text/plain',
            'size_bytes' => 5,
            'storage_path' => $path,
        ]);

        $this->actingAs($admin)->get(route('admin.inbox.attachments.download', $att))
            ->assertOk();
    }

    public function test_inbound_sync_exits_successfully_when_not_configured(): void
    {
        $this->artisan('inbound-mail:sync')->assertSuccessful();
    }
}
