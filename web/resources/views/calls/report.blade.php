@php
    /** @var \Illuminate\Support\Collection<int, object> $summary */
    /** @var array<string, mixed> $filters */
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Call Report Summary</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Account managers and administrators only.
                </p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-8">
        <div class="rounded-lg bg-white p-5 shadow-sm">
            <h3 class="mb-4 text-sm font-semibold text-gray-700">Filters</h3>
            <form method="GET" action="{{ route('calls.report') }}" class="flex flex-wrap items-end gap-4">
                <div class="min-w-[12rem] flex-1">
                    <label for="user_id" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Employee</label>
                    <select
                        name="user_id"
                        id="user_id"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                    <label for="date_from" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Date from</label>
                    <input
                        type="date"
                        name="date_from"
                        id="date_from"
                        value="{{ old('date_from', $filters['date_from'] ?? '') }}"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    @error('date_from')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="date_to" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Date to</label>
                    <input
                        type="date"
                        name="date_to"
                        id="date_to"
                        value="{{ old('date_to', $filters['date_to'] ?? '') }}"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    @error('date_to')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <button
                        type="submit"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        Apply
                    </button>
                </div>
            </form>
        </div>

        <div>
            <h3 class="mb-3 text-sm font-semibold text-gray-700">Summary by employee</h3>
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
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
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($summary as $row)
                            <tr class="hover:bg-gray-50/80">
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
