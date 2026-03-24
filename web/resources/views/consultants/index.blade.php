@php
    use Illuminate\Support\Carbon;

    $usStates = [
        'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'DC', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA',
        'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM',
        'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA',
        'WV', 'WI', 'WY',
    ];

    $onboardingLabels = [
        'w9' => 'W-9 on file',
        'pay_rate_confirmed' => 'Pay rate confirmed',
        'bill_rate_confirmed' => 'Bill rate confirmed',
        'client_assigned' => 'Client assigned',
        'start_date_set' => 'Start date set',
        'end_date_set' => 'End date set',
        'timesheet_template_sent' => 'Timesheet template sent',
    ];
@endphp

<x-app-layout>
    <div
        class="space-y-4"
        x-data="consultantsPage(@js($clients), @js(auth()->user()->isAdmin()))"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold text-gray-800">Consultants</h2>
            @can('admin')
                <button
                    type="button"
                    class="rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    x-on:click="openCreate()"
                >
                    Add Consultant
                </button>
            @endcan
        </div>
        <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-3 py-3">Name</th>
                        <th class="px-3 py-3">Client</th>
                        <th class="px-3 py-3">State</th>
                        <th class="px-3 py-3">Pay Rate</th>
                        <th class="px-3 py-3">Bill Rate</th>
                        <th class="px-3 py-3">GMPH</th>
                        <th class="px-3 py-3">Start</th>
                        <th class="px-3 py-3">End</th>
                        <th class="px-3 py-3">Checklist</th>
                        <th class="px-3 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($consultants as $c)
                        <tr>
                            <td class="px-3 py-2">
                                <span class="font-medium text-gray-900">{{ $c->full_name }}</span>
                            </td>
                            {{-- Client --}}
                            <td
                                class="px-3 py-2"
                                x-data="inlineCell({{ (int) $c->id }}, 'client_id', {{ $c->client_id !== null ? (int) $c->client_id : 'null' }})"
                            >
                                <template x-if="!editing">
                                    <span
                                        @click="startEdit()"
                                        :class="isMissing() ? 'cursor-pointer text-indigo-500 hover:underline text-xs' : 'text-gray-600 cursor-pointer hover:text-indigo-500'"
                                        x-text="displayVal()"
                                    ></span>
                                </template>
                                <template x-if="editing">
                                    <div class="flex items-center gap-1">
                                        <select
                                            x-model="inputVal"
                                            @change="save()"
                                            @keydown.escape="cancel()"
                                            class="rounded border border-indigo-400 px-1 py-0.5 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400 max-w-[140px]"
                                        >
                                            <option value="">— Select —</option>
                                            @foreach ($clients as $cl)
                                                <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" @click="cancel()" class="text-gray-400 hover:text-gray-600 text-xs">✕</button>
                                    </div>
                                </template>
                            </td>
                            {{-- State --}}
                            <td
                                class="px-3 py-2"
                                x-data="inlineCell({{ (int) $c->id }}, 'state', {{ $c->state !== null ? Js::from($c->state) : 'null' }})"
                            >
                                <template x-if="!editing">
                                    <span
                                        @click="startEdit()"
                                        :class="isMissing() ? 'cursor-pointer text-indigo-500 hover:underline text-xs' : 'rounded bg-gray-100 px-2 py-0.5 text-xs font-medium cursor-pointer hover:bg-indigo-100'"
                                        x-text="displayVal()"
                                    ></span>
                                </template>
                                <template x-if="editing">
                                    <div class="flex items-center gap-1">
                                        <select
                                            x-model="inputVal"
                                            @change="save()"
                                            @keydown.escape="cancel()"
                                            class="rounded border border-indigo-400 px-1 py-0.5 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400 w-16"
                                        >
                                            <option value="">—</option>
                                            @foreach ($usStates as $st)
                                                <option value="{{ $st }}">{{ $st }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" @click="cancel()" class="text-gray-400 hover:text-gray-600 text-xs">✕</button>
                                    </div>
                                </template>
                            </td>
                            {{-- Pay Rate --}}
                            <td
                                class="px-3 py-2"
                                x-data="inlineCell({{ (int) $c->id }}, 'pay_rate', {{ $c->pay_rate !== null ? (float) $c->pay_rate : 'null' }})"
                            >
                                <template x-if="!editing">
                                    <span
                                        @click="startEdit()"
                                        :class="isMissing() ? 'cursor-pointer text-indigo-500 hover:underline text-xs' : 'text-gray-700 cursor-pointer hover:text-indigo-500'"
                                        x-text="displayVal()"
                                    ></span>
                                </template>
                                <template x-if="editing">
                                    <div class="flex items-center gap-1">
                                        <input
                                            type="number" step="0.01" min="0"
                                            x-model="inputVal"
                                            @blur="save()"
                                            @keydown.enter="save()"
                                            @keydown.escape="cancel()"
                                            class="w-20 rounded border border-indigo-400 px-1.5 py-0.5 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                        />
                                        <button type="button" @click="cancel()" class="text-gray-400 hover:text-gray-600 text-xs">✕</button>
                                    </div>
                                </template>
                            </td>
                            {{-- Bill Rate --}}
                            <td
                                class="px-3 py-2"
                                x-data="inlineCell({{ (int) $c->id }}, 'bill_rate', {{ $c->bill_rate !== null ? (float) $c->bill_rate : 'null' }})"
                            >
                                <template x-if="!editing">
                                    <span
                                        @click="startEdit()"
                                        :class="isMissing() ? 'cursor-pointer text-indigo-500 hover:underline text-xs' : 'text-gray-700 cursor-pointer hover:text-indigo-500'"
                                        x-text="displayVal()"
                                    ></span>
                                </template>
                                <template x-if="editing">
                                    <div class="flex items-center gap-1">
                                        <input
                                            type="number" step="0.01" min="0"
                                            x-model="inputVal"
                                            @blur="save()"
                                            @keydown.enter="save()"
                                            @keydown.escape="cancel()"
                                            class="w-20 rounded border border-indigo-400 px-1.5 py-0.5 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                        />
                                        <button type="button" @click="cancel()" class="text-gray-400 hover:text-gray-600 text-xs">✕</button>
                                    </div>
                                </template>
                            </td>
                            <td class="px-3 py-2 text-sm text-gray-700">
                                @if($c->gross_margin_per_hour && $c->gross_margin_per_hour > 0)
                                    ${{ number_format((float) $c->gross_margin_per_hour, 2) }}/hr
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            {{-- Start Date --}}
                            <td
                                class="px-3 py-2 text-gray-600"
                                x-data="inlineCell({{ (int) $c->id }}, 'project_start_date', {{ $c->project_start_date ? Js::from(substr((string) $c->project_start_date, 0, 10)) : 'null' }})"
                            >
                                <template x-if="!editing">
                                    <span
                                        @click="startEdit()"
                                        :class="isMissing() ? 'cursor-pointer text-indigo-500 hover:underline text-xs' : 'cursor-pointer hover:text-indigo-500'"
                                        x-text="displayVal()"
                                    ></span>
                                </template>
                                <template x-if="editing">
                                    <div class="flex items-center gap-1">
                                        <input
                                            type="date"
                                            x-model="inputVal"
                                            @blur="save()"
                                            @keydown.enter="save()"
                                            @keydown.escape="cancel()"
                                            class="rounded border border-indigo-400 px-1.5 py-0.5 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                        />
                                        <button type="button" @click="cancel()" class="text-gray-400 hover:text-gray-600 text-xs">✕</button>
                                    </div>
                                </template>
                            </td>
                            {{-- End Date --}}
                            @php
                                $endClass = 'text-gray-400';
                                if ($c->project_end_date) {
                                    $end = Carbon::parse((string) $c->project_end_date)->startOfDay();
                                    $today = Carbon::now()->startOfDay();
                                    $daysLeft = (int) floor(($end->timestamp - $today->timestamp) / 86400);
                                    $endClass = $daysLeft < 0 ? 'text-gray-400' : ($daysLeft <= 7 ? 'text-red-600 font-semibold' : ($daysLeft <= 14 ? 'text-orange-500 font-semibold' : ($daysLeft <= 30 ? 'text-yellow-600' : 'text-gray-700')));
                                }
                            @endphp
                            <td
                                class="px-3 py-2 font-medium"
                                x-data="inlineCell({{ (int) $c->id }}, 'project_end_date', {{ $c->project_end_date ? Js::from(substr((string) $c->project_end_date, 0, 10)) : 'null' }})"
                            >
                                <template x-if="!editing">
                                    <span
                                        @click="startEdit()"
                                        :class="isMissing() ? 'cursor-pointer text-indigo-500 hover:underline text-xs' : 'cursor-pointer hover:text-indigo-500 {{ $endClass }}'"
                                        x-text="displayVal()"
                                    ></span>
                                </template>
                                <template x-if="editing">
                                    <div class="flex items-center gap-1">
                                        <input
                                            type="date"
                                            x-model="inputVal"
                                            @blur="save()"
                                            @keydown.enter="save()"
                                            @keydown.escape="cancel()"
                                            class="rounded border border-indigo-400 px-1.5 py-0.5 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                        />
                                        <button type="button" @click="cancel()" class="text-gray-400 hover:text-gray-600 text-xs">✕</button>
                                    </div>
                                </template>
                            </td>
                            <td class="px-3 py-2">
                                @php
                                    $done = (int) ($c->onboarding_complete ?? 0);
                                    $tot = max(1, (int) ($c->onboarding_total ?? 7));
                                    $rowPct = min(100, ($done / $tot) * 100);
                                    $rowComplete = $done >= $tot;
                                @endphp
                                <button
                                    type="button"
                                    class="max-w-[148px] rounded-lg border px-2 py-1.5 text-left transition hover:border-indigo-300 hover:bg-indigo-50/60 focus:outline-none focus:ring-2 focus:ring-indigo-400 {{ $rowComplete ? 'border-green-200 bg-green-50/90' : 'border-gray-200 bg-white' }}"
                                    @click="openOnboarding({{ (int) $c->id }})"
                                    title="Open onboarding checklist"
                                >
                                    <div class="mb-1 flex items-center justify-between gap-2 text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                                        <span>Progress</span>
                                        <span class="tabular-nums text-gray-700">{{ $done }}/{{ $tot }}</span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200">
                                        <div
                                            class="h-1.5 rounded-full {{ $rowComplete ? 'bg-green-500' : 'bg-indigo-600' }}"
                                            style="width: {{ $rowPct }}%"
                                        ></div>
                                    </div>
                                </button>
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                @can('admin')
                                    <button type="button" class="text-xs text-indigo-600 hover:underline" @click="openEdit({{ (int) $c->id }})">Edit</button>
                                    <button
                                        type="button"
                                        class="ml-1 text-xs text-gray-600 hover:underline"
                                        @click="openW9({{ (int) $c->id }}, {{ $c->w9_on_file ? 'true' : 'false' }})"
                                    >
                                        W-9
                                    </button>
                                    <button type="button" class="ml-1 text-xs text-red-600 hover:underline" @click="confirmDeactivate({{ (int) $c->id }})">Deactivate</button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Add / Edit --}}
        <div
            x-show="showFormModal"
            x-cloak
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
            @keydown.escape.window="showFormModal = false"
        >
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-6 shadow-xl" @click.outside="showFormModal = false">
                <h3 class="text-lg font-semibold" x-text="isEdit ? 'Edit Consultant' : 'Add Consultant'"></h3>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Full Name *</label>
                        <input type="text" x-model="form.full_name" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">State *</label>
                        <select x-model="form.state" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
                            @foreach ($usStates as $st)
                                <option value="{{ $st }}">{{ $st }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Industry Type</label>
                        <select x-model="form.industry_type" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
                            <option value="other">Other</option>
                            <option value="manufacturing">Manufacturing</option>
                            <option value="factory">Factory</option>
                            <option value="mill">Mill</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Pay Rate *</label>
                            <input type="number" step="0.01" x-model="form.pay_rate" @input="autoBillRate()" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Bill Rate *</label>
                            <input type="number" step="0.01" x-model="form.bill_rate" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
                        </div>
                    </div>
                    <div x-show="form.gross_margin_per_hour !== null && form.gross_margin_per_hour !== ''" class="rounded bg-blue-50 px-3 py-2 text-xs text-blue-700">
                        Gross margin/hr from payroll: <span class="font-mono font-semibold" x-text="'$' + parseFloat(form.gross_margin_per_hour || 0).toFixed(2)"></span>
                        — bill rate auto-updates when you change pay rate.
                    </div>
                    <p class="text-sm text-gray-600">Margin: <span class="font-mono font-medium text-gray-900" x-text="marginPct()"></span></p>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Client *</label>
                        <select x-model="form.client_id" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
                            <option value="">— Select —</option>
                            <template x-for="cl in clientList" :key="cl.id">
                                <option :value="String(cl.id)" x-text="cl.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Project Start</label>
                            <input type="date" x-model="form.project_start_date" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Project End</label>
                            <input type="date" x-model="form.project_end_date" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm" />
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100" @click="showFormModal = false">Cancel</button>
                    <button
                        type="button"
                        class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        :disabled="saving"
                        @click="saveConsultant()"
                        x-text="saving ? 'Saving…' : 'Save'"
                    ></button>
                </div>
            </div>
        </div>

        {{-- Onboarding --}}
        <div
            x-show="showOnboardingModal"
            x-cloak
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
            @keydown.escape.window="showOnboardingModal = false"
        >
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold">Onboarding checklist</h3>
                <p class="mt-1 text-xs text-gray-500" x-show="!canEditOnboarding">View only — contact an admin to update checklist items or file the W-9.</p>
                <div class="mt-3 h-2 w-full rounded-full bg-gray-100">
                    <div class="h-2 rounded-full bg-indigo-600 transition-all" :style="'width:' + onboardingProgress() + '%'"></div>
                </div>
                <ul class="mt-4 space-y-2 text-sm">
                    <template x-for="row in onboardingItems" :key="row.id ?? row.item_key">
                        <li class="flex items-center justify-between gap-2 border-b border-gray-50 py-2">
                            <span x-text="onboardingLabel(row.item_key)"></span>
                            <template x-if="canEditOnboarding">
                                <button
                                    type="button"
                                    class="rounded px-2 py-1 text-xs shrink-0"
                                    :class="row.completed ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                                    @click="toggleOnboarding(row)"
                                    x-text="row.completed ? 'Done' : 'Mark'"
                                ></button>
                            </template>
                            <template x-if="!canEditOnboarding">
                                <span
                                    class="rounded px-2 py-1 text-xs shrink-0"
                                    :class="row.completed ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                                    x-text="row.completed ? 'Done' : 'Pending'"
                                ></span>
                            </template>
                        </li>
                    </template>
                </ul>
                @can('admin')
                    <p class="mt-3 text-xs text-gray-500">W-9 file: use <strong>W-9</strong> in the row Actions menu (marks this checklist item when uploaded).</p>
                @endcan
                <button type="button" class="mt-4 text-sm text-gray-600 hover:underline" @click="showOnboardingModal = false">Close</button>
            </div>
        </div>

        {{-- W-9 --}}
        <div
            x-show="showW9Modal"
            x-cloak
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
            @keydown.escape.window="showW9Modal = false"
        >
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold">W-9</h3>
                <p class="mt-1 text-xs text-gray-500">PDF only, max 10MB.</p>
                <a
                    :href="w9ConsultantId ? `/consultants/${w9ConsultantId}/w9` : '#'"
                    target="_blank"
                    rel="noopener"
                    class="mt-3 inline-block text-sm text-indigo-600 hover:underline"
                    x-show="w9ConsultantId && w9HasFile"
                >View current W-9</a>
                <div class="mt-4 space-y-3">
                    <input type="file" x-ref="w9Input" accept=".pdf,application/pdf" class="block w-full text-sm" />
                    <div class="flex justify-end gap-2">
                        <button type="button" class="text-sm text-gray-600" @click="showW9Modal = false">Cancel</button>
                        <button
                            type="button"
                            class="rounded bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700 disabled:opacity-50"
                            :disabled="w9Uploading"
                            @click="uploadW9()"
                            x-text="w9Uploading ? 'Uploading…' : 'Upload'"
                        ></button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Deactivate --}}
        <div
            x-show="deactivateId !== null"
            x-cloak
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
        >
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                <p class="text-sm text-gray-700">Deactivate this consultant?</p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100" @click="deactivateId = null">Cancel</button>
                    <button type="button" class="rounded bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700" @click="doDeactivate()">Deactivate</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const PAGE_CLIENTS = @json($clients);
        const PAGE_STATES = @json($usStates);

        function inlineCell(consultantId, field, initialValue) {
            return {
                editing: false,
                currentVal: initialValue !== null && initialValue !== undefined ? initialValue : null,
                inputVal: '',
                saving: false,

                isMissing() {
                    if (this.currentVal === null || this.currentVal === '') return true;
                    if (field === 'pay_rate' || field === 'bill_rate') return !parseFloat(this.currentVal);
                    return false;
                },

                displayVal() {
                    if (field === 'pay_rate' || field === 'bill_rate') {
                        const v = parseFloat(this.currentVal);
                        return v > 0 ? '$' + v.toFixed(2) + '/hr' : '+ Add rate';
                    }
                    if (field === 'client_id') {
                        const cl = PAGE_CLIENTS.find(c => String(c.id) === String(this.currentVal));
                        return cl ? cl.name : '+ Assign client';
                    }
                    if (field === 'state') {
                        return this.currentVal || '+ Add state';
                    }
                    if (field === 'project_start_date' || field === 'project_end_date') {
                        if (!this.currentVal) return '+ Add date';
                        const d = new Date(this.currentVal);
                        return (d.getMonth() + 1).toString().padStart(2, '0') + '/' + d.getDate().toString().padStart(2, '0') + '/' + d.getFullYear();
                    }
                    return this.currentVal || '—';
                },

                startEdit() {
                    this.inputVal = this.currentVal !== null && this.currentVal !== undefined ? String(this.currentVal) : '';
                    this.editing = true;
                    setTimeout(() => {
                        const el = this.$el.querySelector('input,select');
                        if (el) { el.focus(); el.select?.(); }
                    }, 10);
                },

                async save() {
                    if (this.saving) return;
                    this.editing = false;
                    this.saving = true;
                    const res = await apiFetch(`/consultants/${consultantId}/field`, {
                        method: 'PATCH',
                        body: JSON.stringify({ field: field, value: this.inputVal || null }),
                    });
                    this.saving = false;
                    if (res.ok) {
                        this.currentVal = this.inputVal || null;
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Saved' } }));
                    } else {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Could not save', type: 'error' } }));
                    }
                },

                cancel() {
                    this.editing = false;
                },
            };
        }

        function consultantsPage(clientList, canEditOnboarding) {
            const labels = @json($onboardingLabels);

            return {
                clientList: clientList || [],
                canEditOnboarding: !!canEditOnboarding,
                showFormModal: false,
                showOnboardingModal: false,
                showW9Modal: false,
                isEdit: false,
                saving: false,
                onboardingItems: [],
                onboardingConsultantId: null,
                w9ConsultantId: null,
                w9HasFile: false,
                w9Uploading: false,
                deactivateId: null,
                form: {
                    id: null,
                    full_name: '',
                    state: 'TX',
                    industry_type: 'other',
                    pay_rate: '',
                    bill_rate: '',
                    gross_margin_per_hour: null,
                    client_id: '',
                    project_start_date: '',
                    project_end_date: '',
                },
                autoBillRate() {
                    const gmph = parseFloat(this.form.gross_margin_per_hour);
                    if (!isNaN(gmph) && gmph > 0) {
                        const pay = parseFloat(this.form.pay_rate);
                        if (!isNaN(pay)) {
                            this.form.bill_rate = (pay + gmph).toFixed(4);
                        }
                    }
                },
                marginPct() {
                    const p = parseFloat(this.form.pay_rate) || 0;
                    const b = parseFloat(this.form.bill_rate) || 0;
                    if (!b) return '—';
                    return (((b - p) / b) * 100).toFixed(1) + '%';
                },
                onboardingLabel(key) {
                    return labels[key] || key;
                },
                onboardingProgress() {
                    if (!this.onboardingItems.length) return 0;
                    const done = this.onboardingItems.filter((r) => r.completed).length;
                    return (done / this.onboardingItems.length) * 100;
                },
                openCreate() {
                    this.isEdit = false;
                    this.form = {
                        id: null,
                        full_name: '',
                        state: 'TX',
                        industry_type: 'other',
                        pay_rate: '',
                        bill_rate: '',
                        gross_margin_per_hour: null,
                        client_id: '',
                        project_start_date: '',
                        project_end_date: '',
                    };
                    this.showFormModal = true;
                },
                async openEdit(id) {
                    const r = await apiFetch(`/consultants/${id}`).then((x) => x.json());
                    this.form = {
                        id: r.id,
                        full_name: r.full_name ?? '',
                        state: (r.state || 'TX').toUpperCase().slice(0, 2),
                        industry_type: r.industry_type || 'other',
                        pay_rate: r.pay_rate != null ? String(r.pay_rate) : '',
                        bill_rate: r.bill_rate != null ? String(r.bill_rate) : '',
                        gross_margin_per_hour: r.gross_margin_per_hour != null ? String(r.gross_margin_per_hour) : null,
                        client_id: r.client_id != null ? String(r.client_id) : '',
                        project_start_date: r.project_start_date ? String(r.project_start_date).slice(0, 10) : '',
                        project_end_date: r.project_end_date ? String(r.project_end_date).slice(0, 10) : '',
                    };
                    this.isEdit = true;
                    this.showFormModal = true;
                },
                async saveConsultant() {
                    if (this.form.project_start_date && this.form.project_end_date && this.form.project_end_date < this.form.project_start_date) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'End date must be on or after start date', type: 'error' } }));
                        return;
                    }
                    if (!this.form.client_id) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Client is required', type: 'error' } }));
                        return;
                    }
                    this.saving = true;
                    const body = {
                        full_name: this.form.full_name,
                        pay_rate: Number(this.form.pay_rate),
                        bill_rate: Number(this.form.bill_rate),
                        state: this.form.state,
                        industry_type: this.form.industry_type,
                        client_id: Number(this.form.client_id),
                        project_start_date: this.form.project_start_date || null,
                        project_end_date: this.form.project_end_date || null,
                    };
                    const url = this.isEdit ? `/consultants/${this.form.id}` : '/consultants';
                    const method = this.isEdit ? 'PUT' : 'POST';
                    const res = await apiFetch(url, { method, body: JSON.stringify(body) });
                    this.saving = false;
                    if (res.ok) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Saved' } }));
                        this.showFormModal = false;
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
                async openOnboarding(id) {
                    this.onboardingConsultantId = id;
                    const rows = await apiFetch(`/consultants/${id}/onboarding`).then((r) => r.json());
                    this.onboardingItems = Array.isArray(rows) ? rows : [];
                    this.showOnboardingModal = true;
                },
                async toggleOnboarding(row) {
                    const id = this.onboardingConsultantId;
                    const next = !row.completed;
                    const res = await apiFetch(`/consultants/${id}/onboarding`, {
                        method: 'PUT',
                        body: JSON.stringify({ item: row.item_key, completed: next }),
                    });
                    if (res.ok) {
                        row.completed = next;
                    } else {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Could not update', type: 'error' } }));
                    }
                },
                openW9(id, hasFile) {
                    this.w9ConsultantId = id;
                    this.w9HasFile = !!hasFile;
                    this.showW9Modal = true;
                    this.$nextTick(() => {
                        if (this.$refs.w9Input) this.$refs.w9Input.value = '';
                    });
                },
                async uploadW9() {
                    const file = this.$refs.w9Input?.files?.[0];
                    if (!file || !this.w9ConsultantId) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Choose a PDF file', type: 'error' } }));
                        return;
                    }
                    this.w9Uploading = true;
                    const fd = new FormData();
                    fd.append('w9', file);
                    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const res = await fetch(`/consultants/${this.w9ConsultantId}/w9`, {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd,
                    });
                    this.w9Uploading = false;
                    if (res.ok) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'W-9 uploaded' } }));
                        this.showW9Modal = false;
                        window.location.reload();
                    } else {
                        let msg = 'Upload failed';
                        try {
                            const e = await res.json();
                            if (e.message) msg = e.message;
                        } catch (_) {}
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: msg, type: 'error' } }));
                    }
                },
                confirmDeactivate(id) {
                    this.deactivateId = id;
                },
                async doDeactivate() {
                    await apiFetch(`/consultants/${this.deactivateId}/deactivate`, { method: 'POST', body: '{}' });
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Consultant deactivated' } }));
                    this.deactivateId = null;
                    window.location.reload();
                },
            };
        }
    </script>
</x-app-layout>
