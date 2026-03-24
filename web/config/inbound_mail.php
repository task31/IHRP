<?php

return [

    'enabled' => env('INBOUND_MAIL_SYNC_ENABLED', true),

    'azure_tenant_id' => env('AZURE_TENANT_ID'),

    'azure_client_id' => env('AZURE_CLIENT_ID'),

    'azure_client_secret' => env('AZURE_CLIENT_SECRET'),

    'mailbox_upn' => env('INBOUND_MAILBOX_UPN'),

    /** Max stored body per field (bytes). */
    'max_body_bytes' => (int) env('INBOUND_MAIL_MAX_BODY_BYTES', 524_288),

    /** Messages to fetch per sync. */
    'sync_page_size' => (int) env('INBOUND_MAIL_SYNC_PAGE_SIZE', 25),
];
