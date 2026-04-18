<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight" style="color:var(--fg-1)">Payroll</h2>
    </x-slot>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

    <div
        id="payroll-root"
        class="stack"
        x-data="payrollDashboard()"
        x-init="init()"
    >
        <div class="flex flex-wrap items-end gap-4">
            <div class="field">
                <label>Year</label>
                <select x-model.number="year" class="field-control" style="min-width:140px;">
                    <template x-for="y in yearsList" :key="y">
                        <option :value="y" x-text="y"></option>
                    </template>
                </select>
            </div>
            <template x-if="IS_ADMIN">
                <div class="field">
                    <label>Account manager</label>
                    <select x-model.number="amId" class="field-control" style="min-width:220px;">
                        @foreach ($accountManagers as $am)
                            <option value="{{ $am->id }}">{{ $am->name }}</option>
                        @endforeach
                    </select>
                </div>
            </template>
            <template x-if="IS_ADMIN">
                <button
                    type="button"
                    class="btn btn-primary"
                    @click="uploadOpen = true"
                >Upload payroll file</button>
            </template>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="kpi-card good">
                <p class="kpi-label">YTD net</p>
                <p class="kpi-value mono-num" style="color:var(--fg-1)" x-text="fmtMoney(summary?.totals?.ytd_net)"></p>
            </div>
            <div class="kpi-card">
                <p class="kpi-label">YTD gross</p>
                <p class="kpi-value mono-num" style="color:var(--fg-1)" x-text="fmtMoney(summary?.totals?.ytd_gross)"></p>
            </div>
            <div class="kpi-card warn">
                <p class="kpi-label">Taxes paid</p>
                <p class="kpi-value mono-num" style="color:var(--fg-1)" x-text="fmtMoney(summary?.totals?.taxes_paid)"></p>
            </div>
            <div class="kpi-card brand">
                <p class="kpi-label">Projected annual net</p>
                <p class="kpi-value mono-num" style="font-size:28px;color:var(--fg-1)" x-show="!projection?.projectionSuppressed" x-text="fmtMoney(projection?.projectedAnnual)"></p>
                <p class="kpi-sub" x-show="projection?.projectionSuppressed" style="color:var(--warn-400)" x-text="projection?.message || '—'"></p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="card-base lg:col-span-2">
                <div class="mb-2 flex items-center justify-between">
                    <h3 style="font-size:15px;font-weight:700;color:var(--fg-1)">Gross vs net</h3>
                    <div class="flex gap-2 text-xs">
                        <button type="button" :class="barMode==='biweekly' ? 'btn btn-secondary btn-sm' : 'btn btn-ghost btn-sm'" @click="setBarMode('biweekly')">Bi-weekly</button>
                        <button type="button" :class="barMode==='monthly' ? 'btn btn-secondary btn-sm' : 'btn btn-ghost btn-sm'" @click="setBarMode('monthly')">Monthly</button>
                    </div>
                </div>
                <div class="h-64"><canvas id="payrollBarChart"></canvas></div>
            </div>
            <div class="card-base">
                <h3 style="font-size:15px;font-weight:700;color:var(--fg-1)">Tax mix</h3>
                <div class="h-52"><canvas id="payrollDonutChart"></canvas></div>
                <div id="payrollDonutLegend" class="mt-2 space-y-1" style="font-size:12px;color:var(--fg-2)"></div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="card-base">
                <h3 style="font-size:15px;font-weight:700;color:var(--fg-1)">Cumulative net (YoY)</h3>
                <div class="h-56"><canvas id="payrollYoyChart"></canvas></div>
            </div>
            <div class="card-base">
                <h3 style="font-size:15px;font-weight:700;color:var(--fg-1)">Goal tracker</h3>
                <p class="field-help" x-show="!goalAmount || Number(goalAmount)<=0">No goal set</p>
                <template x-if="goalAmount && Number(goalAmount)>0">
                    <div>
                        <div class="progress-track" style="margin-top:10px;">
                            <div class="progress-fill" style="background:var(--success-500)" :style="`width:${goalPct}%`"></div>
                        </div>
                        <p style="margin-top:10px;font-size:13px;color:var(--fg-2)">
                            <span x-text="goalPct.toFixed(1)"></span>% of goal
                            (<span x-text="fmtMoney(summary?.totals?.ytd_net)"></span> / <span x-text="fmtMoney(goalAmount)"></span>)
                        </p>
                    </div>
                </template>
                <template x-if="IS_ADMIN">
                    <form class="mt-3 flex items-center gap-2" @submit.prevent="saveGoal">
                        <span class="eyebrow">Set goal:</span>
                        <input type="number" min="0" step="0.01" x-model="goalInput" placeholder="e.g. 80000" class="field-control" style="width:140px;" />
                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                    </form>
                </template>
            </div>
        </div>

        {{-- Federal Tax Bracket card (populated by buildBracketCard()) --}}
        <div id="bracketCardWrap"></div>

        <div class="card-base">
            <h3 style="font-size:15px;font-weight:700;color:var(--fg-1)">Multi-year trend</h3>
            <div class="h-56"><canvas id="payrollTrendChart"></canvas></div>
        </div>

        {{-- Pay period detail table (populated by renderPeriodTable()) --}}
        <div id="periodTableWrap"></div>

        <div>
            <button
                type="button"
                class="btn btn-secondary btn-sm"
                @click="drawerOpen = true; loadConsultants()"
            >Consultant breakdown</button>
        </div>

        <template x-if="IS_ADMIN">
            <div class="card-base">
                <h3 style="font-size:15px;font-weight:700;color:var(--fg-1)">AM comparison (<span x-text="year"></span>)</h3>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>AM</th>
                                <th>YTD net</th>
                                <th>YTD gross</th>
                                <th>Share of net</th>
                            </tr>
                        </thead>
                        <tbody id="payrollAmCompareBody">
                            <tr><td colspan="4" style="color:var(--fg-3)">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <div x-show="mappingsOpen" x-cloak class="card-base">
            @include('payroll.mappings')
        </div>

        {{-- Consultant Breakdown Drawer (dark theme) --}}
        <div
            x-show="drawerOpen"
            x-cloak
            class="fixed inset-0 z-40 flex justify-end"
            style="background:rgba(5,7,13,0.76)"
            @keydown.escape.window="drawerOpen = false"
        >
            <div
                class="relative h-full w-full overflow-y-auto p-6 shadow-xl"
                style="max-width:600px;background:var(--bg-2);color:var(--fg-1);border-left:1px solid var(--border-2)"
                @click.outside="drawerOpen = false"
            >
                <div class="mb-5 flex items-center justify-between">
                    <h3 class="text-lg font-semibold" style="color:var(--fg-1)">
                        Consultant Breakdown — <span x-text="year"></span>
                    </h3>
                    <button
                        type="button"
                        class="icon-btn hover:opacity-70"
                        style="width:32px;height:32px;font-size:18px;line-height:1"
                        @click="drawerOpen = false"
                    >✕</button>
                </div>

                <div class="mb-5 grid grid-cols-3 gap-3">
                    <div class="card-soft">
                        <p class="eyebrow" style="margin-bottom:4px;">Active Consultants</p>
                        <p class="mono-num" style="font-size:28px;font-weight:700;color:var(--fg-1);margin:0" x-text="consultants.length"></p>
                    </div>
                    <div class="card-soft">
                        <p class="eyebrow" style="margin-bottom:4px;">Total Commissions</p>
                        <p class="mono-num" style="font-size:22px;font-weight:700;color:var(--success-400);margin:0" x-text="fmtMoney(consultantMeta?.total_am_earnings)"></p>
                    </div>
                    <div class="card-soft">
                        <p class="eyebrow" style="margin-bottom:4px;">Top Earner</p>
                        <p class="text-lg font-bold truncate" style="color:var(--fg-1);margin:0" x-text="(consultantMeta?.top_earner || '—').split(' ')[0]"></p>
                    </div>
                </div>

                <template x-if="consultants.length > 0">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Consultant</th>
                                <th>Commission</th>
                                <th class="text-right">Earned for You</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="c in consultants" :key="c.name">
                                <tr>
                                    <td style="color:var(--fg-1);font-weight:500" x-text="c.name"></td>
                                    <td>
                                        <span
                                            style="border-radius:9999px;padding:2px 8px;font-size:11px;font-weight:600;display:inline-block"
                                            :style="'background:' + tierColor(c.tier) + '22;color:' + tierColor(c.tier)"
                                            x-text="c.tier || '—'"
                                        ></span>
                                    </td>
                                    <td class="mono-num" style="text-align:right;color:var(--success-400);font-weight:600"
                                        x-text="c.am_earnings !== null ? fmtMoney(c.am_earnings) : '—'">
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </template>

                <div x-show="!consultants.length && drawerOpen" class="py-12 text-center" style="color:var(--fg-3)">
                    <p>No consultant data for this period.</p>
                </div>
            </div>
        </div>

        {{-- Upload modal --}}
        <div x-show="uploadOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(5,7,13,0.76)">
            <div class="card-base" style="width:100%;max-width:520px" @click.outside="uploadOpen = false">
                <h3 style="font-size:18px;font-weight:700;color:var(--fg-1)">Upload payroll (.xlsx)</h3>
                <form class="mt-4 space-y-4" @submit.prevent="submitUpload">
                    <div class="field">
                        <label>File</label>
                        <input type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" x-ref="payrollFile" class="field-control" required />
                    </div>
                    <div class="field">
                        <label>Account manager</label>
                        <select x-model.number="uploadAmId" class="field-control" required>
                            @foreach ($accountManagers as $am)
                                <option value="{{ $am->id }}">{{ $am->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Stop reading at row starting with…</label>
                        <input type="text" x-model="uploadStopName" class="field-control" placeholder="AM's full name" required />
                        <p class="field-help">Auto-filled from the selected AM — override only if the name differs in the file.</p>
                    </div>
                    <div x-show="uploadMessage" class="surface-warn" style="padding:12px 14px;font-size:13px;" x-text="uploadMessage"></div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" @click="uploadOpen = false">Cancel</button>
                        <button type="button"
                            class="btn btn-secondary btn-sm"
                            @click="recomputeMargins()"
                            :disabled="recomputeLoading"
                            x-text="recomputeLoading ? 'Recomputing…' : 'Recompute Margins'">
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm">Upload</button>
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
                        leg.innerHTML = labels.map((l, i) => `<div class="flex justify-between gap-2"><span style="color:var(--fg-2)">${l}</span><span class="mono-num" style="color:var(--fg-1)">${this.fmtMoney(values[i])}</span></div>`).join('');
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

                    wrap.innerHTML = `
                        <div class="card-base">
                            <div class="mb-3 flex items-center justify-between">
                                <h3 style="font-size:15px;font-weight:700;color:var(--fg-1)">&#9658; Federal Tax Bracket</h3>
                                <span class="badge info">2026 Single Filer</span>
                            </div>
                            <div style="position:relative;margin-bottom:42px;margin-top:6px">
                                <div style="display:flex;height:26px;border-radius:6px;overflow:hidden;width:100%">${segments}</div>
                                <div style="position:absolute;top:0;left:${markerPct.toFixed(2)}%;transform:translateX(-50%);z-index:1;pointer-events:none">
                                    <div style="width:2px;height:26px;background:var(--bg-1);margin:0 auto"></div>
                                    <div style="background:var(--bg-1);color:var(--fg-1);font-size:10px;padding:2px 7px;border-radius:4px;white-space:nowrap;margin-top:3px;text-align:center">${this.fmtMoney(ytdGross)}</div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div class="card-soft">
                                    <p class="eyebrow" style="margin-bottom:4px;">Marginal Rate</p>
                                    <p class="mono-num" style="font-size:24px;font-weight:700;color:${marginal.color};margin:0 0 2px 0">${marginal.rate}%</p>
                                    <p class="field-help" style="margin:0">on income over ${this.fmtMoney(marginal.min)}</p>
                                </div>
                                <div class="card-soft">
                                    <p class="eyebrow" style="margin-bottom:4px;">Effective Federal Rate</p>
                                    <p class="mono-num" style="font-size:24px;font-weight:700;color:var(--info-400);margin:0 0 2px 0">${effectiveRate !== null ? effectiveRate + '%' : '—'}</p>
                                    <p class="field-help" style="margin:0">of YTD income</p>
                                </div>
                            </div>
                            <p class="surface-info" style="margin-top:12px;padding:8px 12px;font-size:12px;color:var(--info-400)">${insight}</p>
                        </div>`;
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
                        if (green && n > 0) return `<span class="mono-num" style="color:var(--success-400);font-weight:600">${fmt}</span>`;
                        if (!n || Number.isNaN(n)) return `<span class="mono-num" style="color:var(--fg-4)">${fmt}</span>`;
                        return `<span class="mono-num">${fmt}</span>`;
                    };

                    const rowsHtml = displayed.map(p => {
                        const isExp = this.expandedPeriod === p.date;
                        const gross = Number(p.gross) || 0;
                        const pctOf = v => gross > 0
                            ? ` <span class="mono-dim">(${(Number(v) / gross * 100).toFixed(1)}%)</span>`
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
                        ].map(([lbl, val]) => `
                            <div style="display:flex;justify-content:space-between;align-items:baseline;padding:3px 0;border-bottom:1px solid var(--border-1)">
                                <span style="color:var(--fg-3);min-width:80px">${lbl}</span>
                                <span class="mono-num" style="min-width:80px;text-align:right">${this.fmtMoney(val)}</span>
                                ${pctOf(val)}
                            </div>`
                        ).join('');

                        const expandRow = isExp
                            ? `<tr><td colspan="9" style="padding:0 12px 10px 28px;background:rgba(255,255,255,0.02)">
                                <div style="padding:6px 0;font-size:12px">
                                    ${dedRows}
                                    <div style="display:flex;justify-content:space-between;align-items:baseline;padding:5px 0;font-weight:700;border-top:2px solid var(--border-2);margin-top:2px">
                                        <span style="color:var(--fg-2);min-width:80px">Total Ded.</span>
                                        <span class="mono-num" style="min-width:80px;text-align:right">${this.fmtMoney(totalDed)}</span>
                                        ${pctOf(totalDed)}
                                    </div>
                                </div>
                            </td></tr>`
                            : '';

                        return `<tr class="period-row" data-date="${escapeHtml(p.date)}" style="border-bottom:1px solid var(--border-1);cursor:pointer">
                            <td style="padding:9px 12px 9px 8px;font-size:13px;color:var(--fg-1)">${escapeHtml(p.date)} <span class="mono-dim">${isExp ? '&#9650;' : '&#9660;'}</span></td>
                            <td style="padding:9px 12px 9px 0">${cell(p.gross)}</td>
                            <td style="padding:9px 12px 9px 0">${cell(p.federal)}</td>
                            <td style="padding:9px 12px 9px 0">${cell(p.ss)}</td>
                            <td style="padding:9px 12px 9px 0">${cell(p.medicare)}</td>
                            <td style="padding:9px 12px 9px 0">${cell(p.state)}</td>
                            <td style="padding:9px 12px 9px 0">${cell(p.disability)}</td>
                            <td style="padding:9px 12px 9px 0">${cell(p.retirement, true)}</td>
                            <td class="mono-num" style="padding:9px 0 9px 0;font-weight:600;color:var(--fg-1)">${this.fmtMoney(p.net)}</td>
                        </tr>${expandRow}`;
                    }).join('');

                    const footerHtml = `<tr style="border-top:2px solid var(--border-2);background:rgba(255,255,255,0.03);font-weight:600">
                        <td style="padding:8px 12px 8px 8px;font-size:11px;text-transform:uppercase;color:var(--fg-3);letter-spacing:0.04em">YTD Total</td>
                        <td style="padding:8px 12px 8px 0">${cell(t.ytd_gross)}</td>
                        <td style="padding:8px 12px 8px 0">${cell(t.federal)}</td>
                        <td style="padding:8px 12px 8px 0">${cell(t.ss)}</td>
                        <td style="padding:8px 12px 8px 0">${cell(t.medicare)}</td>
                        <td style="padding:8px 12px 8px 0">${cell(t.state)}</td>
                        <td style="padding:8px 12px 8px 0">${cell(t.disability)}</td>
                        <td style="padding:8px 12px 8px 0">${cell(t.retirement_total, true)}</td>
                        <td class="mono-num" style="padding:8px 0 8px 0;color:var(--fg-1)">${this.fmtMoney(t.ytd_net)}</td>
                    </tr>`;

                    const toggleLabel = this.showAllPeriods ? '5 periods' : ('All (' + totalCount + ')');
                    const toggleBtn = totalCount > 5
                        ? `<button id="periodToggleBtn" type="button" class="btn btn-secondary btn-sm">${toggleLabel}</button>`
                        : '';

                    wrap.innerHTML = `
                        <div class="card-base">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                                <div style="display:flex;align-items:center;gap:8px">
                                    <h3 style="font-size:14px;font-weight:600;color:var(--fg-1);margin:0">&#9658; Pay Period Detail</h3>
                                    <span class="badge neutral">${totalCount} period${totalCount !== 1 ? 's' : ''}</span>
                                </div>
                                ${toggleBtn}
                            </div>
                            <div class="table-wrap">
                                <table style="width:100%;border-collapse:collapse;min-width:720px">
                                    <thead><tr style="border-bottom:1px solid var(--border-1);background:rgba(255,255,255,0.03)">
                                        <th style="padding:8px 12px 8px 8px;font-size:11px;text-transform:uppercase;color:var(--fg-3);font-weight:500;text-align:left">Check Date</th>
                                        <th style="padding:8px 12px 8px 0;font-size:11px;text-transform:uppercase;color:var(--fg-3);font-weight:500;text-align:left">Gross</th>
                                        <th style="padding:8px 12px 8px 0;font-size:11px;text-transform:uppercase;color:var(--fg-3);font-weight:500;text-align:left">Federal</th>
                                        <th style="padding:8px 12px 8px 0;font-size:11px;text-transform:uppercase;color:var(--fg-3);font-weight:500;text-align:left">Soc Sec</th>
                                        <th style="padding:8px 12px 8px 0;font-size:11px;text-transform:uppercase;color:var(--fg-3);font-weight:500;text-align:left">Medicare</th>
                                        <th style="padding:8px 12px 8px 0;font-size:11px;text-transform:uppercase;color:var(--fg-3);font-weight:500;text-align:left">State</th>
                                        <th style="padding:8px 12px 8px 0;font-size:11px;text-transform:uppercase;color:var(--fg-3);font-weight:500;text-align:left">Disability</th>
                                        <th style="padding:8px 12px 8px 0;font-size:11px;text-transform:uppercase;color:var(--fg-3);font-weight:500;text-align:left">401k</th>
                                        <th style="padding:8px 0 8px 0;font-size:11px;text-transform:uppercase;color:var(--fg-3);font-weight:500;text-align:left">Net</th>
                                    </tr></thead>
                                    <tbody>
                                        ${rowsHtml || '<tr><td colspan="9" style="padding:16px 8px;color:var(--fg-3)">No payroll data available yet</td></tr>'}
                                        ${footerHtml}
                                    </tbody>
                                </table>
                            </div>
                        </div>`;

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
                    body.innerHTML = (data.perAm || []).map(r => `<tr>
                        <td style="color:var(--fg-1)">${escapeHtml(r.name)}</td>
                        <td class="mono-num">${fmtNum(r.ytd_net)}</td>
                        <td class="mono-num">${fmtNum(r.ytd_gross)}</td>
                        <td class="mono-num">${Number(r.pct_of_total_net).toFixed(1)}%</td>
                    </tr>`).join('') || '<tr><td colspan="4" style="color:var(--fg-3)">No data</td></tr>';
                },
                async loadMappingsTable() {
                    const res = await fetch('/payroll/api/mappings', { headers: { Accept: 'application/json' } });
                    const body = document.getElementById('payrollMappingsBody');
                    if (!res.ok || !body) return;
                    const data = await res.json();
                    const rows = data.mappings || [];
                    if (!rows.length) { body.innerHTML = '<tr><td colspan="4" style="color:var(--fg-3)">No unresolved names</td></tr>'; return; }
                    const consultants = @json($consultants->map(fn ($c) => ['id' => $c->id, 'name' => $c->full_name])->values());
                    body.innerHTML = rows.map(m => `<tr>
                        <td class="mono-num">${escapeHtml(m.raw_name)}</td>
                        <td>${escapeHtml(m.user_name || '')}</td>
                        <td>
                            <select class="field-control payroll-map-select" style="font-size:12px;padding:8px 10px;" data-raw="${escapeHtml(m.raw_name)}" data-uid="${m.user_id}">
                                <option value="">—</option>
                                ${consultants.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('')}
                            </select>
                        </td>
                        <td>
                            <button type="button" class="btn btn-primary btn-sm payroll-map-save">Save</button>
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
