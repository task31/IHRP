@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \App\Models\DailyCallReport> $reports */
    /** @var array<string, array{calls_made: int, contacts_reached: int, submittals: int, interviews_scheduled: int, notes: string}> $myReportsByDate */
    /** @var string $historyPeriod */
    /** @var string $historyRangeLabel */
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Daily Call Reports</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Today: <span class="font-medium text-gray-700">{{ \Illuminate\Support\Carbon::parse($todayDate)->format('l, F j, Y') }}</span>
                </p>
            </div>
        </div>
    </x-slot>

    @if (session('toast'))
        <div
            x-data
            x-init="$nextTick(() => window.dispatchEvent(new CustomEvent('toast', { detail: { message: @json(session('toast')) } })))"
            x-cloak
        ></div>
    @endif

    <div
        class="space-y-8"
        x-data="callReportForm(@js($myReportsByDate), @js($todayDate), @js(
            $errors->any()
                ? [
                    'report_date' => old('report_date', $todayDate),
                    'calls_made' => (int) old('calls_made', 0),
                    'contacts_reached' => (int) old('contacts_reached', 0),
                    'submittals' => (int) old('submittals', 0),
                    'interviews_scheduled' => (int) old('interviews_scheduled', 0),
                    'notes' => old('notes', '') ?? '',
                ]
                : null
        ))"
    >
        {{-- Monthly stats strip --}}
        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @php
                $statMonth = now()->format('F');
            @endphp
            @foreach ([
                ['label' => 'Calls — ' . $statMonth, 'value' => $monthlyStats?->calls_made ?? 0],
                ['label' => 'Contacts — ' . $statMonth, 'value' => $monthlyStats?->contacts_reached ?? 0],
                ['label' => 'Submittals — ' . $statMonth, 'value' => $monthlyStats?->submittals ?? 0],
                ['label' => 'Interviews — ' . $statMonth, 'value' => $monthlyStats?->interviews_scheduled ?? 0],
            ] as $stat)
                <div class="rounded-lg bg-white px-4 py-3 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $stat['label'] }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $stat['value'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="rounded-lg bg-white p-5 shadow-sm">
            <h3 class="mb-4 text-sm font-semibold text-gray-700">Submit or update your report</h3>
            <form method="POST" action="{{ route('calls.store') }}" class="space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label for="report_date" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Date</label>
                        <input
                            type="date"
                            name="report_date"
                            id="report_date"
                            x-model="reportDate"
                            @change="syncFromDate()"
                            max="{{ $todayDate }}"
                            required
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        @error('report_date')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="calls_made" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Calls made</label>
                        <input
                            type="number"
                            name="calls_made"
                            id="calls_made"
                            x-model.number="callsMade"
                            min="0"
                            required
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        @error('calls_made')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="contacts_reached" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Contacts reached</label>
                        <input
                            type="number"
                            name="contacts_reached"
                            id="contacts_reached"
                            x-model.number="contactsReached"
                            min="0"
                            required
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        @error('contacts_reached')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="submittals" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Submittals</label>
                        <input
                            type="number"
                            name="submittals"
                            id="submittals"
                            x-model.number="submittals"
                            min="0"
                            required
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        @error('submittals')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="interviews_scheduled" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Interviews scheduled</label>
                        <input
                            type="number"
                            name="interviews_scheduled"
                            id="interviews_scheduled"
                            x-model.number="interviewsScheduled"
                            min="0"
                            required
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        @error('interviews_scheduled')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div>
                    <label for="notes" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Notes</label>
                    <textarea
                        name="notes"
                        id="notes"
                        rows="3"
                        x-model="notes"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    ></textarea>
                    @error('notes')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <button
                        type="submit"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                        x-text="hasExistingForDate ? 'Update Report' : 'Submit Report'"
                    ></button>
                </div>
            </form>
        </div>

        <div>
            <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">History</h3>
                    <p class="mt-1 text-xs text-gray-500">{{ $historyRangeLabel }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ([
                        ['key' => '30', 'label' => '30 days'],
                        ['key' => '90', 'label' => '90 days'],
                        ['key' => '365', 'label' => '12 mo'],
                        ['key' => 'all', 'label' => 'All'],
                    ] as $opt)
                        @php
                            $active = $historyPeriod === $opt['key'];
                            $href = $opt['key'] === '30'
                                ? route('calls.index')
                                : route('calls.index', ['period' => $opt['key']]);
                        @endphp
                        <a
                            href="{{ $href }}"
                            @class([
                                'rounded-md px-3 py-1.5 text-xs font-medium ring-1 ring-inset transition',
                                'bg-indigo-600 text-white ring-indigo-600' => $active,
                                'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50' => ! $active,
                            ])
                        >{{ $opt['label'] }}</a>
                    @endforeach
                </div>
            </div>
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            @if ($showEmployeeColumn)
                                <th class="px-4 py-3">Employee</th>
                            @endif
                            <th class="px-4 py-3">Calls</th>
                            <th class="px-4 py-3">Contacts</th>
                            <th class="px-4 py-3">Submittals</th>
                            <th class="px-4 py-3">Interviews</th>
                            <th class="px-4 py-3">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($reports as $report)
                            <tr class="hover:bg-gray-50/80">
                                <td class="whitespace-nowrap px-4 py-3 text-gray-900">
                                    {{ $report->report_date->format('M j, Y') }}
                                </td>
                                @if ($showEmployeeColumn)
                                    <td class="px-4 py-3 text-gray-700">
                                        {{ $report->user->name ?? '—' }}
                                    </td>
                                @endif
                                <td class="px-4 py-3 text-gray-700">{{ $report->calls_made }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $report->contacts_reached }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $report->submittals }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $report->interviews_scheduled }}</td>
                                <td class="max-w-xs px-4 py-3 text-gray-600">
                                    @if ($report->notes)
                                        <span class="line-clamp-2" title="{{ $report->notes }}">{{ $report->notes }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $showEmployeeColumn ? 7 : 6 }}" class="px-4 py-8 text-center text-gray-500">
                                    @if ($historyPeriod === 'all')
                                        No call reports yet.
                                    @else
                                        No call reports in this date range.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($reports->hasPages())
                <div class="mt-4">
                    {{ $reports->links() }}
                </div>
            @endif
        </div>
    </div>

    <script>
        function callReportForm(myReportsByDate, todayDate, oldValues) {
            return {
                reportDate: todayDate,
                myReportsByDate,
                callsMade: 0,
                contactsReached: 0,
                submittals: 0,
                interviewsScheduled: 0,
                notes: '',
                get hasExistingForDate() {
                    return Object.prototype.hasOwnProperty.call(this.myReportsByDate, this.reportDate);
                },
                syncFromDate() {
                    const row = this.myReportsByDate[this.reportDate];
                    if (row) {
                        this.callsMade = row.calls_made;
                        this.contactsReached = row.contacts_reached;
                        this.submittals = row.submittals;
                        this.interviewsScheduled = row.interviews_scheduled;
                        this.notes = row.notes ?? '';
                    } else {
                        this.callsMade = 0;
                        this.contactsReached = 0;
                        this.submittals = 0;
                        this.interviewsScheduled = 0;
                        this.notes = '';
                    }
                },
                init() {
                    if (oldValues) {
                        this.reportDate = oldValues.report_date;
                        this.callsMade = oldValues.calls_made;
                        this.contactsReached = oldValues.contacts_reached;
                        this.submittals = oldValues.submittals;
                        this.interviewsScheduled = oldValues.interviews_scheduled;
                        this.notes = oldValues.notes ?? '';
                        return;
                    }
                    this.syncFromDate();
                },
            };
        }
    </script>
</x-app-layout>
