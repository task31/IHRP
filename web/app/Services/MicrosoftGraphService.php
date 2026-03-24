<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class MicrosoftGraphService
{
    public function isConfigured(): bool
    {
        $c = config('inbound_mail');

        return filled($c['azure_tenant_id'])
            && filled($c['azure_client_id'])
            && filled($c['azure_client_secret'])
            && filled($c['mailbox_upn']);
    }

    public function acquireToken(): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $tenant = config('inbound_mail.azure_tenant_id');
        $url = "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token";

        /** @var Response $response */
        $response = Http::asForm()->timeout(30)->post($url, [
            'client_id' => config('inbound_mail.azure_client_id'),
            'client_secret' => config('inbound_mail.azure_client_secret'),
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials',
        ]);

        if (! $response->successful()) {
            Log::warning('Microsoft Graph token request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $response->json('access_token');
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function getInboxMessages(string $token): ?array
    {
        $upn = config('inbound_mail.mailbox_upn');
        $size = (int) config('inbound_mail.sync_page_size', 25);
        $encoded = rawurlencode((string) $upn);
        $url = "https://graph.microsoft.com/v1.0/users/{$encoded}/mailFolders/inbox/messages";

        $response = Http::withToken($token)
            ->timeout(60)
            ->retry(2, 2000, throw: false)
            ->get($url, [
                '$top' => $size,
                '$orderby' => 'receivedDateTime desc',
                '$select' => 'id,internetMessageId,subject,from,receivedDateTime,hasAttachments,body,bodyPreview',
            ]);

        if (! $response->successful()) {
            Log::warning('Microsoft Graph list messages failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $response->json('value');
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function getMessageAttachments(string $token, string $graphMessageId): ?array
    {
        $upn = config('inbound_mail.mailbox_upn');
        $encoded = rawurlencode((string) $upn);
        $mid = rawurlencode($graphMessageId);
        $url = "https://graph.microsoft.com/v1.0/users/{$encoded}/messages/{$mid}/attachments";

        $response = Http::withToken($token)
            ->timeout(120)
            ->retry(2, 2000, throw: false)
            ->get($url);

        if (! $response->successful()) {
            Log::warning('Microsoft Graph list attachments failed', [
                'message_id' => $graphMessageId,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response->json('value');
    }
}
