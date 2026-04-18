<div wire:key="placement-manager-root" class="space-y-4 text-sm text-gray-800">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-gray-900">Placements</h2>
        @can('account_manager')
            <button
                type="button"
                wire:click="openCreate"
                class="btn btn-primary btn-sm"
            >
                Add Placement
            </button>
        @endcan
    </div>

    <div class="card-base">
        <label class="block min-w-[160px] text-xs font-medium text-gray-500">
            Consultant
            <input
                type="text"
                wire:model.live.debounce.300ms="filters.consultant_name"
                placeholder="Search name…"
                class="mt-1 block w-full rounded border border-gray-300 px-2 py-1.5 text-gray-900"
            />
        </label>
        <label class="block min-w-[160px] text-xs font-medium text-gray-500">
            Client
            <select
                wire:model.live="filters.client_id"
                class="mt-1 block w-full rounded border border-gray-300 px-2 py-1.5 text-gray-900"
            >
                <option value="">All</option>
                @foreach ($clientOptions as $opt)
                    <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="block min-w-[140px] text-xs font-medium text-gray-500">
            Status
            <select
                wire:model.live="filters.status"
                class="mt-1 block w-full rounded border border-gray-300 px-2 py-1.5 text-gray-900"
            >
                <option value="">All</option>
                <option value="active">Active</option>
                <option value="ended">Ended</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </label>
    </div>

    <div class="card-base" style="padding:0;overflow-x:auto">
        <table class="table">
            <thead >
                <tr>
                    <th class="px-4 py-3">Consultant</th>
                    <th class="px-4 py-3">Client</th>
                    <th class="px-4 py-3">Job Title</th>
                    <th class="px-4 py-3">Start</th>
                    <th class="px-4 py-3">End</th>
                    <th class="px-4 py-3">Pay Rate</th>
                    <th class="px-4 py-3">Bill Rate</th>
                    <th class="px-4 py-3">PO#</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Account Manager</th>
                    @can('account_manager')
                        <th class="px-4 py-3 text-right">Actions</th>
                    @endcan
                </tr>
            </thead>
            <tbody >
                @forelse ($placements ?? [] as $p)
                    <tr wire:key="placement-row-{{ $p->id }}" class="text-gray-700">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            {{ $p->consultant_name ?? $p->consultant?->full_name ?? '—' }}
                        </td>
                        <td class="px-4 py-3">{{ $p->client?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $p->job_title ?: '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $p->start_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $p->end_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-4 py-3">${{ number_format((float) $p->pay_rate, 2) }}</td>
                        <td class="px-4 py-3">${{ number_format((float) $p->bill_rate, 2) }}</td>
                        <td class="px-4 py-3">{{ $p->po_number ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($p->status === 'active')
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Active</span>
                            @elseif ($p->status === 'ended')
                                <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700">Ended</span>
                            @else
                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Cancelled</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $p->placedBy?->name ?? '—' }}</td>
                        @can('account_manager')
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <button
                                        type="button"
                                        wire:click="openEdit({{ $p->id }})"
                                        class="btn btn-secondary btn-sm"
                                    >
                                        Edit
                                    </button>
                                    <select
                                        wire:key="placement-status-action-{{ $p->id }}-{{ $p->status }}"
                                        class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-800"
                                        wire:change="updateStatus({{ $p->id }}, $event.target.value)"
                                    >
                                        <option value="" disabled {{ $p->status ? '' : 'selected' }}>Status…</option>
                                        <option value="active" {{ $p->status === 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="ended" {{ $p->status === 'ended' ? 'selected' : '' }}>Ended</option>
                                        <option value="cancelled" {{ $p->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                    </select>
                                </div>
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="{{ auth()->user()?->can('account_manager') ? 11 : 10 }}"
                            class="px-4 py-8 text-center text-gray-500"
                        >
                            No placements found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{-- Pagination --}}
        @if($totalPlacements > $perPage)
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-top:1px solid var(--border-1)">
            <div class="text-sm text-gray-600">
                Showing {{ (($page - 1) * $perPage) + 1 }}–{{ min($page * $perPage, $totalPlacements) }} of {{ $totalPlacements }}
            </div>
            <div class="flex gap-2">
                @if($page > 1)
                    <button wire:click="prevPage"
                        class="btn btn-secondary btn-sm">
                        ← Prev
                    </button>
                @endif
                @if(($page * $perPage) < $totalPlacements)
                    <button wire:click="nextPage"
                        class="btn btn-secondary btn-sm">
                        Next →
                    </button>
                @endif
            </div>
        </div>
        @endif
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="cancelForm">
            <div
                class="card-base" style="max-height:90vh;width:100%;max-width:520px;overflow-y:auto"
                @click.stop
                wire:click.stop
            >
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ $editingId ? 'Edit placement' : 'Add placement' }}
                </h3>
                <div class="mt-4 grid gap-3">
                    <label class="block text-xs text-gray-500">
                        Consultant
                        <input
                            type="text"
                            wire:model="consultant_name"
                            placeholder="Full name"
                            class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-gray-900"
                        />
                        @error('consultant_name')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </label>
                    <label class="block text-xs text-gray-500">
                        Client
                        <select
                            wire:model="client_id"
                            class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-gray-900"
                        >
                            <option value="">—</option>
                            @foreach ($clientOptions as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                        @error('client_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </label>
                    <label class="block text-xs text-gray-500">
                        Job title
                        <input
                            type="text"
                            wire:model="job_title"
                            class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-gray-900"
                        />
                        @error('job_title')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs text-gray-500">
                            Start date
                            <input
                                type="date"
                                wire:model="start_date"
                                class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-gray-900"
                            />
                            @error('start_date')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </label>
                        <label class="block text-xs text-gray-500">
                            End date
                            <input
                                type="date"
                                wire:model="end_date"
                                class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-gray-900"
                            />
                            @error('end_date')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </label>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs text-gray-500">
                            Pay rate
                            <input
                                type="text"
                                inputmode="decimal"
                                wire:model="pay_rate"
                                class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-gray-900"
                            />
                            @error('pay_rate')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </label>
                        <label class="block text-xs text-gray-500">
                            Bill rate
                            <input
                                type="text"
                                inputmode="decimal"
                                wire:model="bill_rate"
                                class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-gray-900"
                            />
                            @error('bill_rate')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">PO Number</label>
                        @if (auth()->user()?->role === 'admin')
                            <input
                                type="text"
                                wire:model="po_number"
                                style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;"
                            />
                        @else
                            <p class="mt-1 text-sm text-gray-600">{{ $po_number ?: '—' }}</p>
                        @endif
                    </div>
                    <label class="block text-xs text-gray-500">
                        Notes
                        <textarea
                            wire:model="notes"
                            rows="3"
                            class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-gray-900"
                        ></textarea>
                        @error('notes')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </label>
                    <label class="block text-xs text-gray-500">
                        Status
                        <select
                            wire:model="status"
                            class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-gray-900"
                        >
                            <option value="active">Active</option>
                            <option value="ended">Ended</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        @error('status')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </label>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="cancelForm"
                        class="btn btn-secondary"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        wire:click="save"
                        class="btn btn-primary"
                    >
                        Save
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
