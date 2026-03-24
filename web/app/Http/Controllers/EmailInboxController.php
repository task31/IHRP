<?php

namespace App\Http\Controllers;

use App\Models\EmailInboxAttachment;
use App\Models\EmailInboxMessage;
use App\Services\EmailInboxAttachmentApplyService;
use App\Support\EmailHtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailInboxController extends Controller
{
    public function showJson(EmailInboxMessage $email_inbox_message): JsonResponse
    {
        $this->authorize('admin');

        $email_inbox_message->load('attachments');

        if ($email_inbox_message->status !== 'read') {
            $email_inbox_message->update(['status' => 'read']);
        }

        $from = $email_inbox_message->from_email ?? '—';
        if (filled($email_inbox_message->from_name)) {
            $from = $email_inbox_message->from_name.' <'.$email_inbox_message->from_email.'>';
        }

        $attachments = $email_inbox_message->attachments->map(fn (EmailInboxAttachment $a) => [
            'id' => $a->id,
            'filename' => $a->filename,
            'content_type' => $a->content_type,
            'size_bytes' => $a->size_bytes,
            'download_url' => route('admin.inbox.attachments.download', $a),
            'can_apply_w9' => EmailInboxAttachmentApplyService::attachmentIsPdf($a),
            'can_apply_contract' => EmailInboxAttachmentApplyService::attachmentIsPdf($a),
            'can_apply_timesheet' => EmailInboxAttachmentApplyService::attachmentIsTimesheetable($a),
            'apply_w9_url' => route('admin.inbox.attachments.apply-w9', $a),
            'apply_contract_url' => route('admin.inbox.attachments.apply-contract', $a),
            'apply_timesheet_url' => route('admin.inbox.attachments.apply-timesheet', $a),
        ]);

        return response()->json([
            'id' => $email_inbox_message->id,
            'status' => $email_inbox_message->status,
            'from_label' => $from,
            'subject' => $email_inbox_message->subject ?? '—',
            'received_at' => $email_inbox_message->received_at?->toIso8601String(),
            'mailbox_upn' => $email_inbox_message->mailbox_upn,
            'body_plain' => $email_inbox_message->body_plain,
            'body_html_sanitized' => EmailHtmlSanitizer::sanitize($email_inbox_message->body_html),
            'attachments' => $attachments,
        ]);
    }

    public function downloadAttachment(EmailInboxAttachment $email_inbox_attachment): StreamedResponse
    {
        $this->authorize('admin');

        if (! Storage::disk('local')->exists($email_inbox_attachment->storage_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $email_inbox_attachment->storage_path,
            $email_inbox_attachment->filename,
            ['Content-Type' => $email_inbox_attachment->content_type ?: 'application/octet-stream']
        );
    }

    public function applyW9(Request $request, EmailInboxAttachment $email_inbox_attachment): JsonResponse
    {
        $this->authorize('admin');

        $data = $request->validate([
            'consultant_id' => ['required', 'integer', 'exists:consultants,id'],
        ]);

        app(EmailInboxAttachmentApplyService::class)->applyW9(
            $email_inbox_attachment,
            (int) $data['consultant_id']
        );

        return response()->json([
            'ok' => true,
            'message' => 'W-9 applied to consultant.',
        ]);
    }

    public function applyContract(Request $request, EmailInboxAttachment $email_inbox_attachment): JsonResponse
    {
        $this->authorize('admin');

        $data = $request->validate([
            'consultant_id' => ['required', 'integer', 'exists:consultants,id'],
        ]);

        app(EmailInboxAttachmentApplyService::class)->applyContract(
            $email_inbox_attachment,
            (int) $data['consultant_id']
        );

        return response()->json([
            'ok' => true,
            'message' => 'Contract (MSA) applied to consultant.',
        ]);
    }

    public function applyTimesheet(Request $request, EmailInboxAttachment $email_inbox_attachment): JsonResponse
    {
        $this->authorize('admin');

        $data = $request->validate([
            'consultant_id' => ['required', 'integer', 'exists:consultants,id'],
            'overwrite' => ['sometimes', 'boolean'],
        ]);

        $out = app(EmailInboxAttachmentApplyService::class)->applyTimesheet(
            $email_inbox_attachment,
            (int) $data['consultant_id'],
            (bool) ($data['overwrite'] ?? false)
        );

        $ok = $out['saved'] > 0 || $out['overwrote'] > 0;

        return response()->json([
            'ok' => $ok,
            'saved' => $out['saved'],
            'overwrote' => $out['overwrote'],
            'errors' => $out['errors'],
            'message' => $ok
                ? ($out['overwrote'] > 0 ? 'Timesheet period updated.' : 'Timesheet imported.')
                : ($out['errors'][0] ?? 'Timesheet was not imported.'),
        ], $ok ? 200 : 422);
    }
}
