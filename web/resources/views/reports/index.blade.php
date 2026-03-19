<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Reports</h2>
    </x-slot>

    <div x-data="reportsPage()" class="space-y-6">
        <div class="flex flex-wrap items-center gap-3">
            <label class="text-sm font-semibold text-gray-700">Fiscal year</label>
            <select x-model.number="year" class="rounded border border-gray-300 px-2 py-1.5 text-sm">
                @for ($y = (int) date('Y') - 2; $y <= (int) date('Y') + 1; $y++)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>
        </div>

        <div class="rounded-lg bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-red-700">Year-end summary</h3>
            <p class="mt-1 text-sm text-gray-500">PDF with revenue by client / consultant for the selected year.</p>
            <button type="button" @click="generateYearEnd()" :disabled="loadingYearEnd"
                class="mt-3 rounded bg-red-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                <span x-show="!loadingYearEnd">Generate <span x-text="year"></span> PDF</span>
                <span x-show="loadingYearEnd">Generating…</span>
            </button>
        </div>

        <div class="rounded-lg bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-green-700">QuickBooks CSV export</h3>
            <p class="mt-2 rounded bg-yellow-50 px-3 py-2 text-xs text-yellow-800">
                Account names in the CSV must match your QuickBooks chart of accounts.
            </p>
            <button type="button" @click="exportQuickbooks()" :disabled="loadingQB"
                class="mt-3 rounded bg-green-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                <span x-show="!loadingQB">Export <span x-text="year"></span> QuickBooks CSV</span>
                <span x-show="loadingQB">Exporting…</span>
            </button>
        </div>

        <div class="rounded-lg bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-blue-700">Monthly report</h3>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <select x-model.number="month" class="rounded border border-gray-300 px-2 py-1.5 text-sm">
                    @foreach (['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as $i => $m)
                        <option value="{{ $i + 1 }}">{{ $m }}</option>
                    @endforeach
                </select>
                <button type="button" @click="previewMonthly()"
                    class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white">Preview PDF</button>
                <button type="button" @click="downloadMonthlyCsv()"
                    class="rounded bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-800">Download CSV</button>
            </div>
        </div>

        <div x-show="pdfUrl" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            @keydown.escape.window="closePdf()">
            <div class="flex h-[90vh] w-full max-w-5xl flex-col rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b p-3">
                    <h3 class="font-semibold">PDF preview</h3>
                    <div class="flex gap-2">
                        <a :href="pdfUrl" download class="rounded bg-blue-600 px-3 py-1 text-sm text-white">Download</a>
                        <button type="button" @click="closePdf()" class="text-gray-500 hover:text-gray-800">✕</button>
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
