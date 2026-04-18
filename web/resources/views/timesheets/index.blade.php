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
    <div x-data="{
            importOpen: false,
            viewOpen: false,
            viewData: null,
            viewLoading: false,
            editW1: [0, 0, 0, 0, 0, 0, 0],
            editW2: [0, 0, 0, 0, 0, 0, 0],
            editSaving: false,
            timesheetHoursUrl(id) { return `{{ url('/') }}/timesheets/${id}/hours`; },
            syncEditHoursFromViewData() {
                const w1 = [0, 0, 0, 0, 0, 0, 0], w2 = [0, 0, 0, 0, 0, 0, 0];
                for (const row of (this.viewData?.dailyHours || [])) {
                    const idx = parseInt(row.day_of_week, 10);
                    const wn = parseInt(row.week_number, 10);
                    const h = parseFloat(row.hours) || 0;
                    if (wn === 1) w1[idx] = h;
                    if (wn === 2) w2[idx] = h;
                }
                this.editW1 = w1;
                this.editW2 = w2;
            },
            async saveEditedHours() {
                if (!this.viewData?.id) return;
                this.editSaving = true;
                const res = await apiFetch(this.timesheetHoursUrl(this.viewData.id), {
                    method: 'PATCH',
                    body: JSON.stringify({ week1: this.editW1, week2: this.editW2 }),
                });
                this.editSaving = false;
                if (res.ok) {
                    this.viewData = await res.json();
                    this.syncEditHoursFromViewData();
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Timesheet hours updated' } }));
                } else {
                    const j = await res.json().catch(() => ({}));
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: j.error || 'Update failed', type: 'error' } }));
                }
            },
        }" class="stack">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold" style="color:var(--fg-1)">Timesheets</h2>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('timesheets.template') }}" class="btn btn-secondary btn-sm">
                    Download template
                </a>
                @can('admin')
                    <button type="button" x-on:click="importOpen = true" class="btn btn-primary btn-sm">
                        Import timesheet
                    </button>
                @endcan
            </div>
        </div>
        @can('admin')
            <div class="card-base" x-data="manualTimesheet(@js($consultantMeta))">
                <h3 class="font-semibold" style="color:var(--fg-1)">Manual entry</h3>
                <p style="margin-top:4px;font-size:12px;color:var(--fg-3)">Enter bi-weekly hours (Mon–Sun × 2). Optional state override for OT preview.</p>
                <form method="POST" action="{{ route('timesheets.store') }}" class="mt-4" style="display:flex;flex-direction:column;gap:16px">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="block text-sm">
                            <span class="eyebrow">Consultant</span>
                            <select name="consultant_id" x-model="consultantId" required
                                style="margin-top:6px;width:100%;background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;">
                                <option value="">—</option>
                                @foreach ($consultants as $c)
                                    <option value="{{ $c->id }}">{{ $c->full_name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm">
                            <span class="eyebrow">Client (optional)</span>
                            <select name="client_id"
                                style="margin-top:6px;width:100%;background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;">
                                <option value="">Default from consultant</option>
                                @foreach ($clients as $cl)
                                    <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm">
                            <span class="eyebrow">State override (OT preview)</span>
                            <select name="state" x-model="overrideState"
                                style="margin-top:6px;width:100%;background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;">
                                <option value="">Use consultant state</option>
                                @foreach ($usStates as $st)
                                    <option value="{{ $st }}">{{ $st }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm">
                            <span class="eyebrow">Pay period start</span>
                            <input type="date" name="pay_period_start" required
                                style="margin-top:6px;width:100%;background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;color-scheme:dark;" />
                        </label>
                        <label class="block text-sm">
                            <span class="eyebrow">Pay period end</span>
                            <input type="date" name="pay_period_end" required
                                style="margin-top:6px;width:100%;background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;color-scheme:dark;" />
                        </label>
                    </div>

                    @foreach ([1 => 'Week 1', 2 => 'Week 2'] as $wn => $label)
                        <div>
                            <p style="font-size:12px;font-weight:600;color:var(--fg-2)">{{ $label }} (Mon → Sun)</p>
                            <div class="mt-2 grid grid-cols-7 gap-2">
                                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $i => $day)
                                    <label style="font-size:11px;color:var(--fg-3)">
                                        {{ $day }}
                                        <input type="number" step="0.25" min="0" name="week{{ $wn }}[]" value="0"
                                            x-model.number="week{{ $wn }}[{{ $i }}]"
                                            style="margin-top:4px;width:100%;background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-sm);padding:4px;color:var(--fg-1);font-size:12px;outline:none;text-align:center;" />
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="flex flex-wrap items-end gap-3">
                        <button type="button" @click="previewOT()" class="btn btn-secondary btn-sm">
                            Preview OT
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            Save timesheet
                        </button>
                    </div>

                    <template x-if="otPreview">
                        <div style="border-radius:var(--radius-md);border:1px solid rgba(34,211,238,0.2);background:rgba(34,211,238,0.06);padding:12px;font-size:12px;color:var(--accent-400)">
                            <p style="font-weight:600">OT preview (approx.)</p>
                            <p style="margin-top:4px;color:var(--fg-2)" x-text="'Regular: ' + otPreview.totals.regularHours + ' h, OT: ' + otPreview.totals.otHours + ' h, DT: ' + otPreview.totals.doubleTimeHours + ' h'"></p>
                            <p style="color:var(--fg-3)" x-text="'Rule: ' + otPreview.otRuleApplied"></p>
                        </div>
                    </template>
                </form>
            </div>
        @endcan

        <div class="card-base" style="padding:0;overflow-x:auto">
            <table class="table">
                <thead>
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
                <tbody>
                    @forelse ($timesheets as $t)
                        <tr>
                            <td>{{ $t['pay_period_label'] ?? '' }}</td>
                            <td>{{ $t['consultant_name'] ?? '—' }}</td>
                            <td style="color:var(--fg-3)">{{ $t['client_name'] ?? '—' }}</td>
                            <td>{{ $t['total_regular_hours'] ?? '—' }}</td>
                            <td>{{ $t['total_ot_hours'] ?? '—' }}</td>
                            <td>{{ $t['total_dt_hours'] ?? '—' }}</td>
                            <td>
                                <span class="badge neutral">{{ $t['invoice_status'] ?? '—' }}</span>
                            </td>
                            <td>
                                <button type="button"
                                    x-on:click="viewOpen = true; viewLoading = true; viewData = null; apiFetch('{{ route('timesheets.show', $t['id']) }}').then(r => r.json()).then(d => { viewData = d; viewLoading = false; syncEditHoursFromViewData(); })"
                                    class="btn btn-ghost btn-sm">
                                    View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center;padding:32px;color:var(--fg-3)">No timesheets yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Import modal --}}
        <div x-show="importOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @keydown.escape.window="importOpen = false">
            <div @click.away="importOpen = false" class="card-base" style="max-height:90vh;width:100%;max-width:640px;overflow-y:auto">
                <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border-1);padding-bottom:12px;margin-bottom:16px">
                    <h3 style="font-size:16px;font-weight:600;color:var(--fg-1)">Import timesheet</h3>
                    <button type="button" @click="importOpen = false" style="color:var(--fg-3);background:none;border:none;cursor:pointer;font-size:16px;padding:4px">✕</button>
                </div>
                <div class="mt-4">
                    @livewire('timesheet-wizard')
                </div>
            </div>
        </div>

        {{-- View modal --}}
        <div x-show="viewOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @keydown.escape.window="viewOpen = false">
            <div @click.away="viewOpen = false" class="card-base" style="max-height:90vh;width:100%;max-width:760px;overflow-y:auto">
                <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border-1);padding-bottom:12px;margin-bottom:16px">
                    <h3 style="font-size:16px;font-weight:600;color:var(--fg-1)">Timesheet detail</h3>
                    <button type="button" @click="viewOpen = false" style="color:var(--fg-3);background:none;border:none;cursor:pointer;font-size:16px;padding:4px">✕</button>
                </div>
                <div style="font-size:13px">
                    <template x-if="viewLoading">
                        <p style="color:var(--fg-3)">Loading…</p>
                    </template>
                    <template x-if="!viewLoading && viewData">
                        <div style="display:flex;flex-direction:column;gap:20px" x-init="syncEditHoursFromViewData()">

                            {{-- Header info --}}
                            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px 24px;border-radius:var(--radius-md);background:var(--bg-2);padding:16px" class="sm:grid-cols-3">
                                <div>
                                    <p class="eyebrow">Consultant</p>
                                    <p style="font-weight:500;margin-top:4px" x-text="viewData.consultant_name ?? '—'"></p>
                                </div>
                                <div>
                                    <p class="eyebrow">Client</p>
                                    <p style="font-weight:500;margin-top:4px" x-text="viewData.client_name ?? '—'"></p>
                                </div>
                                <div>
                                    <p class="eyebrow">Pay period</p>
                                    <p style="font-weight:500;margin-top:4px" x-text="viewData.pay_period_label || (viewData.pay_period_start + ' – ' + viewData.pay_period_end)"></p>
                                </div>
                                <div>
                                    <p class="eyebrow">State</p>
                                    <p style="font-weight:500;margin-top:4px" x-text="viewData.state_snapshot"></p>
                                </div>
                                <div>
                                    <p class="eyebrow">Pay rate</p>
                                    <p class="mono-num" style="font-weight:500;margin-top:4px" x-text="'$' + parseFloat(viewData.pay_rate_snapshot).toFixed(2) + ' / hr'"></p>
                                </div>
                                <div>
                                    <p class="eyebrow">Bill rate</p>
                                    <p class="mono-num" style="font-weight:500;margin-top:4px" x-text="'$' + parseFloat(viewData.bill_rate_snapshot).toFixed(2) + ' / hr'"></p>
                                </div>
                                <div style="grid-column:1/-1">
                                    <p class="eyebrow">OT rule applied</p>
                                    <p style="font-weight:500;margin-top:4px" x-text="viewData.ot_rule_applied"></p>
                                </div>
                            </div>

                            @can('admin')
                                <div x-show="!viewData.locked_for_hour_edit" style="border-radius:var(--radius-md);border:1px solid rgba(245,158,11,0.2);background:rgba(245,158,11,0.06);padding:16px">
                                    <p style="font-weight:600;color:var(--warn-400)">Edit daily hours</p>
                                    <p style="margin-top:4px;font-size:11px;color:var(--fg-3)">Totals below use pay/bill rates and state <span style="font-weight:600">from when this timesheet was saved</span> (snapshots).</p>
                                    <div style="margin-top:12px;display:flex;flex-direction:column;gap:12px">
                                        <div>
                                            <p style="margin-bottom:6px;font-size:11px;font-weight:600;color:var(--fg-2)">Week 1 (Mon → Sun)</p>
                                            <div class="grid grid-cols-7 gap-1">
                                                @foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $i => $_d)
                                                    <label style="text-align:center;font-size:10px;color:var(--fg-3)">{{ $_d }}
                                                        <input type="number" step="0.25" min="0" x-model.number="editW1[{{ $i }}]"
                                                            style="margin-top:4px;width:100%;background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-sm);padding:3px;color:var(--fg-1);font-size:11px;outline:none;text-align:center;" />
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div>
                                            <p style="margin-bottom:6px;font-size:11px;font-weight:600;color:var(--fg-2)">Week 2 (Mon → Sun)</p>
                                            <div class="grid grid-cols-7 gap-1">
                                                @foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $i => $_d)
                                                    <label style="text-align:center;font-size:10px;color:var(--fg-3)">{{ $_d }}
                                                        <input type="number" step="0.25" min="0" x-model.number="editW2[{{ $i }}]"
                                                            style="margin-top:4px;width:100%;background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-sm);padding:3px;color:var(--fg-1);font-size:11px;outline:none;text-align:center;" />
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                        <button type="button" @click="saveEditedHours()" :disabled="editSaving" class="btn btn-primary btn-sm" style="align-self:flex-start">
                                            <span x-show="!editSaving">Save hours &amp; recalculate</span>
                                            <span x-show="editSaving">Saving…</span>
                                        </button>
                                    </div>
                                </div>
                                <div x-show="viewData.locked_for_hour_edit" style="border-radius:var(--radius-md);border:1px solid var(--border-1);background:var(--bg-2);padding:12px;font-size:11px;color:var(--fg-3)">
                                    Hours cannot be edited because an invoice is linked to this timesheet.
                                </div>
                            @endcan

                            {{-- Hours & pay by week --}}
                            <div>
                                <p class="eyebrow" style="margin-bottom:8px">Hours &amp; Pay</p>
                                <table class="table" style="font-size:12px">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th style="text-align:right">Reg hrs</th>
                                            <th style="text-align:right">OT hrs</th>
                                            <th style="text-align:right">DT hrs</th>
                                            <th style="text-align:right">Consultant pay</th>
                                            <th style="text-align:right">Client billable</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td style="color:var(--fg-3)">Week 1</td>
                                            <td class="mono-num" style="text-align:right" x-text="parseFloat(viewData.week1_regular_hours).toFixed(2)"></td>
                                            <td class="mono-num" style="text-align:right" x-text="parseFloat(viewData.week1_ot_hours).toFixed(2)"></td>
                                            <td class="mono-num" style="text-align:right" x-text="parseFloat(viewData.week1_dt_hours).toFixed(2)"></td>
                                            <td class="mono-num" style="text-align:right" x-text="'$' + (parseFloat(viewData.week1_regular_pay) + parseFloat(viewData.week1_ot_pay) + parseFloat(viewData.week1_dt_pay)).toFixed(2)"></td>
                                            <td class="mono-num" style="text-align:right" x-text="'$' + (parseFloat(viewData.week1_regular_billable) + parseFloat(viewData.week1_ot_billable) + parseFloat(viewData.week1_dt_billable)).toFixed(2)"></td>
                                        </tr>
                                        <tr>
                                            <td style="color:var(--fg-3)">Week 2</td>
                                            <td class="mono-num" style="text-align:right" x-text="parseFloat(viewData.week2_regular_hours).toFixed(2)"></td>
                                            <td class="mono-num" style="text-align:right" x-text="parseFloat(viewData.week2_ot_hours).toFixed(2)"></td>
                                            <td class="mono-num" style="text-align:right" x-text="parseFloat(viewData.week2_dt_hours).toFixed(2)"></td>
                                            <td class="mono-num" style="text-align:right" x-text="'$' + (parseFloat(viewData.week2_regular_pay) + parseFloat(viewData.week2_ot_pay) + parseFloat(viewData.week2_dt_pay)).toFixed(2)"></td>
                                            <td class="mono-num" style="text-align:right" x-text="'$' + (parseFloat(viewData.week2_regular_billable) + parseFloat(viewData.week2_ot_billable) + parseFloat(viewData.week2_dt_billable)).toFixed(2)"></td>
                                        </tr>
                                        <tr style="font-weight:600">
                                            <td>Total</td>
                                            <td class="mono-num" style="text-align:right" x-text="parseFloat(viewData.total_regular_hours).toFixed(2)"></td>
                                            <td class="mono-num" style="text-align:right" x-text="parseFloat(viewData.total_ot_hours).toFixed(2)"></td>
                                            <td class="mono-num" style="text-align:right" x-text="parseFloat(viewData.total_dt_hours).toFixed(2)"></td>
                                            <td class="mono-num" style="text-align:right" x-text="'$' + parseFloat(viewData.total_consultant_cost).toFixed(2)"></td>
                                            <td class="mono-num" style="text-align:right" x-text="'$' + parseFloat(viewData.total_client_billable).toFixed(2)"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            {{-- Margin summary --}}
                            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
                                <div style="border-radius:var(--radius-md);border:1px solid var(--border-1);background:var(--bg-2);padding:12px;text-align:center">
                                    <p class="eyebrow">Gross revenue</p>
                                    <p class="mono-num" style="margin-top:4px;font-size:18px;font-weight:600" x-text="'$' + parseFloat(viewData.gross_revenue).toFixed(2)"></p>
                                </div>
                                <div style="border-radius:var(--radius-md);border:1px solid var(--border-1);background:var(--bg-2);padding:12px;text-align:center">
                                    <p class="eyebrow">Gross margin</p>
                                    <p class="mono-num" style="margin-top:4px;font-size:18px;font-weight:600" x-text="'$' + parseFloat(viewData.gross_margin_dollars).toFixed(2)"></p>
                                </div>
                                <div style="border-radius:var(--radius-md);border:1px solid var(--border-1);background:var(--bg-2);padding:12px;text-align:center">
                                    <p class="eyebrow">Margin %</p>
                                    <p class="mono-num" style="margin-top:4px;font-size:18px;font-weight:600" x-text="parseFloat(viewData.gross_margin_percent).toFixed(1) + '%'"></p>
                                </div>
                            </div>

                            {{-- Invoice status --}}
                            <div style="display:flex;align-items:center;gap:8px">
                                <span class="eyebrow">Invoice status:</span>
                                <span class="badge neutral" x-text="viewData.invoice_status ?? 'pending'"></span>
                            </div>

                        </div>
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
