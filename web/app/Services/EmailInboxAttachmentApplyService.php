<?php

namespace App\Services;

use App\Http\Controllers\TimesheetController;
use App\Models\Consultant;
use App\Models\ConsultantOnboardingItem;
use App\Models\EmailInboxAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EmailInboxAttachmentApplyService
{
    public static function attachmentIsPdf(EmailInboxAttachment $attachment): bool
    {
        $fn = Str::lower($attachment->filename);
        if (Str::endsWith($fn, '.pdf')) {
            return true;
        }
        $ct = Str::lower((string) $attachment->content_type);

        return str_contains($ct, 'pdf');
    }

    public static function attachmentIsTimesheetable(EmailInboxAttachment $attachment): bool
    {
        $fn = Str::lower($attachment->filename);
        foreach (['.xlsx', '.csv', '.txt'] as $ext) {
            if (Str::endsWith($fn, $ext)) {
                return true;
            }
        }
        $ct = Str::lower((string) $attachment->content_type);

        return str_contains($ct, 'spreadsheet')
            || str_contains($ct, 'excel')
            || str_contains($ct, 'csv')
            || $ct === 'text/plain';
    }

    public function applyW9(EmailInboxAttachment $attachment, int $consultantId): void
    {
        if (! self::attachmentIsPdf($attachment)) {
            throw ValidationException::withMessages([
                'attachment' => ['This attachment is not a PDF.'],
            ]);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($attachment->storage_path)) {
            throw ValidationException::withMessages([
                'attachment' => ['Attachment file is missing from storage.'],
            ]);
        }

        $consultant = Consultant::query()->find($consultantId);
        if (! $consultant) {
            throw ValidationException::withMessages([
                'consultant_id' => ['Consultant not found.'],
            ]);
        }

        $srcFull = $disk->path($attachment->storage_path);
        $name = "consultant_{$consultantId}.pdf";

        if ($consultant->w9_file_path) {
            $disk->delete('uploads/w9s/'.$consultant->w9_file_path);
        }

        $disk->put('uploads/w9s/'.$name, file_get_contents($srcFull) ?: '');

        $consultant->update(['w9_file_path' => $name, 'w9_on_file' => true]);

        ConsultantOnboardingItem::query()->updateOrCreate(
            ['consultant_id' => $consultantId, 'item_key' => 'w9'],
            ['completed' => true]
        );

        AppService::auditLog('consultants', $consultantId, 'UPDATE', [], [
            'w9_file_path' => $name,
            'source' => 'email_inbox',
            'email_inbox_attachment_id' => $attachment->id,
        ]);
    }

    public function applyContract(EmailInboxAttachment $attachment, int $consultantId): void
    {
        if (! self::attachmentIsPdf($attachment)) {
            throw ValidationException::withMessages([
                'attachment' => ['This attachment is not a PDF.'],
            ]);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($attachment->storage_path)) {
            throw ValidationException::withMessages([
                'attachment' => ['Attachment file is missing from storage.'],
            ]);
        }

        $consultant = Consultant::query()->find($consultantId);
        if (! $consultant) {
            throw ValidationException::withMessages([
                'consultant_id' => ['Consultant not found.'],
            ]);
        }

        $srcFull = $disk->path($attachment->storage_path);
        $name = "consultant_{$consultantId}.pdf";

        if ($consultant->contract_file_path) {
            $disk->delete('uploads/contracts/'.$consultant->contract_file_path);
        }

        $disk->put('uploads/contracts/'.$name, file_get_contents($srcFull) ?: '');

        $consultant->update(['contract_file_path' => $name, 'contract_on_file' => true]);

        ConsultantOnboardingItem::query()->updateOrCreate(
            ['consultant_id' => $consultantId, 'item_key' => 'msa_contract'],
            ['completed' => true]
        );

        AppService::auditLog('consultants', $consultantId, 'UPDATE', [], [
            'contract_file_path' => $name,
            'source' => 'email_inbox',
            'email_inbox_attachment_id' => $attachment->id,
        ]);
    }

    /**
     * @return array{saved: int, overwrote: int, errors: list<string>}
     */
    public function applyTimesheet(EmailInboxAttachment $attachment, int $consultantId, bool $overwrite): array
    {
        if (! self::attachmentIsTimesheetable($attachment)) {
            throw ValidationException::withMessages([
                'attachment' => ['Only .xlsx, .csv, or .txt timesheet files can be imported from the inbox.'],
            ]);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($attachment->storage_path)) {
            throw ValidationException::withMessages([
                'attachment' => ['Attachment file is missing from storage.'],
            ]);
        }

        $srcFull = $disk->path($attachment->storage_path);
        if (! is_readable($srcFull)) {
            throw ValidationException::withMessages([
                'attachment' => ['Attachment file is not readable.'],
            ]);
        }

        $size = filesize($srcFull);
        if ($size === false || $size > 10240 * 1024) {
            throw ValidationException::withMessages([
                'attachment' => ['File must be 10 MB or smaller.'],
            ]);
        }

        $consultant = Consultant::query()->find($consultantId);
        if (! $consultant) {
            throw ValidationException::withMessages([
                'consultant_id' => ['Consultant not found.'],
            ]);
        }

        if (! $consultant->client_id) {
            throw ValidationException::withMessages([
                'consultant_id' => ['Assign a client to this consultant before importing a timesheet.'],
            ]);
        }

        $uploaded = new UploadedFile(
            $srcFull,
            $attachment->filename,
            $attachment->content_type ?: null,
            null,
            true
        );

        $parser = app(TimesheetParseService::class);
        $result = $parser->parse($uploaded);

        if (($result['format'] ?? '') !== 'biweekly-template') {
            throw ValidationException::withMessages([
                'attachment' => [
                    'Only the official bi-weekly Excel template can be imported from the inbox. For CSV or other formats, use Timesheets → Upload wizard.',
                ],
            ]);
        }

        $parsedRows = $result['parsedRows'] ?? null;
        if (! is_array($parsedRows) || $parsedRows === []) {
            throw ValidationException::withMessages([
                'attachment' => ['Could not read hours from this template.'],
            ]);
        }

        if (count($parsedRows) > 1) {
            throw ValidationException::withMessages([
                'attachment' => [
                    'This file appears to contain multiple blocks. Open Timesheets and use the import wizard instead.',
                ],
            ]);
        }

        $pr = $parsedRows[0];
        if (! is_array($pr)) {
            throw ValidationException::withMessages([
                'attachment' => ['Invalid parsed row.'],
            ]);
        }

        $storedName = now()->format('Ymd_His').'_inbox_'.$attachment->id.'_'.basename($attachment->filename);
        $storedPath = 'uploads/timesheets/'.$storedName;
        $disk->put($storedPath, file_get_contents($srcFull) ?: '');

        $row = [
            'consultantId' => $consultant->id,
            'clientId' => $consultant->client_id,
            'payPeriodStart' => $pr['payPeriodStart'],
            'payPeriodEnd' => $pr['payPeriodEnd'],
            'week1Hours' => $pr['week1Hours'] ?? array_fill(0, 7, 0.0),
            'week2Hours' => $pr['week2Hours'] ?? array_fill(0, 7, 0.0),
            'overwrite' => $overwrite,
        ];

        /** @var TimesheetController $ctrl */
        $ctrl = app(TimesheetController::class);

        return $ctrl->saveBatch([$row], $storedPath, false, null);
    }
}
