<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold" style="color:var(--fg-1)">Reports &amp; budgets</h2>
    </x-slot>

    <div x-data="reportsPage()" x-init="loadBudget()" class="stack">
        <div class="flex flex-wrap items-center gap-3">
            <label class="field" style="min-width:140px;">
                <span class="eyebrow">Fiscal Year</span>
                <select x-model.number="year" @change="loadBudget()">
                @for ($y = (int) date('Y') - 2; $y <= (int) date('Y') + 1; $y++)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
                </select>
            </label>
        </div>

        <div class="card-base">
            <h3 style="color:var(--brand-300);">Year-end summary</h3>
            <p style="margin-top:6px;font-size:13px;color:var(--fg-3);">PDF with revenue by client and consultant for the selected year.</p>
            <button type="button" @click="generateYearEnd()" :disabled="loadingYearEnd"
                class="btn btn-danger" style="margin-top:12px;">
                <span x-show="!loadingYearEnd">Generate <span x-text="year"></span> PDF</span>
                <span x-show="loadingYearEnd">Generating…</span>
            </button>
        </div>

        <div class="card-base">
            <h3 style="color:var(--success-400);">QuickBooks CSV export</h3>
            <p class="flash-banner flash-warn" style="margin-top:10px;">
                Account names in the CSV must match your QuickBooks chart of accounts.
            </p>
            <button type="button" @click="exportQuickbooks()" :disabled="loadingQB"
                class="btn btn-primary" style="margin-top:12px;">
                <span x-show="!loadingQB">Export <span x-text="year"></span> QuickBooks CSV</span>
                <span x-show="loadingQB">Exporting…</span>
            </button>
        </div>

        <div class="card-base">
            <h3 style="color:var(--accent-300);">Monthly report</h3>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <select x-model.number="month">
                    @foreach (['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as $i => $m)
                        <option value="{{ $i + 1 }}">{{ $m }}</option>
                    @endforeach
                </select>
                <button type="button" @click="previewMonthly()" class="btn btn-primary btn-sm">Preview PDF</button>
                <button type="button" @click="downloadMonthlyCsv()" class="btn btn-secondary btn-sm">Download CSV</button>
            </div>
        </div>

        <div class="card-base">
            <h3>FY budgets</h3>
            <p style="margin-top:6px;font-size:13px;color:var(--fg-3);">BridgeBio vs other spend for the fiscal year selected above.</p>
            <div class="mt-4 space-y-4">
                <template x-if="budgetLoading">
                    <p class="text-sm text-gray-500">Loading…</p>
                </template>
                <template x-if="!budgetLoading && budget">
                    <div class="space-y-4 text-sm">
                        <div>
                            <div class="flex justify-between text-gray-700">
                                <span x-text="budget.bridgebio?.clientName || 'BridgeBio'"></span>
                                <span class="text-xs text-gray-500">
                                    $<span x-text="Number(budget.bridgebio?.spent || 0).toLocaleString()"></span>
                                    / $<span x-text="Number(budget.bridgebio?.budget || 0).toLocaleString()"></span>
                                </span>
                            </div>
                            <div class="mt-1 h-2 w-full rounded-full bg-gray-100">
                                <div class="h-2 rounded-full transition-all"
                                    :class="budgetBarColor(budget.bridgebio?.pct)"
                                    :style="'width:' + Math.min(Number(budget.bridgebio?.pct || 0), 100) + '%'"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-gray-700">
                                <span>All other</span>
                                <span class="text-xs text-gray-500">
                                    $<span x-text="Number(budget.other?.spent || 0).toLocaleString()"></span>
                                    / $<span x-text="Number(budget.other?.budget || 0).toLocaleString()"></span>
                                </span>
                            </div>
                            <div class="mt-1 h-2 w-full rounded-full bg-gray-100">
                                <div class="h-2 rounded-full transition-all"
                                    :class="budgetBarColor(budget.other?.pct)"
                                    :style="'width:' + Math.min(Number(budget.other?.pct || 0), 100) + '%'"></div>
                            </div>
                        </div>
                        @can('admin')
                            <form @submit.prevent="saveBudgets()" class="grid gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2">
                                <label class="text-xs text-gray-600">BridgeBio client ID
                                    <input type="text" x-model="budgetForm.bridgebioClientId" class="mt-1 w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                                </label>
                                <label class="text-xs text-gray-600">BridgeBio budget ($)
                                    <input type="number" step="0.01" min="0" x-model.number="budgetForm.bridgebioBudget" class="mt-1 w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                                </label>
                                <label class="text-xs text-gray-600 sm:col-span-2">Other budget ($)
                                    <input type="number" step="0.01" min="0" x-model.number="budgetForm.otherBudget" class="mt-1 w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                                </label>
                                <button type="submit" :disabled="budgetSaving"
                                    class="btn btn-primary sm:col-span-2">
                                    Save FY budgets
                                </button>
                            </form>
                        @endcan
                    </div>
                </template>
            </div>
        </div>

        <div x-show="pdfUrl" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            @keydown.escape.window="closePdf()">
            <div class="card-base" style="display:flex;flex-direction:column;max-height:90vh;width:100%;max-width:900px">
                <div class="flex items-center justify-between border-b p-3">
                    <h3 class="font-semibold">PDF preview</h3>
                    <div class="flex gap-2">
                        <a :href="pdfUrl" download class="btn btn-primary btn-sm">Download</a>
                        <button type="button" @click="closePdf()" class="btn btn-ghost btn-sm">✕</button>
                    </div>
                </div>
                <iframe :src="pdfUrl" class="w-full flex-1 border-0" title="PDF preview"></iframe>
            </div>
        </div>
    </div>

    <script>
        function reportsPage() {
            return {
                year: {{ (int) date('Y') }},
                month: {{ (int) date('n') }},
                pdfUrl: '',
                loadingYearEnd: false,
                loadingQB: false,
                budget: null,
                budgetLoading: true,
                budgetSaving: false,
                budgetForm: { bridgebioClientId: '', bridgebioBudget: 0, otherBudget: 0 },
                budgetBarColor(pct) {
                    const p = Number(pct || 0);
                    if (p >= 100) return 'bg-red-500';
                    if (p >= 80) return 'bg-orange-500';
                    if (p >= 70) return 'bg-yellow-500';
                    return 'bg-green-500';
                },
                async loadBudget() {
                    this.budgetLoading = true;
                    try {
                        const res = await apiFetch(`{{ url('/budget') }}/${this.year}`);
                        this.budget = await res.json();
                        if (this.budget?.bridgebio) {
                            this.budgetForm.bridgebioClientId = String(this.budget.bridgebio.clientId ?? '');
                            this.budgetForm.bridgebioBudget = Number(this.budget.bridgebio.budget ?? 0);
                            this.budgetForm.otherBudget = Number(this.budget.other?.budget ?? 0);
                        }
                    } catch (e) {
                        this.budget = null;
                    } finally {
                        this.budgetLoading = false;
                    }
                },
                async saveBudgets() {
                    this.budgetSaving = true;
                    const res = await apiFetch(`{{ url('/budget') }}/${this.year}`, {
                        method: 'PUT',
                        body: JSON.stringify({
                            bridgebioClientId: this.budgetForm.bridgebioClientId,
                            bridgebioBudget: this.budgetForm.bridgebioBudget,
                            otherBudget: this.budgetForm.otherBudget,
                        }),
                    });
                    this.budgetSaving = false;
                    if (res.ok) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Budgets saved' } }));
                        await this.loadBudget();
                    } else {
                        const j = await res.json().catch(() => ({}));
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: j.error || 'Save failed', type: 'error' } }));
                    }
                },
                closePdf() {
                    if (this.pdfUrl) URL.revokeObjectURL(this.pdfUrl);
                    this.pdfUrl = '';
                },
                async generateYearEnd() {
                    this.loadingYearEnd = true;
                    try {
                        const res = await apiFetch(`{{ url('/reports/year-end') }}?year=${this.year}`, {
                            headers: { Accept: 'application/pdf' },
                        });
                        const blob = await res.blob();
                        if (this.pdfUrl) URL.revokeObjectURL(this.pdfUrl);
                        this.pdfUrl = URL.createObjectURL(blob);
                    } finally {
                        this.loadingYearEnd = false;
                    }
                },
                exportQuickbooks() {
                    this.loadingQB = true;
                    window.location = `{{ url('/reports/quickbooks') }}?year=${this.year}`;
                    this.loadingQB = false;
                },
                async previewMonthly() {
                    const res = await apiFetch(`{{ url('/reports/monthly') }}?year=${this.year}&month=${this.month}`, {
                        headers: { Accept: 'application/pdf' },
                    });
                    const blob = await res.blob();
                    if (this.pdfUrl) URL.revokeObjectURL(this.pdfUrl);
                    this.pdfUrl = URL.createObjectURL(blob);
                },
                downloadMonthlyCsv() {
                    window.location = `{{ route('reports.monthly-csv') }}?year=${this.year}&month=${this.month}`;
                },
            };
        }
    </script>
</x-app-layout>
