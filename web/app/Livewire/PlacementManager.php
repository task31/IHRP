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
        'consultant_id',
        'client_id',
        'placed_by',
        'job_title',
        'start_date',
        'end_date',
        'pay_rate',
        'bill_rate',
        'status',
        'notes',
    ];

    public array $filters = [
        'consultant_id' => '',
        'client_id' => '',
        'status' => '',
    ];

    public bool $showForm = false;

    public ?int $editingId = null;

    public $consultant_id = '';

    public $client_id = '';

    public $job_title = '';

    public $start_date = '';

    public $end_date = '';

    public $pay_rate = '';

    public $bill_rate = '';

    public $notes = '';

    public $status = 'active';

    /** @var \Illuminate\Database\Eloquent\Collection<int, Placement>|null */
    public $placements;

    /** @var list<array{id: int, label: string}> */
    public array $consultantOptions = [];

    /** @var list<array{id: int, label: string}> */
    public array $clientOptions = [];

    public function mount(): void
    {
        $this->loadDropdownLists();
        $this->loadPlacements();
    }

    public function loadDropdownLists(): void
    {
        if (Gate::allows('account_manager')) {
            $this->consultantOptions = Consultant::query()
                ->orderBy('full_name')
                ->get(['id', 'full_name'])
                ->map(fn (Consultant $c) => ['id' => (int) $c->id, 'label' => (string) $c->full_name])
                ->values()
                ->all();
        } else {
            $cid = auth()->user()?->consultant_id;
            $this->consultantOptions = $cid
                ? Consultant::query()
                    ->where('id', $cid)
                    ->get(['id', 'full_name'])
                    ->map(fn (Consultant $c) => ['id' => (int) $c->id, 'label' => (string) $c->full_name])
                    ->values()
                    ->all()
                : [];
        }

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

        if (! Gate::allows('account_manager')) {
            $query->where('consultant_id', auth()->user()->consultant_id ?? 0);
        }

        if ($this->filters['consultant_id'] !== '' && $this->filters['consultant_id'] !== null) {
            $query->where('consultant_id', (int) $this->filters['consultant_id']);
        }
        if ($this->filters['client_id'] !== '' && $this->filters['client_id'] !== null) {
            $query->where('client_id', (int) $this->filters['client_id']);
        }
        if ($this->filters['status'] !== '' && $this->filters['status'] !== null) {
            $query->where('status', (string) $this->filters['status']);
        }

        $this->placements = $query->get();
    }

    public function updated($name): void
    {
        if (is_string($name) && str_starts_with($name, 'filters.')) {
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
        $this->consultant_id = (string) $placement->consultant_id;
        $this->client_id = (string) $placement->client_id;
        $this->job_title = (string) ($placement->job_title ?? '');
        $this->start_date = $placement->start_date?->format('Y-m-d') ?? '';
        $this->end_date = $placement->end_date?->format('Y-m-d') ?? '';
        $this->pay_rate = (string) $placement->pay_rate;
        $this->bill_rate = (string) $placement->bill_rate;
        $this->notes = (string) ($placement->notes ?? '');
        $this->status = (string) $placement->status;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless(Gate::allows('account_manager'), 403);

        $this->validate([
            'consultant_id' => ['required', 'integer', 'exists:consultants,id'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'pay_rate' => ['required', 'numeric', 'min:0'],
            'bill_rate' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'ended', 'cancelled'])],
        ]);

        $payload = [
            'consultant_id' => (int) $this->consultant_id,
            'client_id' => (int) $this->client_id,
            'job_title' => $this->job_title !== '' ? trim((string) $this->job_title) : null,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date !== '' ? $this->end_date : null,
            'pay_rate' => $this->pay_rate,
            'bill_rate' => $this->bill_rate,
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
        if (! in_array($status, ['ended', 'cancelled'], true)) {
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
        $this->consultant_id = '';
        $this->client_id = '';
        $this->job_title = '';
        $this->start_date = '';
        $this->end_date = '';
        $this->pay_rate = '';
        $this->bill_rate = '';
        $this->notes = '';
        $this->status = 'active';
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.placement-manager');
    }
}
