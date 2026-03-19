<?php

namespace App\Livewire;

use App\Http\Controllers\TimesheetController;
use App\Models\Consultant;
use App\Services\TimesheetParseService;
use Livewire\Component;
use Livewire\WithFileUploads;

class TimesheetWizard extends Component
{
    use WithFileUploads;

    public int $step = 1;

    public $file;

    public string $sourceFilePath = '';

    public string $parseFormat = '';

    /** @var list<string> */
    public array $parseColumns = [];

    /** @var list<array<string, mixed>> */
    public array $parseFlatRows = [];

    /** @var list<array<string, mixed>> */
    public array $builtRows = [];

    /** @var list<string> */
    public array $parseErrors = [];

    /** @var array{saved: int, overwrote: int, errors: list<string>}|null */
    public ?array $importResult = null;

    public string $mapConsultant = '';

    public string $mapPayStart = '';

    public string $mapPayEnd = '';

    public string $mapTotalHours = '';

    public function resetWizard(): void
    {
        $this->reset([
            'step', 'file', 'sourceFilePath', 'parseFormat', 'parseColumns', 'parseFlatRows',
            'builtRows', 'parseErrors', 'importResult', 'mapConsultant', 'mapPayStart', 'mapPayEnd', 'mapTotalHours',
        ]);
        $this->step = 1;
    }

    public function uploadFile(): void
    {
        $this->validate(['file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240']]);
        $this->parseErrors = [];
        $this->builtRows = [];
        $this->importResult = null;

        $parser = new TimesheetParseService;
        $result = $parser->parse($this->file);

        $this->sourceFilePath = $this->file->storeAs(
            'uploads/timesheets',
            now()->format('Ymd_His').'_'.$this->file->getClientOriginalName(),
            'local'
        );

        $this->parseFormat = (string) ($result['format'] ?? 'unknown');
        $this->parseColumns = $result['columns'] ?? [];
        $this->parseFlatRows = $result['rows'] ?? [];

        $saved = $result['savedMapping'] ?? null;
        if (is_array($saved)) {
            $this->mapConsultant = (string) ($saved['consultant'] ?? $saved['consultantName'] ?? '');
            $this->mapPayStart = (string) ($saved['payPeriodStart'] ?? $saved['pay_start'] ?? '');
            $this->mapPayEnd = (string) ($saved['payPeriodEnd'] ?? $saved['pay_end'] ?? '');
            $this->mapTotalHours = (string) ($saved['totalHours'] ?? $saved['hours'] ?? '');
        }

        if ($this->parseFormat === 'biweekly-template' && ! empty($result['parsedRows']) && is_array($result['parsedRows'])) {
            $this->builtRows = [];
            foreach ($result['parsedRows'] as $pr) {
                if (! is_array($pr)) {
                    continue;
                }
                $name = trim((string) ($pr['consultantName'] ?? ''));
                $cid = $this->resolveConsultantId($name);
                if ($cid === null) {
                    $this->parseErrors[] = "Unknown consultant name: {$name}";

                    continue;
                }
                $consultant = Consultant::query()->find($cid);
                if (! $consultant) {
                    continue;
                }
                $this->builtRows[] = [
                    'consultantId' => $cid,
                    'clientId' => $consultant->client_id,
                    'payPeriodStart' => $pr['payPeriodStart'],
                    'payPeriodEnd' => $pr['payPeriodEnd'],
                    'week1Hours' => $pr['week1Hours'] ?? array_fill(0, 7, 0.0),
                    'week2Hours' => $pr['week2Hours'] ?? array_fill(0, 7, 0.0),
                    'overwrite' => false,
                ];
            }
            if ($this->builtRows === [] && $this->parseErrors === []) {
                $this->parseErrors[] = 'No rows parsed from bi-weekly template.';
            }
            if ($this->builtRows !== []) {
                $this->step = 2;
            }

            return;
        }

        if ($this->parseFormat === 'flat-csv' && $this->parseColumns !== [] && $this->parseFlatRows !== []) {
            $this->step = 2;

            return;
        }

        $this->parseErrors[] = 'Unsupported or empty file format. Use the official bi-weekly template or a flat CSV with headers.';
    }

    public function applyFlatMapping(): void
    {
        $this->parseErrors = [];
        foreach (['mapConsultant' => $this->mapConsultant, 'mapPayStart' => $this->mapPayStart, 'mapPayEnd' => $this->mapPayEnd] as $label => $col) {
            if ($col === '' || ! in_array($col, $this->parseColumns, true)) {
                $this->parseErrors[] = "Invalid column mapping ({$label}).";

                return;
            }
        }
        if ($this->mapTotalHours !== '' && ! in_array($this->mapTotalHours, $this->parseColumns, true)) {
            $this->parseErrors[] = 'Invalid total hours column.';

            return;
        }

        $this->builtRows = [];
        foreach ($this->parseFlatRows as $r) {
            if (! is_array($r)) {
                continue;
            }
            $name = trim((string) ($r[$this->mapConsultant] ?? ''));
            $cid = $this->resolveConsultantId($name);
            if ($cid === null) {
                $this->parseErrors[] = "Unknown consultant: {$name}";

                continue;
            }
            $consultant = Consultant::query()->find($cid);
            if (! $consultant) {
                continue;
            }
            $startRaw = $r[$this->mapPayStart] ?? '';
            $endRaw = $r[$this->mapPayEnd] ?? '';
            $start = $this->parseSpreadsheetDate($startRaw);
            $end = $this->parseSpreadsheetDate($endRaw);
            if ($start === null || $end === null) {
                $this->parseErrors[] = "Invalid dates for row ({$name}).";

                continue;
            }
            $total = 0.0;
            if ($this->mapTotalHours !== '') {
                $total = (float) ($r[$this->mapTotalHours] ?? 0);
            }
            [$w1, $w2] = $this->distributeTotalHours($total);

            $this->builtRows[] = [
                'consultantId' => $cid,
                'clientId' => $consultant->client_id,
                'payPeriodStart' => $start,
                'payPeriodEnd' => $end,
                'week1Hours' => $w1,
                'week2Hours' => $w2,
                'overwrite' => false,
            ];
        }

        if ($this->builtRows === []) {
            $this->parseErrors[] = 'No valid rows after mapping.';
        }
    }

    public function confirmImport(): void
    {
        $this->parseErrors = [];
        if ($this->builtRows === []) {
            $this->parseErrors[] = 'Nothing to import.';

            return;
        }

        $this->step = 3;

        /** @var TimesheetController $ctrl */
        $ctrl = app(TimesheetController::class);
        $path = $this->sourceFilePath !== '' ? $this->sourceFilePath : null;
        $this->importResult = $ctrl->saveBatch($this->builtRows, $path, false, null);

        $this->step = 4;
    }

    public function finishImport(): void
    {
        $this->js('window.location.reload()');
    }

    /**
     * @return array{0: list<float>, 1: list<float>}
     */
    private function distributeTotalHours(float $total): array
    {
        $cents = max(0, (int) round($total * 100));
        $per = intdiv($cents, 14);
        $rem = $cents % 14;
        $out = [];
        for ($i = 0; $i < 14; $i++) {
            $out[] = ($per + ($i < $rem ? 1 : 0)) / 100;
        }

        return [array_slice($out, 0, 7), array_slice($out, 7, 7)];
    }

    private function parseSpreadsheetDate(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            try {
                return TimesheetParseService::serialToISO((float) $raw);
            } catch (\Throwable) {
                return null;
            }
        }
        $s = trim((string) $raw);
        $ts = strtotime($s);

        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function resolveConsultantId(string $name): ?int
    {
        if ($name === '') {
            return null;
        }
        $n = strtolower($name);
        $hit = Consultant::query()
            ->where('active', true)
            ->whereRaw('LOWER(TRIM(full_name)) = ?', [$n])
            ->value('id');

        return $hit !== null ? (int) $hit : null;
    }

    public function render()
    {
        return view('livewire.timesheet-wizard');
    }
}
