<?php

namespace Database\Seeders;

use App\Models\EmailInboxAttachment;
use App\Models\EmailInboxMessage;
use Illuminate\Database\Seeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Imaginary inbox rows for local UI testing (Admin → Users → Email inbox).
 *
 * Run: php artisan db:seed --class=EmailInboxDemoSeeder
 */
class EmailInboxDemoSeeder extends Seeder
{
    private const PREFIX = 'demo-ihrp-';

    public function run(): void
    {
        $disk = Storage::disk('local');
        $mailbox = config('inbound_mail.mailbox_upn') ?: 'payroll-ingest@matchpointegroup.com';

        $this->purgeExistingDemo($disk);

        $defs = [
            [
                'suffix' => '001',
                'from_name' => 'Jordan Lee',
                'from_email' => 'jordan.lee@example.com',
                'subject' => 'Timesheet — week ending Mar 21',
                'received_days_ago' => 2,
                'status' => 'new',
                'body_preview' => 'Hi team, attached is my timesheet for last week. Thanks!',
                'body_plain' => "Hi team,\n\nAttached is my timesheet for last week. Let me know if you need anything else.\n\n— Jordan",
                'body_html' => null,
                'attachments' => [
                    ['filename' => 'jordan_timesheet_week12.xlsx', 'bytes' => "PK\x03\x04 demo xlsx placeholder\n", 'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                ],
            ],
            [
                'suffix' => '002',
                'from_name' => 'Priya Sharma',
                'from_email' => 'priya.sharma@acmecorp.example',
                'subject' => 'Re: W-9 for new consultant',
                'received_days_ago' => 3,
                'status' => 'new',
                'body_preview' => 'Attached is the signed W-9. Please confirm receipt.',
                'body_plain' => null,
                'body_html' => '<p>Attached is the signed <strong>W-9</strong>. Please confirm receipt.</p><p>Thanks,<br>Priya</p>',
                'attachments' => [
                    ['filename' => 'W9_Priya_Sharma_2025.pdf', 'bytes' => "%PDF-1.4\n% demo pdf placeholder\n", 'type' => 'application/pdf'],
                ],
            ],
            [
                'suffix' => '003',
                'from_name' => 'Marcus Chen',
                'from_email' => 'mchen@contractor.example',
                'subject' => 'Question about holiday pay',
                'received_days_ago' => 5,
                'status' => 'read',
                'body_preview' => 'Does Memorial Day count as a billable holiday for my current assignment?',
                'body_plain' => "Hi,\n\nDoes Memorial Day count as a billable holiday for my current assignment at Acme?\n\nMarcus",
                'body_html' => null,
                'attachments' => [],
            ],
            [
                'suffix' => '004',
                'from_name' => null,
                'from_email' => 'noreply@notifications.example',
                'subject' => 'Expense report submitted',
                'received_days_ago' => 7,
                'status' => 'new',
                'body_preview' => 'Your expense report #EXP-8842 was submitted for approval.',
                'body_plain' => 'Your expense report #EXP-8842 was submitted for approval. No action required.',
                'body_html' => null,
                'attachments' => [
                    ['filename' => 'receipts_march.zip', 'bytes' => "PK\x05\x06 demo zip\n", 'type' => 'application/zip'],
                    ['filename' => 'summary.csv', 'bytes' => "date,amount,description\n2025-03-01,42.10,Parking\n", 'type' => 'text/csv'],
                ],
            ],
            [
                'suffix' => '005',
                'from_name' => 'Alex Rivera',
                'from_email' => 'alex.rivera@example.com',
                'subject' => 'Quick ping — start date moved',
                'received_days_ago' => 1,
                'status' => 'new',
                'body_preview' => 'Client asked to slide start to April 7. Still good on your side?',
                'body_plain' => "Hey — client asked to slide start to April 7. Still good on your side?\n\nAlex",
                'body_html' => null,
                'attachments' => [],
            ],
        ];

        foreach ($defs as $d) {
            $graphId = self::PREFIX.$d['suffix'];
            $pathPrefix = 'demo-inbox/'.$graphId;

            $msg = EmailInboxMessage::query()->create([
                'graph_message_id' => $graphId,
                'internet_message_id' => null,
                'mailbox_upn' => $mailbox,
                'from_name' => $d['from_name'],
                'from_email' => $d['from_email'],
                'subject' => $d['subject'],
                'received_at' => now()->subDays($d['received_days_ago']),
                'has_attachments' => count($d['attachments']) > 0,
                'status' => $d['status'],
                'body_preview' => $d['body_preview'],
                'body_plain' => $d['body_plain'],
                'body_html' => $d['body_html'],
                'body_content_type' => $d['body_html'] ? 'html' : 'text',
            ]);

            foreach ($d['attachments'] as $att) {
                $relPath = $pathPrefix.'/'.$att['filename'];
                $bytes = $att['bytes'];
                $disk->put($relPath, $bytes);

                EmailInboxAttachment::query()->create([
                    'email_inbox_message_id' => $msg->id,
                    'graph_attachment_id' => null,
                    'filename' => $att['filename'],
                    'content_type' => $att['type'],
                    'size_bytes' => strlen($bytes),
                    'storage_path' => $relPath,
                ]);
            }
        }

        $this->command?->info('Seeded '.count($defs).' demo inbox messages (graph ids: '.self::PREFIX.'*).');
    }

    private function purgeExistingDemo(FilesystemAdapter $disk): void
    {
        $ids = EmailInboxMessage::query()
            ->where('graph_message_id', 'like', self::PREFIX.'%')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $attachments = EmailInboxAttachment::query()
            ->whereIn('email_inbox_message_id', $ids)
            ->get();

        foreach ($attachments as $a) {
            if ($disk->exists($a->storage_path)) {
                $disk->delete($a->storage_path);
            }
            $a->delete();
        }

        EmailInboxMessage::query()->whereIn('id', $ids)->delete();

        if ($disk->exists('demo-inbox')) {
            $disk->deleteDirectory('demo-inbox');
        }
    }
}
