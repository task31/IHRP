@php
    use Illuminate\Support\Carbon;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="row-between">
            <h2 style="font-size:22px;font-weight:700;letter-spacing:-0.01em;">Invoices</h2>
            @php
                $invoiceOtTemplate = storage_path('app/templates/invoice_template_ot.xlsx');
                $invoiceOtTemplateVer = is_file($invoiceOtTemplate) ? (string) filemtime($invoiceOtTemplate) : (string) time();
            @endphp
            <a href="{{ route('invoices.template') }}?v={{ $invoiceOtTemplateVer }}"
                class="btn btn-secondary">
                Download template
            </a>
        </div>
    </x-slot>

    <div class="stack" x-data="invoicesPage()">
        <form method="GET" action="{{ route('invoices.index') }}" class="card-base" style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px;">
            <div>
                <label class="eyebrow">Status</label>
                <select name="status" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:7px 10px;font-size:13px;color:var(--fg-1);outline:none;font-family:var(--font-sans);margin-top:4px;">
                    <option value="">All</option>
                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                    <option value="sent" @selected(($filters['status'] ?? '') === 'sent')>Sent</option>
                    <option value="paid" @selected(($filters['status'] ?? '') === 'paid')>Paid</option>
                </select>
            </div>
            <div>
                <label class="eyebrow">Client</label>
                <select name="clientId" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:7px 10px;font-size:13px;color:var(--fg-1);outline:none;font-family:var(--font-sans);margin-top:4px;">
                    <option value="">All</option>
                    @foreach ($clients as $cl)
                        <option value="{{ $cl->id }}" @selected((string) ($filters['clientId'] ?? '') === (string) $cl->id)>{{ $cl->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="eyebrow">Consultant</label>
                <select name="consultantId" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:7px 10px;font-size:13px;color:var(--fg-1);outline:none;font-family:var(--font-sans);margin-top:4px;">
                    <option value="">All</option>
                    @foreach ($consultants as $co)
                        <option value="{{ $co->id }}" @selected((string) ($filters['consultantId'] ?? '') === (string) $co->id)>{{ $co->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="eyebrow">From</label>
                <input type="date" name="startDate" value="{{ $filters['startDate'] ?? '' }}" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:7px 10px;font-size:13px;color:var(--fg-1);outline:none;font-family:var(--font-sans);margin-top:4px;" />
            </div>
            <div>
                <label class="eyebrow">To</label>
                <input type="date" name="endDate" value="{{ $filters['endDate'] ?? '' }}" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:7px 10px;font-size:13px;color:var(--fg-1);outline:none;font-family:var(--font-sans);margin-top:4px;" />
            </div>
            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="{{ route('invoices.index') }}" class="btn btn-secondary">Clear</a>
        </form>

        <div class="card-base" style="padding:0;overflow-x:auto;">
            <table class="table">
                <thead >
                    <tr>
                        <th >#</th>
                        <th >Date</th>
                        <th >Due</th>
                        <th >Consultant</th>
                        <th >Client</th>
                        <th >Amount</th>
                        <th >PO #</th>
                        <th >Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody >
                    @forelse ($invoices as $inv)
                        <tr>
                            <td class="mono-num" style="color:var(--accent-300);">{{ $inv->invoice_number }}</td>
                            <td >{{ $inv->invoice_date ? Carbon::parse($inv->invoice_date)->format('m/d/Y') : '—' }}</td>
                            <td >{{ $inv->due_date ? Carbon::parse($inv->due_date)->format('m/d/Y') : '—' }}</td>
                            <td style="color:var(--fg-1);font-weight:500;">{{ $inv->consultant?->full_name ?? '—' }}</td>
                            <td style="color:var(--fg-1);font-weight:500;">{{ $inv->client?->name ?? '—' }}</td>
                            <td class="mono-num" style="color:var(--fg-1);font-weight:600;">${{ number_format((float) $inv->total_amount_due, 2) }}</td>
                            <td class="px-3 py-2">
                                @can('admin')
                                    <span x-show="editingPo !== {{ $inv->id }}" class="inline-flex items-center gap-1">
                                        <span style="color:var(--fg-2);">{{ $inv->po_number ?? '—' }}</span>
                                        <button type="button" class="btn btn-ghost btn-sm" @click="editingPo = {{ $inv->id }}; poDraft = @js($inv->po_number ?? '')">Edit</button>
                                    </span>
                                    <span x-show="editingPo === {{ $inv->id }}" x-cloak class="inline-flex items-center gap-1">
                                        <input type="text" x-model="poDraft" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:6px;padding:3px 6px;font-size:12px;color:var(--fg-1);width:96px;outline:none;font-family:var(--font-sans);" />
                                        <button type="button" class="btn btn-ghost btn-sm" style="color:var(--success-400);" @click="savePo({{ $inv->id }})">✓</button>
                                        <button type="button" class="btn btn-ghost btn-sm" @click="editingPo = null">✕</button>
                                    </span>
                                @else
                                    <span style="color:var(--fg-2);">{{ $inv->po_number ?? '—' }}</span>
                                @endcan
                            </td>
                            <td class="px-3 py-2">
                                @php
                                    $st = $inv->status;
                                    $badge =
                                        $st === 'paid'
                                            ? 'ok'
                                            : ($st === 'sent'
                                                ? 'teal'
                                                : 'neutral');
                                @endphp
                                <span class="badge {{ $badge }}">{{ $st }}</span>
                            </td>
                            <td style="text-align:right;white-space:nowrap;">
                                <button type="button" class="btn btn-ghost btn-sm" @click="openPreview({{ $inv->id }})">Preview</button>
                                <button type="button" class="btn btn-ghost btn-sm" @click="exportPdf({{ $inv->id }})">Export</button>
                                @can('admin')
                                    <button type="button" class="btn btn-ghost btn-sm" @click="regeneratePdf({{ $inv->id }})">Regen PDF</button>
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-sm"
                                        @click="openSend(@js([
                                            'id' => $inv->id,
                                            'number' => $inv->invoice_number,
                                            'amount' => (string) $inv->total_amount_due,
                                            'due' => $inv->due_date ? $inv->due_date->format('Y-m-d') : '',
                                            'to' => $inv->client?->smtp_email ?: $inv->client?->email ?: '',
                                        ]))"
                                    >
                                        Send
                                    </button>
                                    @if ($inv->status === 'pending')
                                        <button type="button" class="btn btn-ghost btn-sm" @click="setStatus({{ $inv->id }}, 'sent')">Mark Sent</button>
                                    @endif
                                    @if (in_array($inv->status, ['pending', 'sent'], true))
                                        <button type="button" class="btn btn-ghost btn-sm" style="color:var(--success-400);" @click="setStatus({{ $inv->id }}, 'paid')">Mark Paid</button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="padding:32px;text-align:center;color:var(--fg-3);">No invoices match.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Preview --}}
        <div
            x-show="previewId !== null"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            @keydown.escape.window="closePreview()"
        >
            <div class="card-base" style="height:85vh;width:100%;max-width:1024px;display:flex;flex-direction:column;">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--border-1);">
                    <h3 style="font-size:15px;font-weight:700;">Invoice preview</h3>
                    <div style="display:flex;gap:8px;">
                        <button type="button" class="btn btn-ghost btn-sm" x-show="previewId" @click="exportPdf(previewId)">Export PDF</button>
                        <button type="button" class="btn btn-ghost btn-sm" @click="closePreview()">✕</button>
                    </div>
                </div>
                <div style="position:relative;min-height:0;flex:1;display:flex;flex-direction:column;">
                    <div
                        x-show="previewLoading"
                        x-cloak
                        style="position:absolute;inset:0;z-index:10;display:flex;align-items:center;justify-content:center;background:rgba(13,22,38,0.85);font-size:13px;color:var(--fg-3);"
                    >
                        Loading PDF…
                    </div>
                    <iframe
                        :src="previewSrc"
                        style="min-height:0;flex:1;width:100%;"
                        title="Invoice PDF"
                        @load="previewLoading = false"
                    ></iframe>
                </div>
            </div>
        </div>

        {{-- Send email --}}
        <div
            x-show="sendPayload !== null"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
        >
            <div class="card-base" style="width:100%;max-width:440px;" @click.outside="sendPayload = null">
                <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;">Send invoice email</h3>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="eyebrow">To</label>
                        <input type="email" x-model="sendForm.to" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:9px 12px;font-size:13px;color:var(--fg-1);width:100%;outline:none;font-family:var(--font-sans);margin-top:4px;" />
                    </div>
                    <div>
                        <label class="eyebrow">Subject</label>
                        <input type="text" x-model="sendForm.subject" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:9px 12px;font-size:13px;color:var(--fg-1);width:100%;outline:none;font-family:var(--font-sans);margin-top:4px;" />
                    </div>
                    <div>
                        <label class="eyebrow">Notes</label>
                        <textarea x-model="sendForm.note" rows="3" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:9px 12px;font-size:13px;color:var(--fg-1);width:100%;outline:none;font-family:var(--font-sans);margin-top:4px;"></textarea>
                    </div>
                </div>
                <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:8px;">
                    <button type="button" class="btn btn-ghost" @click="sendPayload = null">Cancel</button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="sendLoading"
                        @click="sendEmail()"
                        x-text="sendLoading ? 'Sending…' : 'Send'"
                    ></button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function invoicesPage() {
            return {
                editingPo: null,
                poDraft: '',
                previewId: null,
                previewSrc: '',
                previewLoading: false,
                sendPayload: null,
                sendForm: { to: '', subject: '', note: '' },
                sendLoading: false,
                closePreview() {
                    this.previewId = null;
                    this.previewSrc = '';
                    this.previewLoading = false;
                },
                openPreview(id) {
                    this.previewLoading = true;
                    this.previewId = id;
                    this.previewSrc = '';
                    this.$nextTick(() => {
                        this.previewSrc = `/invoices/${id}/preview`;
                    });
                },
                exportPdf(id) {
                    window.location = `/invoices/${id}/export`;
                },
                openSend(payload) {
                    this.sendPayload = payload;
                    this.sendForm.to = payload.to || '';
                    const dueFmt = payload.due ? new Date(payload.due + 'T12:00:00').toLocaleDateString() : '';
                    this.sendForm.subject = `Invoice #${payload.number} — $${Number(payload.amount).toFixed(2)} due ${dueFmt}`;
                    this.sendForm.note = '';
                },
                async savePo(invoiceId) {
                    const res = await apiFetch('/invoices/update-po', {
                        method: 'POST',
                        body: JSON.stringify({ invoiceId, poNumber: this.poDraft || null }),
                    });
                    if (res.ok) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'PO updated' } }));
                        this.editingPo = null;
                        window.location.reload();
                    } else {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Could not update PO', type: 'error' } }));
                    }
                },
                async setStatus(id, status) {
                    const res = await apiFetch(`/invoices/${id}/status`, {
                        method: 'PATCH',
                        body: JSON.stringify({ status }),
                    });
                    if (res.ok) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Status updated' } }));
                        window.location.reload();
                    } else {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Update failed', type: 'error' } }));
                    }
                },
                async regeneratePdf(id) {
                    const res = await apiFetch(`/invoices/${id}/regenerate-pdf`, { method: 'POST' });
                    if (res.ok) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'PDF regenerated' } }));
                    } else {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Regen failed', type: 'error' } }));
                    }
                },
                async sendEmail() {
                    if (!this.sendPayload) return;
                    this.sendLoading = true;
                    const res = await apiFetch('/invoices/send', {
                        method: 'POST',
                        body: JSON.stringify({
                            invoiceId: this.sendPayload.id,
                            recipientEmail: this.sendForm.to,
                            subject: this.sendForm.subject,
                            note: this.sendForm.note || null,
                        }),
                    });
                    this.sendLoading = false;
                    if (res.ok) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Email sent' } }));
                        this.sendPayload = null;
                        window.location.reload();
                    } else {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Send failed', type: 'error' } }));
                    }
                },
            };
        }
    </script>
</x-app-layout>
