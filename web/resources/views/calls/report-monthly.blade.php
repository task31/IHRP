@php
    /** @var \Illuminate\Support\Collection<int, object> $monthlyRows */
    /** @var array<string, mixed> $filters */
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
    /** @var list<int> $yearOptions */
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold" style="color:var(--fg-1)">Call report — by month</h2>
                <p style="margin-top:4px;font-size:13px;color:var(--fg-3)">Team totals per calendar month. Administrators only.</p>
            </div>
        </div>
    </x-slot>

    <div class="stack">
        @include('calls.partials.report-tabs', ['active' => 'monthly'])

        <div class="card-base">
            <h3 class="eyebrow">Filters</h3>
            <form method="GET" action="{{ route('calls.report.monthly') }}" class="flex flex-wrap items-end gap-4">
                <div class="min-w-[10rem]">
                    <label for="year" class="eyebrow">Year</label>
                    <select
                        name="year"
                        id="year"
                        style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;"
                    >
                        @foreach ($yearOptions as $y)
                            <option value="{{ $y }}" @selected((int) old('year', $filters['year'] ?? now()->year) === $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                    @error('year')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
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
            <h3 class="mb-3 text-sm font-semibold text-gray-700">Totals for {{ (int) ($filters['year'] ?? now()->year) }}</h3>
            <div class="card-base" style="padding:0;overflow-x:auto">
                <table class="table">
                    <thead >
                        <tr>
                            <th class="px-4 py-3">Month</th>
                            <th class="px-4 py-3">Report days</th>
                            <th class="px-4 py-3">Calls</th>
                            <th class="px-4 py-3">Contacts</th>
                            <th class="px-4 py-3">Submittals</th>
                            <th class="px-4 py-3">Interviews</th>
                        </tr>
                    </thead>
                    <tbody >
                        @foreach ($monthlyRows as $row)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $row->label }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $row->total_days }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $row->total_calls }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $row->total_contacts }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $row->total_submittals }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $row->total_interviews }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
