@php
    $defaults = [
        'agency_name' => '',
        'agency_address' => '',
        'agency_phone' => '',
        'agency_email' => '',
        'agency_logo_base64' => '',
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_user' => '',
        'smtp_password' => '',
        'smtp_encryption' => 'tls',
        'smtp_from_name' => '',
        'smtp_from_address' => '',
        'alert_threshold_critical' => '7',
        'alert_threshold_warning' => '14',
        'alert_threshold_notice' => '30',
        'budget_alert_threshold_warning' => '80',
        'budget_alert_threshold_critical' => '100',
    ];
    $settingsArr = array_merge($defaults, $settings->all());
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Settings</h2>
    </x-slot>

    <div x-data="settingsPage(@js($settingsArr), @js(['prefix' => $sequence->prefix, 'next_number' => $sequence->next_number]))" class="flex flex-col gap-6 md:flex-row">
        <nav class="w-full shrink-0 space-y-1 md:w-48">
            @foreach (['agency' => 'Agency Info', 'logo' => 'Logo', 'invoicing' => 'Invoice #', 'smtp' => 'SMTP', 'backup' => 'Backup', 'alerts' => 'Alerts'] as $tab => $label)
                <button type="button" @click="activeTab = '{{ $tab }}'"
                    :class="activeTab === '{{ $tab }}' ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-600 hover:bg-gray-50'"
                    class="w-full rounded px-3 py-2 text-left text-sm">{{ $label }}</button>
            @endforeach
        </nav>

        <div class="min-w-0 flex-1 rounded-lg bg-white p-5 shadow-sm">
            <div x-show="activeTab === 'agency'" class="space-y-3">
                <label class="block text-sm text-gray-600">Agency name
                    <input type="text" x-model="form.agency_name" class="mt-1 w-full max-w-md rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <label class="block text-sm text-gray-600">Mailing address
                    <textarea x-model="form.agency_address" rows="3" class="mt-1 w-full max-w-md rounded border border-gray-300 px-2 py-1.5 text-sm"></textarea>
                </label>
                <label class="block text-sm text-gray-600">Phone
                    <input type="text" x-model="form.agency_phone" class="mt-1 w-full max-w-md rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <label class="block text-sm text-gray-600">Email
                    <input type="email" x-model="form.agency_email" class="mt-1 w-full max-w-md rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <button type="button" @click="save(['agency_name','agency_address','agency_phone','agency_email'])"
                    class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save</button>
            </div>

            <div x-show="activeTab === 'logo'" class="space-y-3">
                <img x-show="form.agency_logo_base64" :src="form.agency_logo_base64" class="mb-4 max-h-20 rounded border border-gray-100 p-1" alt="Logo" />
                <form method="POST" action="{{ route('settings.logo') }}" enctype="multipart/form-data" class="space-y-2">
                    @csrf
                    <input type="file" name="logo" accept="image/*" class="block text-sm" required />
                    <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">Upload logo</button>
                </form>
            </div>

            <div x-show="activeTab === 'invoicing'" class="space-y-3">
                <label class="block text-sm text-gray-600">Prefix
                    <input type="text" x-model="seq.prefix" class="mt-1 w-full max-w-xs rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <label class="block text-sm text-gray-600">Next number
                    <input type="number" min="1" x-model.number="seq.next_number" class="mt-1 w-full max-w-xs rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <p class="text-sm text-gray-600">Preview: <code class="rounded bg-gray-100 px-2 py-0.5 text-sm" x-text="invoicePreview()"></code></p>
                <button type="button" @click="saveSequence()" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save</button>
            </div>

            <div x-show="activeTab === 'smtp'" class="space-y-3">
                <label class="block text-sm text-gray-600">Host
                    <input type="text" x-model="form.smtp_host" class="mt-1 w-full max-w-md rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <label class="block text-sm text-gray-600">Port
                    <input type="number" x-model.number="form.smtp_port" class="mt-1 w-full max-w-xs rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <label class="block text-sm text-gray-600">Username
                    <input type="text" x-model="form.smtp_user" class="mt-1 w-full max-w-md rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <label class="block text-sm text-gray-600">Password
                    <input type="password" x-model="form.smtp_password" autocomplete="new-password" class="mt-1 w-full max-w-md rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <label class="block text-sm text-gray-600">Encryption
                    <select x-model="form.smtp_encryption" class="mt-1 w-full max-w-xs rounded border border-gray-300 px-2 py-1.5 text-sm">
                        <option value="tls">tls</option>
                        <option value="ssl">ssl</option>
                    </select>
                </label>
                <label class="block text-sm text-gray-600">From name
                    <input type="text" x-model="form.smtp_from_name" class="mt-1 w-full max-w-md rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <label class="block text-sm text-gray-600">From address
                    <input type="email" x-model="form.smtp_from_address" class="mt-1 w-full max-w-md rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="testSmtp()" class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm hover:bg-gray-50">Test connection</button>
                    <button type="button"
                        @click="save(['smtp_host','smtp_port','smtp_user','smtp_password','smtp_encryption','smtp_from_name','smtp_from_address'])"
                        class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save</button>
                </div>
            </div>

            <div x-show="activeTab === 'backup'" class="space-y-3">
                <button type="button" @click="createBackup()" class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">Create backup now</button>
                <table class="mt-4 w-full text-sm">
                    <thead class="border-b text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="pb-2 pr-2">Created</th>
                            <th class="pb-2 pr-2">File</th>
                            <th class="pb-2">Status</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($backups as $b)
                            <tr>
                                <td class="py-2 pr-2 text-gray-600">{{ $b->created_at }}</td>
                                <td class="py-2 pr-2 font-mono text-xs">{{ basename($b->file_path) }}</td>
                                <td class="py-2 pr-2">{{ $b->status }}</td>
                                <td class="py-2">
                                    <a href="{{ route('backups.show', $b) }}" class="text-indigo-600 hover:underline">Download</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-gray-500">No backups yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div x-show="activeTab === 'alerts'" class="space-y-3">
                <label class="block text-sm text-gray-600">Critical (days)
                    <input type="number" min="0" x-model="form.alert_threshold_critical" class="mt-1 w-full max-w-xs rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <label class="block text-sm text-gray-600">Warning (days)
                    <input type="number" min="0" x-model="form.alert_threshold_warning" class="mt-1 w-full max-w-xs rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <label class="block text-sm text-gray-600">Notice (days)
                    <input type="number" min="0" x-model="form.alert_threshold_notice" class="mt-1 w-full max-w-xs rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <label class="block text-sm text-gray-600">Budget warning (%)
                    <input type="number" min="0" step="0.1" x-model="form.budget_alert_threshold_warning" class="mt-1 w-full max-w-xs rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <label class="block text-sm text-gray-600">Budget critical (%)
                    <input type="number" min="0" step="0.1" x-model="form.budget_alert_threshold_critical" class="mt-1 w-full max-w-xs rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <button type="button"
                    @click="save(['alert_threshold_critical','alert_threshold_warning','alert_threshold_notice','budget_alert_threshold_warning','budget_alert_threshold_critical'])"
                    class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save</button>
            </div>
        </div>
    </div>

    <script>
        function settingsPage(settings, sequence) {
            return {
                activeTab: 'agency',
                form: settings,
                seq: { prefix: sequence.prefix ?? '', next_number: sequence.next_number ?? 1 },
                invoicePreview() {
                    const n = String(this.seq.next_number ?? 1);
                    return (this.seq.prefix || '') + n.padStart(6, '0');
                },
                async save(keys) {
                    for (const key of keys) {
                        await apiFetch(@json(route('settings.update')), {
                            method: 'PATCH',
                            body: JSON.stringify({ key, value: this.form[key] ?? '' }),
                        });
                    }
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Saved' } }));
                },
                async saveSequence() {
                    await apiFetch(@json(route('invoice-sequence.update', ['invoice_sequence' => 1])), {
                        method: 'PUT',
                        headers: { Accept: 'application/json' },
                        body: JSON.stringify({
                            prefix: this.seq.prefix,
                            startNumber: this.seq.next_number,
                        }),
                    });
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Invoice numbering saved' } }));
                },
                async testSmtp() {
                    const r = await apiFetch(@json(route('settings.test-smtp')), { method: 'POST', body: '{}' }).then((x) => x.json());
                    window.dispatchEvent(
                        new CustomEvent('toast', {
                            detail: { message: r.ok ? 'SMTP OK' : (r.error || 'Failed'), type: r.ok ? 'success' : 'error' },
                        })
                    );
                },
                async createBackup() {
                    const r = await apiFetch(@json(route('backups.store')), { method: 'POST', body: '{}' }).then((x) => x.json());
                    window.dispatchEvent(
                        new CustomEvent('toast', {
                            detail: {
                                message: r.ok ? 'Backup created' : (r.error || 'Failed'),
                                type: r.ok ? 'success' : 'error',
                            },
                        })
                    );
                    if (r.ok) window.location.reload();
                },
            };
        }
    </script>
</x-app-layout>
