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
                <label class="field">Agency name
                    <input type="text" x-model="form.agency_name" class="field-control" style="max-width:420px;" />
                </label>
                <label class="field">Mailing address
                    <textarea x-model="form.agency_address" rows="3" class="field-control" style="max-width:420px;"></textarea>
                </label>
                <label class="field">Phone
                    <input type="text" x-model="form.agency_phone" class="field-control" style="max-width:420px;" />
                </label>
                <label class="field">Email
                    <input type="email" x-model="form.agency_email" class="field-control" style="max-width:420px;" />
                </label>
                <button type="button" @click="save(['agency_name','agency_address','agency_phone','agency_email'])"
                    class="btn btn-primary btn-sm">Save</button>
            </div>

            <div x-show="activeTab === 'logo'" style="display:flex;flex-direction:column;gap:12px">
                <img x-show="form.agency_logo_base64" :src="form.agency_logo_base64" class="mb-4 max-h-20 rounded p-1 surface-muted" alt="Logo" />
                <form method="POST" action="{{ route('settings.logo') }}" enctype="multipart/form-data" class="space-y-2">
                    @csrf
                    <input type="file" name="logo" accept="image/*" class="block text-sm" required />
                    <button type="submit" class="btn btn-primary btn-sm">Upload logo</button>
                </form>
            </div>

            <div x-show="activeTab === 'invoicing'" style="display:flex;flex-direction:column;gap:12px">
                <label class="field">Prefix
                    <input type="text" x-model="seq.prefix" class="field-control" style="max-width:320px;" />
                </label>
                <label class="field">Next number
                    <input type="number" min="1" x-model.number="seq.next_number" class="field-control" style="max-width:320px;" />
                </label>
                <p class="field-help">Preview: <code class="mono-num" style="border-radius:var(--radius-sm);background:var(--bg-4);padding:2px 6px;" x-text="invoicePreview()"></code></p>
                <button type="button" @click="saveSequence()" class="btn btn-primary btn-sm">Save</button>
            </div>

            <div x-show="activeTab === 'smtp'" style="display:flex;flex-direction:column;gap:12px">
                <label class="field">Host
                    <input type="text" x-model="form.smtp_host" class="field-control" style="max-width:420px;" />
                </label>
                <label class="field">Port
                    <input type="number" x-model.number="form.smtp_port" class="field-control" style="max-width:320px;" />
                </label>
                <label class="field">Username
                    <input type="text" x-model="form.smtp_user" class="field-control" style="max-width:420px;" />
                </label>
                <label class="field">Password
                    <input type="password" x-model="form.smtp_password" autocomplete="new-password" class="field-control" style="max-width:420px;" />
                </label>
                <label class="field">Encryption
                    <select x-model="form.smtp_encryption" class="field-control" style="max-width:320px;">
                        <option value="tls">tls</option>
                        <option value="ssl">ssl</option>
                    </select>
                </label>
                <label class="field">From name
                    <input type="text" x-model="form.smtp_from_name" class="field-control" style="max-width:420px;" />
                </label>
                <label class="field">From address
                    <input type="email" x-model="form.smtp_from_address" class="field-control" style="max-width:420px;" />
                </label>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="testSmtp()" class="btn btn-secondary btn-sm">Test connection</button>
                    <button type="button"
                        @click="save(['smtp_host','smtp_port','smtp_user','smtp_password','smtp_encryption','smtp_from_name','smtp_from_address'])"
                        class="btn btn-primary btn-sm">Save</button>
                </div>
            </div>

            <div x-show="activeTab === 'backup'" style="display:flex;flex-direction:column;gap:12px">
                <button type="button" @click="createBackup()" class="btn btn-primary btn-sm">Create backup now</button>
                <div class="table-wrap">
                <table class="table" style="margin-top:4px;">
                    <thead>
                        <tr>
                            <th>Created</th>
                            <th>File</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($backups as $b)
                            <tr>
                                <td>{{ $b->created_at }}</td>
                                <td class="mono-num">{{ basename($b->file_path) }}</td>
                                <td><span class="badge {{ $b->status === 'completed' ? 'ok' : ($b->status === 'failed' ? 'bad' : 'neutral') }}">{{ $b->status }}</span></td>
                                <td>
                                    <a href="{{ route('backups.show', $b) }}" class="link-accent">Download</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="color:var(--fg-3)">No backups yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            <div x-show="activeTab === 'alerts'" style="display:flex;flex-direction:column;gap:12px">
                <label class="field">Critical (days)
                    <input type="number" min="0" x-model="form.alert_threshold_critical" class="field-control" style="max-width:320px;" />
                </label>
                <label class="field">Warning (days)
                    <input type="number" min="0" x-model="form.alert_threshold_warning" class="field-control" style="max-width:320px;" />
                </label>
                <label class="field">Notice (days)
                    <input type="number" min="0" x-model="form.alert_threshold_notice" class="field-control" style="max-width:320px;" />
                </label>
                <label class="field">Budget warning (%)
                    <input type="number" min="0" step="0.1" x-model="form.budget_alert_threshold_warning" class="field-control" style="max-width:320px;" />
                </label>
                <label class="field">Budget critical (%)
                    <input type="number" min="0" step="0.1" x-model="form.budget_alert_threshold_critical" class="field-control" style="max-width:320px;" />
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
