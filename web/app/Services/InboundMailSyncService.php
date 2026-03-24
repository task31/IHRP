<?php

namespace App\Services;

use App\Models\EmailInboxAttachment;
use App\Models\EmailInboxMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class InboundMailSyncService
{
    private const ALLOWED_EXTENSIONS = [
        'pdf', 'xlsx', 'xls', 'png', 'jpg', 'jpeg', 'gif', 'txt', 'csv', 'doc', 'docx',
    ];

    public function __construct(
        private readonly MicrosoftGraphService $graph,
    ) {}

    /**
     * @return array{imported: int, skipped: string|null}
     */
    public function sync(bool $dryRun = false): array
    {
        if (! config('inbound_mail.enabled', true)) {
            return ['imported' => 0, 'skipped' => 'sync disabled via config'];
        }

        if (! $this->graph->isConfigured()) {
            return ['imported' => 0, 'skipped' => 'Microsoft Graph not configured (missing env)'];
        }

        $token = $this->graph->acquireToken();
        if ($token === null) {
            return ['imported' => 0, 'skipped' => 'token acquisition failed'];
        }

        $messages = $this->graph->getInboxMessages($token);
        if ($messages === null) {
            return ['imported' => 0, 'skipped' => 'list messages failed'];
        }

        $imported = 0;
        $maxBody = (int) config('inbound_mail.max_body_bytes', 524_288);
        $mailboxUpn = (string) config('inbound_mail.mailbox_upn');

        foreach ($messages as $raw) {
            $graphId = $raw['id'] ?? null;
            if (! is_string($graphId) || $graphId === '') {
                continue;
            }

            if (EmailInboxMessage::query()->where('graph_message_id', $graphId)->exists()) {
                continue;
            }

            if ($dryRun) {
                $imported++;

                continue;
            }

            $parsed = $this->parseMessagePayload($raw, $maxBody);

            DB::transaction(function () use ($token, $graphId, $mailboxUpn, $parsed, &$imported, $raw): void {
                $msg = EmailInboxMessage::query()->create([
                    'graph_message_id' => $graphId,
                    'internet_message_id' => $parsed['internet_message_id'],
                    'mailbox_upn' => $mailboxUpn,
                    'from_name' => $parsed['from_name'],
                    'from_email' => $parsed['from_email'],
                    'subject' => $parsed['subject'],
                    'received_at' => $parsed['received_at'],
                    'has_attachments' => (bool) ($raw['hasAttachments'] ?? false),
                    'status' => 'new',
                    'body_preview' => $parsed['body_preview'],
                    'body_plain' => $parsed['body_plain'],
                    'body_html' => $parsed['body_html'],
                    'body_content_type' => $parsed['body_content_type'],
                ]);

                $this->importAttachments($token, $graphId, $msg);

                $imported++;
            });
        }

        if ($imported > 0 && ! $dryRun) {
            AppService::auditLog(
                'email_inbox_messages',
                0,
                'INBOUND_SYNC',
                [],
                ['imported' => $imported],
                "Microsoft Graph inbox sync imported {$imported} new message(s)",
            );
        }

        return ['imported' => $imported, 'skipped' => null];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{
     *     internet_message_id: ?string,
     *     from_name: ?string,
     *     from_email: ?string,
     *     subject: ?string,
     *     received_at: ?Carbon,
     *     body_preview: string,
     *     body_plain: ?string,
     *     body_html: ?string,
     *     body_content_type: ?string
     * }
     */
    private function parseMessagePayload(array $raw, int $maxBody): array
    {
        $internetMessageId = isset($raw['internetMessageId']) && is_string($raw['internetMessageId'])
            ? $raw['internetMessageId']
            : null;

        $from = $raw['from']['emailAddress'] ?? null;
        $fromName = is_array($from) && isset($from['name']) && is_string($from['name']) ? $from['name'] : null;
        $fromEmail = is_array($from) && isset($from['address']) && is_string($from['address']) ? $from['address'] : null;

        $subject = isset($raw['subject']) && is_string($raw['subject']) ? $raw['subject'] : null;

        $receivedAt = null;
        if (isset($raw['receivedDateTime']) && is_string($raw['receivedDateTime'])) {
            try {
                $receivedAt = Carbon::parse($raw['receivedDateTime']);
            } catch (\Throwable) {
                $receivedAt = null;
            }
        }

        $body = $raw['body'] ?? null;
        $contentType = is_array($body) && isset($body['contentType']) && is_string($body['contentType'])
            ? strtolower($body['contentType'])
            : null;
        $content = is_array($body) && isset($body['content']) && is_string($body['content'])
            ? $body['content']
            : '';

        $bodyPlain = null;
        $bodyHtml = null;
        $storeType = null;

        if ($contentType === 'text') {
            $bodyPlain = $this->truncateBody($content, $maxBody);
            $storeType = 'text';
        } elseif ($contentType === 'html') {
            $bodyHtml = $this->truncateBody($content, $maxBody);
            $storeType = 'html';
        } elseif ($content !== '') {
            $bodyPlain = $this->truncateBody($content, $maxBody);
            $storeType = 'text';
        }

        $graphPreview = isset($raw['bodyPreview']) && is_string($raw['bodyPreview']) ? $raw['bodyPreview'] : null;
        $bodyPreview = $this->buildPreview($bodyPlain, $bodyHtml, $graphPreview);

        return [
            'internet_message_id' => $internetMessageId,
            'from_name' => $fromName,
            'from_email' => $fromEmail,
            'subject' => $subject,
            'received_at' => $receivedAt,
            'body_preview' => $bodyPreview,
            'body_plain' => $bodyPlain,
            'body_html' => $bodyHtml,
            'body_content_type' => $storeType,
        ];
    }

    private function truncateBody(string $content, int $maxBytes): string
    {
        if (strlen($content) <= $maxBytes) {
            return $content;
        }

        return substr($content, 0, $maxBytes)."\n\n[truncated]";
    }

    private function buildPreview(?string $plain, ?string $html, ?string $graphPreview): string
    {
        if ($graphPreview !== null && trim($graphPreview) !== '') {
            $oneLine = trim(preg_replace('/\s+/u', ' ', $graphPreview) ?? '');

            return Str::limit($oneLine, 200, '…');
        }

        $base = ($plain !== null && $plain !== '') ? $plain : strip_tags((string) $html);
        $oneLine = trim(preg_replace('/\s+/u', ' ', $base) ?? '');

        return Str::limit($oneLine, 200, '…');
    }

    private function importAttachments(string $token, string $graphMessageId, EmailInboxMessage $msg): void
    {
        $attachments = $this->graph->getMessageAttachments($token, $graphMessageId);
        if ($attachments === null || $attachments === []) {
            return;
        }

        foreach ($attachments as $att) {
            $odataType = $att['@odata.type'] ?? '';
            if ($odataType !== '#microsoft.graph.fileAttachment') {
                continue;
            }

            $name = isset($att['name']) && is_string($att['name']) ? $att['name'] : 'attachment';
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext === '' || ! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                Log::info('Skipping disallowed inbox attachment type', ['filename' => $name]);

                continue;
            }

            $bytesB64 = $att['contentBytes'] ?? null;
            if (! is_string($bytesB64) || $bytesB64 === '') {
                Log::warning('Attachment missing contentBytes', ['graph_message_id' => $graphMessageId, 'name' => $name]);

                continue;
            }

            $binary = base64_decode($bytesB64, true);
            if ($binary === false) {
                continue;
            }

            $safeBase = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'file';
            $safeName = $safeBase.'.'.$ext;
            $dir = 'uploads/inbound/'.$msg->id;
            $path = $dir.'/'.$safeName;

            $i = 1;
            while (Storage::disk('local')->exists($path)) {
                $path = $dir.'/'.$safeBase.'-'.$i.'.'.$ext;
                $i++;
            }

            Storage::disk('local')->put($path, $binary);

            $graphAttId = isset($att['id']) && is_string($att['id']) ? $att['id'] : null;
            $mime = isset($att['contentType']) && is_string($att['contentType']) ? $att['contentType'] : null;
            $size = isset($att['size']) && is_int($att['size']) ? $att['size'] : strlen($binary);

            EmailInboxAttachment::query()->create([
                'email_inbox_message_id' => $msg->id,
                'graph_attachment_id' => $graphAttId,
                'filename' => $name,
                'content_type' => $mime,
                'size_bytes' => $size,
                'storage_path' => $path,
            ]);
        }
    }
}
