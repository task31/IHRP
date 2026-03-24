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
                <h2 class="text-xl font-semibold text-gray-800">Call report — by month</h2>
                <p class="mt-1 text-sm text-gray-500">Team totals per calendar month. Administrators only.</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-8">
        @include('calls.partials.report-tabs', ['active' => 'monthly'])

        <div class="rounded-lg bg-white p-5 shadow-sm">
            <h3 class="mb-4 text-sm font-semibold text-gray-700">Filters</h3>
            <form method="GET" action="{{ route('calls.report.monthly') }}" class="flex flex-wrap items-end gap-4">
                <div class="min-w-[10rem]">
                    <label for="year" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Year</label>
                    <select
                        name="year"
                        id="year"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
            <h3 class="mb-3 text-sm font-semibold text-gray-700">Totals for {{ (int) ($filters['year'] ?? now()->year) }}</h3>
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Month</th>
                            <th class="px-4 py-3">Report days</th>
                            <th class="px-4 py-3">Calls</th>
                            <th class="px-4 py-3">Contacts</th>
                            <th class="px-4 py-3">Submittals</th>
                            <th class="px-4 py-3">Interviews</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($monthlyRows as $row)
                            <tr class="hover:bg-gray-50/80">
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
