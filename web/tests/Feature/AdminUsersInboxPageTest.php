<?php

namespace Tests\Feature;

use App\Models\EmailInboxAttachment;
use App\Models\EmailInboxMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersInboxPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_users_index_returns_200_and_renders_inbox_section(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('User Directory', false);
        $response->assertSee('Email inbox', false);
        $response->assertSee('No messages yet', false);
        $response->assertSee('Search inbox', false);
    }

    public function test_admin_users_index_shows_inbox_row_when_message_exists(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $msg = EmailInboxMessage::query()->create([
            'graph_message_id' => 'g-test-1',
            'internet_message_id' => null,
            'mailbox_upn' => 'inbox@test.local',
            'from_name' => 'Pat',
            'from_email' => 'pat@example.com',
            'subject' => 'Timesheet attached',
            'received_at' => now(),
            'has_attachments' => true,
            'status' => 'new',
            'body_preview' => 'Please find attached…',
            'body_plain' => 'Please find attached.',
            'body_html' => null,
            'body_content_type' => 'text',
        ]);

        EmailInboxAttachment::query()->create([
            'email_inbox_message_id' => $msg->id,
            'graph_attachment_id' => null,
            'filename' => 'week.xlsx',
            'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size_bytes' => 100,
            'storage_path' => 'uploads/inbound/'.$msg->id.'/week.xlsx',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('Pat', false);
        $response->assertSee('Timesheet attached', false);
        $response->assertSee('week.xlsx', false);
        $response->assertSee('Please find attached', false);
    }

    public function test_view_button_loads_message_json(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $msg = EmailInboxMessage::query()->create([
            'graph_message_id' => 'g-json-1',
            'internet_message_id' => null,
            'mailbox_upn' => 'inbox@test.local',
            'from_name' => null,
            'from_email' => 'only@email.com',
            'subject' => 'JSON test',
            'received_at' => now(),
            'has_attachments' => false,
            'status' => 'new',
            'body_preview' => 'Hi',
            'body_plain' => 'Full body',
            'body_html' => null,
            'body_content_type' => 'text',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.inbox.message.json', $msg))
            ->assertOk()
            ->assertJsonPath('subject', 'JSON test')
            ->assertJsonPath('body_plain', 'Full body')
            ->assertJsonPath('status', 'read');
    }

    public function test_inbox_search_filters_messages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $base = [
            'internet_message_id' => null,
            'mailbox_upn' => 'inbox@test.local',
            'received_at' => now(),
            'has_attachments' => false,
            'status' => 'new',
            'body_html' => null,
            'body_content_type' => 'text',
        ];

        EmailInboxMessage::query()->create(array_merge($base, [
            'graph_message_id' => 'g-search-a',
            'from_name' => 'Alpha',
            'from_email' => 'a@unique.test',
            'subject' => 'Invoice Q4 unique-marker-aaa',
            'body_preview' => 'preview a',
            'body_plain' => 'plain a',
        ]));

        EmailInboxMessage::query()->create(array_merge($base, [
            'graph_message_id' => 'g-search-b',
            'from_name' => 'Beta',
            'from_email' => 'b@other.test',
            'subject' => 'Unrelated subject',
            'body_preview' => 'preview b',
            'body_plain' => 'plain b',
        ]));

        $response = $this->actingAs($admin)->get(route('admin.users.index', [
            'inbox_search' => 'unique-marker-aaa',
        ]));

        $response->assertOk();
        $response->assertSee('unique-marker-aaa', false);
        $response->assertDontSee('Unrelated subject', false);
    }

    public function test_inbox_search_empty_shows_no_match_message(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        EmailInboxMessage::query()->create([
            'graph_message_id' => 'g-only',
            'internet_message_id' => null,
            'mailbox_upn' => 'inbox@test.local',
            'from_name' => 'Solo',
            'from_email' => 'solo@test.local',
            'subject' => 'Only message',
            'received_at' => now(),
            'has_attachments' => false,
            'status' => 'new',
            'body_preview' => 'x',
            'body_plain' => 'x',
            'body_html' => null,
            'body_content_type' => 'text',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['inbox_search' => 'zzznomatchzz']))
            ->assertOk()
            ->assertSee('No messages match your search.', false);
    }
}
