@php
    use Illuminate\Support\Carbon;

    $f = $filters ?? [];
    $sum = $summary ?? ['byPeriod' => [], 'byConsultant' => [], 'byClient' => [], 'totals' => null];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold" style="color:var(--fg-1)">Ledger</h2>
    </x-slot>

    <div class="stack" x-data="{ activeView: 'detail' }">
        <div class="flex flex-wrap items-center gap-3">
            <button
                type="button"
                :class="activeView === 'detail' ? 'btn btn-primary btn-sm' : 'btn btn-secondary btn-sm'"
                @click="activeView = 'detail'"
            >
                Detail
            </button>
            <button
                type="button"
                :class="activeView === 'summary' ? 'btn btn-primary btn-sm' : 'btn btn-secondary btn-sm'"
                @click="activeView = 'summary'"
            >
                Summary
            </button>
        </div>

        <form method="GET" action="{{ route('ledger.index') }}" class="card-base">
            <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
            <div class="field">
                <label>From</label>
                <input type="date" name="startDate" value="{{ $f['startDate'] ?? '' }}" class="field-control" />
            </div>
            <div class="field">
                <label>To</label>
                <input type="date" name="endDate" value="{{ $f['endDate'] ?? '' }}" class="field-control" />
            </div>
            <div class="field">
                <label>Consultant</label>
                <select name="consultantId" class="field-control">
                    <option value="">All</option>
                    @foreach ($consultantsInLedger as $co)
                        <option value="{{ $co->id }}" @selected((string) ($f['consultantId'] ?? '') === (string) $co->id)>{{ $co->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Client</label>
                <select name="clientId" class="field-control">
                    <option value="">All</option>
                    @foreach ($clientsInLedger as $cl)
                        <option value="{{ $cl->id }}" @selected((string) ($f['clientId'] ?? '') === (string) $cl->id)>{{ $cl->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Invoice status</label>
                <select name="invoiceStatus" class="field-control">
                    <option value="">All</option>
                    <option value="pending" @selected(($f['invoiceStatus'] ?? '') === 'pending')>Pending</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            <a href="{{ route('ledger.index') }}" class="btn btn-secondary btn-sm">Clear</a>
            </div>
            </div>
        </form>

        {{-- Detail --}}
        <div x-show="activeView === 'detail'" x-cloak class="card-base" style="padding:0;overflow-x:auto">
            <table class="table">
                <thead >
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
                <tbody >
                    @forelse ($timesheets as $t)
                        @php
                            $pct = (float) ($t->gross_margin_percent ?? 0);
                            $marginClass = $pct < 20 ? 'text-red-600' : ($pct < 30 ? 'text-yellow-600' : 'text-green-600');
                        @endphp
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap" style="color:var(--fg-1)">
                                {{ Carbon::parse($t->pay_period_start)->format('m/d/Y') }} – {{ Carbon::parse($t->pay_period_end)->format('m/d/Y') }}
                            </td>
                            <td class="px-3 py-2" style="color:var(--fg-1)">{{ $t->consultant_name }}</td>
                            <td class="px-3 py-2">{{ $t->client_name }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $t->total_regular_hours, 2) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $t->total_ot_hours, 2) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">${{ number_format((float) $t->total_consultant_cost, 2) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">${{ number_format((float) $t->total_client_billable, 2) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">${{ number_format((float) $t->gross_margin_dollars, 2) }}</td>
                            <td class="px-3 py-2 text-right font-medium tabular-nums {{ $marginClass }}">{{ number_format($pct, 1) }}%</td>
                            <td class="px-3 py-2">
                                <span class="badge {{ ($t->invoice_status ?? '') === 'pending' ? 'warn' : ((($t->invoice_status ?? '') === 'paid' || ($t->invoice_status ?? '') === 'sent') ? 'ok' : 'neutral') }}">{{ $t->invoice_status ?? '—' }}</span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                @can('admin')
                                    @if (empty($t->invoice_id))
                                        <button
                                            type="button"
                                            class="btn btn-ghost btn-sm"
                                            onclick="ledgerGenerateInvoice({{ (int) $t->id }})"
                                        >
                                            Create invoice
                                        </button>
                                    @else
                                        <span class="mono-dim">Invoiced</span>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-3 py-8 text-center" style="color:var(--fg-3)">No timesheets in ledger.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if (count($timesheets) > 0)
                    <tfoot>
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
        <div x-show="activeView === 'summary'" x-cloak class="stack">
            <div class="card-base">
                <h3 class="mb-3 font-semibold" style="color:var(--fg-1)">By pay period</h3>
                <div style="overflow-x:auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>#</th>
                                <th class="text-right">Cost</th>
                                <th class="text-right">Billable</th>
                                <th class="text-right">Margin $</th>
                                <th class="text-right">Margin %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sum['byPeriod'] as $row)
                                @php
                                    $bp = (float) ($row->blended_margin_percent ?? 0);
                                    $bc = $bp < 20 ? 'text-red-600' : ($bp < 30 ? 'text-yellow-600' : 'text-green-600');
                                @endphp
                                <tr>
                                    <td class="whitespace-nowrap">
                                        {{ Carbon::parse($row->pay_period_start)->format('m/d/Y') }} – {{ Carbon::parse($row->pay_period_end)->format('m/d/Y') }}
                                    </td>
                                    <td class="mono-num">{{ (int) ($row->row_count ?? 0) }}</td>
                                    <td class="text-right mono-num">${{ number_format((float) ($row->total_consultant_cost ?? 0), 2) }}</td>
                                    <td class="text-right mono-num">${{ number_format((float) ($row->total_client_billable ?? 0), 2) }}</td>
                                    <td class="text-right mono-num">${{ number_format((float) ($row->gross_margin_dollars ?? 0), 2) }}</td>
                                    <td class="text-right font-medium mono-num {{ $bc }}">{{ number_format($bp, 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-base">
                <h3 class="mb-3 font-semibold" style="color:var(--fg-1)">By consultant</h3>
                <div style="overflow-x:auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Consultant</th>
                                <th>#</th>
                                <th class="text-right">Cost</th>
                                <th class="text-right">Billable</th>
                                <th class="text-right">Margin $</th>
                                <th class="text-right">Margin %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sum['byConsultant'] as $row)
                                @php
                                    $bp = (float) ($row->blended_margin_percent ?? 0);
                                    $bc = $bp < 20 ? 'text-red-600' : ($bp < 30 ? 'text-yellow-600' : 'text-green-600');
                                @endphp
                                <tr>
                                    <td style="color:var(--fg-1)">{{ $row->consultant_name }}</td>
                                    <td class="mono-num">{{ (int) ($row->row_count ?? 0) }}</td>
                                    <td class="text-right mono-num">${{ number_format((float) ($row->total_consultant_cost ?? 0), 2) }}</td>
                                    <td class="text-right mono-num">${{ number_format((float) ($row->total_client_billable ?? 0), 2) }}</td>
                                    <td class="text-right mono-num">${{ number_format((float) ($row->gross_margin_dollars ?? 0), 2) }}</td>
                                    <td class="text-right font-medium mono-num {{ $bc }}">{{ number_format($bp, 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-base">
                <h3 class="mb-3 font-semibold" style="color:var(--fg-1)">By client</h3>
                <div style="overflow-x:auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>#</th>
                                <th class="text-right">Cost</th>
                                <th class="text-right">Billable</th>
                                <th class="text-right">Margin $</th>
                                <th class="text-right">Margin %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sum['byClient'] as $row)
                                @php
                                    $bp = (float) ($row->blended_margin_percent ?? 0);
                                    $bc = $bp < 20 ? 'text-red-600' : ($bp < 30 ? 'text-yellow-600' : 'text-green-600');
                                @endphp
                                <tr>
                                    <td style="color:var(--fg-1)">{{ $row->client_name }}</td>
                                    <td class="mono-num">{{ (int) ($row->row_count ?? 0) }}</td>
                                    <td class="text-right mono-num">${{ number_format((float) ($row->total_consultant_cost ?? 0), 2) }}</td>
                                    <td class="text-right mono-num">${{ number_format((float) ($row->total_client_billable ?? 0), 2) }}</td>
                                    <td class="text-right mono-num">${{ number_format((float) ($row->gross_margin_dollars ?? 0), 2) }}</td>
                                    <td class="text-right font-medium mono-num {{ $bc }}">{{ number_format($bp, 1) }}%</td>
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
