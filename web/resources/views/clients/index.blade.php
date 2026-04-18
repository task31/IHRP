@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Client> $clients */
    /** @var \Illuminate\Support\Collection<string|int, mixed> $spentByClient */
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $accountManagers */
@endphp

<x-app-layout>
    <div class="stack" x-data="clientsPage()">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold" style="color:var(--fg-1)">Clients</h2>
            @can('admin')
                <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    x-on:click="openCreate()"
                >
                    Add Client
                </button>
            @endcan
        </div>
        <div class="card-base" style="padding:0;overflow-x:auto">
            <table class="table">
                <thead >
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
                <tbody >
                    @foreach ($clients as $client)
                        @php
                            $spent = (float) ($spentByClient[$client->id] ?? 0);
                            $budget = (float) $client->total_budget;
                            $pct = $budget > 0 ? min(100, ($spent / $budget) * 100) : 0;
                        @endphp
                        <tr style="{{ $client->active ? '' : 'opacity:0.55' }}">
                            <td style="font-weight:500">
                                {{ $client->name }}
                                @if (! $client->active)
                                    <span class="badge neutral" style="margin-left:6px">Inactive</span>
                                @endif
                            </td>
                            <td>{{ $client->accountManager->name ?? '—' }}</td>
                            <td>{{ $client->billing_contact_name ?? '—' }}</td>
                            <td>
                                @if ($client->email)
                                    <a href="mailto:{{ $client->email }}" style="color:var(--accent-400);text-decoration:none" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">{{ $client->email }}</a>
                                @else
                                    <span style="color:var(--fg-4)">—</span>
                                @endif
                            </td>
                            <td style="color:var(--fg-3)">{{ $client->payment_terms ?? 'Net 30' }}</td>
                            <td>
                                @if ($budget > 0)
                                    <div style="max-width:140px">
                                        <div style="margin-bottom:3px;display:flex;justify-content:space-between;font-size:11px;color:var(--fg-3)">
                                            <span>${{ number_format($spent, 0) }}</span>
                                            <span>${{ number_format($budget, 0) }}</span>
                                        </div>
                                        <div style="height:4px;width:100%;border-radius:2px;background:var(--bg-5)">
                                            <div
                                                style="height:4px;border-radius:2px;width:{{ $pct }}%;background:{{ $pct >= 100 ? 'var(--danger-400)' : ($pct >= 80 ? 'var(--warn-400)' : 'var(--ok-400)') }}"
                                            ></div>
                                        </div>
                                    </div>
                                @else
                                    <span style="font-size:11px;color:var(--fg-4)">Not set</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('admin')
                                    @if ($client->active)
                                        <button
                                            type="button"
                                            class="btn btn-ghost btn-sm"
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
            <div class="card-base" style="max-height:90vh;width:100%;max-width:520px;overflow-y:auto" @click.outside="showModal = false">
                <h3 class="text-lg font-semibold text-gray-900" x-text="isEdit ? 'Edit Client' : 'Add Client'"></h3>
                <div class="mt-4 space-y-3">
                    @can('admin')
                        <div>
                            <label class="eyebrow">Account manager</label>
                            <select x-model="form.account_manager_id" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;">
                                <option value="">— Unassigned —</option>
                                @foreach ($accountManagers as $am)
                                    <option value="{{ $am->id }}">{{ $am->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endcan
                    <div>
                        <label class="eyebrow">Client Name *</label>
                        <input type="text" x-model="form.name" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;" />
                    </div>
                    <div>
                        <label class="eyebrow">Billing Contact</label>
                        <input type="text" x-model="form.billing_contact_name" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;" />
                    </div>
                    <div>
                        <label class="eyebrow">Billing Address</label>
                        <textarea x-model="form.billing_address" rows="3" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;"></textarea>
                    </div>
                    <div>
                        <label class="eyebrow">Billing Email *</label>
                        <input type="email" x-model="form.email" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;" />
                    </div>
                    <div>
                        <label class="eyebrow">SMTP Email *</label>
                        <input type="email" x-model="form.smtp_email" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;" />
                    </div>
                    <div>
                        <label class="eyebrow">Payment Terms</label>
                        <select x-model="form.payment_terms" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;">
                            <option value="Net 15">Net 15</option>
                            <option value="Net 30">Net 30</option>
                            <option value="Net 45">Net 45</option>
                            <option value="Net 60">Net 60</option>
                            <option value="Due on Receipt">Due on Receipt</option>
                        </select>
                    </div>
                    <div>
                        <label class="eyebrow">Total Budget</label>
                        <input type="number" step="0.01" x-model="form.total_budget" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost btn-sm" @click="showModal = false">Cancel</button>
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
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
            <div class="card-base" style="width:100%;max-width:400px">
                <p class="text-sm text-gray-700">Deactivate this client? They will be hidden from active lists.</p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost btn-sm" @click="confirmId = null">Cancel</button>
                    <button type="button" class="btn btn-danger btn-sm" @click="deactivate()">Deactivate</button>
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
