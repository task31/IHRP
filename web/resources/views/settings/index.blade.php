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
        <h2 class="text-lg font-semibold" style="color:var(--fg-1)">Settings</h2>
    </x-slot>

    <div x-data="settingsPage(@js($settingsArr), @js(['prefix' => $sequence->prefix, 'next_number' => $sequence->next_number]))" class="flex flex-col gap-6 md:flex-row">
        <nav class="w-full shrink-0 space-y-1 md:w-48">
            @foreach (['agency' => 'Agency Info', 'logo' => 'Logo', 'invoicing' => 'Invoice #', 'smtp' => 'SMTP', 'backup' => 'Backup', 'alerts' => 'Alerts'] as $tab => $label)
                <button type="button" @click="activeTab = '{{ $tab }}'"
                    :style="activeTab === '{{ $tab }}' ? 'background:rgba(34,211,238,0.12);color:var(--accent-400);font-weight:600;border-left:2px solid var(--accent-400);border-radius:0 var(--radius-sm) var(--radius-sm) 0' : 'color:var(--fg-3)'"
                    style="display:block;width:100%;padding:8px 12px;text-align:left;font-size:13px;background:none;border:none;cursor:pointer;border-left:2px solid transparent">{{ $label }}</button>
            @endforeach
        </nav>

        <div class="card-base">
            <div x-show="activeTab === 'agency'" style="display:flex;flex-direction:column;gap:12px">
                <label style="font-size:13px;color:var(--fg-2)">Agency name
                    <input type="text" x-model="form.agency_name" style="margin-top:6px;background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;max-width:420px;" />
                </label>
                <label style="font-size:13px;color:var(--fg-2)">Mailing address
                    <textarea x-model="form.agency_address" rows="3" style="margin-top:6px;background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;max-width:420px;"></textarea>
                </label>
                <label style="font-size:13px;color:var(--fg-2)">Phone
                    <input type="text" x-model="form.agency_phone" style="margin-top:6px;background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;max-width:420px;" />
                </label>
                <label style="font-size:13px;color:var(--fg-2)">Email
                    <input type="email" x-model="form.agency_email" style="margin-top:6px;background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;max-width:420px;" />
                </label>
                <button type="button" @click="save(['agency_name','agency_address','agency_phone','agency_email'])"
                    class="btn btn-primary btn-sm">Save</button>
            </div>

            <div x-show="activeTab === 'logo'" style="display:flex;flex-direction:column;gap:12px">
                <img x-show="form.agency_logo_base64" :src="form.agency_logo_base64" class="mb-4 max-h-20 rounded border border-gray-100 p-1" alt="Logo" />
                <form method="POST" action="{{ route('settings.logo') }}" enctype="multipart/form-data" class="space-y-2">
                    @csrf
                    <input type="file" name="logo" accept="image/*" class="block text-sm" required />
                    <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">Upload logo</button>
                </form>
            </div>

            <div x-show="activeTab === 'invoicing'" style="display:flex;flex-direction:column;gap:12px">
                <label style="font-size:13px;color:var(--fg-2)">Prefix
                    <input type="text" x-model="seq.prefix" class="mt-1 w-full max-w-xs rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <label style="font-size:13px;color:var(--fg-2)">Next number
                    <input type="number" min="1" x-model.number="seq.next_number" class="mt-1 w-full max-w-xs rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <p class="text-sm text-gray-600">Preview: <code style="border-radius:var(--radius-sm);background:var(--bg-4);padding:2px 6px;font-size:12px;font-family:var(--font-mono)" x-text="invoicePreview()"></code></p>
                <button type="button" @click="saveSequence()" class="btn btn-primary btn-sm">Save</button>
            </div>

            <div x-show="activeTab === 'smtp'" style="display:flex;flex-direction:column;gap:12px">
                <label style="font-size:13px;color:var(--fg-2)">Host
                    <input type="text" x-model="form.smtp_host" style="margin-top:6px;background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;max-width:420px;" />
                </label>
                <label style="font-size:13px;color:var(--fg-2)">Port
                    <input type="number" x-model.number="form.smtp_port" class="mt-1 w-full max-w-xs rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <label style="font-size:13px;color:var(--fg-2)">Username
                    <input type="text" x-model="form.smtp_user" style="margin-top:6px;background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;max-width:420px;" />
                </label>
                <label style="font-size:13px;color:var(--fg-2)">Password
                    <input type="password" x-model="form.smtp_password" autocomplete="new-password" style="margin-top:6px;background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;max-width:420px;" />
                </label>
                <label style="font-size:13px;color:var(--fg-2)">Encryption
                    <select x-model="form.smtp_encryption" class="mt-1 w-full max-w-xs rounded border border-gray-300 px-2 py-1.5 text-sm">
                        <option value="tls">tls</option>
                        <option value="ssl">ssl</option>
                    </select>
                </label>
                <label style="font-size:13px;color:var(--fg-2)">From name
                    <input type="text" x-model="form.smtp_from_name" style="margin-top:6px;background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;max-width:420px;" />
                </label>
                <label style="font-size:13px;color:var(--fg-2)">From address
                    <input type="email" x-model="form.smtp_from_address" style="margin-top:6px;background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;max-width:420px;" />
                </label>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="testSmtp()" class="btn btn-secondary btn-sm">Test connection</button>
                    <button type="button"
                        @click="save(['smtp_host','smtp_port','smtp_user','smtp_password','smtp_encryption','smtp_from_name','smtp_from_address'])"
                        class="btn btn-primary btn-sm">Save</button>
                </div>
            </div>

            <div x-show="activeTab === 'backup'" style="display:flex;flex-direction:column;gap:12px">
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
                    <tbody >
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

            <div x-show="activeTab === 'alerts'" style="display:flex;flex-direction:column;gap:12px">
                <label style="font-size:13px;color:var(--fg-2)">Critical (days)
                    <input type="number" min="0" x-model="form.alert_threshold_critical" class="mt-1 w-full max-w-xs rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <label style="font-size:13px;color:var(--fg-2)">Warning (days)
                    <input type="number" min="0" x-model="form.alert_threshold_warning" class="mt-1 w-full max-w-xs rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <label style="font-size:13px;color:var(--fg-2)">Notice (days)
                    <input type="number" min="0" x-model="form.alert_threshold_notice" class="mt-1 w-full max-w-xs rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <label style="font-size:13px;color:var(--fg-2)">Budget warning (%)
                    <input type="number" min="0" step="0.1" x-model="form.budget_alert_threshold_warning" class="mt-1 w-full max-w-xs rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <label style="font-size:13px;color:var(--fg-2)">Budget critical (%)
                    <input type="number" min="0" step="0.1" x-model="form.budget_alert_threshold_critical" class="mt-1 w-full max-w-xs rounded border border-gray-300 px-2 py-1.5 text-sm" />
                </label>
                <button type="button"
                    @click="save(['alert_threshold_critical','alert_threshold_warning','alert_threshold_notice','budget_alert_threshold_warning','budget_alert_threshold_critical'])"
                    class="btn btn-primary btn-sm">Save</button>
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
