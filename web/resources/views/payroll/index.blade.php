<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Payroll</h2>
    </x-slot>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

    <div
        id="payroll-root"
        class="space-y-6"
        x-data="payrollDashboard()"
        x-init="init()"
    >
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600">Year</label>
                <select x-model.number="year" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm">
                    <template x-for="y in yearsList" :key="y">
                        <option :value="y" x-text="y"></option>
                    </template>
                </select>
            </div>
            <template x-if="IS_ADMIN">
                <div>
                    <label class="block text-xs font-medium text-gray-600">Account manager</label>
                    <select x-model.number="amId" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm">
                        @foreach ($accountManagers as $am)
                            <option value="{{ $am->id }}">{{ $am->name }}</option>
                        @endforeach
                    </select>
                </div>
            </template>
            <template x-if="IS_ADMIN">
                <button
                    type="button"
                    class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
                    @click="uploadOpen = true"
                >Upload payroll file</button>
            </template>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">YTD net</p>
                <p class="mt-1 text-2xl font-bold text-gray-900" x-text="fmtMoney(summary?.totals?.ytd_net)"></p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">YTD gross</p>
                <p class="mt-1 text-2xl font-bold text-gray-900" x-text="fmtMoney(summary?.totals?.ytd_gross)"></p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">Taxes paid</p>
                <p class="mt-1 text-2xl font-bold text-gray-900" x-text="fmtMoney(summary?.totals?.taxes_paid)"></p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">Projected annual net</p>
                <p class="mt-1 text-lg font-bold text-gray-900" x-show="!projection?.projectionSuppressed" x-text="fmtMoney(projection?.projectedAnnual)"></p>
                <p class="mt-1 text-sm text-amber-700" x-show="projection?.projectionSuppressed" x-text="projection?.message || '—'"></p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="rounded-lg bg-white p-4 shadow-sm lg:col-span-2">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-800">Gross vs net</h3>
                    <div class="flex gap-2 text-xs">
                        <button type="button" :class="barMode==='biweekly' ? 'font-semibold text-gray-900' : 'text-gray-500'" @click="setBarMode('biweekly')">Bi-weekly</button>
                        <button type="button" :class="barMode==='monthly' ? 'font-semibold text-gray-900' : 'text-gray-500'" @click="setBarMode('monthly')">Monthly</button>
                    </div>
                </div>
                <div class="h-64"><canvas id="payrollBarChart"></canvas></div>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <h3 class="mb-2 text-sm font-semibold text-gray-800">Tax mix</h3>
                <div class="h-52"><canvas id="payrollDonutChart"></canvas></div>
                <div id="payrollDonutLegend" class="mt-2 space-y-1 text-xs text-gray-600"></div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <h3 class="mb-2 text-sm font-semibold text-gray-800">Cumulative net (YoY)</h3>
                <div class="h-56"><canvas id="payrollYoyChart"></canvas></div>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <h3 class="mb-2 text-sm font-semibold text-gray-800">Goal tracker</h3>
                <p class="text-sm text-gray-600" x-show="!goalAmount || Number(goalAmount)<=0">No goal set</p>
                <template x-if="goalAmount && Number(goalAmount)>0">
                    <div>
                        <div class="h-3 w-full overflow-hidden rounded-full bg-gray-200">
                            <div class="h-3 rounded-full bg-emerald-500 transition-all" :style="`width:${goalPct}%`"></div>
                        </div>
                        <p class="mt-2 text-sm text-gray-700">
                            <span x-text="goalPct.toFixed(1)"></span>% of goal
                            (<span x-text="fmtMoney(summary?.totals?.ytd_net)"></span> / <span x-text="fmtMoney(goalAmount)"></span>)
                        </p>
                    </div>
                </template>
                <template x-if="IS_ADMIN">
                    <form class="mt-3 flex items-center gap-2" @submit.prevent="saveGoal">
                        <span class="text-xs text-gray-500">Set goal:</span>
                        <input type="number" min="0" step="0.01" x-model="goalInput" placeholder="e.g. 80000" class="w-32 rounded-md border-gray-300 text-sm shadow-sm" />
                        <button type="submit" class="rounded-md bg-gray-900 px-3 py-1 text-xs font-medium text-white hover:bg-gray-800">Save</button>
                    </form>
                </template>
            </div>
        </div>

        <div class="rounded-lg bg-white p-4 shadow-sm">
            <h3 class="mb-2 text-sm font-semibold text-gray-800">Multi-year trend</h3>
            <div class="h-56"><canvas id="payrollTrendChart"></canvas></div>
        </div>

        <div class="rounded-lg bg-white p-4 shadow-sm">
            <h3 class="mb-2 text-sm font-semibold text-gray-800">Period detail</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-xs uppercase text-gray-500">
                            <th class="py-2 pr-4">Date</th>
                            <th class="py-2 pr-4">Gross</th>
                            <th class="py-2 pr-4">Net</th>
                            <th class="py-2">Cumulative net</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="p in (summary?.periods || [])" :key="p.date">
                            <tr class="border-b border-gray-100">
                                <td class="py-2 pr-4" x-text="p.date"></td>
                                <td class="py-2 pr-4" x-text="fmtMoney(p.gross)"></td>
                                <td class="py-2 pr-4" x-text="fmtMoney(p.net)"></td>
                                <td class="py-2" x-text="fmtMoney(p.cumulative_net)"></td>
                            </tr>
                        </template>
                        <tr x-show="!(summary?.periods?.length)">
                            <td colspan="4" class="py-4 text-gray-500">No payroll data available yet</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <button
                type="button"
                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-800 shadow-sm hover:bg-gray-50"
                @click="drawerOpen = true; loadConsultants()"
            >Consultant breakdown</button>
        </div>

        <template x-if="IS_ADMIN">
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-semibold text-gray-800">AM comparison (<span x-text="year"></span>)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b text-xs uppercase text-gray-500">
                                <th class="py-2 pr-4">AM</th>
                                <th class="py-2 pr-4">YTD net</th>
                                <th class="py-2 pr-4">YTD gross</th>
                                <th class="py-2">Share of net</th>
                            </tr>
                        </thead>
                        <tbody id="payrollAmCompareBody">
                            <tr><td colspan="4" class="py-3 text-gray-500">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <div x-show="mappingsOpen" x-cloak class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            @include('payroll.mappings')
        </div>

        {{-- Drawer --}}
        <div
            x-show="drawerOpen"
            x-cloak
            class="fixed inset-0 z-40 flex justify-end bg-black/30"
            @keydown.escape.window="drawerOpen = false"
        >
            <div class="h-full w-full max-w-md overflow-y-auto bg-white p-5 shadow-xl" @click.outside="drawerOpen = false">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Consultant breakdown</h3>
                    <button type="button" class="text-gray-500 hover:text-gray-800" @click="drawerOpen = false">✕</button>
                </div>
                <p class="mt-1 text-xs text-gray-500">Scoped to the selected AM and year.</p>
                <table class="mt-4 min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-xs uppercase text-gray-500">
                            <th class="py-2">Name</th>
                            <th class="py-2">Gross</th>
                            <th class="py-2">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="c in consultants" :key="c.name">
                            <tr class="border-b border-gray-100">
                                <td class="py-2" x-text="c.name"></td>
                                <td class="py-2" x-text="fmtMoney(c.total_gross)"></td>
                                <td class="py-2" x-text="(c.pct_of_total ?? 0) + '%'"></td>
                            </tr>
                        </template>
                        <tr x-show="!consultants.length && drawerOpen">
                            <td colspan="3" class="py-4 text-gray-500">No consultant data for this period</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Upload modal --}}
        <div x-show="uploadOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl" @click.outside="uploadOpen = false">
                <h3 class="text-lg font-semibold">Upload payroll (.xlsx)</h3>
                <form class="mt-4 space-y-4" @submit.prevent="submitUpload">
                    <div>
                        <label class="block text-xs font-medium text-gray-600">File</label>
                        <input type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" x-ref="payrollFile" class="mt-1 block w-full text-sm" required />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Account manager</label>
                        <select x-model.number="uploadAmId" class="mt-1 w-full rounded-md border-gray-300 text-sm" required>
                            @foreach ($accountManagers as $am)
                                <option value="{{ $am->id }}">{{ $am->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Stop reading at row starting with…</label>
                        <input type="text" x-model="uploadStopName" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="e.g. Rafael Zobel" required />
                        <p class="mt-1 text-xs text-gray-500">This is the AM&apos;s full name as it appears in the payroll file. Rows at and after this name are excluded.</p>
                    </div>
                    <div x-show="uploadMessage" class="rounded-md bg-amber-50 p-3 text-sm text-amber-900" x-text="uploadMessage"></div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm" @click="uploadOpen = false">Cancel</button>
                        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const IS_ADMIN = @json(auth()->user()->role === 'admin');
        const INITIAL_AM_ID = @json($accountManagers->first()->id ?? null);

        function payrollDashboard() {
            return {
                IS_ADMIN,
                year: new Date().getFullYear(),
                amId: INITIAL_AM_ID,
                yearsList: [new Date().getFullYear()],
                summary: null,
                monthly: null,
                annualTotals: null,
                projection: null,
                goalAmount: '0',
                barMode: 'biweekly',
                isLoading: false,
                drawerOpen: false,
                consultants: [],
                uploadOpen: false,
                uploadAmId: INITIAL_AM_ID,
                uploadStopName: '',
                uploadMessage: '',
                mappingsOpen: false,
                barInst: null,
                donutInst: null,
                yoyInst: null,
                trendInst: null,
                goalPct: 0,
                goalInput: '',
                fmtMoney(v) {
                    if (v === null || v === undefined || v === '') return '—';
                    const n = Number(v);
                    if (Number.isNaN(n)) return '—';
                    return '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
                async init() {
                    await this.reload();
                    this.$watch('year', () => this.reload());
                    if (IS_ADMIN) this.$watch('amId', () => this.reload());
                    if (IS_ADMIN) {
                        await this.loadAggregate();
                        await this.loadMappingsTable();
                    }
                },
                dashboardUrl() {
                    const p = new URLSearchParams({ year: this.year });
                    if (IS_ADMIN && this.amId) p.set('user_id', this.amId);
                    return '/payroll/api/dashboard?' + p.toString();
                },
                async reload() {
                    if (this.isLoading) return;
                    this.isLoading = true;
                    const res = await fetch(this.dashboardUrl(), { headers: { Accept: 'application/json' } });
                    if (!res.ok) { this.isLoading = false; return; }
                    const data = await res.json();
                    this.summary = data.summary;
                    this.monthly = data.monthly;
                    this.annualTotals = data.annualTotals;
                    this.projection = data.projection;
                    this.goalAmount = data.goal?.amount ?? '0';
                    this.yearsList = (data.years && data.years.length) ? data.years : [this.year];
                    if (!this.yearsList.includes(this.year)) this.yearsList.push(this.year);
                    this.yearsList.sort((a,b) => b - a);
                    this.updateGoalPct();
                    this.isLoading = false;
                    this.$nextTick(() => this.renderCharts());
                    if (IS_ADMIN) this.loadAggregate();
                },
                updateGoalPct() {
                    const g = Number(this.goalAmount);
                    const net = Number(this.summary?.totals?.ytd_net ?? 0);
                    this.goalPct = g > 0 ? Math.min(100, (net / g) * 100) : 0;
                },
                setBarMode(mode) {
                    this.barMode = mode;
                    this.renderBarChart();
                },
                barSource() {
                    if (this.barMode === 'monthly' && this.monthly?.months?.length) {
                        return {
                            labels: this.monthly.months.map(m => m.month.slice(0, 7)),
                            gross: this.monthly.months.map(m => Number(m.gross)),
                            net: this.monthly.months.map(m => Number(m.net)),
                        };
                    }
                    const periods = this.summary?.periods || [];
                    return {
                        labels: periods.map(p => p.date),
                        gross: periods.map(p => Number(p.gross)),
                        net: periods.map(p => Number(p.net)),
                    };
                },
                renderBarChart() {
                    const ctx = document.getElementById('payrollBarChart');
                    if (!ctx) return;
                    const src = this.barSource();
                    if (this.barInst) this.barInst.destroy();
                    this.barInst = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: src.labels,
                            datasets: [
                                { label: 'Gross', data: src.gross, backgroundColor: 'rgba(16, 185, 129, 0.35)' },
                                { label: 'Net', data: src.net, backgroundColor: 'rgba(234, 179, 8, 0.65)' },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { x: { ticks: { maxRotation: 0, font: { size: 10 } } }, y: { ticks: { font: { size: 10 } } } },
                        },
                    });
                },
                renderDonut() {
                    const ctx = document.getElementById('payrollDonutChart');
                    const leg = document.getElementById('payrollDonutLegend');
                    if (!ctx) return;
                    const t = this.summary?.totals || {};
                    const labels = ['Federal', 'Soc Sec', 'Medicare', 'State', 'Disability', '401k'];
                    const values = [
                        Number(t.federal || 0), Number(t.ss || 0), Number(t.medicare || 0),
                        Number(t.state || 0), Number(t.disability || 0), Number(this.summary?.totals?.retirement_total || 0),
                    ];
                    if (this.donutInst) this.donutInst.destroy();
                    this.donutInst = new Chart(ctx, {
                        type: 'doughnut',
                        data: { labels, datasets: [{ data: values, backgroundColor: ['#f87171','#fb923c','#facc15','#f472b6','#ca8a04','#818cf8'] }] },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, cutout: '65%' },
                    });
                    if (leg) {
                        leg.innerHTML = labels.map((l, i) => `<div class="flex justify-between gap-2"><span>${l}</span><span>${this.fmtMoney(values[i])}</span></div>`).join('');
                    }
                },
                renderYoy() {
                    const ctx = document.getElementById('payrollYoyChart');
                    if (!ctx) return;
                    const periods = this.summary?.periods || [];
                    const prior = this.summary?.prior_year_periods || [];
                    const proj = this.projection || {};
                    const completed = periods.length;
                    const totalPeriods = 26;
                    const actualData = periods.map((p, i) => ({ x: i + 1, y: Number(p.cumulative_net) }));
                    const priorData = prior.map((p, i) => ({ x: i + 1, y: Number(p.cumulative_net) }));
                    const avgNet = Number(proj.avg_net_per_period || 0);
                    const projData = [];
                    if (completed > 0 && !proj.projectionSuppressed) {
                        const last = Number(periods[completed - 1].cumulative_net);
                        projData.push({ x: completed, y: last });
                        for (let i = completed + 1; i <= totalPeriods; i++) {
                            projData.push({ x: i, y: Math.round((last + avgNet * (i - completed)) * 100) / 100 });
                        }
                    }
                    if (this.yoyInst) this.yoyInst.destroy();
                    this.yoyInst = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: Array.from({ length: totalPeriods }, (_, i) => String(i + 1)),
                            datasets: [
                                { label: 'This year', data: actualData.map(d => d.y), borderColor: '#ca8a04', tension: 0.25, spanGaps: true },
                                { label: 'Projected', data: projData.map(d => d.y), borderColor: 'rgba(202,138,4,0.4)', borderDash: [4,4], tension: 0.2, spanGaps: true },
                                { label: 'Prior year', data: priorData.map(d => d.y), borderColor: '#10b981', tension: 0.25, spanGaps: true },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } },
                            scales: { y: { ticks: { font: { size: 10 } } }, x: { ticks: { font: { size: 10 } } } },
                        },
                    });
                },
                renderTrend() {
                    const ctx = document.getElementById('payrollTrendChart');
                    if (!ctx) return;
                    const years = (this.annualTotals?.years || []).map(y => y.year);
                    const nets = (this.annualTotals?.years || []).map(y => Number(y.net));
                    if (this.trendInst) this.trendInst.destroy();
                    this.trendInst = new Chart(ctx, {
                        type: 'bar',
                        data: { labels: years, datasets: [{ label: 'Net', data: nets, backgroundColor: 'rgba(59, 130, 246, 0.45)' }] },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
                    });
                },
                renderCharts() {
                    this.renderBarChart();
                    this.renderDonut();
                    this.renderYoy();
                    this.renderTrend();
                },
                async loadConsultants() {
                    const p = new URLSearchParams({ year: this.year });
                    if (IS_ADMIN && this.amId) p.set('user_id', this.amId);
                    const res = await fetch('/payroll/api/consultants?' + p, { headers: { Accept: 'application/json' } });
                    if (!res.ok) { this.consultants = []; return; }
                    const data = await res.json();
                    this.consultants = data.consultants || [];
                },
                async loadAggregate() {
                    const res = await fetch('/payroll/api/aggregate?year=' + this.year, { headers: { Accept: 'application/json' } });
                    const body = document.getElementById('payrollAmCompareBody');
                    if (!res.ok || !body) return;
                    const data = await res.json();
                    body.innerHTML = (data.perAm || []).map(r => `<tr class="border-b border-gray-100">
                        <td class="py-2 pr-4">${escapeHtml(r.name)}</td>
                        <td class="py-2 pr-4">${fmtNum(r.ytd_net)}</td>
                        <td class="py-2 pr-4">${fmtNum(r.ytd_gross)}</td>
                        <td class="py-2">${Number(r.pct_of_total_net).toFixed(1)}%</td>
                    </tr>`).join('') || '<tr><td colspan="4" class="py-3 text-gray-500">No data</td></tr>';
                },
                async loadMappingsTable() {
                    const res = await fetch('/payroll/api/mappings', { headers: { Accept: 'application/json' } });
                    const body = document.getElementById('payrollMappingsBody');
                    if (!res.ok || !body) return;
                    const data = await res.json();
                    const rows = data.mappings || [];
                    if (!rows.length) { body.innerHTML = '<tr><td colspan="4" class="py-3 text-gray-500">No unresolved names</td></tr>'; return; }
                    const consultants = @json($consultants->map(fn ($c) => ['id' => $c->id, 'name' => $c->full_name])->values());
                    body.innerHTML = rows.map(m => `<tr class="border-b border-gray-100">
                        <td class="py-2 pr-4">${escapeHtml(m.raw_name)}</td>
                        <td class="py-2 pr-4">${escapeHtml(m.user_name || '')}</td>
                        <td class="py-2 pr-4">
                            <select class="rounded border-gray-300 text-xs payroll-map-select" data-raw="${escapeHtml(m.raw_name)}" data-uid="${m.user_id}">
                                <option value="">—</option>
                                ${consultants.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('')}
                            </select>
                        </td>
                        <td class="py-2">
                            <button type="button" class="rounded bg-gray-900 px-2 py-1 text-xs text-white payroll-map-save">Save</button>
                        </td>
                    </tr>`).join('');
                    const rootEl = document.getElementById('payroll-root');
                    body.querySelectorAll('.payroll-map-save').forEach(btn => {
                        btn.addEventListener('click', async (e) => {
                            const tr = e.target.closest('tr');
                            const sel = tr.querySelector('select');
                            const cid = sel.value;
                            if (!cid) return;
                            const raw = sel.dataset.raw;
                            const uid = sel.dataset.uid;
                            const r = await apiFetch('/payroll/api/mappings', { method: 'PUT', body: JSON.stringify({ raw_name: raw, user_id: Number(uid), consultant_id: Number(cid) }) });
                            if (r.ok && rootEl && window.Alpine) {
                                const comp = window.Alpine.$data(rootEl);
                                if (comp?.loadMappingsTable) await comp.loadMappingsTable();
                            }
                        });
                    });
                },
                async saveGoal() {
                    const amount = parseFloat(this.goalInput);
                    if (!this.amId || isNaN(amount) || amount < 0) return;
                    const res = await apiFetch('/payroll/api/goal', {
                        method: 'POST',
                        body: JSON.stringify({ user_id: this.amId, year: this.year, goal_amount: amount }),
                    });
                    if (res.ok) {
                        this.goalInput = '';
                        await this.reload();
                    }
                },
                async submitUpload() {
                    this.uploadMessage = '';
                    const f = this.$refs.payrollFile.files[0];
                    if (!f) return;
                    const fd = new FormData();
                    fd.append('file', f);
                    fd.append('user_id', this.uploadAmId);
                    fd.append('stop_name', this.uploadStopName);
                    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const res = await fetch('/payroll/upload', { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token }, body: fd });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        this.uploadMessage = data.message || data.error || 'Upload failed';
                        return;
                    }
                    let msg = `Imported ${data.recordCount} periods. Years: ${(data.yearsAffected || []).join(', ')}.`;
                    if ((data.newConsultants || []).length) {
                        msg += ` Auto-created ${data.newConsultants.length} new consultant(s): ` + data.newConsultants.join(', ') + '.';
                    }
                    this.uploadMessage = msg;
                    this.uploadOpen = false;
                    await this.reload();
                },
            };
        }
        function escapeHtml(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
        function fmtNum(v) {
            const n = Number(v);
            return '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    </script>
</x-app-layout>
