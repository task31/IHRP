<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
    </x-slot>

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
</x-app-layout>
