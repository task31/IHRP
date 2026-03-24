@php
    use Illuminate\Support\Carbon;
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Invoices</h2>
    </x-slot>

    <div class="space-y-4" x-data="invoicesPage()">
        <form method="GET" action="{{ route('invoices.index') }}" class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm">
            <div>
                <label class="block text-xs font-medium text-gray-600">Status</label>
                <select name="status" class="mt-1 rounded border border-gray-300 px-2 py-1.5 text-sm">
                    <option value="">All</option>
                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                    <option value="sent" @selected(($filters['status'] ?? '') === 'sent')>Sent</option>
                    <option value="paid" @selected(($filters['status'] ?? '') === 'paid')>Paid</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">Client</label>
                <select name="clientId" class="mt-1 rounded border border-gray-300 px-2 py-1.5 text-sm">
                    <option value="">All</option>
                    @foreach ($clients as $cl)
                        <option value="{{ $cl->id }}" @selected((string) ($filters['clientId'] ?? '') === (string) $cl->id)>{{ $cl->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">Consultant</label>
                <select name="consultantId" class="mt-1 rounded border border-gray-300 px-2 py-1.5 text-sm">
                    <option value="">All</option>
                    @foreach ($consultants as $co)
                        <option value="{{ $co->id }}" @selected((string) ($filters['consultantId'] ?? '') === (string) $co->id)>{{ $co->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">From</label>
                <input type="date" name="startDate" value="{{ $filters['startDate'] ?? '' }}" class="mt-1 rounded border border-gray-300 px-2 py-1.5 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">To</label>
                <input type="date" name="endDate" value="{{ $filters['endDate'] ?? '' }}" class="mt-1 rounded border border-gray-300 px-2 py-1.5 text-sm" />
            </div>
            <button type="submit" class="rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Apply</button>
            <a href="{{ route('invoices.index') }}" class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Clear</a>
        </form>

        <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-3 py-3">#</th>
                        <th class="px-3 py-3">Date</th>
                        <th class="px-3 py-3">Due</th>
                        <th class="px-3 py-3">Consultant</th>
                        <th class="px-3 py-3">Client</th>
                        <th class="px-3 py-3">Amount</th>
                        <th class="px-3 py-3">PO #</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($invoices as $inv)
                        <tr>
                            <td class="px-3 py-2 font-mono text-xs text-gray-800">{{ $inv->invoice_number }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $inv->invoice_date ? Carbon::parse($inv->invoice_date)->format('m/d/Y') : '—' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $inv->due_date ? Carbon::parse($inv->due_date)->format('m/d/Y') : '—' }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $inv->consultant?->full_name ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $inv->client?->name ?? '—' }}</td>
                            <td class="px-3 py-2 font-medium text-gray-900">${{ number_format((float) $inv->total_amount_due, 2) }}</td>
                            <td class="px-3 py-2">
                                @can('admin')
                                    <span x-show="editingPo !== {{ $inv->id }}" class="inline-flex items-center gap-1">
                                        <span class="text-gray-700">{{ $inv->po_number ?? '—' }}</span>
                                        <button type="button" class="text-xs text-indigo-600 hover:underline" @click="editingPo = {{ $inv->id }}; poDraft = @js($inv->po_number ?? '')">Edit</button>
                                    </span>
                                    <span x-show="editingPo === {{ $inv->id }}" x-cloak class="inline-flex items-center gap-1">
                                        <input type="text" x-model="poDraft" class="w-24 rounded border px-1 py-0.5 text-xs" />
                                        <button type="button" class="text-xs text-green-600" @click="savePo({{ $inv->id }})">✓</button>
                                        <button type="button" class="text-xs text-gray-400" @click="editingPo = null">✕</button>
                                    </span>
                                @else
                                    <span class="text-gray-700">{{ $inv->po_number ?? '—' }}</span>
                                @endcan
                            </td>
                            <td class="px-3 py-2">
                                @php
                                    $st = $inv->status;
                                    $badge =
                                        $st === 'paid'
                                            ? 'bg-green-100 text-green-700'
                                            : ($st === 'sent'
                                                ? 'bg-blue-100 text-blue-700'
                                                : 'bg-gray-100 text-gray-700');
                                @endphp
                                <span class="rounded px-2 py-0.5 text-xs font-medium {{ $badge }}">{{ $st }}</span>
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <button type="button" class="text-xs text-indigo-600 hover:underline" @click="openPreview({{ $inv->id }})">Preview</button>
                                <button type="button" class="ml-1 text-xs text-gray-600 hover:underline" @click="exportPdf({{ $inv->id }})">Export</button>
                                @can('admin')
                                    <button
                                        type="button"
                                        class="ml-1 text-xs text-gray-600 hover:underline"
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
                                        <button type="button" class="ml-1 text-xs text-blue-600 hover:underline" @click="setStatus({{ $inv->id }}, 'sent')">Mark Sent</button>
                                    @endif
                                    @if (in_array($inv->status, ['pending', 'sent'], true))
                                        <button type="button" class="ml-1 text-xs text-green-600 hover:underline" @click="setStatus({{ $inv->id }}, 'paid')">Mark Paid</button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-3 py-8 text-center text-gray-500">No invoices match.</td>
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
            <div class="flex h-[85vh] w-full max-w-5xl flex-col rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b px-4 py-3">
                    <h3 class="font-semibold">Invoice preview</h3>
                    <div class="flex gap-2">
                        <button type="button" class="text-sm text-indigo-600 hover:underline" x-show="previewId" @click="exportPdf(previewId)">Export PDF</button>
                        <button type="button" class="text-gray-500" @click="closePreview()">✕</button>
                    </div>
                </div>
                <div class="relative min-h-0 flex flex-1 flex-col">
                    <div
                        x-show="previewLoading"
                        x-cloak
                        class="absolute inset-0 z-10 flex items-center justify-center bg-white/80 text-sm text-gray-600"
                    >
                        Loading PDF…
                    </div>
                    <iframe
                        :src="previewSrc"
                        class="min-h-0 flex-1 w-full"
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
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" @click.outside="sendPayload = null">
                <h3 class="text-lg font-semibold">Send invoice email</h3>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600">To</label>
                        <input type="email" x-model="sendForm.to" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Subject</label>
                        <input type="text" x-model="sendForm.subject" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Notes</label>
                        <textarea x-model="sendForm.note" rows="3" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="text-sm text-gray-600" @click="sendPayload = null">Cancel</button>
                    <button
                        type="button"
                        class="rounded bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700 disabled:opacity-50"
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
