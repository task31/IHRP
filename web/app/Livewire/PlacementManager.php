<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\Consultant;
use App\Models\Placement;
use App\Services\AppService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PlacementManager extends Component
{
    /** @var list<string> */
    private const AUDIT_FIELDS = [
        'consultant_name',
        'client_id',
        'placed_by',
        'job_title',
        'start_date',
        'end_date',
        'pay_rate',
        'bill_rate',
        'po_number',
        'status',
        'notes',
    ];

    public array $filters = [
        'consultant_name' => '',
        'client_id' => '',
        'status' => '',
    ];

    public int $page = 1;
    public int $perPage = 50;
    public int $totalPlacements = 0;

    public bool $showForm = false;

    public ?int $editingId = null;

    public $consultant_name = '';

    public $client_id = '';

    public $job_title = '';

    public $start_date = '';

    public $end_date = '';

    public $pay_rate = '';

    public $bill_rate = '';

    public string $po_number = '';

    public $notes = '';

    public $status = 'active';

    /** @var \Illuminate\Database\Eloquent\Collection<int, Placement>|null */
    public $placements;

    /** @var list<array{id: int, label: string}> */
    public array $clientOptions = [];

    public function mount(): void
    {
        $this->loadDropdownLists();
        $this->loadPlacements();
    }

    public function loadDropdownLists(): void
    {
        $this->clientOptions = Client::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $c) => ['id' => (int) $c->id, 'label' => (string) $c->name])
            ->values()
            ->all();
    }

    public function loadPlacements(): void
    {
        $query = Placement::query()
            ->with(['consultant', 'client', 'placedBy'])
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if (Gate::allows('account_manager') && ! Gate::allows('admin')) {
            $query->where('placed_by', auth()->id());
        }

        if ($this->filters['consultant_name'] !== '' && $this->filters['consultant_name'] !== null) {
            $query->where('consultant_name', 'like', '%' . $this->filters['consultant_name'] . '%');
        }
        if ($this->filters['client_id'] !== '' && $this->filters['client_id'] !== null) {
            $query->where('client_id', (int) $this->filters['client_id']);
        }
        if ($this->filters['status'] !== '' && $this->filters['status'] !== null) {
            $query->where('status', (string) $this->filters['status']);
        }

        $result = $query->paginate($this->perPage, ['*'], 'page', $this->page);
        $this->placements = $result->items();
        $this->totalPlacements = $result->total();
    }

    public function updated($name): void
    {
        if (is_string($name) && str_starts_with($name, 'filters.')) {
            $this->page = 1;
            $this->loadPlacements();
        }
    }

    public function openCreate(): void
    {
        abort_unless(Gate::allows('account_manager'), 403);
        $this->editingId = null;
        $this->resetFormFields();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        abort_unless(Gate::allows('account_manager'), 403);
        $placement = Placement::query()->findOrFail($id);
        Gate::authorize('update', $placement);

        $this->editingId = $id;
        $this->consultant_name = (string) ($placement->consultant_name ?? $placement->consultant?->full_name ?? '');
        $this->client_id = (string) $placement->client_id;
        $this->job_title = (string) ($placement->job_title ?? '');
        $this->start_date = $placement->start_date?->format('Y-m-d') ?? '';
        $this->end_date = $placement->end_date?->format('Y-m-d') ?? '';
        $this->pay_rate = (string) $placement->pay_rate;
        $this->bill_rate = (string) $placement->bill_rate;
        $this->po_number = (string) ($placement->po_number ?? '');
        $this->notes = (string) ($placement->notes ?? '');
        $this->status = (string) $placement->status;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless(Gate::allows('account_manager'), 403);

        $this->validate([
            'consultant_name' => ['required', 'string', 'max:255'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'pay_rate' => ['required', 'numeric', 'min:0'],
            'bill_rate' => ['required', 'numeric', 'min:0'],
            'po_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'ended', 'cancelled'])],
        ]);

        $consultantName = trim((string) $this->consultant_name);
        $clientId = (int) $this->client_id;

        $consultant = Consultant::query()
            ->whereRaw('LOWER(full_name) = ?', [strtolower($consultantName)])
            ->first();

        if (! $consultant) {
            $consultant = Consultant::query()->create([
                'full_name' => $consultantName,
                'pay_rate' => $this->pay_rate,
                'bill_rate' => $this->bill_rate,
                'state' => '',
                'industry_type' => 'other',
                'client_id' => $clientId,
                'project_start_date' => $this->start_date !== '' ? $this->start_date : null,
            ]);
        }

        $payload = [
            'consultant_name' => $consultantName,
            'consultant_id' => $consultant->id,
            'client_id' => $clientId,
            'job_title' => $this->job_title !== '' ? trim((string) $this->job_title) : null,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date !== '' ? $this->end_date : null,
            'pay_rate' => $this->pay_rate,
            'bill_rate' => $this->bill_rate,
            'po_number' => $this->po_number !== '' ? trim($this->po_number) : null,
            'notes' => $this->notes !== '' ? (string) $this->notes : null,
            'status' => $this->status,
        ];

        if ($this->editingId === null) {
            Gate::authorize('create', Placement::class);

            $placement = Placement::query()->create(array_merge($payload, [
                'placed_by' => auth()->id(),
            ]));

            AppService::auditLog(
                'placements',
                (int) $placement->id,
                'INSERT',
                [],
                $placement->only(self::AUDIT_FIELDS),
            );
        } else {
            $placement = Placement::query()->findOrFail($this->editingId);
            Gate::authorize('update', $placement);

            $before = $placement->only(self::AUDIT_FIELDS);
            $placement->fill($payload);
            $placement->save();

            AppService::auditLog(
                'placements',
                (int) $placement->id,
                'UPDATE',
                $before,
                $placement->fresh()->only(self::AUDIT_FIELDS),
            );
        }

        $this->cancelForm();
        $this->loadPlacements();
    }

    public function updateStatus(int $id, string $status): void
    {
        abort_unless(Gate::allows('account_manager'), 403);

        $status = strtolower(trim($status));
        if (! in_array($status, ['active', 'ended', 'cancelled'], true)) {
            return;
        }

        $placement = Placement::query()->findOrFail($id);
        Gate::authorize('update', $placement);

        $before = $placement->only(self::AUDIT_FIELDS);
        $placement->status = $status;
        $placement->save();

        AppService::auditLog(
            'placements',
            (int) $placement->id,
            'UPDATE',
            $before,
            $placement->fresh()->only(self::AUDIT_FIELDS),
        );

        if ($placement->consultant_id) {
            $consultant = Consultant::query()->find($placement->consultant_id);
            if ($consultant) {
                if ($status === 'ended' || $status === 'cancelled') {
                    $consultant->project_end_date = now()->toDateString();
                } elseif ($status === 'active') {
                    $consultant->project_end_date = null;
                }
                $consultant->save();
            }
        }

        $this->loadPlacements();
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetFormFields();
    }

    private function resetFormFields(): void
    {
        $this->consultant_name = '';
        $this->client_id = '';
        $this->job_title = '';
        $this->start_date = '';
        $this->end_date = '';
        $this->pay_rate = '';
        $this->bill_rate = '';
        $this->po_number = '';
        $this->notes = '';
        $this->status = 'active';
        $this->resetValidation();
    }

    public function nextPage(): void
    {
        if (($this->page * $this->perPage) < $this->totalPlacements) {
            $this->page++;
            $this->loadPlacements();
        }
    }

    public function prevPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
            $this->loadPlacements();
        }
    }

    public function render()
    {
        return view('livewire.placement-manager');
    }
}
