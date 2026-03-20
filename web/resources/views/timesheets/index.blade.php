@php
    $usStates = [
        'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY',
        'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND',
        'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY', 'DC', 'PR',
    ];
    $consultantMeta = $consultants->mapWithKeys(
        fn ($c) => [
            $c->id => [
                'state' => (string) $c->state,
                'pay_rate' => (float) $c->pay_rate,
            ],
        ]
    );
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold text-gray-800">Timesheets</h2>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('timesheets.template') }}"
                    class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                    Download template
                </a>
                @can('admin')
                    <button type="button" @click="importOpen = true"
                        class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                        Import timesheet
                    </button>
                @endcan
            </div>
        </div>
    </x-slot>

    <div x-data="{ importOpen: false, viewOpen: false, viewData: null, viewLoading: false }" class="space-y-6">
        @can('admin')
            <div class="rounded-lg bg-white p-5 shadow-sm" x-data="manualTimesheet(@js($consultantMeta))">
                <h3 class="font-semibold text-gray-800">Manual entry</h3>
                <p class="mt-1 text-xs text-gray-500">Enter bi-weekly hours (Mon–Sun × 2). Optional state override for OT preview.</p>
                <form method="POST" action="{{ route('timesheets.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="block text-sm">
                            <span class="text-gray-600">Consultant</span>
                            <select name="consultant_id" x-model="consultantId" required
                                class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
                                <option value="">—</option>
                                @foreach ($consultants as $c)
                                    <option value="{{ $c->id }}">{{ $c->full_name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm">
                            <span class="text-gray-600">Client (optional)</span>
                            <select name="client_id" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
                                <option value="">Default from consultant</option>
                                @foreach ($clients as $cl)
                                    <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm">
                            <span class="text-gray-600">State override (OT preview)</span>
                            <select name="state" x-model="overrideState" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
                                <option value="">Use consultant state</option>
                                @foreach ($usStates as $st)
                                    <option value="{{ $st }}">{{ $st }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm">
                            <span class="text-gray-600">Pay period start</span>
                            <input type="date" name="pay_period_start" required
                                class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
                        </label>
                        <label class="block text-sm">
                            <span class="text-gray-600">Pay period end</span>
                            <input type="date" name="pay_period_end" required
                                class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
                        </label>
                    </div>

                    @foreach ([1 => 'Week 1', 2 => 'Week 2'] as $wn => $label)
                        <div>
                            <p class="text-sm font-medium text-gray-700">{{ $label }} (Mon → Sun)</p>
                            <div class="mt-2 grid grid-cols-7 gap-2">
                                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $i => $day)
                                    <label class="text-xs text-gray-600">
                                        {{ $day }}
                                        <input type="number" step="0.25" min="0" name="week{{ $wn }}[]" value="0"
                                            x-model.number="week{{ $wn }}[{{ $i }}]"
                                            class="mt-0.5 w-full rounded border border-gray-300 px-1 py-1 text-sm" />
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="flex flex-wrap items-end gap-3">
                        <button type="button" @click="previewOT()"
                            class="rounded border border-gray-400 bg-white px-3 py-1.5 text-sm hover:bg-gray-50">
                            Preview OT
                        </button>
                        <button type="submit"
                            class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                            Save timesheet
                        </button>
                    </div>

                    <template x-if="otPreview">
                        <div class="rounded border border-blue-100 bg-blue-50 p-3 text-xs text-blue-900">
                            <p class="font-medium">OT preview (approx.)</p>
                            <p class="mt-1" x-text="'Regular: ' + otPreview.totals.regularHours + ' h, OT: ' + otPreview.totals.otHours + ' h, DT: ' + otPreview.totals.doubleTimeHours + ' h'"></p>
                            <p class="text-gray-600" x-text="'Rule: ' + otPreview.otRuleApplied"></p>
                        </div>
                    </template>
                </form>
            </div>
        @endcan

        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Pay period</th>
                        <th class="px-4 py-3">Consultant</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Reg</th>
                        <th class="px-4 py-3">OT</th>
                        <th class="px-4 py-3">DT</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($timesheets as $t)
                        <tr>
                            <td class="px-4 py-2 text-gray-700">
                                {{ \Illuminate\Support\Carbon::parse($t['pay_period_start'])->format('M j') }}
                                –
                                {{ \Illuminate\Support\Carbon::parse($t['pay_period_end'])->format('M j, Y') }}
                            </td>
                            <td class="px-4 py-2">{{ $t['consultant_name'] ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $t['client_name'] ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $t['total_regular_hours'] ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $t['total_ot_hours'] ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $t['total_dt_hours'] ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs">{{ $t['invoice_status'] ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-2">
                                <button type="button"
                                    x-on:click="viewOpen = true; viewLoading = true; viewData = null; apiFetch('{{ route('timesheets.show', $t['id']) }}').then(r => r.json()).then(d => { viewData = d; viewLoading = false; })"
                                    class="text-xs font-medium text-indigo-600 hover:underline">
                                    View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">No timesheets yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Import modal --}}
        <div x-show="importOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @keydown.escape.window="importOpen = false">
            <div @click.away="importOpen = false" class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h3 class="text-lg font-semibold">Import timesheet</h3>
                    <button type="button" @click="importOpen = false" class="text-gray-500 hover:text-gray-800">✕</button>
                </div>
                <div class="mt-4">
                    @livewire('timesheet-wizard')
                </div>
            </div>
        </div>

        {{-- View modal --}}
        <div x-show="viewOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @keydown.escape.window="viewOpen = false">
            <div @click.away="viewOpen = false" class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="text-lg font-semibold">Timesheet detail</h3>
                    <button type="button" @click="viewOpen = false" class="text-gray-500 hover:text-gray-800">✕</button>
                </div>
                <div class="mt-4 text-sm">
                    <template x-if="viewLoading"><p class="text-gray-500">Loading…</p></template>
                    <template x-if="!viewLoading && viewData">
                        <pre class="max-h-[60vh] overflow-auto rounded bg-gray-50 p-3 text-xs" x-text="JSON.stringify(viewData, null, 2)"></pre>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <script>
        function manualTimesheet(meta) {
            return {
                consultantId: '',
                overrideState: '',
                week1: [0, 0, 0, 0, 0, 0, 0],
                week2: [0, 0, 0, 0, 0, 0, 0],
                otPreview: null,
                async previewOT() {
                    this.otPreview = null;
                    const id = this.consultantId;
                    if (!id || !meta[id]) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Select a consultant', type: 'error' } }));
                        return;
                    }
                    const m = meta[id];
                    const state = (this.overrideState && this.overrideState.length === 2) ? this.overrideState : m.state;
                    const res = await apiFetch(@json(route('timesheets.preview-ot')), {
                        method: 'POST',
                        body: JSON.stringify({
                            state,
                            week1Hours: this.week1,
                            week2Hours: this.week2,
                            payRate: m.pay_rate,
                        }),
                    });
                    if (!res.ok) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Preview failed', type: 'error' } }));
                        return;
                    }
                    this.otPreview = await res.json();
                },
            };
        }
    </script>
</x-app-layout>
