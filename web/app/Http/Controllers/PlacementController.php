<?php

namespace App\Http\Controllers;

use App\Models\Placement;
use App\Services\AppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlacementController extends Controller
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

    public function index(Request $request): JsonResponse|View
    {
        $this->authorize('viewAny', Placement::class);

        $user = Auth::user();
        $query = Placement::query()
            ->with(['consultant', 'client', 'placedBy'])
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if ($user->role === 'employee') {
            if ($user->consultant_id === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('consultant_id', $user->consultant_id);
            }
        }

        $placements = $query->get();

        if ($request->expectsJson()) {
            return response()->json($placements);
        }

        return view('placements.index', [
            'placements' => $placements,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Placement::class);

        $data = $this->validatedPlacementPayload($request);

        $placement = Placement::query()->create(array_merge($data, [
            'placed_by' => Auth::id(),
        ]));

        AppService::auditLog(
            'placements',
            (int) $placement->id,
            'INSERT',
            [],
            $placement->only(self::AUDIT_FIELDS),
        );

        if ($request->expectsJson()) {
            return response()->json($placement->load(['consultant', 'client', 'placedBy']), 201);
        }

        return back()->with('success', 'Placement created.');
    }

    public function update(Request $request, Placement $placement): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $placement);

        $data = $this->validatedPlacementPayload($request);

        $before = $placement->only(self::AUDIT_FIELDS);

        $placement->fill($data);
        $placement->save();

        AppService::auditLog(
            'placements',
            (int) $placement->id,
            'UPDATE',
            $before,
            $placement->fresh()->only(self::AUDIT_FIELDS),
        );

        if ($request->expectsJson()) {
            return response()->json($placement->load(['consultant', 'client', 'placedBy']));
        }

        return back()->with('success', 'Placement updated.');
    }

    public function destroy(Request $request, Placement $placement): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $placement);

        $before = $placement->only(self::AUDIT_FIELDS);

        $placement->status = 'cancelled';
        $placement->save();

        AppService::auditLog(
            'placements',
            (int) $placement->id,
            'UPDATE',
            $before,
            $placement->fresh()->only(self::AUDIT_FIELDS),
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $placement->load(['consultant', 'client', 'placedBy'])]);
        }

        return back()->with('success', 'Placement cancelled.');
    }

    /**
     * @return array{
     *     consultant_id: int,
     *     client_id: int,
     *     job_title: string|null,
     *     start_date: string,
     *     end_date: string|null,
     *     pay_rate: string,
     *     bill_rate: string,
     *     status: string,
     *     notes: string|null
     * }
     */
    private function validatedPlacementPayload(Request $request): array
    {
        $data = $request->validate([
            'consultant_id' => ['required', 'integer', 'exists:consultants,id'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'pay_rate' => ['required', 'numeric', 'min:0'],
            'bill_rate' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'ended', 'cancelled'])],
            'notes' => ['nullable', 'string'],
        ]);

        return [
            'consultant_id' => (int) $data['consultant_id'],
            'client_id' => (int) $data['client_id'],
            'job_title' => isset($data['job_title']) ? trim((string) $data['job_title']) : null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'pay_rate' => $data['pay_rate'],
            'bill_rate' => $data['bill_rate'],
            'status' => $data['status'],
            'notes' => isset($data['notes']) ? (string) $data['notes'] : null,
        ];
    }
}
