<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Consultant;
use App\Models\ConsultantOnboardingItem;
use App\Services\AppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ConsultantController extends Controller
{
    private const ONBOARDING_ITEMS = [
        'w9',
        'pay_rate_confirmed',
        'bill_rate_confirmed',
        'client_assigned',
        'start_date_set',
        'end_date_set',
        'timesheet_template_sent',
    ];

    private const MUTABLE = [
        'full_name', 'pay_rate', 'bill_rate', 'state', 'industry_type',
        'client_id', 'project_start_date', 'project_end_date',
    ];

    public function index(Request $request): JsonResponse|View
    {
        $this->authorize('account_manager');

        $rows = DB::select('
            SELECT c.*, cl.name AS client_name,
                   (SELECT COUNT(*) FROM consultant_onboarding_items WHERE consultant_id = c.id AND completed = 1) AS onboarding_complete,
                   (SELECT COUNT(*) FROM consultant_onboarding_items WHERE consultant_id = c.id) AS onboarding_total
            FROM consultants c
            LEFT JOIN clients cl ON cl.id = c.client_id
            WHERE c.active = 1
            ORDER BY c.full_name
        ');

        if ($request->expectsJson()) {
            return response()->json($rows);
        }

        $clients = Client::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('consultants.index', [
            'consultants' => $rows,
            'clients' => $clients,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorize('account_manager');
        $row = DB::selectOne('
            SELECT c.*, cl.name AS client_name
            FROM consultants c
            LEFT JOIN clients cl ON cl.id = c.client_id
            WHERE c.id = ?
        ', [$id]);
        if (! $row) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json($row);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('admin');
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'pay_rate' => ['required', 'numeric'],
            'bill_rate' => ['required', 'numeric'],
            'state' => ['required', 'string', 'size:2'],
            'industry_type' => ['nullable', 'string', 'max:50'],
            'client_id' => ['required', 'exists:clients,id'],
            'project_start_date' => ['nullable', 'date'],
            'project_end_date' => ['nullable', 'date'],
        ]);

        $consultant = DB::transaction(function () use ($data) {
            $c = Consultant::query()->create([
                'full_name' => trim($data['full_name']),
                'pay_rate' => $data['pay_rate'],
                'bill_rate' => $data['bill_rate'],
                'state' => strtoupper($data['state']),
                'industry_type' => $data['industry_type'] ?? 'other',
                'client_id' => $data['client_id'],
                'project_start_date' => $data['project_start_date'] ?? null,
                'project_end_date' => $data['project_end_date'] ?? null,
                'active' => true,
            ]);

            foreach (self::ONBOARDING_ITEMS as $item) {
                ConsultantOnboardingItem::query()->firstOrCreate(
                    ['consultant_id' => $c->id, 'item_key' => $item],
                    ['completed' => false]
                );
            }

            if (! empty($data['pay_rate'])) {
                $this->setOnboardingItem((int) $c->id, 'pay_rate_confirmed', true);
            }
            if (! empty($data['bill_rate'])) {
                $this->setOnboardingItem((int) $c->id, 'bill_rate_confirmed', true);
            }
            $this->setOnboardingItem((int) $c->id, 'client_assigned', true);
            if (! empty($data['project_start_date'])) {
                $this->setOnboardingItem((int) $c->id, 'start_date_set', true);
            }
            if (! empty($data['project_end_date'])) {
                $this->setOnboardingItem((int) $c->id, 'end_date_set', true);
            }

            AppService::auditLog('consultants', (int) $c->id, 'INSERT', [], $c->only(self::MUTABLE));

            return $c;
        });

        return response()->json($consultant, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorize('admin');
        $consultant = Consultant::query()->find($id);
        if (! $consultant) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'pay_rate' => ['required', 'numeric'],
            'bill_rate' => ['required', 'numeric'],
            'state' => ['required', 'string', 'size:2'],
            'industry_type' => ['nullable', 'string', 'max:50'],
            'client_id' => ['required', 'exists:clients,id'],
            'project_start_date' => ['nullable', 'date'],
            'project_end_date' => ['nullable', 'date'],
        ]);

        $old = $consultant->only(self::MUTABLE);

        DB::transaction(function () use ($consultant, $data, $old) {
            $consultant->update([
                'full_name' => trim($data['full_name']),
                'pay_rate' => $data['pay_rate'],
                'bill_rate' => $data['bill_rate'],
                'state' => strtoupper($data['state']),
                'industry_type' => $data['industry_type'] ?? 'other',
                'client_id' => $data['client_id'],
                'project_start_date' => $data['project_start_date'] ?? null,
                'project_end_date' => $data['project_end_date'] ?? null,
            ]);

            $new = $consultant->fresh()->only(self::MUTABLE);
            foreach (self::MUTABLE as $field) {
                if (self::stringify($old[$field] ?? null) !== self::stringify($new[$field] ?? null)) {
                    $action = in_array($field, ['pay_rate', 'bill_rate'], true) ? 'RATE_CHANGE' : 'UPDATE';
                    AppService::auditLog('consultants', (int) $consultant->id, $action, [$field => $old[$field] ?? null], [$field => $new[$field] ?? null]);
                }
            }

            if (! empty($data['client_id']) && empty($old['client_id'])) {
                $this->setOnboardingItem((int) $consultant->id, 'client_assigned', true);
            }
            if (! empty($data['project_start_date']) && empty($old['project_start_date'])) {
                $this->setOnboardingItem((int) $consultant->id, 'start_date_set', true);
            }
            if (! empty($data['project_end_date']) && empty($old['project_end_date'])) {
                $this->setOnboardingItem((int) $consultant->id, 'end_date_set', true);
            }
        });

        return response()->json($consultant->fresh());
    }

    public function patchField(Request $request, string $id): JsonResponse
    {
        $this->authorize('admin');
        $consultant = Consultant::query()->find($id);
        if (! $consultant) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $data = $request->validate([
            'field' => ['required', 'string', 'in:pay_rate,bill_rate,state,client_id,project_start_date,project_end_date'],
            'value' => ['nullable'],
        ]);

        $field = $data['field'];
        $value = ($data['value'] === '' || $data['value'] === null) ? null : $data['value'];

        $old = [$field => $consultant->$field];
        $consultant->update([$field => $value]);
        $new = [$field => $consultant->fresh()->$field];

        $action = in_array($field, ['pay_rate', 'bill_rate'], true) ? 'RATE_CHANGE' : 'UPDATE';
        AppService::auditLog('consultants', (int) $consultant->id, $action, $old, $new);

        // Sync onboarding flags
        DB::transaction(function () use ($consultant, $field, $value, $old) {
            if ($field === 'client_id' && ! empty($value) && empty($old['client_id'])) {
                $this->setOnboardingItem((int) $consultant->id, 'client_assigned', true);
            }
            if ($field === 'project_start_date' && ! empty($value) && empty($old['project_start_date'])) {
                $this->setOnboardingItem((int) $consultant->id, 'start_date_set', true);
            }
            if ($field === 'project_end_date' && ! empty($value) && empty($old['project_end_date'])) {
                $this->setOnboardingItem((int) $consultant->id, 'end_date_set', true);
            }
            if ($field === 'pay_rate' && ! empty($value)) {
                $this->setOnboardingItem((int) $consultant->id, 'pay_rate_confirmed', true);
            }
            if ($field === 'bill_rate' && ! empty($value)) {
                $this->setOnboardingItem((int) $consultant->id, 'bill_rate_confirmed', true);
            }
        });

        return response()->json(['success' => true]);
    }

    public function destroy(string $id): JsonResponse
    {
        return $this->deactivate($id);
    }

    public function deactivate(string $id): JsonResponse
    {
        $this->authorize('admin');
        $consultant = Consultant::query()->where('id', $id)->where('active', true)->first();
        if (! $consultant) {
            return response()->json(['error' => 'Consultant not found or already inactive'], 404);
        }

        $consultant->update(['active' => false]);
        AppService::auditLog('consultants', (int) $consultant->id, 'DELETE', ['active' => true], ['active' => false]);

        return response()->json(['ok' => true]);
    }

    public function onboardingIndex(string $id): JsonResponse
    {
        $this->authorize('account_manager');
        $items = ConsultantOnboardingItem::query()->where('consultant_id', $id)->orderBy('id')->get();

        return response()->json($items);
    }

    public function onboardingUpdate(Request $request, string $id): JsonResponse
    {
        $this->authorize('admin');
        $data = $request->validate([
            'item' => ['required', 'string', 'max:64'],
            'completed' => ['required', 'boolean'],
        ]);

        $this->setOnboardingItem((int) $id, $data['item'], (bool) $data['completed']);
        AppService::auditLog('consultants', (int) $id, 'UPDATE', [], ['onboarding_'.$data['item'] => $data['completed'] ? 'completed' : 'unchecked']);

        return response()->json(['ok' => true]);
    }

    public function endDateAlerts(Request $request): JsonResponse
    {
        $this->authorize('account_manager');
        $days = (int) $request->query('days', 30);

        $rows = DB::select('
            SELECT c.*, cl.name AS client_name
            FROM consultants c
            LEFT JOIN clients cl ON cl.id = c.client_id
            WHERE c.active = 1
              AND c.project_end_date IS NOT NULL
              AND c.project_end_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY c.project_end_date ASC
        ', [$days]);

        return response()->json($rows);
    }

    public function extendEndDate(Request $request, string $id): JsonResponse
    {
        $this->authorize('admin');
        $data = $request->validate(['project_end_date' => ['required', 'date']]);
        $consultant = Consultant::query()->find($id);
        if (! $consultant) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $old = $consultant->project_end_date?->format('Y-m-d');
        $consultant->update(['project_end_date' => $data['project_end_date']]);
        $this->setOnboardingItem((int) $id, 'end_date_set', true);
        AppService::auditLog('consultants', (int) $id, 'UPDATE', ['project_end_date' => $old], ['project_end_date' => $data['project_end_date']]);

        return response()->json(['ok' => true]);
    }

    public function w9Upload(Request $request, string $id): JsonResponse
    {
        $this->authorize('admin');
        $request->validate(['w9' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
        $consultant = Consultant::query()->find($id);
        if (! $consultant) {
            return response()->json(['error' => 'Not found'], 404);
        }

        if ($consultant->w9_file_path) {
            Storage::disk('local')->delete('uploads/w9s/'.$consultant->w9_file_path);
        }

        $name = "consultant_{$id}.pdf";
        $request->file('w9')->storeAs('uploads/w9s', $name, 'local');
        $consultant->update(['w9_file_path' => $name, 'w9_on_file' => true]);
        $this->setOnboardingItem((int) $id, 'w9', true);
        AppService::auditLog('consultants', (int) $id, 'UPDATE', [], ['w9_file_path' => $name]);

        return response()->json(['ok' => true, 'path' => $name]);
    }

    public function w9Path(Request $request, string $id): JsonResponse|BinaryFileResponse
    {
        $this->authorize('account_manager');
        $consultant = Consultant::query()->find($id);
        if (! $consultant || ! $consultant->w9_file_path) {
            if ($request->expectsJson()) {
                return response()->json(null);
            }

            abort(404);
        }
        $full = storage_path('app/uploads/w9s/'.$consultant->w9_file_path);
        if (! is_file($full)) {
            if ($request->expectsJson()) {
                return response()->json(['missing' => true]);
            }

            abort(404);
        }

        if (! $request->expectsJson()) {
            return response()->file($full, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $consultant->w9_file_path).'"',
            ]);
        }

        return response()->json(['path' => $full, 'fileName' => $consultant->w9_file_path]);
    }

    public function w9Delete(string $id): JsonResponse
    {
        $this->authorize('admin');
        $consultant = Consultant::query()->find($id);
        if (! $consultant) {
            return response()->json(['error' => 'Not found'], 404);
        }

        if ($consultant->w9_file_path) {
            Storage::disk('local')->delete('uploads/w9s/'.$consultant->w9_file_path);
        }
        $old = $consultant->w9_file_path;
        $consultant->update(['w9_file_path' => null, 'w9_on_file' => false]);
        $this->setOnboardingItem((int) $id, 'w9', false);
        AppService::auditLog('consultants', (int) $id, 'UPDATE', ['w9_file_path' => $old], ['w9_file_path' => null]);

        return response()->json(['ok' => true]);
    }

    private function setOnboardingItem(int $consultantId, string $itemKey, bool $completed): void
    {
        ConsultantOnboardingItem::query()->updateOrCreate(
            ['consultant_id' => $consultantId, 'item_key' => $itemKey],
            ['completed' => $completed]
        );
    }

    private static function stringify(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }

        return (string) $v;
    }
}
