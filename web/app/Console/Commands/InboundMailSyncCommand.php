<?php

namespace App\Console\Commands;

use App\Services\InboundMailSyncService;
use Illuminate\Console\Command;

class InboundMailSyncCommand extends Command
{
    protected $signature = 'inbound-mail:sync {--dry-run : Count messages that would import without writing}';

    protected $description = 'Pull new messages from Microsoft Graph ingest mailbox into email_inbox_* tables';

    public function handle(InboundMailSyncService $sync): int
    {
        $dry = (bool) $this->option('dry-run');
        $result = $sync->sync($dry);

        if ($result['skipped'] !== null) {
            $this->warn($result['skipped']);
        }

        $this->info($dry
            ? "Dry run: {$result['imported']} message(s) would be imported."
            : "Imported {$result['imported']} new message(s).");

        return self::SUCCESS;
    }
}
