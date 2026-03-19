@php
    use Illuminate\Support\Carbon;

    $f = $filters ?? [];
    $sum = $summary ?? ['byPeriod' => [], 'byConsultant' => [], 'byClient' => [], 'totals' => null];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Ledger</h2>
    </x-slot>

    <div class="space-y-4" x-data="{ activeView: 'detail' }">
        <div class="flex flex-wrap items-center gap-3">
            <button
                type="button"
                class="rounded px-3 py-1.5 text-sm font-medium"
                :class="activeView === 'detail' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 shadow-sm'"
                @click="activeView = 'detail'"
            >
                Detail
            </button>
            <button
                type="button"
                class="rounded px-3 py-1.5 text-sm font-medium"
                :class="activeView === 'summary' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 shadow-sm'"
                @click="activeView = 'summary'"
            >
                Summary
            </button>
        </div>

        <form method="GET" action="{{ route('ledger.index') }}" class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm">
            <div>
                <label class="block text-xs font-medium text-gray-600">From</label>
                <input type="date" name="startDate" value="{{ $f['startDate'] ?? '' }}" class="mt-1 rounded border border-gray-300 px-2 py-1.5 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">To</label>
                <input type="date" name="endDate" value="{{ $f['endDate'] ?? '' }}" class="mt-1 rounded border border-gray-300 px-2 py-1.5 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">Consultant</label>
                <select name="consultantId" class="mt-1 rounded border border-gray-300 px-2 py-1.5 text-sm">
                    <option value="">All</option>
                    @foreach ($consultantsInLedger as $co)
                        <option value="{{ $co->id }}" @selected((string) ($f['consultantId'] ?? '') === (string) $co->id)>{{ $co->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">Client</label>
                <select name="clientId" class="mt-1 rounded border border-gray-300 px-2 py-1.5 text-sm">
                    <option value="">All</option>
                    @foreach ($clientsInLedger as $cl)
                        <option value="{{ $cl->id }}" @selected((string) ($f['clientId'] ?? '') === (string) $cl->id)>{{ $cl->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">Invoice status</label>
                <select name="invoiceStatus" class="mt-1 rounded border border-gray-300 px-2 py-1.5 text-sm">
                    <option value="">All</option>
                    <option value="pending" @selected(($f['invoiceStatus'] ?? '') === 'pending')>Pending</option>
                </select>
            </div>
            <button type="submit" class="rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Apply</button>
            <a href="{{ route('ledger.index') }}" class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Clear</a>
        </form>

        {{-- Detail --}}
        <div x-show="activeView === 'detail'" x-cloak class="overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-3 py-3">Pay period</th>
                        <th class="px-3 py-3">Consultant</th>
                        <th class="px-3 py-3">Client</th>
                        <th class="px-3 py-3 text-right">Reg</th>
                        <th class="px-3 py-3 text-right">OT</th>
                        <th class="px-3 py-3 text-right">Cost</th>
                        <th class="px-3 py-3 text-right">Billable</th>
                        <th class="px-3 py-3 text-right">Margin $</th>
                        <th class="px-3 py-3 text-right">Margin %</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($timesheets as $t)
                        @php
                            $pct = (float) ($t->gross_margin_percent ?? 0);
                            $marginClass = $pct < 20 ? 'text-red-600' : ($pct < 30 ? 'text-yellow-600' : 'text-green-600');
                        @endphp
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap text-gray-800">
                                {{ Carbon::parse($t->pay_period_start)->format('m/d/Y') }} – {{ Carbon::parse($t->pay_period_end)->format('m/d/Y') }}
                            </td>
                            <td class="px-3 py-2 text-gray-800">{{ $t->consultant_name }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $t->client_name }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $t->total_regular_hours, 2) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $t->total_ot_hours, 2) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">${{ number_format((float) $t->total_consultant_cost, 2) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">${{ number_format((float) $t->total_client_billable, 2) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">${{ number_format((float) $t->gross_margin_dollars, 2) }}</td>
                            <td class="px-3 py-2 text-right font-medium tabular-nums {{ $marginClass }}">{{ number_format($pct, 1) }}%</td>
                            <td class="px-3 py-2">
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs">{{ $t->invoice_status ?? '—' }}</span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                @can('admin')
                                    @if (empty($t->invoice_id))
                                        <button
                                            type="button"
                                            class="text-xs text-indigo-600 hover:underline"
                                            onclick="ledgerGenerateInvoice({{ (int) $t->id }})"
                                        >
                                            Create invoice
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400">Invoiced</span>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-3 py-8 text-center text-gray-500">No timesheets in ledger.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if (count($timesheets) > 0)
                    <tfoot class="border-t-2 border-gray-200 bg-gray-50 font-medium">
                        <tr>
                            <td class="px-3 py-2" colspan="3">Totals</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format($footer['reg'], 2) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format($footer['ot'], 2) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">${{ number_format($footer['cost'], 2) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">${{ number_format($footer['billable'], 2) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">${{ number_format($footer['margin'], 2) }}</td>
                            @php
                                $fp = $footer['margin_pct'];
                                $fc = $fp < 20 ? 'text-red-600' : ($fp < 30 ? 'text-yellow-600' : 'text-green-600');
                            @endphp
                            <td class="px-3 py-2 text-right tabular-nums {{ $fc }}">{{ number_format($fp, 1) }}%</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        {{-- Summary --}}
        <div x-show="activeView === 'summary'" x-cloak class="space-y-6">
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <h3 class="mb-3 font-semibold text-gray-900">By pay period</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="py-2 pr-4">Period</th>
                                <th class="py-2 pr-4">#</th>
                                <th class="py-2 pr-4 text-right">Cost</th>
                                <th class="py-2 pr-4 text-right">Billable</th>
                                <th class="py-2 pr-4 text-right">Margin $</th>
                                <th class="py-2 text-right">Margin %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sum['byPeriod'] as $row)
                                @php
                                    $bp = (float) ($row->blended_margin_percent ?? 0);
                                    $bc = $bp < 20 ? 'text-red-600' : ($bp < 30 ? 'text-yellow-600' : 'text-green-600');
                                @endphp
                                <tr class="border-t border-gray-100">
                                    <td class="py-2 pr-4 whitespace-nowrap">
                                        {{ Carbon::parse($row->pay_period_start)->format('m/d/Y') }} – {{ Carbon::parse($row->pay_period_end)->format('m/d/Y') }}
                                    </td>
                                    <td class="py-2 pr-4">{{ (int) ($row->row_count ?? 0) }}</td>
                                    <td class="py-2 pr-4 text-right">${{ number_format((float) ($row->total_consultant_cost ?? 0), 2) }}</td>
                                    <td class="py-2 pr-4 text-right">${{ number_format((float) ($row->total_client_billable ?? 0), 2) }}</td>
                                    <td class="py-2 pr-4 text-right">${{ number_format((float) ($row->gross_margin_dollars ?? 0), 2) }}</td>
                                    <td class="py-2 text-right font-medium {{ $bc }}">{{ number_format($bp, 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-lg bg-white p-5 shadow-sm">
                <h3 class="mb-3 font-semibold text-gray-900">By consultant</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="py-2 pr-4">Consultant</th>
                                <th class="py-2 pr-4">#</th>
                                <th class="py-2 pr-4 text-right">Cost</th>
                                <th class="py-2 pr-4 text-right">Billable</th>
                                <th class="py-2 pr-4 text-right">Margin $</th>
                                <th class="py-2 text-right">Margin %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sum['byConsultant'] as $row)
                                @php
                                    $bp = (float) ($row->blended_margin_percent ?? 0);
                                    $bc = $bp < 20 ? 'text-red-600' : ($bp < 30 ? 'text-yellow-600' : 'text-green-600');
                                @endphp
                                <tr class="border-t border-gray-100">
                                    <td class="py-2 pr-4">{{ $row->consultant_name }}</td>
                                    <td class="py-2 pr-4">{{ (int) ($row->row_count ?? 0) }}</td>
                                    <td class="py-2 pr-4 text-right">${{ number_format((float) ($row->total_consultant_cost ?? 0), 2) }}</td>
                                    <td class="py-2 pr-4 text-right">${{ number_format((float) ($row->total_client_billable ?? 0), 2) }}</td>
                                    <td class="py-2 pr-4 text-right">${{ number_format((float) ($row->gross_margin_dollars ?? 0), 2) }}</td>
                                    <td class="py-2 text-right font-medium {{ $bc }}">{{ number_format($bp, 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-lg bg-white p-5 shadow-sm">
                <h3 class="mb-3 font-semibold text-gray-900">By client</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="py-2 pr-4">Client</th>
                                <th class="py-2 pr-4">#</th>
                                <th class="py-2 pr-4 text-right">Cost</th>
                                <th class="py-2 pr-4 text-right">Billable</th>
                                <th class="py-2 pr-4 text-right">Margin $</th>
                                <th class="py-2 text-right">Margin %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sum['byClient'] as $row)
                                @php
                                    $bp = (float) ($row->blended_margin_percent ?? 0);
                                    $bc = $bp < 20 ? 'text-red-600' : ($bp < 30 ? 'text-yellow-600' : 'text-green-600');
                                @endphp
                                <tr class="border-t border-gray-100">
                                    <td class="py-2 pr-4">{{ $row->client_name }}</td>
                                    <td class="py-2 pr-4">{{ (int) ($row->row_count ?? 0) }}</td>
                                    <td class="py-2 pr-4 text-right">${{ number_format((float) ($row->total_consultant_cost ?? 0), 2) }}</td>
                                    <td class="py-2 pr-4 text-right">${{ number_format((float) ($row->total_client_billable ?? 0), 2) }}</td>
                                    <td class="py-2 pr-4 text-right">${{ number_format((float) ($row->gross_margin_dollars ?? 0), 2) }}</td>
                                    <td class="py-2 text-right font-medium {{ $bc }}">{{ number_format($bp, 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function ledgerGenerateInvoice(timesheetId) {
            const res = await apiFetch('/invoices/generate', {
                method: 'POST',
                body: JSON.stringify({ timesheetId }),
            });
            if (res.ok) {
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Invoice created' } }));
                window.location.reload();
            } else {
                let msg = 'Could not create invoice';
                try {
                    const e = await res.json();
                    if (e.message) msg = e.message;
                } catch (_) {}
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: msg, type: 'error' } }));
            }
        }
    </script>
</x-app-layout>
