<div wire:key="placement-manager-root" class="stack">
    <div class="row-between" style="align-items:flex-end;">
        <div>
            <div class="eyebrow">Placement Pipeline</div>
            <h2 style="margin-top:4px;font-size:22px;font-weight:700;letter-spacing:-0.01em;">Placements</h2>
        </div>
        @can('account_manager')
            <button type="button" wire:click="openCreate" class="btn btn-primary btn-sm">Add Placement</button>
        @endcan
    </div>

    <div class="card-base" style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px;">
        <label class="field" style="min-width:180px;flex:1 1 220px;">
            <span class="eyebrow">Consultant</span>
            <input type="text" wire:model.live.debounce.300ms="filters.consultant_name" placeholder="Search name…" />
        </label>
        <label class="field" style="min-width:180px;flex:1 1 220px;">
            <span class="eyebrow">Client</span>
            <select wire:model.live="filters.client_id">
                <option value="">All</option>
                @foreach ($clientOptions as $opt)
                    <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="field" style="min-width:160px;flex:1 1 180px;">
            <span class="eyebrow">Status</span>
            <select wire:model.live="filters.status">
                <option value="">All</option>
                <option value="active">Active</option>
                <option value="ended">Ended</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </label>
    </div>

    <div class="card-base" style="padding:0;overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Consultant</th>
                    <th>Client</th>
                    <th>Job Title</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Pay Rate</th>
                    <th>Bill Rate</th>
                    <th>PO#</th>
                    <th>Status</th>
                    <th>Account Manager</th>
                    @can('account_manager')
                        <th style="text-align:right;">Actions</th>
                    @endcan
                </tr>
            </thead>
            <tbody>
                @forelse ($placements ?? [] as $p)
                    <tr wire:key="placement-row-{{ $p->id }}">
                        <td style="color:var(--fg-1);font-weight:500;">
                            {{ $p->consultant_name ?? $p->consultant?->full_name ?? '—' }}
                        </td>
                        <td>{{ $p->client?->name ?? '—' }}</td>
                        <td>{{ $p->job_title ?: '—' }}</td>
                        <td class="mono-dim">{{ $p->start_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="mono-dim">{{ $p->end_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="mono-num">${{ number_format((float) $p->pay_rate, 2) }}</td>
                        <td class="mono-num">${{ number_format((float) $p->bill_rate, 2) }}</td>
                        <td>{{ $p->po_number ?? '—' }}</td>
                        <td>
                            @if ($p->status === 'active')
                                <span class="badge ok">Active</span>
                            @elseif ($p->status === 'ended')
                                <span class="badge neutral">Ended</span>
                            @else
                                <span class="badge bad">Cancelled</span>
                            @endif
                        </td>
                        <td>{{ $p->placedBy?->name ?? '—' }}</td>
                        @can('account_manager')
                            <td style="text-align:right;">
                                <div style="display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px;">
                                    <button type="button" wire:click="openEdit({{ $p->id }})" class="btn btn-secondary btn-sm">Edit</button>
                                    <select
                                        wire:key="placement-status-action-{{ $p->id }}-{{ $p->status }}"
                                        wire:change="updateStatus({{ $p->id }}, $event.target.value)"
                                        style="min-width:110px;padding:6px 10px;font-size:12px;"
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
                        <td colspan="{{ auth()->user()?->can('account_manager') ? 11 : 10 }}" style="padding:32px;text-align:center;color:var(--fg-3);">
                            No placements found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($totalPlacements > $perPage)
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-top:1px solid var(--border-1);flex-wrap:wrap;">
                <div style="font-size:13px;color:var(--fg-3);">
                    Showing {{ (($page - 1) * $perPage) + 1 }}–{{ min($page * $perPage, $totalPlacements) }} of {{ $totalPlacements }}
                </div>
                <div style="display:flex;gap:8px;">
                    @if($page > 1)
                        <button wire:click="prevPage" class="btn btn-secondary btn-sm">← Prev</button>
                    @endif
                    @if(($page * $perPage) < $totalPlacements)
                        <button wire:click="nextPage" class="btn btn-secondary btn-sm">Next →</button>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:click.self="cancelForm">
            <div class="card-base" style="max-height:90vh;width:100%;max-width:560px;overflow-y:auto;" @click.stop wire:click.stop>
                <h3 style="font-size:18px;font-weight:700;margin-bottom:16px;">
                    {{ $editingId ? 'Edit Placement' : 'Add Placement' }}
                </h3>

                <div class="stack-sm">
                    <label class="field">
                        <span class="eyebrow">Consultant</span>
                        <input type="text" wire:model="consultant_name" placeholder="Full name" />
                        @error('consultant_name')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </label>

                    <label class="field">
                        <span class="eyebrow">Client</span>
                        <select wire:model="client_id">
                            <option value="">—</option>
                            @foreach ($clientOptions as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                        @error('client_id')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </label>

                    <label class="field">
                        <span class="eyebrow">Job Title</span>
                        <input type="text" wire:model="job_title" />
                        @error('job_title')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </label>

                    <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
                        <label class="field">
                            <span class="eyebrow">Start Date</span>
                            <input type="date" wire:model="start_date" />
                            @error('start_date')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </label>
                        <label class="field">
                            <span class="eyebrow">End Date</span>
                            <input type="date" wire:model="end_date" />
                            @error('end_date')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </label>
                    </div>

                    <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
                        <label class="field">
                            <span class="eyebrow">Pay Rate</span>
                            <input type="text" inputmode="decimal" wire:model="pay_rate" />
                            @error('pay_rate')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </label>
                        <label class="field">
                            <span class="eyebrow">Bill Rate</span>
                            <input type="text" inputmode="decimal" wire:model="bill_rate" />
                            @error('bill_rate')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </label>
                    </div>

                    <div>
                        <div class="eyebrow">PO Number</div>
                        @if (auth()->user()?->role === 'admin')
                            <input type="text" wire:model="po_number" style="margin-top:6px;width:100%;" />
                        @else
                            <p style="margin-top:6px;font-size:13px;color:var(--fg-3);">{{ $po_number ?: '—' }}</p>
                        @endif
                    </div>

                    <label class="field">
                        <span class="eyebrow">Notes</span>
                        <textarea wire:model="notes" rows="3"></textarea>
                        @error('notes')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </label>

                    <label class="field">
                        <span class="eyebrow">Status</span>
                        <select wire:model="status">
                            <option value="active">Active</option>
                            <option value="ended">Ended</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        @error('status')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </label>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                    <button type="button" wire:click="cancelForm" class="btn btn-secondary">Cancel</button>
                    <button type="button" wire:click="save" class="btn btn-primary">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
