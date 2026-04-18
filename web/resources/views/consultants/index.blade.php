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
        'msa_contract' => 'MSA / contract on file',
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
        class="stack"
        x-data="consultantsPage(@js($clients), @js(auth()->user()->isAdmin()))"
    >
        <div class="row-between">
            <h2 style="font-size:22px;font-weight:700;letter-spacing:-0.01em;">Consultants</h2>
            @can('admin')
                <button
                    type="button"
                    class="btn btn-primary"
                    x-on:click="openCreate()"
                >
                    Add Consultant
                </button>
            @endcan
        </div>
        <div class="card-base" style="padding:0;overflow-x:auto;">
            <table class="table">
                <thead >
                    <tr>
                        <th >Name</th>
                        <th >Client</th>
                        <th >State</th>
                        <th >Pay Rate</th>
                        <th >Bill Rate</th>
                        <th >GMPH</th>
                        <th >Start</th>
                        <th >End</th>
                        <th >Checklist</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody >
                    @foreach ($consultants as $c)
                        <tr>
                            <td >
                                <span style="color:var(--fg-1);font-weight:500;">{{ $c->full_name }}</span>
                            </td>
                            {{-- Client --}}
                            <td
                                class="px-3 py-2"
                                x-data="inlineCell({{ (int) $c->id }}, 'client_id', {{ $c->client_id !== null ? (int) $c->client_id : 'null' }})"
                            >
                                <template x-if="!editing">
                                    <span
                                        @click="startEdit()"
                                        :class="isMissing() ? 'cursor-pointer text-indigo-500 hover:underline text-xs' : 'mono-dim cursor-pointer'"
                                        x-text="displayVal()"
                                    ></span>
                                </template>
                                <template x-if="editing">
                                    <div class="flex items-center gap-1">
                                        <select
                                            x-model="inputVal"
                                            @change="save()"
                                            @keydown.escape="cancel()"
                                            style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:6px;padding:3px 6px;font-size:12px;color:var(--fg-1);max-width:140px;outline:none;font-family:var(--font-sans);"
                                        >
                                            <option value="">— Select —</option>
                                            @foreach ($clients as $cl)
                                                <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" @click="cancel()" class="btn btn-ghost btn-sm">✕</button>
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
                                        :class="isMissing() ? 'cursor-pointer' style='color:var(--accent-400);font-size:12px;' : 'badge neutral cursor-pointer'"
                                        x-text="displayVal()"
                                    ></span>
                                </template>
                                <template x-if="editing">
                                    <div class="flex items-center gap-1">
                                        <select
                                            x-model="inputVal"
                                            @change="save()"
                                            @keydown.escape="cancel()"
                                            style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:6px;padding:3px 6px;font-size:12px;color:var(--fg-1);width:64px;outline:none;font-family:var(--font-sans);"
                                        >
                                            <option value="">—</option>
                                            @foreach ($usStates as $st)
                                                <option value="{{ $st }}">{{ $st }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" @click="cancel()" class="btn btn-ghost btn-sm">✕</button>
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
                                        :class="isMissing() ? 'cursor-pointer text-indigo-500 hover:underline text-xs' : 'mono-num cursor-pointer'"
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
                                            style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:6px;padding:3px 6px;font-size:12px;color:var(--fg-1);width:80px;outline:none;font-family:var(--font-sans);"
                                        />
                                        <button type="button" @click="cancel()" class="btn btn-ghost btn-sm">✕</button>
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
                                        :class="isMissing() ? 'cursor-pointer text-indigo-500 hover:underline text-xs' : 'mono-num cursor-pointer'"
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
                                            style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:6px;padding:3px 6px;font-size:12px;color:var(--fg-1);width:80px;outline:none;font-family:var(--font-sans);"
                                        />
                                        <button type="button" @click="cancel()" class="btn btn-ghost btn-sm">✕</button>
                                    </div>
                                </template>
                            </td>
                            <td class="mono-num">
                                @if($c->gross_margin_per_hour && $c->gross_margin_per_hour > 0)
                                    ${{ number_format((float) $c->gross_margin_per_hour, 2) }}/hr
                                @else
                                    <span style="color:var(--fg-4);">—</span>
                                @endif
                            </td>
                            {{-- Start Date --}}
                            <td
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
                                            style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:6px;padding:3px 6px;font-size:12px;color:var(--fg-1);outline:none;font-family:var(--font-sans);"
                                        />
                                        <button type="button" @click="cancel()" class="btn btn-ghost btn-sm">✕</button>
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
                                            style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:6px;padding:3px 6px;font-size:12px;color:var(--fg-1);outline:none;font-family:var(--font-sans);"
                                        />
                                        <button type="button" @click="cancel()" class="btn btn-ghost btn-sm">✕</button>
                                    </div>
                                </template>
                            </td>
                            <td class="px-3 py-2">
                                @php
                                    $done = (int) ($c->onboarding_complete ?? 0);
                                    $tot = max(1, (int) ($c->onboarding_total ?? 8));
                                    $rowPct = min(100, ($done / $tot) * 100);
                                    $rowComplete = $done >= $tot;
                                @endphp
                                <button
                                    type="button"
                                    class="badge {{ $rowComplete ? 'ok' : 'neutral' }}" style="padding:6px 8px;max-width:148px;text-align:left;cursor:pointer;display:block;width:100%;"
                                    @click="openOnboarding({{ (int) $c->id }})"
                                    title="Open onboarding checklist"
                                >
                                    <div class="eyebrow" style="margin-bottom:4px;display:flex;justify-content:space-between;">
                                        <span>Progress</span>
                                        <span class="mono-num">{{ $done }}/{{ $tot }}</span>
                                    </div>
                                    <div style="height:4px;width:100%;border-radius:999px;background:var(--bg-5);margin-top:4px;">
                                        <div
                                            style="height:4px;border-radius:999px;background:{{ $rowComplete ? 'var(--success-500)' : 'var(--accent-400)' }}"
                                            style="width: {{ $rowPct }}%"
                                        ></div>
                                    </div>
                                </button>
                            </td>
                            <td style="text-align:right;white-space:nowrap;">
                                @can('admin')
                                    <button type="button" class="btn btn-ghost btn-sm" @click="openEdit({{ (int) $c->id }})">Edit</button>
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-sm"
                                        @click="openContract({{ (int) $c->id }}, {{ ($c->contract_on_file ?? false) ? 'true' : 'false' }})"
                                    >
                                        Contract
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-sm"
                                        @click="openW9({{ (int) $c->id }}, {{ $c->w9_on_file ? 'true' : 'false' }})"
                                    >
                                        W-9
                                    </button>
                                    <button type="button" class="btn btn-ghost btn-sm" style="color:var(--danger-400);" @click="confirmDeactivate({{ (int) $c->id }})">Deactivate</button>
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
            <div class="card-base" style="max-height:90vh;width:100%;max-width:520px;overflow-y:auto;" @click.outside="showFormModal = false">
                <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;" x-text="isEdit ? 'Edit Consultant' : 'Add Consultant'"></h3>
                <div style="display:flex;flex-direction:column;gap:12px;margin-top:16px;">
                    <div>
                        <label class="eyebrow">Full Name *</label>
                        <input type="text" x-model="form.full_name" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:9px 12px;font-size:13px;color:var(--fg-1);width:100%;outline:none;font-family:var(--font-sans);margin-top:4px;" />
                    </div>
                    <div>
                        <label class="eyebrow">State *</label>
                        <select x-model="form.state" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:9px 12px;font-size:13px;color:var(--fg-1);width:100%;outline:none;font-family:var(--font-sans);margin-top:4px;">
                            @foreach ($usStates as $st)
                                <option value="{{ $st }}">{{ $st }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="eyebrow">Industry Type</label>
                        <select x-model="form.industry_type" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:9px 12px;font-size:13px;color:var(--fg-1);width:100%;outline:none;font-family:var(--font-sans);margin-top:4px;">
                            <option value="other">Other</option>
                            <option value="manufacturing">Manufacturing</option>
                            <option value="factory">Factory</option>
                            <option value="mill">Mill</option>
                        </select>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <label class="eyebrow">Pay Rate *</label>
                            <input type="number" step="0.01" x-model="form.pay_rate" @input="autoBillRate()" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:9px 12px;font-size:13px;color:var(--fg-1);width:100%;outline:none;font-family:var(--font-sans);margin-top:4px;" />
                        </div>
                        <div>
                            <label class="eyebrow">Bill Rate *</label>
                            <input type="number" step="0.01" x-model="form.bill_rate" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:9px 12px;font-size:13px;color:var(--fg-1);width:100%;outline:none;font-family:var(--font-sans);margin-top:4px;" />
                        </div>
                    </div>
                    <div x-show="form.gross_margin_per_hour !== null && form.gross_margin_per_hour !== ''" style="background:rgba(34,211,238,0.08);border:1px solid rgba(34,211,238,0.20);border-radius:var(--radius-md);padding:8px 12px;font-size:12px;color:var(--accent-300);">
                        Gross margin/hr from payroll: <span class="font-mono font-semibold" x-text="'$' + parseFloat(form.gross_margin_per_hour || 0).toFixed(2)"></span>
                        — bill rate auto-updates when you change pay rate.
                    </div>
                    <p style="font-size:13px;color:var(--fg-2);">Margin: <span class="mono-num" x-text="marginPct()"></span></p>
                    <div>
                        <label class="eyebrow">Client *</label>
                        <select x-model="form.client_id" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:9px 12px;font-size:13px;color:var(--fg-1);width:100%;outline:none;font-family:var(--font-sans);margin-top:4px;">
                            <option value="">— Select —</option>
                            <template x-for="cl in clientList" :key="cl.id">
                                <option :value="String(cl.id)" x-text="cl.name"></option>
                            </template>
                        </select>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <label class="eyebrow">Project Start</label>
                            <input type="date" x-model="form.project_start_date" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:9px 12px;font-size:13px;color:var(--fg-1);width:100%;outline:none;font-family:var(--font-sans);margin-top:4px;" />
                        </div>
                        <div>
                            <label class="eyebrow">Project End</label>
                            <input type="date" x-model="form.project_end_date" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:9px 12px;font-size:13px;color:var(--fg-1);width:100%;outline:none;font-family:var(--font-sans);margin-top:4px;" />
                        </div>
                    </div>
                </div>
                <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:8px;">
                    <button type="button" class="btn btn-ghost" @click="showFormModal = false">Cancel</button>
                    <button
                        type="button"
                        class="btn btn-primary"
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
            <div class="card-base" style="width:100%;max-width:440px;">
                <h3 style="font-size:16px;font-weight:700;">Onboarding checklist</h3>
                <p style="font-size:12px;color:var(--fg-3);margin-bottom:8px;" x-show="!canEditOnboarding">View only — contact an admin to update checklist items or upload the W-9 / contract.</p>
                <div style="height:6px;width:100%;border-radius:999px;background:var(--bg-5);margin:8px 0;">
                    <div style="height:6px;border-radius:999px;background:var(--accent-400);transition:width 0.3s;" :style="'width:' + onboardingProgress() + '%'"></div>
                </div>
                <ul style="margin-top:8px;">
                    <template x-for="row in onboardingItems" :key="row.id ?? row.item_key">
                        <li style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 0;border-bottom:1px solid var(--border-1);">
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
                    <p style="font-size:12px;color:var(--fg-3);margin-top:8px;">W-9 and client–agency contract (MSA): use <strong>Contract</strong> or <strong>W-9</strong> in the row Actions menu (checklist updates when uploaded).</p>
                @endcan
                <button type="button" class="mt-4 text-sm text-gray-600 hover:underline" @click="showOnboardingModal = false">Close</button>
            </div>
        </div>

        {{-- Contract (MSA) --}}
        <div
            x-show="showContractModal"
            x-cloak
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
            @keydown.escape.window="showContractModal = false"
        >
            <div class="card-base" style="width:100%;max-width:440px;">
                <h3 style="font-size:16px;font-weight:700;">Contract (MSA)</h3>
                <p style="font-size:12px;color:var(--fg-3);">Client–agency master service agreement for this consultant. PDF only, max 10MB.</p>
                <a
                    :href="contractConsultantId ? `/consultants/${contractConsultantId}/contract` : '#'"
                    target="_blank"
                    rel="noopener"
                    style="display:inline-block;margin-top:8px;font-size:13px;color:var(--accent-300);"
                    x-show="contractConsultantId && contractHasFile"
                >View current contract</a>
                <div style="display:flex;flex-direction:column;gap:12px;margin-top:16px;">
                    <input type="file" x-ref="contractInput" accept=".pdf,application/pdf" style="font-size:13px;color:var(--fg-2);font-family:var(--font-sans);" />
                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px;">
                        <button type="button" class="btn btn-ghost" @click="showContractModal = false">Cancel</button>
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="contractUploading"
                            @click="uploadContract()"
                            x-text="contractUploading ? 'Uploading…' : 'Upload'"
                        ></button>
                    </div>
                </div>
            </div>
        </div>

        {{-- W-9 --}}
        <div
            x-show="showW9Modal"
            x-cloak
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
            @keydown.escape.window="showW9Modal = false"
        >
            <div class="card-base" style="width:100%;max-width:440px;">
                <h3 style="font-size:16px;font-weight:700;">W-9</h3>
                <p style="font-size:12px;color:var(--fg-3);">PDF only, max 10MB.</p>
                <a
                    :href="w9ConsultantId ? `/consultants/${w9ConsultantId}/w9` : '#'"
                    target="_blank"
                    rel="noopener"
                    style="display:inline-block;margin-top:8px;font-size:13px;color:var(--accent-300);"
                    x-show="w9ConsultantId && w9HasFile"
                >View current W-9</a>
                <div style="display:flex;flex-direction:column;gap:12px;margin-top:16px;">
                    <input type="file" x-ref="w9Input" accept=".pdf,application/pdf" style="font-size:13px;color:var(--fg-2);font-family:var(--font-sans);" />
                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px;">
                        <button type="button" class="btn btn-ghost" @click="showW9Modal = false">Cancel</button>
                        <button
                            type="button"
                            class="btn btn-primary"
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
            <div class="card-base" style="width:100%;max-width:380px;">
                <p style="font-size:14px;color:var(--fg-2);">Deactivate this consultant?</p>
                <div style="margin-top:16px;display:flex;justify-content:flex-end;gap:8px;">
                    <button type="button" class="btn btn-ghost" @click="deactivateId = null">Cancel</button>
                    <button type="button" class="btn btn-danger" @click="doDeactivate()">Deactivate</button>
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
                showContractModal: false,
                showW9Modal: false,
                isEdit: false,
                saving: false,
                onboardingItems: [],
                onboardingConsultantId: null,
                contractConsultantId: null,
                contractHasFile: false,
                contractUploading: false,
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
                openContract(id, hasFile) {
                    this.contractConsultantId = id;
                    this.contractHasFile = !!hasFile;
                    this.showContractModal = true;
                    this.$nextTick(() => {
                        if (this.$refs.contractInput) this.$refs.contractInput.value = '';
                    });
                },
                async uploadContract() {
                    const file = this.$refs.contractInput?.files?.[0];
                    if (!file || !this.contractConsultantId) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Choose a PDF file', type: 'error' } }));
                        return;
                    }
                    this.contractUploading = true;
                    const fd = new FormData();
                    fd.append('contract', file);
                    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const res = await fetch(`/consultants/${this.contractConsultantId}/contract`, {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd,
                    });
                    this.contractUploading = false;
                    if (res.ok) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Contract uploaded' } }));
                        this.showContractModal = false;
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
