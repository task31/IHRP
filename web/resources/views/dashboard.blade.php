<x-app-layout>
    <div x-data="dashboardPage()" x-init="init()" class="space-y-6">

        {{-- ── Page heading ─────────────────────────────────────────── --}}
        <h2 class="text-xl font-semibold text-gray-800">Dashboard</h2>

        {{-- ── KPI stat cards ──────────────────────────────────────── --}}
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

        {{-- ── End-Date Alerts ─────────────────────────────────────── --}}
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
                                    x-on:click="extending = true; newDate = a.project_end_date"
                                >
                                    Extend
                                </button>
                                <span x-show="extending" class="inline-flex items-center gap-1">
                                    <input type="date" x-model="newDate" class="rounded border px-1 py-0.5 text-xs" />
                                    <button
                                        type="button"
                                        class="text-xs text-green-600"
                                        x-on:click="extendDate(a.id, newDate); extending = false"
                                    >Save</button>
                                    <button type="button" class="text-xs text-gray-400" x-on:click="extending = false">Cancel</button>
                                </span>
                            </div>
                        </td>
                    </tr>
                </template>
            </table>
        </div>

        {{-- ── Budget Utilization (admin only) ─────────────────────── --}}
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

        {{-- ── Call Activity (admin only) ───────────────────────────── --}}
        @if(auth()->user()->role === 'admin')
        <div class="rounded-lg bg-white p-5 shadow-sm">
            {{-- Header + period picker --}}
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 class="font-semibold text-gray-800">Call Activity</h3>
                <div class="flex rounded-md border border-gray-200 text-xs font-medium overflow-hidden">
                    <template x-for="p in [{v:'week',l:'This Week'},{v:'month',l:'This Month'},{v:'quarter',l:'This Quarter'},{v:'year',l:'This Year'}]" :key="p.v">
                        <button
                            type="button"
                            class="px-3 py-1.5 transition-colors"
                            :class="callsPeriod === p.v ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                            x-on:click="callsPeriod = p.v; loadCalls()"
                            x-text="p.l"
                        ></button>
                    </template>
                </div>
            </div>

            {{-- Empty state --}}
            <div x-show="calls && calls.team.total_dials === 0" class="py-8 text-center text-sm text-gray-400">
                No call data for this period.
            </div>

            <div x-show="calls && calls.team.total_dials > 0" x-cloak>

                {{-- Team summary cards --}}
                <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-lg bg-indigo-50 p-3 text-center">
                        <p class="text-xs text-indigo-600 font-medium">Total Dials</p>
                        <p class="mt-0.5 text-2xl font-bold text-indigo-700" x-text="calls.team.total_dials"></p>
                        <p class="text-xs text-indigo-400" x-text="calls.team.avg_dials_per_day + ' / day avg'"></p>
                    </div>
                    <div class="rounded-lg bg-emerald-50 p-3 text-center">
                        <p class="text-xs text-emerald-600 font-medium">Total Connects</p>
                        <p class="mt-0.5 text-2xl font-bold text-emerald-700" x-text="calls.team.total_connects"></p>
                        <p class="text-xs text-emerald-400" x-text="calls.team.active_ams + ' AMs reporting'"></p>
                    </div>
                    <div class="rounded-lg bg-violet-50 p-3 text-center">
                        <p class="text-xs text-violet-600 font-medium">Connect Rate</p>
                        <p class="mt-0.5 text-2xl font-bold text-violet-700" x-text="calls.team.connect_rate + '%'"></p>
                        <p class="text-xs text-violet-400">live calls / dials</p>
                    </div>
                    <div class="rounded-lg bg-amber-50 p-3 text-center">
                        <p class="text-xs text-amber-600 font-medium">Submittals</p>
                        <p class="mt-0.5 text-2xl font-bold text-amber-700" x-text="calls.team.total_submittals"></p>
                        <p class="text-xs text-amber-400" x-text="calls.team.total_interviews + ' interviews'"></p>
                    </div>
                </div>

                {{-- Trend chart + Leaderboard --}}
                <div class="mb-5 grid gap-4 lg:grid-cols-5">
                    {{-- Trend chart --}}
                    <div class="lg:col-span-3">
                        <p class="mb-2 text-xs font-medium text-gray-500 uppercase tracking-wide">Dials & Connects Trend</p>
                        <div class="relative h-48">
                            <canvas id="callsTrendChart"></canvas>
                        </div>
                    </div>

                    {{-- Leaderboard --}}
                    <div class="lg:col-span-2">
                        <p class="mb-2 text-xs font-medium text-gray-500 uppercase tracking-wide">Top Performers</p>
                        <div class="space-y-2">
                            <template x-for="(am, idx) in calls.by_am.slice(0, 5)" :key="am.name">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                        :class="idx === 0 ? 'bg-yellow-400 text-yellow-900' : idx === 1 ? 'bg-gray-300 text-gray-700' : idx === 2 ? 'bg-orange-300 text-orange-900' : 'bg-gray-100 text-gray-500'"
                                        x-text="idx + 1"
                                    ></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between">
                                            <span class="truncate text-sm font-medium" x-text="am.name.split(' ')[0]"></span>
                                            <span class="ml-2 shrink-0 text-xs font-semibold text-indigo-600" x-text="am.dials + ' dials'"></span>
                                        </div>
                                        <div class="mt-0.5 h-1.5 w-full rounded-full bg-gray-100">
                                            <div
                                                class="h-1.5 rounded-full bg-indigo-400"
                                                :style="'width:' + (calls.by_am[0].dials > 0 ? (am.dials / calls.by_am[0].dials * 100) : 0) + '%'"
                                            ></div>
                                        </div>
                                    </div>
                                    <span class="shrink-0 text-xs text-emerald-600 font-medium" x-text="am.connect_rate + '%'"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- AM breakdown table --}}
                <div>
                    <p class="mb-2 text-xs font-medium text-gray-500 uppercase tracking-wide">AM Breakdown</p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-3 py-2">Account Manager</th>
                                    <th class="px-3 py-2 text-right">Dials</th>
                                    <th class="px-3 py-2 text-right">Connects</th>
                                    <th class="px-3 py-2 text-right">Connect %</th>
                                    <th class="px-3 py-2 text-right">Submittals</th>
                                    <th class="px-3 py-2 text-right">Interviews</th>
                                    <th class="px-3 py-2 text-right">Avg Dials/Day</th>
                                    <th class="px-3 py-2 text-right">Days Reported</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="am in calls.by_am" :key="am.name">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-2 font-medium text-gray-900" x-text="am.name"></td>
                                        <td class="px-3 py-2 text-right font-semibold text-indigo-600" x-text="am.dials"></td>
                                        <td class="px-3 py-2 text-right text-emerald-600" x-text="am.connects"></td>
                                        <td class="px-3 py-2 text-right">
                                            <span
                                                class="rounded px-1.5 py-0.5 text-xs font-medium"
                                                :class="am.connect_rate >= 30 ? 'bg-green-100 text-green-700' : am.connect_rate >= 15 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'"
                                                x-text="am.connect_rate + '%'"
                                            ></span>
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-700" x-text="am.submittals"></td>
                                        <td class="px-3 py-2 text-right text-gray-700" x-text="am.interviews"></td>
                                        <td class="px-3 py-2 text-right text-gray-500" x-text="am.avg_dials_per_day"></td>
                                        <td class="px-3 py-2 text-right text-gray-500" x-text="am.days_reported"></td>
                                    </tr>
                                </template>
                                {{-- Team total row --}}
                                <tr class="border-t-2 border-gray-300 bg-gray-50 font-semibold">
                                    <td class="px-3 py-2 text-gray-700">Team Total</td>
                                    <td class="px-3 py-2 text-right text-indigo-700" x-text="calls.team.total_dials"></td>
                                    <td class="px-3 py-2 text-right text-emerald-700" x-text="calls.team.total_connects"></td>
                                    <td class="px-3 py-2 text-right">
                                        <span
                                            class="rounded px-1.5 py-0.5 text-xs font-medium"
                                            :class="calls.team.connect_rate >= 30 ? 'bg-green-100 text-green-700' : calls.team.connect_rate >= 15 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'"
                                            x-text="calls.team.connect_rate + '%'"
                                        ></span>
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-700" x-text="calls.team.total_submittals"></td>
                                    <td class="px-3 py-2 text-right text-gray-700" x-text="calls.team.total_interviews"></td>
                                    <td class="px-3 py-2 text-right text-gray-500" x-text="calls.team.avg_dials_per_day"></td>
                                    <td class="px-3 py-2 text-right text-gray-500" x-text="calls.team.days_with_data"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        function dashboardPage() {
            return {
                stats: {},
                alerts: [],
                budgets: [],
                callsPeriod: 'month',
                calls: null,
                callsChart: null,

                async init() {
                    await this.loadStats();
                    this.loadCalls();
                },

                async loadStats() {
                    const s = await apiFetch('/dashboard/stats').then((r) => r.json());
                    this.stats = s.stub ? {} : s;
                    if (s.stub) { this.alerts = []; this.budgets = []; return; }
                    const [aRes, bRes] = await Promise.all([
                        apiFetch('/consultants/end-date-alerts'),
                        apiFetch('/budget'),
                    ]);
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

                async loadCalls() {
                    const r = await apiFetch('/dashboard/calls-stats?period=' + this.callsPeriod);
                    if (!r.ok) return;
                    this.calls = await r.json();
                    this.$nextTick(() => this.renderCallsChart());
                },

                renderCallsChart() {
                    const canvas = document.getElementById('callsTrendChart');
                    if (!canvas || !this.calls?.trend?.length) return;
                    if (this.callsChart) this.callsChart.destroy();
                    this.callsChart = new Chart(canvas, {
                        type: 'line',
                        data: {
                            labels: this.calls.trend.map((t) => t.date),
                            datasets: [
                                {
                                    label: 'Dials',
                                    data: this.calls.trend.map((t) => t.dials),
                                    borderColor: '#6366f1',
                                    backgroundColor: 'rgba(99,102,241,0.08)',
                                    fill: true,
                                    tension: 0.35,
                                    pointRadius: 3,
                                },
                                {
                                    label: 'Connects',
                                    data: this.calls.trend.map((t) => t.connects),
                                    borderColor: '#10b981',
                                    backgroundColor: 'rgba(16,185,129,0.08)',
                                    fill: true,
                                    tension: 0.35,
                                    pointRadius: 3,
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'top', labels: { boxWidth: 10, font: { size: 11 } } } },
                            scales: {
                                x: { ticks: { font: { size: 10 }, maxRotation: 45 } },
                                y: { beginAtZero: true, ticks: { font: { size: 10 } } },
                            },
                        },
                    });
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
</x-app-layout>
