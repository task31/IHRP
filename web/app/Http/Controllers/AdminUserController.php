<?php

namespace App\Http\Controllers;

use App\Models\Consultant;
use App\Models\EmailInboxMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $this->authorize('admin');

        $users = User::query()
            ->with('consultant:id,full_name,active')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $inboxSearch = trim((string) $request->query('inbox_search', ''));

        $inboxConsultants = Schema::hasTable('consultants')
            ? Consultant::query()->where('active', true)->orderBy('full_name')->get(['id', 'full_name'])
            : collect();

        $inboxMessages = Schema::hasTable('email_inbox_messages')
            ? EmailInboxMessage::query()
                ->with('attachments')
                ->latest('received_at')
                ->when($inboxSearch !== '', function ($q) use ($inboxSearch) {
                    $term = '%'.addcslashes($inboxSearch, '%_\\').'%';
                    $q->where(function ($inner) use ($term) {
                        $inner->where('subject', 'like', $term)
                            ->orWhere('from_name', 'like', $term)
                            ->orWhere('from_email', 'like', $term)
                            ->orWhere('body_preview', 'like', $term)
                            ->orWhere('body_plain', 'like', $term);
                    });
                })
                ->paginate(12, ['*'], 'inbox_page')
                ->withQueryString()
            : new LengthAwarePaginator([], 0, 12, 1, [
                'path' => $request->url(),
                'pageName' => 'inbox_page',
            ]);

        return view('admin.users.index', compact('users', 'inboxMessages', 'inboxSearch', 'inboxConsultants'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->authorize('admin');

        $consultants = $this->consultantsForUserForm();

        return view('admin.users.create', compact('consultants'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:admin,account_manager'],
            'consultant_id' => ['nullable', 'exists:consultants,id'],
            'active' => ['nullable', 'boolean'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'consultant_id' => $this->normalizedConsultantId($validated['role'], $validated['consultant_id'] ?? null),
            'active' => (bool) ($validated['active'] ?? true),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): RedirectResponse
    {
        $this->authorize('admin');

        return redirect()->route('admin.users.edit', $id);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user): View
    {
        $this->authorize('admin');

        $consultants = $this->consultantsForUserForm($user);

        return view('admin.users.edit', compact('user', 'consultants'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'in:admin,account_manager'],
            'consultant_id' => ['nullable', 'exists:consultants,id'],
            'active' => ['nullable', 'boolean'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'consultant_id' => $this->normalizedConsultantId($validated['role'], $validated['consultant_id'] ?? null),
            'active' => (bool) ($validated['active'] ?? false),
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $user->update($payload);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('admin');

        if ($user->id === auth()->id()) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'You cannot deactivate your own account.');
        }

        $user->update(['active' => false]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deactivated.');
    }

    /**
     * Active consultants plus, on edit, the user's current link even if that consultant is inactive.
     *
     * @return Collection<int, Consultant>
     */
    private function consultantsForUserForm(?User $user = null): Collection
    {
        $q = Consultant::query()
            ->where(function ($q) use ($user) {
                $q->where('active', true);
                if ($user?->consultant_id) {
                    $q->orWhere('id', $user->consultant_id);
                }
            })
            ->orderBy('full_name');

        return $q->get();
    }

    private function normalizedConsultantId(string $role, mixed $consultantId): ?int
    {
        if ($role !== 'account_manager') {
            return null;
        }
        if ($consultantId === null || $consultantId === '') {
            return null;
        }

        return (int) $consultantId;
    }
}
