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
                <h2 class="text-lg font-semibold" style="color:var(--fg-1)">Daily Call Reports</h2>
                <p style="margin-top:4px;font-size:13px;color:var(--fg-3)">
                    Today: <span style="font-weight:600;color:var(--fg-2)">{{ \Illuminate\Support\Carbon::parse($todayDate)->format('l, F j, Y') }}</span>
                </p>
            </div>
            @can('admin')
                <a href="{{ route('calls.report') }}" class="btn btn-secondary btn-sm">Admin summaries</a>
            @endcan
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
        class="stack"
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
                <div class="kpi-card">
                    <p class="kpi-label">{{ $stat['label'] }}</p>
                    <p class="kpi-value mono-num" style="margin-top:12px;color:var(--fg-1)">{{ $stat['value'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="card-base">
            <h3 class="eyebrow">Submit or update your report</h3>
            <form method="POST" action="{{ route('calls.store') }}" class="stack">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="field">
                        <label for="report_date">Date</label>
                        <input
                            type="date"
                            name="report_date"
                            id="report_date"
                            x-model="reportDate"
                            @change="syncFromDate()"
                            max="{{ $todayDate }}"
                            required
                            class="field-control"
                        />
                        @error('report_date')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="calls_made">Calls made</label>
                        <input
                            type="number"
                            name="calls_made"
                            id="calls_made"
                            x-model.number="callsMade"
                            min="0"
                            required
                            class="field-control"
                        />
                        @error('calls_made')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="contacts_reached">Contacts reached</label>
                        <input
                            type="number"
                            name="contacts_reached"
                            id="contacts_reached"
                            x-model.number="contactsReached"
                            min="0"
                            required
                            class="field-control"
                        />
                        @error('contacts_reached')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="submittals">Submittals</label>
                        <input
                            type="number"
                            name="submittals"
                            id="submittals"
                            x-model.number="submittals"
                            min="0"
                            required
                            class="field-control"
                        />
                        @error('submittals')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="interviews_scheduled">Interviews scheduled</label>
                        <input
                            type="number"
                            name="interviews_scheduled"
                            id="interviews_scheduled"
                            x-model.number="interviewsScheduled"
                            min="0"
                            required
                            class="field-control"
                        />
                        @error('interviews_scheduled')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="field">
                    <label for="notes">Notes</label>
                    <textarea
                        name="notes"
                        id="notes"
                        rows="3"
                        x-model="notes"
                        class="field-control"
                    ></textarea>
                    @error('notes')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        x-text="hasExistingForDate ? 'Update Report' : 'Submit Report'"
                    ></button>
                </div>
            </form>
        </div>

        <div>
            <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 style="font-size:13px;font-weight:600;color:var(--fg-2)">History</h3>
                    <p style="margin-top:4px;font-size:11px;color:var(--fg-3)">{{ $historyRangeLabel }}</p>
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
                            @class(['btn btn-sm', 'btn-primary' => $active, 'btn-secondary' => !$active])
                        >{{ $opt['label'] }}</a>
                    @endforeach
                </div>
            </div>
            <div class="card-base table-wrap" style="padding:0;">
                <table class="table">
                    <thead >
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
                    <tbody >
                        @forelse ($reports as $report)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3" style="color:var(--fg-1)">
                                    {{ $report->report_date->format('M j, Y') }}
                                </td>
                                @if ($showEmployeeColumn)
                                    <td class="px-4 py-3">
                                        {{ $report->user->name ?? '—' }}
                                    </td>
                                @endif
                                <td class="px-4 py-3 mono-num">{{ $report->calls_made }}</td>
                                <td class="px-4 py-3 mono-num">{{ $report->contacts_reached }}</td>
                                <td class="px-4 py-3 mono-num">{{ $report->submittals }}</td>
                                <td class="px-4 py-3 mono-num">{{ $report->interviews_scheduled }}</td>
                                <td class="max-w-xs px-4 py-3">
                                    @if ($report->notes)
                                        <span class="line-clamp-2" title="{{ $report->notes }}">{{ $report->notes }}</span>
                                    @else
                                        <span style="color:var(--fg-4)">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $showEmployeeColumn ? 7 : 6 }}" class="px-4 py-8 text-center" style="color:var(--fg-3)">
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
