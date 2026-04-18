@php
    /** @var \Illuminate\Support\Collection<int, object> $summary */
    /** @var array<string, mixed> $filters */
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold" style="color:var(--fg-1)">Call report — by employee</h2>
                <p style="margin-top:4px;font-size:13px;color:var(--fg-3)">Roll-up by person over a date range. Administrators only.</p>
            </div>
        </div>
    </x-slot>

    <div class="stack">
        @include('calls.partials.report-tabs', ['active' => 'employees'])

        <div class="card-base">
            <h3 class="eyebrow">Filters</h3>
            <form method="GET" action="{{ route('calls.report') }}" class="flex flex-wrap items-end gap-4">
                <div class="min-w-[12rem] flex-1">
                    <label for="user_id" class="eyebrow">Employee</label>
                    <select
                        name="user_id"
                        id="user_id"
                        style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;"
                    >
                        <option value="" @selected((string) old('user_id', $filters['user_id'] ?? '') === '')>All users</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected((string) old('user_id', $filters['user_id'] ?? '') === (string) $u->id)>
                                {{ $u->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('user_id')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="date_from" class="eyebrow">Date from</label>
                    <input
                        type="date"
                        name="date_from"
                        id="date_from"
                        value="{{ old('date_from', $filters['date_from'] ?? '') }}"
                        style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;"
                    />
                    @error('date_from')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="date_to" class="eyebrow">Date to</label>
                    <input
                        type="date"
                        name="date_to"
                        id="date_to"
                        value="{{ old('date_to', $filters['date_to'] ?? '') }}"
                        style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;"
                    />
                    @error('date_to')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Apply
                    </button>
                </div>
            </form>
        </div>

        <div>
            <h3 class="mb-3 text-sm font-semibold text-gray-700">Summary by employee</h3>
            <div class="card-base" style="padding:0;overflow-x:auto">
                <table class="table">
                    <thead >
                        <tr>
                            <th class="px-4 py-3">Employee</th>
                            <th class="px-4 py-3">Total Days</th>
                            <th class="px-4 py-3">Total Calls</th>
                            <th class="px-4 py-3">Total Contacts</th>
                            <th class="px-4 py-3">Total Submittals</th>
                            <th class="px-4 py-3">Total Interviews</th>
                            <th class="px-4 py-3">Avg Calls/Day</th>
                        </tr>
                    </thead>
                    <tbody >
                        @forelse ($summary as $row)
                            <tr>
                                <td class="px-4 py-3 text-gray-900">
                                    <div class="font-medium">{{ $row->user_name }}</div>
                                    <div class="text-xs text-gray-500">{{ $row->user_email }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ (int) $row->total_days }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ (int) $row->total_calls }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ (int) $row->total_contacts }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ (int) $row->total_submittals }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ (int) $row->total_interviews }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ number_format((float) $row->avg_calls_per_day, 1) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    No data for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
