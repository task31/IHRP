<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
    </x-slot>

    @if (auth()->user()->role === 'employee')
        @php
            $placement = $placement ?? null;
            $recentCalls = $recentCalls ?? collect();
            $totalCalls = $recentCalls->sum('calls_made');
            $totalSubmittals = $recentCalls->sum('submittals');
            $daysReported = $recentCalls->count();
        @endphp

        @if (session('toast'))
            <div
                x-data
                x-init="$nextTick(() => window.dispatchEvent(new CustomEvent('toast', { detail: { message: @json(session('toast')) } })))"
                x-cloak
            ></div>
        @endif

        <div class="space-y-6">
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <h3 class="mb-4 font-semibold">My Placement</h3>
                @if ($placement)
                    <dl class="space-y-2 text-sm">
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-gray-500">Consultant name</dt>
                            <dd class="font-medium text-gray-900">{{ $placement->consultant?->full_name ?? '—' }}</dd>
                        </div>
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-gray-500">Client</dt>
                            <dd class="font-medium text-gray-900">{{ $placement->client?->name ?? '—' }}</dd>
                        </div>
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-gray-500">Job title</dt>
                            <dd class="font-medium text-gray-900">{{ $placement->job_title ?? '—' }}</dd>
                        </div>
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-gray-500">Start date</dt>
                            <dd class="font-medium text-gray-900">{{ $placement->start_date?->format('M j, Y') ?? '—' }}</dd>
                        </div>
                        <div class="pt-1">
                            <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Active</span>
                        </div>
                    </dl>
                @else
                    <p class="text-sm text-gray-600">No active placement on file. Contact your account manager.</p>
                @endif
            </div>

            <div class="rounded-lg bg-white p-5 shadow-sm">
                <h3 class="mb-4 font-semibold">My Activity — Last 7 Days</h3>
                <div class="mb-4 flex flex-wrap gap-4 text-sm text-gray-700">
                    <span><span class="text-gray-500">Total calls</span> <span class="font-semibold">{{ $totalCalls }}</span></span>
                    <span class="text-gray-300">|</span>
                    <span><span class="text-gray-500">Total submittals</span> <span class="font-semibold">{{ $totalSubmittals }}</span></span>
                    <span class="text-gray-300">|</span>
                    <span><span class="text-gray-500">Days reported</span> <span class="font-semibold">{{ $daysReported }}</span></span>
                </div>
                @if ($recentCalls->isEmpty())
                    <p class="text-sm text-gray-600">No reports in the last 7 days.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    <th class="pb-2 pr-3">Date</th>
                                    <th class="pb-2 pr-3">Calls</th>
                                    <th class="pb-2 pr-3">Contacts</th>
                                    <th class="pb-2 pr-3">Submittals</th>
                                    <th class="pb-2">Interviews</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentCalls as $r)
                                    <tr class="border-t border-gray-100">
                                        <td class="py-2 pr-3">{{ $r->report_date->format('M j, Y') }}</td>
                                        <td class="py-2 pr-3">{{ $r->calls_made }}</td>
                                        <td class="py-2 pr-3">{{ $r->contacts_reached }}</td>
                                        <td class="py-2 pr-3">{{ $r->submittals }}</td>
                                        <td class="py-2">{{ $r->interviews_scheduled }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <p class="mt-4">
                    <a href="{{ route('calls.index') }}" class="text-sm font-medium text-blue-600 hover:underline">View all / Submit report →</a>
                </p>
            </div>

            <div class="rounded-lg bg-white p-5 shadow-sm">
                <h3 class="mb-1 font-semibold">Today's Report</h3>
                <p class="mb-4 text-sm text-gray-500">{{ now()->format('l, F j, Y') }}</p>
                <form method="POST" action="{{ route('calls.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="report_date" value="{{ now()->toDateString() }}" />
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label for="dash_calls_made" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Calls made</label>
                            <input
                                type="number"
                                name="calls_made"
                                id="dash_calls_made"
                                value="{{ old('calls_made', 0) }}"
                                min="0"
                                required
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            @error('calls_made')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="dash_contacts_reached" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Contacts reached</label>
                            <input
                                type="number"
                                name="contacts_reached"
                                id="dash_contacts_reached"
                                value="{{ old('contacts_reached', 0) }}"
                                min="0"
                                required
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            @error('contacts_reached')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="dash_submittals" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Submittals</label>
                            <input
                                type="number"
                                name="submittals"
                                id="dash_submittals"
                                value="{{ old('submittals', 0) }}"
                                min="0"
                                required
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            @error('submittals')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="dash_interviews_scheduled" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Interviews</label>
                            <input
                                type="number"
                                name="interviews_scheduled"
                                id="dash_interviews_scheduled"
                                value="{{ old('interviews_scheduled', 0) }}"
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
                        <label for="dash_notes" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Notes (optional)</label>
                        <textarea
                            name="notes"
                            id="dash_notes"
                            rows="3"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    @error('report_date')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Submit report
                    </button>
                </form>
            </div>
        </div>
    @else
        <div x-data="dashboardPage()" x-init="loadStats()" class="space-y-6">
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Active Consultants</p>
                    <p class="mt-1 text-3xl font-bold" x-text="stats.activeConsultants ?? '—'"></p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Active Clients</p>
                    <p class="mt-1 text-3xl font-bold" x-text="stats.activeClients ?? '—'"></p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Pending Invoices</p>
                    <p class="mt-1 text-3xl font-bold" x-text="stats.pendingInvoicesCount ?? '—'"></p>
                    <p
                        class="text-xs text-gray-400"
                        x-show="stats.pendingInvoicesAmount > 0"
                        x-text="'$' + Number(stats.pendingInvoicesAmount).toFixed(2) + ' due'"
                    ></p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">MTD Revenue</p>
                    <p
                        class="mt-1 text-3xl font-bold"
                        x-text="stats.mtdRevenue != null ? '$' + Number(stats.mtdRevenue).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '—'"
                    ></p>
                </div>
            </div>

            <div class="rounded-lg bg-white p-5 shadow-sm" x-show="alerts.length > 0" x-cloak>
                <h3 class="mb-3 font-semibold">End-Date Alerts (<span x-text="alerts.length"></span>)</h3>
                <div class="mb-3 flex gap-4 text-xs">
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-red-500"></span> Critical (≤7d)</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-orange-400"></span> Warning (≤14d)</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-yellow-400"></span> Notice (≤30d)</span>
                </div>
                <table class="w-full text-sm">
                    <template x-for="a in alerts" :key="a.id">
                        <tr class="border-t border-gray-100">
                            <td class="flex flex-wrap items-center gap-2 py-2">
                                <span :class="tierColor(a.daysLeft)" class="inline-block h-2 w-2 shrink-0 rounded-full"></span>
                                <span x-text="a.full_name"></span>
                                <span
                                    class="rounded px-1 text-xs"
                                    :class="a.daysLeft <= 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'"
                                    x-text="a.daysLeft <= 0 ? Math.abs(a.daysLeft) + 'd overdue' : a.daysLeft + 'd left'"
                                ></span>
                            </td>
                            <td class="text-gray-500" x-text="a.client_name ?? 'Unassigned'"></td>
                            <td class="text-gray-500" x-text="a.project_end_date"></td>
                            <td class="text-right">
                                <div x-data="{ extending: false, newDate: '' }">
                                    <button
                                        type="button"
                                        x-show="!extending"
                                        class="text-xs text-blue-600 hover:underline"
                                        @click="extending = true; newDate = a.project_end_date"
                                    >
                                        Extend
                                    </button>
                                    <span x-show="extending" class="inline-flex items-center gap-1">
                                        <input type="date" x-model="newDate" class="rounded border px-1 py-0.5 text-xs" />
                                        <button
                                            type="button"
                                            class="text-xs text-green-600"
                                            @click="extendDate(a.id, newDate); extending = false"
                                        >
                                            Save
                                        </button>
                                        <button type="button" class="text-xs text-gray-400" @click="extending = false">Cancel</button>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </template>
                </table>
            </div>

            @if(auth()->user()->role === 'admin')
            <div class="rounded-lg bg-white p-5 shadow-sm" x-show="budgets.length > 0" x-cloak>
                <h3 class="mb-3 font-semibold">Budget Utilization</h3>
                <template x-for="b in budgets" :key="b.client_id">
                    <div class="mb-4">
                        <div class="mb-1 flex justify-between text-sm">
                            <span class="font-medium" :class="budgetColor(b.pct)" x-text="b.client_name"></span>
                            <span class="text-xs text-gray-500">
                                $<span x-text="Number(b.spent).toLocaleString()"></span>
                                / $<span x-text="Number(b.budget).toLocaleString()"></span>
                            </span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-gray-100">
                            <div
                                class="h-2 rounded-full transition-all"
                                :class="budgetColor(b.pct).replace('text-', 'bg-')"
                                :style="'width:' + Math.min(b.pct, 100) + '%'"
                            ></div>
                        </div>
                    </div>
                </template>
            </div>
            @endif
        </div>

        <script>
            function dashboardPage() {
                return {
                    stats: {},
                    alerts: [],
                    budgets: [],
                    async loadStats() {
                        const s = await apiFetch('/dashboard/stats').then((r) => r.json());
                        this.stats = s.stub ? {} : s;
                        if (s.stub) {
                            this.alerts = [];
                            this.budgets = [];
                            return;
                        }
                        const [aRes, bRes] = await Promise.all([apiFetch('/consultants/end-date-alerts'), apiFetch('/budget')]);
                        const a = aRes.ok ? await aRes.json() : [];
                        const b = bRes.ok ? await bRes.json() : [];
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        this.alerts = (Array.isArray(a) ? a : [])
                            .map((c) => ({
                                ...c,
                                daysLeft: c.project_end_date
                                    ? Math.round((new Date(c.project_end_date + 'T12:00:00') - today) / 86400000)
                                    : null,
                            }))
                            .filter((c) => c.daysLeft !== null && c.daysLeft <= 30);
                        this.budgets = Array.isArray(b) ? b.filter((x) => Number(x.budget) > 0) : [];
                    },
                    async extendDate(id, newDate) {
                        await apiFetch(`/consultants/${id}/extend-end-date`, {
                            method: 'POST',
                            body: JSON.stringify({ project_end_date: newDate }),
                        });
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'End date updated' } }));
                        this.loadStats();
                    },
                    tierColor(days) {
                        if (days <= 7) return 'bg-red-500';
                        if (days <= 14) return 'bg-orange-400';
                        return 'bg-yellow-400';
                    },
                    budgetColor(pct) {
                        if (pct >= 100) return 'text-red-600';
                        if (pct >= 80) return 'text-orange-500';
                        if (pct >= 70) return 'text-yellow-500';
                        return 'text-green-600';
                    },
                };
            }
        </script>
    @endif
</x-app-layout>
