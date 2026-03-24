@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Client> $clients */
    /** @var \Illuminate\Support\Collection<string|int, mixed> $spentByClient */
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $accountManagers */
@endphp

<x-app-layout>
    <div class="space-y-4" x-data="clientsPage()">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold text-gray-800">Clients</h2>
            @can('admin')
                <button
                    type="button"
                    class="rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    x-on:click="openCreate()"
                >
                    Add Client
                </button>
            @endcan
        </div>
        <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Account manager</th>
                        <th class="px-4 py-3">Billing Contact</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Terms</th>
                        <th class="px-4 py-3">Budget</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($clients as $client)
                        @php
                            $spent = (float) ($spentByClient[$client->id] ?? 0);
                            $budget = (float) $client->total_budget;
                            $pct = $budget > 0 ? min(100, ($spent / $budget) * 100) : 0;
                        @endphp
                        <tr class="{{ $client->active ? '' : 'bg-gray-50 opacity-75' }}">
                            <td class="px-4 py-3 font-medium text-gray-900">
                                {{ $client->name }}
                                @if (! $client->active)
                                    <span class="ml-2 rounded bg-gray-200 px-1.5 py-0.5 text-xs text-gray-600">Inactive</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $client->accountManager->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $client->billing_contact_name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($client->email)
                                    <a href="mailto:{{ $client->email }}" class="text-indigo-600 hover:underline">{{ $client->email }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $client->payment_terms ?? 'Net 30' }}</td>
                            <td class="px-4 py-3">
                                @if ($budget > 0)
                                    <div class="max-w-[140px]">
                                        <div class="mb-0.5 flex justify-between text-xs text-gray-500">
                                            <span>${{ number_format($spent, 0) }}</span>
                                            <span>${{ number_format($budget, 0) }}</span>
                                        </div>
                                        <div class="h-1.5 w-full rounded-full bg-gray-100">
                                            <div
                                                class="h-1.5 rounded-full {{ $pct >= 100 ? 'bg-red-500' : ($pct >= 80 ? 'bg-orange-400' : 'bg-green-500') }}"
                                                style="width: {{ $pct }}%"
                                            ></div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400">Not set</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('admin')
                                    @if ($client->active)
                                        <button
                                            type="button"
                                            class="text-xs text-indigo-600 hover:underline"
                                            @click="openEdit({{ $client->id }})"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="ml-2 text-xs text-red-600 hover:underline"
                                            @click="confirmDeactivate({{ $client->id }})"
                                        >
                                            Deactivate
                                        </button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Add / Edit modal --}}
        <div
            x-show="showModal"
            x-cloak
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
            @keydown.escape.window="showModal = false"
        >
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-6 shadow-xl" @click.outside="showModal = false">
                <h3 class="text-lg font-semibold text-gray-900" x-text="isEdit ? 'Edit Client' : 'Add Client'"></h3>
                <div class="mt-4 space-y-3">
                    @can('admin')
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Account manager</label>
                            <select x-model="form.account_manager_id" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
                                <option value="">— Unassigned —</option>
                                @foreach ($accountManagers as $am)
                                    <option value="{{ $am->id }}">{{ $am->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endcan
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Client Name *</label>
                        <input type="text" x-model="form.name" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Billing Contact</label>
                        <input type="text" x-model="form.billing_contact_name" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Billing Address</label>
                        <textarea x-model="form.billing_address" rows="3" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Billing Email *</label>
                        <input type="email" x-model="form.email" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">SMTP Email *</label>
                        <input type="email" x-model="form.smtp_email" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Payment Terms</label>
                        <select x-model="form.payment_terms" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
                            <option value="Net 15">Net 15</option>
                            <option value="Net 30">Net 30</option>
                            <option value="Net 45">Net 45</option>
                            <option value="Net 60">Net 60</option>
                            <option value="Due on Receipt">Due on Receipt</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Total Budget</label>
                        <input type="number" step="0.01" x-model="form.total_budget" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Default PO #</label>
                        <input type="text" x-model="form.po_number" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100" @click="showModal = false">Cancel</button>
                    <button
                        type="button"
                        class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        :disabled="saving"
                        @click="save()"
                        x-text="saving ? 'Saving…' : 'Save'"
                    ></button>
                </div>
            </div>
        </div>

        {{-- Deactivate confirm --}}
        <div
            x-show="confirmId !== null"
            x-cloak
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
            @keydown.escape.window="confirmId = null"
        >
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                <p class="text-sm text-gray-700">Deactivate this client? They will be hidden from active lists.</p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100" @click="confirmId = null">Cancel</button>
                    <button type="button" class="rounded bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700" @click="deactivate()">Deactivate</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function clientsPage() {
            return {
                showModal: false,
                isEdit: false,
                saving: false,
                form: {
                    id: null,
                    account_manager_id: '',
                    name: '',
                    billing_contact_name: '',
                    billing_address: '',
                    email: '',
                    smtp_email: '',
                    payment_terms: 'Net 30',
                    total_budget: '',
                    po_number: '',
                },
                confirmId: null,
                openCreate() {
                    this.isEdit = false;
                    this.form = {
                        id: null,
                        account_manager_id: '',
                        name: '',
                        billing_contact_name: '',
                        billing_address: '',
                        email: '',
                        smtp_email: '',
                        payment_terms: 'Net 30',
                        total_budget: '',
                        po_number: '',
                    };
                    this.showModal = true;
                },
                async openEdit(id) {
                    const r = await apiFetch(`/clients/${id}`).then((x) => x.json());
                    this.form = {
                        id: r.id,
                        account_manager_id: r.account_manager_id != null ? String(r.account_manager_id) : '',
                        name: r.name ?? '',
                        billing_contact_name: r.billing_contact_name ?? '',
                        billing_address: r.billing_address ?? '',
                        email: r.email ?? '',
                        smtp_email: r.smtp_email ?? '',
                        payment_terms: r.payment_terms ?? 'Net 30',
                        total_budget: r.total_budget != null ? String(r.total_budget) : '',
                        po_number: r.po_number ?? '',
                    };
                    this.isEdit = true;
                    this.showModal = true;
                },
                async save() {
                    this.saving = true;
                    const payload = {
                        name: this.form.name,
                        billing_contact_name: this.form.billing_contact_name || null,
                        billing_address: this.form.billing_address || null,
                        email: this.form.email || null,
                        smtp_email: this.form.smtp_email || null,
                        payment_terms: this.form.payment_terms,
                        total_budget: this.form.total_budget === '' ? null : Number(this.form.total_budget),
                        po_number: this.form.po_number || null,
                        account_manager_id:
                            this.form.account_manager_id === '' || this.form.account_manager_id === null
                                ? null
                                : Number(this.form.account_manager_id),
                    };
                    const url = this.isEdit ? `/clients/${this.form.id}` : '/clients';
                    const method = this.isEdit ? 'PUT' : 'POST';
                    const res = await apiFetch(url, { method, body: JSON.stringify(payload) });
                    this.saving = false;
                    if (res.ok) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Saved' } }));
                        this.showModal = false;
                        window.location.reload();
                    } else {
                        let msg = 'Error';
                        try {
                            const e = await res.json();
                            if (e.message) msg = e.message;
                            else if (e.errors) {
                                const first = Object.values(e.errors)[0];
                                msg = Array.isArray(first) ? first[0] : String(first);
                            }
                        } catch (_) {}
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: msg, type: 'error' } }));
                    }
                },
                confirmDeactivate(id) {
                    this.confirmId = id;
                },
                async deactivate() {
                    await apiFetch(`/clients/${this.confirmId}`, { method: 'DELETE' });
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Client deactivated' } }));
                    this.confirmId = null;
                    window.location.reload();
                },
            };
        }
    </script>
</x-app-layout>
