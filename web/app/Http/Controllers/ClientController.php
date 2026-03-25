<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use App\Services\AppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    private const MUTABLE = [
        'name', 'billing_contact_name', 'billing_address',
        'email', 'smtp_email', 'payment_terms', 'total_budget',
        'account_manager_id',
    ];

    public function index(Request $request): JsonResponse|View
    {
        $this->authorize('account_manager');

        $clients = Client::query()
            ->with(['accountManager:id,name'])
            ->orderBy('name')
            ->get();

        if ($request->expectsJson()) {
            return response()->json(
                Client::query()
                    ->where('active', true)
                    ->with(['accountManager:id,name'])
                    ->orderBy('name')
                    ->get()
            );
        }

        $spentByClient = DB::table('timesheets')
            ->select('client_id', DB::raw('COALESCE(SUM(total_client_billable), 0) as spent'))
            ->groupBy('client_id')
            ->pluck('spent', 'client_id');

        $accountManagers = Auth::user()->isAdmin()
            ? User::query()
                ->where('role', 'account_manager')
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        return view('clients.index', [
            'clients' => $clients,
            'spentByClient' => $spentByClient,
            'accountManagers' => $accountManagers,
        ]);
    }

    public function show(string $id): JsonResponse|Response
    {
        $this->authorize('account_manager');
        $client = Client::query()->find($id);
        if (! $client) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json($client);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('admin');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'billing_contact_name' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'smtp_email' => ['nullable', 'email', 'max:255'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'total_budget' => ['nullable', 'numeric'],
            'account_manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', 'account_manager'),
            ],
        ]);

        $client = Client::query()->create([
            'name' => trim($data['name']),
            'billing_contact_name' => isset($data['billing_contact_name']) ? trim((string) $data['billing_contact_name']) : null,
            'billing_address' => isset($data['billing_address']) ? trim((string) $data['billing_address']) : null,
            'email' => isset($data['email']) ? trim((string) $data['email']) : null,
            'smtp_email' => isset($data['smtp_email']) ? trim((string) $data['smtp_email']) : null,
            'payment_terms' => $data['payment_terms'] ?? 'Net 30',
            'total_budget' => $data['total_budget'] ?? 0,
            'account_manager_id' => $data['account_manager_id'] ?? null,
            'active' => true,
        ]);

        AppService::auditLog('clients', (int) $client->id, 'INSERT', [], $client->only(self::MUTABLE));

        return response()->json($client, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorize('admin');
        $client = Client::query()->find($id);
        if (! $client) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'billing_contact_name' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'smtp_email' => ['nullable', 'email', 'max:255'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'total_budget' => ['nullable', 'numeric'],
            'account_manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', 'account_manager'),
            ],
        ]);

        $old = $client->only(self::MUTABLE);

        $client->update([
            'name' => trim($data['name']),
            'billing_contact_name' => isset($data['billing_contact_name']) ? trim((string) $data['billing_contact_name']) : null,
            'billing_address' => isset($data['billing_address']) ? trim((string) $data['billing_address']) : null,
            'email' => isset($data['email']) ? trim((string) $data['email']) : null,
            'smtp_email' => isset($data['smtp_email']) ? trim((string) $data['smtp_email']) : null,
            'payment_terms' => $data['payment_terms'] ?? 'Net 30',
            'total_budget' => $data['total_budget'] ?? $client->total_budget,
            'account_manager_id' => $data['account_manager_id'] ?? null,
        ]);

        $new = $client->fresh()->only(self::MUTABLE);
        if ($old !== $new) {
            AppService::auditLog('clients', (int) $client->id, 'UPDATE', $old, $new);
        }

        return response()->json($client->fresh());
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorize('admin');
        $client = Client::query()->where('id', $id)->where('active', true)->first();
        if (! $client) {
            return response()->json(['error' => 'Client not found or already inactive'], 404);
        }

        $client->update(['active' => false]);
        AppService::auditLog('clients', (int) $client->id, 'DELETE', ['active' => true], ['active' => false]);

        return response()->json(['ok' => true]);
    }
}
