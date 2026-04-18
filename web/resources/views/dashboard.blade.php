<x-app-layout>
    <div x-data="dashboardPage()" x-init="init()" class="stack">

        {{-- ── Page heading ──────────────────────────────────────────── --}}
        <div class="row-between">
            <div>
                <div class="eyebrow">Overview &middot; {{ now()->format('F Y') }}</div>
                <div style="font-size:22px;font-weight:700;letter-spacing:-0.01em;margin-top:4px;">Dashboard</div>
            </div>
        </div>

        {{-- ── KPI stat cards ──────────────────────────────────────── --}}
        <div class="grid-4">
            <div class="kpi-card">
                <div class="kpi-head">
                    <div>
                        <div class="kpi-label">Active Consultants</div>
                        <div class="kpi-value" x-text="stats.activeConsultants ?? '—'"></div>
                        <div class="kpi-sub">Placed &amp; active</div>
                    </div>
                    <div class="kpi-chip">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-head">
                    <div>
                        <div class="kpi-label">Active Clients</div>
                        <div class="kpi-value" x-text="stats.activeClients ?? '—'"></div>
                        <div class="kpi-sub">Billed this month</div>
                    </div>
                    <div class="kpi-chip">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                    </div>
                </div>
            </div>
            <div class="kpi-card warn">
                <div class="kpi-head">
                    <div>
                        <div class="kpi-label">Pending Invoices</div>
                        <div class="kpi-value" x-text="stats.pendingInvoicesCount ?? '—'"></div>
                        <div class="kpi-sub" x-show="stats.pendingInvoicesAmount > 0" x-text="'$' + Number(stats.pendingInvoicesAmount).toFixed(2) + ' due'"></div>
                        <div class="kpi-sub" x-show="!stats.pendingInvoicesAmount || stats.pendingInvoicesAmount <= 0">Awaiting payment</div>
                    </div>
                    <div class="kpi-chip">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    </div>
                </div>
            </div>
            <div class="kpi-card good">
                <div class="kpi-head">
                    <div>
                        <div class="kpi-label">MTD Revenue</div>
                        <div class="kpi-value" x-text="stats.mtdRevenue != null ? '$' + Number(stats.mtdRevenue).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '—'"></div>
                        <div class="kpi-sub">Month to date</div>
                    </div>
                    <div class="kpi-chip">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── End-Date Alerts ─────────────────────────────────────── --}}
        <div class="card-base" x-show="alerts.length > 0" x-cloak>
            <div class="row-between" style="margin-bottom:16px;">
                <div>
                    <h3 style="font-size:15px;font-weight:700;letter-spacing:-0.005em;">End-Date Alerts <span style="color:var(--fg-3);font-weight:400;" x-text="'(' + alerts.length + ')'"></span></h3>
                    <div style="font-size:12px;color:var(--fg-3);margin-top:3px;">Consultants with projects ending in the next 30 days</div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <span class="badge bad"><span class="dot" style="background:var(--danger-500)"></span>Critical ≤7d</span>
                    <span class="badge warn"><span class="dot" style="background:var(--warn-500)"></span>Warning ≤14d</span>
                    <span class="badge info"><span class="dot" style="background:var(--info-500)"></span>Notice ≤30d</span>
                </div>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Consultant</th>
                        <th>Client</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="a in alerts" :key="a.id">
                        <tr>
                            <td style="color:var(--fg-1);font-weight:500;" x-text="a.full_name"></td>
                            <td x-text="a.client_name ?? 'Unassigned'"></td>
                            <td class="mono-dim" x-text="a.project_end_date"></td>
                            <td>
                                <span
                                    class="badge"
                                    :class="a.daysLeft <= 7 ? 'bad' : a.daysLeft <= 14 ? 'warn' : 'info'"
                                >
                                    <span class="dot" :style="a.daysLeft <= 7 ? 'background:var(--danger-500)' : a.daysLeft <= 14 ? 'background:var(--warn-500)' : 'background:var(--info-500)'"></span>
                                    <span x-text="a.daysLeft <= 0 ? Math.abs(a.daysLeft) + 'd overdue' : a.daysLeft + 'd left'"></span>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div x-data="{ extending: false, newDate: '' }">
                                    <button
                                        type="button"
                                        x-show="!extending"
                                        class="btn btn-ghost btn-sm"
                                        x-on:click="extending = true; newDate = a.project_end_date"
                                    >Extend →</button>
                                    <span x-show="extending" style="display:inline-flex;align-items:center;gap:6px;">
                                        <input type="date" x-model="newDate" style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:6px;padding:4px 8px;font-size:12px;color:var(--fg-1);outline:none;" />
                                        <button type="button" class="btn btn-primary btn-sm" x-on:click="extendDate(a.id, newDate); extending = false">Save</button>
                                        <button type="button" class="btn btn-ghost btn-sm" x-on:click="extending = false">Cancel</button>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- ── Budget Utilization (admin only) ─────────────────────── --}}
        @if(auth()->user()->role === 'admin')
        <div class="card-base" x-show="budgets.length > 0" x-cloak>
            <h3 style="font-size:15px;font-weight:700;letter-spacing:-0.005em;margin-bottom:16px;">Budget Utilization</h3>
            <template x-for="b in budgets" :key="b.client_id">
                <div style="margin-bottom:16px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <span style="font-size:13px;font-weight:500;color:var(--fg-1);" x-text="b.client_name"></span>
                        <span style="font-size:11px;font-family:var(--font-mono);color:var(--fg-3);">
                            $<span x-text="Number(b.spent).toLocaleString()"></span>
                            / $<span x-text="Number(b.budget).toLocaleString()"></span>
                        </span>
                    </div>
                    <div style="height:6px;width:100%;border-radius:999px;background:var(--bg-5);">
                        <div
                            style="height:6px;border-radius:999px;transition:width 0.3s;"
                            :style="'width:' + Math.min(b.pct, 100) + '%;background:' + (b.pct >= 100 ? 'var(--danger-500)' : b.pct >= 80 ? 'var(--warn-500)' : 'var(--success-500)')"
                        ></div>
                    </div>
                </div>
            </template>
        </div>
        @endif

        {{-- ── Call Activity (admin only) ───────────────────────────── --}}
        @if(auth()->user()->role === 'admin')
        <div class="card-base">
            <div class="row-between" style="margin-bottom:16px;">
                <h3 style="font-size:15px;font-weight:700;letter-spacing:-0.005em;">Call Activity</h3>
                <div style="display:flex;border:1px solid var(--border-2);border-radius:var(--radius-md);overflow:hidden;">
                    <template x-for="p in [{v:'week',l:'This Week'},{v:'month',l:'This Month'},{v:'quarter',l:'This Quarter'},{v:'year',l:'This Year'}]" :key="p.v">
                        <button
                            type="button"
                            style="padding:6px 12px;font-size:12px;font-weight:600;font-family:var(--font-sans);transition:background 120ms,color 120ms;border:none;cursor:pointer;"
                            :style="callsPeriod === p.v ? 'background:var(--accent-400);color:var(--fg-on-accent);' : 'background:transparent;color:var(--fg-3);'"
                            x-on:click="callsPeriod = p.v; loadCalls()"
                            x-text="p.l"
                        ></button>
                    </template>
                </div>
            </div>

            <div x-show="calls && calls.team.total_dials === 0" style="padding:32px 0;text-align:center;font-size:13px;color:var(--fg-4);">
                No call data for this period.
            </div>

            <div x-show="calls && calls.team.total_dials > 0" x-cloak>
                {{-- Team summary mini-cards --}}
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
                    <div style="background:rgba(34,211,238,0.08);border:1px solid rgba(34,211,238,0.18);border-radius:var(--radius-md);padding:12px;text-align:center;">
                        <div class="eyebrow" style="color:var(--accent-300);">Total Dials</div>
                        <div style="font-size:28px;font-weight:700;font-family:var(--font-display);color:var(--fg-1);margin-top:4px;" x-text="calls.team.total_dials"></div>
                        <div style="font-size:11px;color:var(--fg-3);font-family:var(--font-mono);margin-top:2px;" x-text="calls.team.avg_dials_per_day + ' / day avg'"></div>
                    </div>
                    <div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.18);border-radius:var(--radius-md);padding:12px;text-align:center;">
                        <div class="eyebrow" style="color:var(--success-400);">Total Connects</div>
                        <div style="font-size:28px;font-weight:700;font-family:var(--font-display);color:var(--fg-1);margin-top:4px;" x-text="calls.team.total_connects"></div>
                        <div style="font-size:11px;color:var(--fg-3);font-family:var(--font-mono);margin-top:2px;" x-text="calls.team.active_ams + ' AMs reporting'"></div>
                    </div>
                    <div style="background:rgba(139,92,246,0.08);border:1px solid rgba(139,92,246,0.18);border-radius:var(--radius-md);padding:12px;text-align:center;">
                        <div class="eyebrow" style="color:#C4B5FD;">Connect Rate</div>
                        <div style="font-size:28px;font-weight:700;font-family:var(--font-display);color:var(--fg-1);margin-top:4px;" x-text="calls.team.connect_rate + '%'"></div>
                        <div style="font-size:11px;color:var(--fg-3);margin-top:2px;">live calls / dials</div>
                    </div>
                    <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.18);border-radius:var(--radius-md);padding:12px;text-align:center;">
                        <div class="eyebrow" style="color:var(--warn-400);">Submittals</div>
                        <div style="font-size:28px;font-weight:700;font-family:var(--font-display);color:var(--fg-1);margin-top:4px;" x-text="calls.team.total_submittals"></div>
                        <div style="font-size:11px;color:var(--fg-3);font-family:var(--font-mono);margin-top:2px;" x-text="calls.team.total_interviews + ' interviews'"></div>
                    </div>
                </div>

                {{-- Trend chart + Leaderboard --}}
                <div style="display:grid;grid-template-columns:3fr 2fr;gap:20px;margin-bottom:20px;">
                    <div>
                        <div class="eyebrow" style="margin-bottom:8px;">Dials &amp; Connects Trend</div>
                        <div style="position:relative;height:180px;">
                            <canvas id="callsTrendChart"></canvas>
                        </div>
                    </div>
                    <div>
                        <div class="eyebrow" style="margin-bottom:8px;">Top Performers</div>
                        <div style="display:flex;flex-direction:column;gap:10px;">
                            <template x-for="(am, idx) in calls.by_am.slice(0, 5)" :key="am.name">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span
                                        style="width:20px;height:20px;border-radius:999px;display:grid;place-items:center;font-size:11px;font-weight:700;flex:none;"
                                        :style="idx === 0 ? 'background:var(--warn-400);color:#1a0f00;' : idx === 1 ? 'background:var(--fg-3);color:var(--bg-0);' : idx === 2 ? 'background:var(--brand-300);color:var(--bg-0);' : 'background:var(--bg-5);color:var(--fg-3);'"
                                        x-text="idx + 1"
                                    ></span>
                                    <div style="flex:1;min-width:0;">
                                        <div style="display:flex;align-items:center;justify-content:space-between;">
                                            <span style="font-size:13px;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" x-text="am.name.split(' ')[0]"></span>
                                            <span style="margin-left:8px;font-size:11px;font-weight:600;color:var(--accent-300);font-family:var(--font-mono);" x-text="am.dials + ' dials'"></span>
                                        </div>
                                        <div style="margin-top:4px;height:4px;width:100%;border-radius:999px;background:var(--bg-5);">
                                            <div
                                                style="height:4px;border-radius:999px;background:var(--accent-400);"
                                                :style="'width:' + (calls.by_am[0].dials > 0 ? (am.dials / calls.by_am[0].dials * 100) : 0) + '%'"
                                            ></div>
                                        </div>
                                    </div>
                                    <span style="font-size:11px;font-weight:600;color:var(--success-400);font-family:var(--font-mono);" x-text="am.connect_rate + '%'"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- AM breakdown table --}}
                <div>
                    <div class="eyebrow" style="margin-bottom:8px;">AM Breakdown</div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Account Manager</th>
                                <th style="text-align:right;">Dials</th>
                                <th style="text-align:right;">Connects</th>
                                <th style="text-align:right;">Connect %</th>
                                <th style="text-align:right;">Submittals</th>
                                <th style="text-align:right;">Interviews</th>
                                <th style="text-align:right;">Avg/Day</th>
                                <th style="text-align:right;">Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="am in calls.by_am" :key="am.name">
                                <tr>
                                    <td style="color:var(--fg-1);font-weight:500;" x-text="am.name"></td>
                                    <td class="mono-num" style="text-align:right;color:var(--accent-300);" x-text="am.dials"></td>
                                    <td class="mono-num" style="text-align:right;color:var(--success-400);" x-text="am.connects"></td>
                                    <td style="text-align:right;">
                                        <span
                                            class="badge"
                                            :class="am.connect_rate >= 30 ? 'ok' : am.connect_rate >= 15 ? 'warn' : 'bad'"
                                            x-text="am.connect_rate + '%'"
                                        ></span>
                                    </td>
                                    <td class="mono-dim" style="text-align:right;" x-text="am.submittals"></td>
                                    <td class="mono-dim" style="text-align:right;" x-text="am.interviews"></td>
                                    <td class="mono-dim" style="text-align:right;" x-text="am.avg_dials_per_day"></td>
                                    <td class="mono-dim" style="text-align:right;" x-text="am.days_reported"></td>
                                </tr>
                            </template>
                            <tr style="border-top:1px solid var(--border-3);background:var(--bg-4);">
                                <td style="font-weight:600;color:var(--fg-1);">Team Total</td>
                                <td class="mono-num" style="text-align:right;color:var(--accent-300);font-weight:700;" x-text="calls.team.total_dials"></td>
                                <td class="mono-num" style="text-align:right;color:var(--success-400);font-weight:700;" x-text="calls.team.total_connects"></td>
                                <td style="text-align:right;">
                                    <span
                                        class="badge"
                                        :class="calls.team.connect_rate >= 30 ? 'ok' : calls.team.connect_rate >= 15 ? 'warn' : 'bad'"
                                        x-text="calls.team.connect_rate + '%'"
                                    ></span>
                                </td>
                                <td class="mono-dim" style="text-align:right;" x-text="calls.team.total_submittals"></td>
                                <td class="mono-dim" style="text-align:right;" x-text="calls.team.total_interviews"></td>
                                <td class="mono-dim" style="text-align:right;" x-text="calls.team.avg_dials_per_day"></td>
                                <td class="mono-dim" style="text-align:right;" x-text="calls.team.days_with_data"></td>
                            </tr>
                        </tbody>
                    </table>
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
                                    borderColor: '#22d3ee',
                                    backgroundColor: 'rgba(34,211,238,0.08)',
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
                            plugins: {
                                legend: { position: 'top', labels: { boxWidth: 10, font: { size: 11 }, color: '#8691aa' } },
                            },
                            scales: {
                                x: { ticks: { font: { size: 10 }, maxRotation: 45, color: '#8691aa' }, grid: { color: 'rgba(255,255,255,0.04)' } },
                                y: { beginAtZero: true, ticks: { font: { size: 10 }, color: '#8691aa' }, grid: { color: 'rgba(255,255,255,0.04)' } },
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
