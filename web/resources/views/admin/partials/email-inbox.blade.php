@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \App\Models\EmailInboxMessage> $inboxMessages */
    /** @var string $inboxSearch */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Consultant>|iterable<int, object> $inboxConsultants */
    $inboxSearch = $inboxSearch ?? '';
    $inboxConsultants = $inboxConsultants ?? collect();
@endphp

<div
    id="email-inbox"
    class="mt-10 scroll-mt-6"
    x-data="emailInboxDrawer()"
    @keydown.escape.window="drawerOpen && closeDrawer()"
>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="mb-4">
                <h3 class="text-lg font-medium text-gray-900">Email inbox</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Messages synced from the ingest mailbox (Microsoft Graph). Configure <code class="rounded bg-gray-100 px-1 text-xs">AZURE_*</code> and
                    <code class="rounded bg-gray-100 px-1 text-xs">INBOUND_MAILBOX_UPN</code> in <code class="text-xs">.env</code>; run
                    <code class="text-xs">php artisan inbound-mail:sync</code> or wait for the scheduler.
                </p>
            </div>

            <form
                method="get"
                action="{{ route('admin.users.index') }}"
                class="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center"
                role="search"
            >
                @if (request()->filled('page'))
                    <input type="hidden" name="page" value="{{ request('page') }}">
                @endif
                <input type="hidden" name="inbox_page" value="1">
                <label class="sr-only" for="inbox_search">Search inbox</label>
                <input
                    id="inbox_search"
                    type="search"
                    name="inbox_search"
                    value="{{ $inboxSearch }}"
                    placeholder="Search by subject, sender, or body…"
                    class="w-full min-w-0 flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:max-w-md"
                    autocomplete="off"
                >
                <div class="flex shrink-0 gap-2">
                    <button
                        type="submit"
                        class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                    >
                        Search
                    </button>
                    @if ($inboxSearch !== '')
                        <a
                            href="{{ route('admin.users.index', request()->except(['inbox_search', 'inbox_page'])) }}"
                            class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                        >
                            Clear
                        </a>
                    @endif
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">From</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">Title</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">Preview</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">Attachments</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">Status</th>
                            <th class="px-3 py-2 text-right font-semibold text-gray-700">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($inboxMessages as $msg)
                            <tr data-inbox-row="{{ $msg->id }}">
                                <td class="px-3 py-2 align-top text-gray-900">
                                    <div class="font-medium">{{ $msg->from_name ?? '—' }}</div>
                                    @if ($msg->from_email)
                                        <div class="text-xs text-gray-500">{{ $msg->from_email }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 align-top text-gray-800">{{ $msg->subject ?? '—' }}</td>
                                <td class="max-w-xs px-3 py-2 align-top text-gray-600">
                                    <p class="line-clamp-2 text-xs">{{ $msg->body_preview }}</p>
                                </td>
                                <td class="px-3 py-2 align-top text-gray-700">
                                    @if ($msg->attachments->isEmpty())
                                        <span class="text-gray-400">—</span>
                                    @else
                                        <ul class="space-y-0.5 text-xs">
                                            @foreach ($msg->attachments as $att)
                                                <li class="flex items-center gap-1">
                                                    <span class="text-gray-400" aria-hidden="true">📎</span>
                                                    <span class="truncate" title="{{ $att->filename }}">{{ $att->filename }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <span
                                        data-inbox-badge
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $msg->status === 'read' ? 'bg-gray-100 text-gray-600' : ($msg->status === 'new' ? 'bg-blue-50 text-blue-800' : 'bg-gray-100 text-gray-700') }}"
                                    >
                                        {{ $msg->status }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 align-top text-right">
                                    <button
                                        type="button"
                                        class="text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline"
                                        @click="openView({{ $msg->id }})"
                                    >
                                        View
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-8 text-center text-gray-500">
                                    @if ($inboxSearch !== '')
                                        No messages match your search.
                                    @else
                                        No messages yet. After Graph is configured, run <code class="text-xs">php artisan inbound-mail:sync</code>.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($inboxMessages->hasPages())
                <div class="mt-4">
                    {{ $inboxMessages->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Backdrop + right drawer --}}
    <div
        x-show="drawerOpen"
        x-cloak
        class="fixed inset-0 z-40"
        aria-modal="true"
        role="dialog"
    >
        <div
            class="absolute inset-0 bg-black/50"
            @click="closeDrawer()"
        ></div>
        <div
            x-show="drawerOpen"
            x-transition:enter="transform transition ease-out duration-200"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="absolute inset-y-0 right-0 z-50 flex w-full max-w-xl flex-col bg-white shadow-xl"
            @click.stop
        >
            <div class="flex items-start justify-between border-b border-gray-200 px-4 py-3">
                <h4 class="pr-8 text-base font-semibold text-gray-900" x-text="detail && detail.subject ? detail.subject : 'Message'"></h4>
                <button
                    type="button"
                    class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                    @click="closeDrawer()"
                    aria-label="Close"
                >
                    ✕
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 text-sm">
                <template x-if="loading">
                    <p class="text-gray-500">Loading…</p>
                </template>
                <template x-if="!loading && detail && detail.error">
                    <p class="text-red-600">Could not load message.</p>
                </template>
                <template x-if="!loading && detail && !detail.error">
                    <div class="space-y-4">
                        <div class="text-xs text-gray-500">
                            <div><span class="font-medium text-gray-600">From:</span> <span x-text="detail.from_label"></span></div>
                            <div class="mt-1"><span class="font-medium text-gray-600">Mailbox:</span> <span x-text="detail.mailbox_upn"></span></div>
                            <div class="mt-1"><span class="font-medium text-gray-600">Received:</span> <span x-text="detail.received_at || '—'"></span></div>
                        </div>

                        <div class="border-t border-gray-100 pt-3">
                            <template x-if="detail.body_html_sanitized">
                                <div class="prose prose-sm max-w-none text-gray-800" x-html="detail.body_html_sanitized"></div>
                            </template>
                            <template x-if="!detail.body_html_sanitized && detail.body_plain">
                                <pre class="whitespace-pre-wrap break-words font-sans text-gray-800" x-text="detail.body_plain"></pre>
                            </template>
                            <template x-if="!detail.body_html_sanitized && !detail.body_plain">
                                <p class="text-gray-500">No body content.</p>
                            </template>
                        </div>

                        <div class="border-t border-gray-100 pt-3">
                            <h5 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Attachments</h5>
                            <template x-if="!detail.attachments || detail.attachments.length === 0">
                                <p class="text-gray-500">None</p>
                            </template>
                            <template x-if="detail.attachments && detail.attachments.length && consultantsList.length">
                                <div class="mb-3 rounded border border-indigo-100 bg-indigo-50/60 p-3 text-xs">
                                    <label class="mb-1 block font-medium text-gray-800" for="inbox-apply-consultant">Consultant</label>
                                    <select
                                        id="inbox-apply-consultant"
                                        x-model="selectedConsultantId"
                                        class="w-full rounded-md border border-gray-300 bg-white px-2 py-1.5 text-gray-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    >
                                        <option value="">Select consultant…</option>
                                        <template x-for="c in consultantsList" :key="c.id">
                                            <option :value="String(c.id)" x-text="c.full_name"></option>
                                        </template>
                                    </select>
                                    <label class="mt-2 flex cursor-pointer items-start gap-2 text-gray-700">
                                        <input
                                            type="checkbox"
                                            x-model="timesheetOverwrite"
                                            class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        >
                                        <span>When importing a timesheet, overwrite if that pay period already exists</span>
                                    </label>
                                </div>
                            </template>
                            <template x-if="detail.attachments && detail.attachments.length && !consultantsList.length">
                                <p class="mb-2 text-amber-800">Add an active consultant before applying attachments.</p>
                            </template>
                            <p class="mb-2 text-xs text-gray-500">
                                Import timesheet from here works only for the official bi-weekly Excel template (one consultant per file). For CSV or multi-row files, use <a href="{{ route('timesheets.index') }}" class="font-medium text-indigo-600 hover:underline">Timesheets → Upload</a>.
                            </p>
                            <ul class="space-y-3" x-show="detail.attachments && detail.attachments.length">
                                <template x-for="att in (detail.attachments || [])" :key="att.id">
                                    <li class="rounded border border-gray-100 bg-gray-50 px-3 py-2 text-xs">
                                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                            <span class="flex min-w-0 items-center gap-1">
                                                <span class="text-gray-400" aria-hidden="true">📎</span>
                                                <span class="truncate font-medium text-gray-800" x-text="att.filename"></span>
                                            </span>
                                            <a
                                                :href="att.download_url"
                                                class="shrink-0 font-medium text-indigo-600 hover:underline"
                                            >Download</a>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                class="rounded bg-gray-200 px-2 py-1 text-xs font-medium text-gray-800 hover:bg-gray-300 disabled:opacity-50"
                                                :disabled="!att.can_apply_w9 || applyBusy !== null"
                                                x-show="att.can_apply_w9"
                                                @click="applyW9(att)"
                                            >
                                                <span x-show="applyBusy !== att.id">Apply as W-9</span>
                                                <span x-show="applyBusy === att.id">…</span>
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded bg-slate-200 px-2 py-1 text-xs font-medium text-slate-900 hover:bg-slate-300 disabled:opacity-50"
                                                :disabled="!att.can_apply_contract || applyBusy !== null"
                                                x-show="att.can_apply_contract"
                                                @click="applyContract(att)"
                                            >
                                                <span x-show="applyBusy !== att.id">Apply as contract (MSA)</span>
                                                <span x-show="applyBusy === att.id">…</span>
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded bg-indigo-100 px-2 py-1 text-xs font-medium text-indigo-900 hover:bg-indigo-200 disabled:opacity-50"
                                                :disabled="!att.can_apply_timesheet || applyBusy !== null"
                                                x-show="att.can_apply_timesheet"
                                                @click="applyTimesheet(att)"
                                            >
                                                <span x-show="applyBusy !== att.id">Import timesheet</span>
                                                <span x-show="applyBusy === att.id">…</span>
                                            </button>
                                        </div>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
    function emailInboxDrawer() {
        const base = @json(url('/admin/inbox/messages'));
        const consultantsList = @json($inboxConsultants->values());

        return {
            drawerOpen: false,
            loading: false,
            detail: null,
            consultantsList,
            selectedConsultantId: '',
            timesheetOverwrite: false,
            applyBusy: null,
            parseErrorMessage(data) {
                if (!data || typeof data !== 'object') {
                    return 'Request failed';
                }
                if (data.message && typeof data.message === 'string') {
                    return data.message;
                }
                if (data.errors && typeof data.errors === 'object') {
                    const first = Object.values(data.errors).flat()[0];

                    return typeof first === 'string' ? first : 'Request failed';
                }

                return 'Request failed';
            },
            async applyW9(att) {
                if (!this.selectedConsultantId) {
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Select a consultant first.', type: 'error' } }));

                    return;
                }
                if (!att.can_apply_w9) {
                    return;
                }
                this.applyBusy = att.id;
                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                try {
                    const res = await fetch(att.apply_w9_url, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ consultant_id: Number(this.selectedConsultantId) }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: this.parseErrorMessage(data), type: 'error' } }));

                        return;
                    }
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: data.message || 'W-9 saved' } }));
                } finally {
                    this.applyBusy = null;
                }
            },
            async applyContract(att) {
                if (!this.selectedConsultantId) {
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Select a consultant first.', type: 'error' } }));

                    return;
                }
                if (!att.can_apply_contract) {
                    return;
                }
                this.applyBusy = att.id;
                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                try {
                    const res = await fetch(att.apply_contract_url, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ consultant_id: Number(this.selectedConsultantId) }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: this.parseErrorMessage(data), type: 'error' } }));

                        return;
                    }
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: data.message || 'Contract saved' } }));
                } finally {
                    this.applyBusy = null;
                }
            },
            async applyTimesheet(att) {
                if (!this.selectedConsultantId) {
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Select a consultant first.', type: 'error' } }));

                    return;
                }
                if (!att.can_apply_timesheet) {
                    return;
                }
                this.applyBusy = att.id;
                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                try {
                    const res = await fetch(att.apply_timesheet_url, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            consultant_id: Number(this.selectedConsultantId),
                            overwrite: !!this.timesheetOverwrite,
                        }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: this.parseErrorMessage(data), type: 'error' } }));

                        return;
                    }
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: data.message || 'Timesheet imported' } }));
                } finally {
                    this.applyBusy = null;
                }
            },
            badgeClass(status) {
                if (status === 'read') {
                    return 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-600';
                }
                if (status === 'new') {
                    return 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-blue-50 text-blue-800';
                }
                return 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700';
            },
            syncRowStatus(id, status) {
                if (!status) {
                    return;
                }
                const row = document.querySelector('[data-inbox-row="' + id + '"]');
                if (!row) {
                    return;
                }
                const badge = row.querySelector('[data-inbox-badge]');
                if (!badge) {
                    return;
                }
                badge.textContent = status;
                badge.className = this.badgeClass(status);
            },
            async openView(id) {
                this.drawerOpen = true;
                this.loading = true;
                this.detail = null;
                try {
                    const res = await fetch(base + '/' + id + '/json', {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) {
                        throw new Error('load failed');
                    }
                    this.detail = await res.json();
                    if (this.detail.status) {
                        this.syncRowStatus(id, this.detail.status);
                    }
                } catch (e) {
                    this.detail = { error: true };
                } finally {
                    this.loading = false;
                }
            },
            closeDrawer() {
                this.drawerOpen = false;
                this.detail = null;
                this.loading = false;
                this.applyBusy = null;
            },
        };
    }
</script>
