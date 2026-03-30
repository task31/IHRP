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

        {{-- Federal Tax Bracket card (populated by buildBracketCard()) --}}
        <div id="bracketCardWrap"></div>

        <div class="rounded-lg bg-white p-4 shadow-sm">
            <h3 class="mb-2 text-sm font-semibold text-gray-800">Multi-year trend</h3>
            <div class="h-56"><canvas id="payrollTrendChart"></canvas></div>
        </div>

        {{-- Pay period detail table (populated by renderPeriodTable()) --}}
        <div id="periodTableWrap"></div>

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

        {{-- Consultant Breakdown Drawer (dark theme) --}}
        <div
            x-show="drawerOpen"
            x-cloak
            class="fixed inset-0 z-40 flex justify-end"
            style="background:rgba(0,0,0,0.55)"
            @keydown.escape.window="drawerOpen = false"
        >
            <div
                class="relative h-full w-full overflow-y-auto p-6 shadow-xl"
                style="max-width:600px;background:#0f172a;color:#f8fafc"
                @click.outside="drawerOpen = false"
            >
                <div class="mb-5 flex items-center justify-between">
                    <h3 class="text-lg font-semibold" style="color:#f8fafc">
                        Consultant Breakdown — <span x-text="year"></span>
                    </h3>
                    <button
                        type="button"
                        style="color:#94a3b8;background:none;border:none;cursor:pointer;font-size:18px;line-height:1"
                        class="hover:opacity-70"
                        @click="drawerOpen = false"
                    >✕</button>
                </div>

                <div class="mb-5 grid grid-cols-3 gap-3">
                    <div class="rounded-lg p-3" style="background:#1e293b">
                        <p style="font-size:11px;color:#94a3b8;margin:0 0 4px 0">Active Consultants</p>
                        <p class="text-2xl font-bold" style="color:#f8fafc;margin:0" x-text="consultants.length"></p>
                    </div>
                    <div class="rounded-lg p-3" style="background:#1e293b">
                        <p style="font-size:11px;color:#94a3b8;margin:0 0 4px 0">Total Commissions</p>
                        <p class="text-xl font-bold" style="color:#22c55e;margin:0" x-text="fmtMoney(consultantMeta?.total_am_earnings)"></p>
                    </div>
                    <div class="rounded-lg p-3" style="background:#1e293b">
                        <p style="font-size:11px;color:#94a3b8;margin:0 0 4px 0">Top Earner</p>
                        <p class="text-lg font-bold truncate" style="color:#f8fafc;margin:0" x-text="(consultantMeta?.top_earner || '—').split(' ')[0]"></p>
                    </div>
                </div>

                <template x-if="consultants.length > 0">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="border-bottom:1px solid #334155">
                                <th style="padding:8px 12px 8px 0;font-size:11px;text-transform:uppercase;color:#64748b;font-weight:500;text-align:left">Consultant</th>
                                <th style="padding:8px 12px 8px 0;font-size:11px;text-transform:uppercase;color:#64748b;font-weight:500;text-align:left">Commission</th>
                                <th style="padding:8px 12px 8px 0;font-size:11px;text-transform:uppercase;color:#64748b;font-weight:500;text-align:right">Earned for You</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="c in consultants" :key="c.name">
                                <tr style="border-bottom:1px solid #1e293b">
                                    <td style="padding:10px 12px 10px 0;font-size:13px;color:#e2e8f0;font-weight:500" x-text="c.name"></td>
                                    <td style="padding:10px 12px 10px 0">
                                        <span
                                            style="border-radius:9999px;padding:2px 8px;font-size:11px;font-weight:600;display:inline-block"
                                            :style="'background:' + tierColor(c.tier) + '22;color:' + tierColor(c.tier)"
                                            x-text="c.tier || '—'"
                                        ></span>
                                    </td>
                                    <td style="padding:10px 12px 10px 0;font-size:13px;font-weight:600;text-align:right;color:#22c55e"
                                        x-text="c.am_earnings !== null ? fmtMoney(c.am_earnings) : '—'">
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </template>

                <div x-show="!consultants.length && drawerOpen" class="py-12 text-center" style="color:#475569">
                    <p>No consultant data for this period.</p>
                </div>
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
                        <input type="text" x-model="uploadStopName" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="AM's full name" required />
                        <p class="mt-1 text-xs text-gray-500">Auto-filled from the selected AM — override only if the name differs in the file.</p>
                    </div>
                    <div x-show="uploadMessage" class="rounded-md bg-amber-50 p-3 text-sm text-amber-900" x-text="uploadMessage"></div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm" @click="uploadOpen = false">Cancel</button>
                        <button type="button"
                            class="rounded-md border border-blue-300 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100"
                            @click="recomputeMargins()"
                            :disabled="recomputeLoading"
                            x-text="recomputeLoading ? 'Recomputing…' : 'Recompute Margins'">
                        </button>
                        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const IS_ADMIN = @json(auth()->user()->role === 'admin');
        const INITIAL_AM_ID = @json($accountManagers->first()->id ?? null);
        const AM_NAMES = @json($accountManagers->pluck('name', 'id'));

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
                consultantMeta: null,
                showAllPeriods: false,
                expandedPeriod: null,
                uploadOpen: false,
                uploadAmId: INITIAL_AM_ID,
                uploadStopName: AM_NAMES[INITIAL_AM_ID] || '',
                uploadMessage: '',
                recomputeLoading: false,
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
                tierColor(tier) {
                    const map = { '50%': '#2dd4bf', '35%': '#60a5fa', '20%': '#a78bfa', '10%': '#fbbf24' };
                    return map[tier] || '#94a3b8';
                },
                async init() {
                    await this.reload();
                    this.$watch('year', () => this.reload());
                    this.$watch('uploadAmId', val => { this.uploadStopName = AM_NAMES[val] || ''; });
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
                    this.buildBracketCard();
                    this.renderPeriodTable();
                },
                buildBracketCard() {
                    const wrap = document.getElementById('bracketCardWrap');
                    if (!wrap) return;
                    if (!this.summary?.totals) { wrap.innerHTML = ''; return; }

                    const BRACKETS = [
                        { rate: 10, min: 0,       max: 11925,   color: '#00c9a7' },
                        { rate: 12, min: 11925,   max: 48475,   color: '#60a5fa' },
                        { rate: 22, min: 48475,   max: 103350,  color: '#818cf8' },
                        { rate: 24, min: 103350,  max: 197300,  color: '#fbbf24' },
                        { rate: 32, min: 197300,  max: 250525,  color: '#f97316' },
                        { rate: 35, min: 250525,  max: 626350,  color: '#f87171' },
                        { rate: 37, min: 626350,  max: Infinity, color: '#dc2626' },
                    ];
                    const CAP = 260000;

                    const ytdGross = Number(this.summary.totals.ytd_gross || 0);
                    const ytdFederal = Number(this.summary.totals.federal || 0);

                    let marginal = BRACKETS[0];
                    for (const b of BRACKETS) {
                        if (ytdGross >= b.min) marginal = b;
                        else break;
                    }

                    const effectiveRate = ytdGross > 0 ? (ytdFederal / ytdGross * 100).toFixed(1) : null;
                    const rawPct = CAP > 0 ? (Math.min(ytdGross, CAP) / CAP * 100) : 0;
                    const markerPct = Math.min(Math.max(rawPct, 3), 97);

                    const segments = BRACKETS.map(b => {
                        const sMin = Math.min(b.min, CAP);
                        const sMax = Math.min(b.max === Infinity ? CAP : b.max, CAP);
                        if (sMin >= CAP || sMax <= sMin) return '';
                        const w = ((sMax - sMin) / CAP * 100).toFixed(3);
                        return '<div style="width:' + w + '%;background:' + b.color + ';height:100%;display:flex;align-items:center;justify-content:center;overflow:hidden;min-width:0;flex-shrink:0">'
                            + '<span style="font-size:9px;color:rgba(255,255,255,0.9);font-weight:700;white-space:nowrap;padding:0 2px">' + b.rate + '%</span>'
                            + '</div>';
                    }).join('');

                    const insight = ytdGross > 0
                        ? "You're in the " + marginal.rate + "% bracket, but only pay " + effectiveRate + "% on your total income \u2014 because lower brackets are taxed at their lower rates first."
                        : 'Enter payroll data to see your federal tax bracket position.';

                    wrap.innerHTML = '<div class="rounded-lg bg-white p-4 shadow-sm">'
                        + '<div class="mb-3 flex items-center justify-between">'
                        + '<h3 class="text-sm font-semibold text-gray-800">&#9658; Federal Tax Bracket</h3>'
                        + '<span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">2026 Single Filer</span>'
                        + '</div>'
                        + '<div style="position:relative;margin-bottom:42px;margin-top:6px">'
                        + '<div style="display:flex;height:26px;border-radius:6px;overflow:hidden;width:100%">' + segments + '</div>'
                        + '<div style="position:absolute;top:0;left:' + markerPct.toFixed(2) + '%;transform:translateX(-50%);z-index:1;pointer-events:none">'
                        + '<div style="width:2px;height:26px;background:#1e293b;margin:0 auto"></div>'
                        + '<div style="background:#1e293b;color:white;font-size:10px;padding:2px 7px;border-radius:4px;white-space:nowrap;margin-top:3px;text-align:center">' + this.fmtMoney(ytdGross) + '</div>'
                        + '</div>'
                        + '</div>'
                        + '<div class="flex gap-3">'
                        + '<div style="flex:1;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px">'
                        + '<p style="font-size:11px;color:#64748b;margin:0 0 4px 0">Marginal Rate</p>'
                        + '<p style="font-size:24px;font-weight:700;color:' + marginal.color + ';margin:0 0 2px 0">' + marginal.rate + '%</p>'
                        + '<p style="font-size:11px;color:#94a3b8;margin:0">on income over ' + this.fmtMoney(marginal.min) + '</p>'
                        + '</div>'
                        + '<div style="flex:1;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px">'
                        + '<p style="font-size:11px;color:#64748b;margin:0 0 4px 0">Effective Federal Rate</p>'
                        + '<p style="font-size:24px;font-weight:700;color:#3b82f6;margin:0 0 2px 0">' + (effectiveRate !== null ? effectiveRate + '%' : '—') + '</p>'
                        + '<p style="font-size:11px;color:#94a3b8;margin:0">of YTD income</p>'
                        + '</div>'
                        + '</div>'
                        + '<p style="margin-top:12px;background:#eff6ff;border-radius:6px;padding:8px 12px;font-size:12px;color:#1e40af">' + insight + '</p>'
                        + '</div>';
                },
                renderPeriodTable() {
                    const wrap = document.getElementById('periodTableWrap');
                    if (!wrap) return;
                    if (!this.summary) { wrap.innerHTML = ''; return; }

                    const allPeriods = [...(this.summary.periods || [])].reverse();
                    const totalCount = allPeriods.length;
                    const displayed = this.showAllPeriods ? allPeriods : allPeriods.slice(0, 5);
                    const t = this.summary.totals || {};

                    const cell = (v, green) => {
                        const n = Number(v);
                        const fmt = this.fmtMoney(v);
                        if (green && n > 0) return '<span style="color:#10b981;font-weight:600">' + fmt + '</span>';
                        if (!n || Number.isNaN(n)) return '<span style="color:#cbd5e1">' + fmt + '</span>';
                        return fmt;
                    };

                    const rowsHtml = displayed.map(p => {
                        const isExp = this.expandedPeriod === p.date;
                        const gross = Number(p.gross) || 0;
                        const pctOf = v => gross > 0
                            ? ' <span style="color:#94a3b8;font-size:11px">(' + (Number(v) / gross * 100).toFixed(1) + '%)</span>'
                            : '';
                        const totalDed = ['federal','ss','medicare','state','disability','retirement']
                            .reduce((s, k) => s + (Number(p[k]) || 0), 0);

                        const dedRows = [
                            ['Federal', p.federal],
                            ['Soc Sec', p.ss],
                            ['Medicare', p.medicare],
                            ['State', p.state],
                            ['Disability', p.disability],
                            ['401k', p.retirement],
                        ].map(([lbl, val]) =>
                            '<div style="display:flex;justify-content:space-between;align-items:baseline;padding:3px 0;border-bottom:1px solid #f1f5f9">'
                            + '<span style="color:#64748b;min-width:80px">' + lbl + '</span>'
                            + '<span style="color:#374151;min-width:80px;text-align:right">' + this.fmtMoney(val) + '</span>'
                            + pctOf(val)
                            + '</div>'
                        ).join('');

                        const expandRow = isExp
                            ? '<tr><td colspan="9" style="padding:0 12px 10px 28px;background:#f8fafc">'
                                + '<div style="padding:6px 0;font-size:12px">'
                                + dedRows
                                + '<div style="display:flex;justify-content:space-between;align-items:baseline;padding:5px 0;font-weight:700;border-top:2px solid #e2e8f0;margin-top:2px">'
                                + '<span style="color:#374151;min-width:80px">Total Ded.</span>'
                                + '<span style="color:#374151;min-width:80px;text-align:right">' + this.fmtMoney(totalDed) + '</span>'
                                + pctOf(totalDed)
                                + '</div>'
                                + '</div>'
                                + '</td></tr>'
                            : '';

                        return '<tr class="period-row" data-date="' + escapeHtml(p.date) + '" style="border-bottom:1px solid #f3f4f6;cursor:pointer" onmouseenter="this.style.background=\'#f9fafb\'" onmouseleave="this.style.background=\'\'">'
                            + '<td style="padding:9px 12px 9px 8px;font-size:13px">' + escapeHtml(p.date) + ' <span style="font-size:10px;color:#94a3b8">' + (isExp ? '&#9650;' : '&#9660;') + '</span></td>'
                            + '<td style="padding:9px 12px 9px 0;font-size:13px">' + this.fmtMoney(p.gross) + '</td>'
                            + '<td style="padding:9px 12px 9px 0;font-size:13px">' + cell(p.federal) + '</td>'
                            + '<td style="padding:9px 12px 9px 0;font-size:13px">' + cell(p.ss) + '</td>'
                            + '<td style="padding:9px 12px 9px 0;font-size:13px">' + cell(p.medicare) + '</td>'
                            + '<td style="padding:9px 12px 9px 0;font-size:13px">' + cell(p.state) + '</td>'
                            + '<td style="padding:9px 12px 9px 0;font-size:13px">' + cell(p.disability) + '</td>'
                            + '<td style="padding:9px 12px 9px 0;font-size:13px">' + cell(p.retirement, true) + '</td>'
                            + '<td style="padding:9px 0 9px 0;font-size:13px;font-weight:600">' + this.fmtMoney(p.net) + '</td>'
                            + '</tr>'
                            + expandRow;
                    }).join('');

                    const footerHtml = '<tr style="border-top:2px solid #d1d5db;background:#f9fafb;font-weight:600">'
                        + '<td style="padding:8px 12px 8px 8px;font-size:11px;text-transform:uppercase;color:#6b7280;letter-spacing:0.04em">YTD Total</td>'
                        + '<td style="padding:8px 12px 8px 0;font-size:13px">' + this.fmtMoney(t.ytd_gross) + '</td>'
                        + '<td style="padding:8px 12px 8px 0;font-size:13px">' + this.fmtMoney(t.federal) + '</td>'
                        + '<td style="padding:8px 12px 8px 0;font-size:13px">' + this.fmtMoney(t.ss) + '</td>'
                        + '<td style="padding:8px 12px 8px 0;font-size:13px">' + this.fmtMoney(t.medicare) + '</td>'
                        + '<td style="padding:8px 12px 8px 0;font-size:13px">' + this.fmtMoney(t.state) + '</td>'
                        + '<td style="padding:8px 12px 8px 0;font-size:13px">' + this.fmtMoney(t.disability) + '</td>'
                        + '<td style="padding:8px 12px 8px 0;font-size:13px">' + this.fmtMoney(t.retirement_total) + '</td>'
                        + '<td style="padding:8px 0 8px 0;font-size:13px">' + this.fmtMoney(t.ytd_net) + '</td>'
                        + '</tr>';

                    const toggleLabel = this.showAllPeriods ? '5 periods' : ('All (' + totalCount + ')');
                    const toggleBtn = totalCount > 5
                        ? '<button id="periodToggleBtn" type="button" style="border:1px solid #d1d5db;border-radius:6px;padding:2px 10px;font-size:12px;color:#374151;background:white;cursor:pointer">' + toggleLabel + '</button>'
                        : '';

                    wrap.innerHTML = '<div class="rounded-lg bg-white p-4 shadow-sm">'
                        + '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">'
                        + '<div style="display:flex;align-items:center;gap:8px">'
                        + '<h3 style="font-size:14px;font-weight:600;color:#1f2937;margin:0">&#9658; Pay Period Detail</h3>'
                        + '<span style="background:#f3f4f6;border-radius:9999px;padding:1px 8px;font-size:11px;color:#6b7280">' + totalCount + ' period' + (totalCount !== 1 ? 's' : '') + '</span>'
                        + '</div>'
                        + toggleBtn
                        + '</div>'
                        + '<div style="overflow-x:auto">'
                        + '<table style="width:100%;border-collapse:collapse;min-width:720px">'
                        + '<thead><tr style="border-bottom:1px solid #e5e7eb;background:#f9fafb">'
                        + '<th style="padding:8px 12px 8px 8px;font-size:11px;text-transform:uppercase;color:#9ca3af;font-weight:500;text-align:left">Check Date</th>'
                        + '<th style="padding:8px 12px 8px 0;font-size:11px;text-transform:uppercase;color:#9ca3af;font-weight:500;text-align:left">Gross</th>'
                        + '<th style="padding:8px 12px 8px 0;font-size:11px;text-transform:uppercase;color:#9ca3af;font-weight:500;text-align:left">Federal</th>'
                        + '<th style="padding:8px 12px 8px 0;font-size:11px;text-transform:uppercase;color:#9ca3af;font-weight:500;text-align:left">Soc Sec</th>'
                        + '<th style="padding:8px 12px 8px 0;font-size:11px;text-transform:uppercase;color:#9ca3af;font-weight:500;text-align:left">Medicare</th>'
                        + '<th style="padding:8px 12px 8px 0;font-size:11px;text-transform:uppercase;color:#9ca3af;font-weight:500;text-align:left">State</th>'
                        + '<th style="padding:8px 12px 8px 0;font-size:11px;text-transform:uppercase;color:#9ca3af;font-weight:500;text-align:left">Disability</th>'
                        + '<th style="padding:8px 12px 8px 0;font-size:11px;text-transform:uppercase;color:#9ca3af;font-weight:500;text-align:left">401k</th>'
                        + '<th style="padding:8px 0 8px 0;font-size:11px;text-transform:uppercase;color:#9ca3af;font-weight:500;text-align:left">Net</th>'
                        + '</tr></thead>'
                        + '<tbody>'
                        + (rowsHtml || '<tr><td colspan="9" style="padding:16px 8px;color:#9ca3af">No payroll data available yet</td></tr>')
                        + footerHtml
                        + '</tbody>'
                        + '</table>'
                        + '</div>'
                        + '</div>';

                    wrap.querySelectorAll('.period-row').forEach(tr => {
                        tr.addEventListener('click', () => {
                            const d = tr.dataset.date;
                            this.expandedPeriod = this.expandedPeriod === d ? null : d;
                            this.renderPeriodTable();
                        });
                    });

                    const tb = document.getElementById('periodToggleBtn');
                    if (tb) {
                        tb.addEventListener('click', () => {
                            this.showAllPeriods = !this.showAllPeriods;
                            this.renderPeriodTable();
                        });
                    }
                },
                async loadConsultants() {
                    const p = new URLSearchParams({ year: this.year });
                    if (IS_ADMIN && this.amId) p.set('user_id', this.amId);
                    const res = await fetch('/payroll/api/consultants?' + p, { headers: { Accept: 'application/json' } });
                    if (!res.ok) { this.consultants = []; this.consultantMeta = null; return; }
                    const data = await res.json();
                    this.consultants = data.consultants || [];
                    this.consultantMeta = { total_am_earnings: data.total_am_earnings, top_earner: data.top_earner, total_periods: data.total_periods };
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
                async recomputeMargins() {
                    if (!this.uploadAmId) return;
                    this.recomputeLoading = true;
                    this.uploadMessage = '';
                    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const fd = new FormData();
                    fd.append('user_id', this.uploadAmId);
                    const res = await fetch('/payroll/recompute-margins', { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token }, body: fd });
                    const data = await res.json().catch(() => ({}));
                    this.recomputeLoading = false;
                    if (!res.ok) {
                        this.uploadMessage = data.message || 'Recompute failed';
                        return;
                    }
                    this.uploadMessage = `Margins recomputed for ${data.updated} consultant entries.`;
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
