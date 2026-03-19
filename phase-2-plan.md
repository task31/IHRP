# Phase 2 Plan — Frontend Port
_Created: 2026-03-19_
_Mode: PARALLEL (Phase 2a + Phase 2b)_

> **For agentic workers:** Read `phase-1-plan.md` and `CLAUDE.md` first. All controllers from Phase 1
> already exist and return JSON. Phase 2 is **pure frontend** — Blade views + Alpine.js + Livewire for
> timesheets. No new business logic. See CLAUDE.md conventions before writing any code.

**Goal:** Port all 8 Electron screens to browser-viewable Blade + Alpine.js pages, with Livewire for
the Timesheets upload wizard.

**Architecture:** Server-side Blade rendering with server-embedded initial data. Modals and in-page
CRUD use Alpine.js `fetch()` calls to the existing JSON API endpoints. Timesheets upload/batch-import
is a Livewire component. No build pipeline — Tailwind CDN and Alpine CDN already in layout.

**Tech Stack:** Laravel Blade, Alpine.js 3.x (CDN), Livewire 3.x (installed), Tailwind CDN,
barryvdh/laravel-dompdf (already installed), existing Blade component library in `resources/views/components/`.

---

## Context

Phase 1 delivered 13 controllers that all return JSON. Phase 2 adds Blade views so each page
is accessible via a browser URL. The controllers are updated with a one-liner to dual-respond:
JSON for AJAX, view for browser — no routes change, no business logic changes.

Carry-forward items from Phase 1 review to embed in Phase 2 tasks:
- `BudgetController::alerts()` — add audit log for flag writes (Budget page, Step 7)
- `ReportController::saveCsv()` — replace generic row passthrough with server-driven query (Reports, Step 7)
- `timesheets.source_file_path` — populate on upload in TimesheetController (Step 6)
- Add comment to `DashboardController` explaining `abort_unless` pattern (Step 1)
- Drop `storage/app/templates/timesheet_template.xlsx` placeholder so template download returns 200

## Dependency

**Requires:** Phase 1 complete (all 13 controllers + all routes exist) ✅
**Unlocks:** Phase 3 (employee call-reporting + placements add on top of this frontend)

---

## Source Reference (Electron screens → Blade targets)

| Electron page | Blade view target | Complexity |
|---|---|---|
| `Dashboard.jsx` | `resources/views/dashboard.blade.php` (update stub) | Low |
| `Clients.jsx` | `resources/views/clients/index.blade.php` | Medium |
| `Consultants.jsx` | `resources/views/consultants/index.blade.php` | Medium-High |
| `Timesheets.jsx` (1,445 lines) | `app/Livewire/TimesheetWizard.php` + `timesheets/wizard.blade.php` + `timesheets/index.blade.php` | **Highest** |
| `Invoices.jsx` | `resources/views/invoices/index.blade.php` | Medium |
| `Ledger.jsx` | `resources/views/ledger/index.blade.php` | Medium |
| `Reports.jsx` | `resources/views/reports/index.blade.php` | Medium-High |
| `Settings.jsx` | `resources/views/settings/index.blade.php` | Medium |

---

## Phase Map

```
[Step 0 — Shared Setup]  (do FIRST, shared by both workstreams)
       ↓
[Phase 2a] ──────────────── [Phase 2b]
  Step 1: Dashboard           Step 5: Timesheets (Livewire)
  Step 2: Clients             Step 6: Reports
  Step 3: Consultants         Step 7: Budget + Settings (carry-forwards)
  Step 4: Invoices + Ledger
       ↓                             ↓
[Step 8 — Verification]  (merge both workstreams, full smoke test)
```

---

## Step 0 — Shared Setup (MUST run before 2a or 2b start)

### Files

- Modify: `web/resources/views/layouts/app.blade.php`
- Modify: `web/app/Http/Controllers/DashboardController.php` (carry-forward comment)

### 0a — Fix sidebar navigation

Replace all `href="#"` placeholders with real named routes and `@can` gates.

```blade
{{-- In layouts/app.blade.php sidebar nav, replace placeholders: --}}

<a href="{{ route('dashboard') }}" @class(['...active classes...' => request()->routeIs('dashboard'), '...base classes...'])>
    Dashboard
</a>

@can('account_manager')
<a href="{{ route('clients.index') }}" @class([...])>Clients</a>
<a href="{{ route('consultants.index') }}" @class([...])>Consultants</a>
<a href="{{ route('timesheets.index') }}" @class([...])>Timesheets</a>
<a href="{{ route('invoices.index') }}" @class([...])>Invoices</a>
<a href="{{ route('ledger.index') }}" @class([...])>Ledger</a>
<a href="{{ route('reports.index') }}" @class([...])>Reports</a>
@endcan

@can('admin')
<a href="{{ route('settings.index') }}" @class([...])>Settings</a>
<a href="{{ route('admin.users.index') }}" @class([...])>Admin</a>
@endcan
```

Active link detection: `request()->routeIs('clients.*')` etc.

### 0b — Add Alpine.js toast component to layout

Add a reusable toast notification system to `app.blade.php`. This is used by EVERY page for save confirmations.

```blade
{{-- In app.blade.php, BEFORE </body>: --}}
<div
    x-data="toastManager()"
    x-on:toast.window="add($event.detail)"
    class="fixed bottom-4 right-4 z-50 space-y-2"
>
    <template x-for="t in toasts" :key="t.id">
        <div x-show="t.show" x-transition
             :class="t.type === 'error' ? 'bg-red-600' : 'bg-green-600'"
             class="text-white px-4 py-3 rounded shadow-lg text-sm flex items-center gap-2">
            <span x-text="t.message"></span>
            <button @click="remove(t.id)" class="ml-auto opacity-70 hover:opacity-100">✕</button>
        </div>
    </template>
</div>

<script>
function toastManager() {
    return {
        toasts: [],
        add({ message, type = 'success', duration = 3500 }) {
            const id = Date.now();
            this.toasts.push({ id, message, type, show: true });
            setTimeout(() => this.remove(id), duration);
        },
        remove(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        }
    };
}
// Fire a toast from anywhere: window.dispatchEvent(new CustomEvent('toast', { detail: { message: '...', type: 'success' } }))
</script>

{{-- Also add this to <head> for Alpine x-cloak: --}}
<style>[x-cloak] { display: none !important; }</style>
```

### 0c — Add CSRF meta tag helper

In `app.blade.php` `<head>`:
```blade
<meta name="csrf-token" content="{{ csrf_token() }}">
```

Add a global fetch helper in `<script>` before Alpine loads:
```javascript
function apiFetch(url, options = {}) {
    return fetch(url, {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            ...options.headers,
        },
        ...options,
    });
}
```

### 0d — DashboardController carry-forward comment

In `DashboardController.php`, add above the `abort_unless` line:
```php
// abort_unless used intentionally (not $this->authorize) — employees can access
// this endpoint to see their own stub stats; $this->authorize('account_manager')
// would block them. Remove this comment if employee dashboard is removed in Phase 3.
```

- [ ] **Step 0: Apply all shared setup changes to app.blade.php**
- [ ] **Commit:** `git add web/resources/views/layouts/app.blade.php web/app/Http/Controllers/DashboardController.php && git commit -m "feat: wire sidebar nav, add toast system, csrf helper"`

---

## [Phase 2a] — Table Pages

*Workstream handles: Dashboard, Clients, Consultants, Invoices, Ledger*

---

### Step 1 — Dashboard

**Files:**
- Modify: `web/resources/views/dashboard.blade.php` (currently a stub)
- Modify: `web/app/Http/Controllers/DashboardController.php` (make index return Blade view)

**Controller change:**

The existing `index()` method in `DashboardController` is named `index` but routes to `GET /dashboard/stats`. The Breeze `GET /dashboard` route calls a closure. Swap the closure to the controller.

In `routes/web.php`, change:
```php
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');
```
to:
```php
Route::get('/dashboard', [DashboardController::class, 'page'])
    ->middleware('auth')->name('dashboard');
```

Add `page()` method to `DashboardController`:
```php
public function page(Request $request)
{
    return view('dashboard');
}
```

The Blade view will call `GET /dashboard/stats` via Alpine on mount to populate cards.

**Blade view — `dashboard.blade.php`:**

```blade
<x-app-layout>
    <x-slot name="header"><h2>Dashboard</h2></x-slot>

    <div x-data="dashboardPage()" x-init="loadStats()" class="space-y-6">

        {{-- 4 stat cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg p-5 shadow-sm">
                <p class="text-sm text-gray-500">Active Consultants</p>
                <p class="text-3xl font-bold mt-1" x-text="stats.activeConsultants ?? '—'"></p>
            </div>
            <div class="bg-white rounded-lg p-5 shadow-sm">
                <p class="text-sm text-gray-500">Active Clients</p>
                <p class="text-3xl font-bold mt-1" x-text="stats.activeClients ?? '—'"></p>
            </div>
            <div class="bg-white rounded-lg p-5 shadow-sm">
                <p class="text-sm text-gray-500">Pending Invoices</p>
                <p class="text-3xl font-bold mt-1" x-text="stats.pendingInvoicesCount ?? '—'"></p>
                <p class="text-xs text-gray-400" x-show="stats.pendingInvoicesAmount > 0"
                   x-text="'$' + Number(stats.pendingInvoicesAmount).toFixed(2) + ' due'"></p>
            </div>
            <div class="bg-white rounded-lg p-5 shadow-sm">
                <p class="text-sm text-gray-500">MTD Revenue</p>
                <p class="text-3xl font-bold mt-1"
                   x-text="stats.mtdRevenue != null ? '$' + Number(stats.mtdRevenue).toLocaleString('en-US', {minimumFractionDigits:2}) : '—'"></p>
            </div>
        </div>

        {{-- End-date alerts (loaded from /consultants/end-date-alerts) --}}
        <div class="bg-white rounded-lg shadow-sm p-5" x-show="alerts.length > 0" x-cloak>
            <h3 class="font-semibold mb-3">End-Date Alerts (<span x-text="alerts.length"></span>)</h3>
            {{-- Tier legend --}}
            <div class="flex gap-4 text-xs mb-3">
                <span class="flex items-center gap-1"><span class="w-2 h-2 bg-red-500 rounded-full"></span> Critical (≤7d)</span>
                <span class="flex items-center gap-1"><span class="w-2 h-2 bg-orange-400 rounded-full"></span> Warning (≤14d)</span>
                <span class="flex items-center gap-1"><span class="w-2 h-2 bg-yellow-400 rounded-full"></span> Notice (≤30d)</span>
            </div>
            <table class="w-full text-sm">
                <template x-for="a in alerts" :key="a.id">
                    <tr class="border-t">
                        <td class="py-2 flex items-center gap-2">
                            <span :class="tierColor(a.daysLeft)" class="w-2 h-2 rounded-full inline-block"></span>
                            <span x-text="a.full_name"></span>
                            <span class="text-xs px-1 rounded"
                                  :class="a.daysLeft <= 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'"
                                  x-text="a.daysLeft <= 0 ? Math.abs(a.daysLeft) + 'd overdue' : a.daysLeft + 'd left'"></span>
                        </td>
                        <td x-text="a.client_name ?? 'Unassigned'" class="text-gray-500"></td>
                        <td x-text="a.project_end_date" class="text-gray-500"></td>
                        <td>
                            {{-- Inline extend --}}
                            <div x-data="{ extending: false, newDate: '' }">
                                <button x-show="!extending" @click="extending = true; newDate = a.project_end_date"
                                        class="text-xs text-blue-600 hover:underline">Extend</button>
                                <span x-show="extending" class="flex items-center gap-1">
                                    <input type="date" x-model="newDate" class="text-xs border rounded px-1 py-0.5">
                                    <button @click="extendDate(a.id, newDate); extending = false" class="text-xs text-green-600">Save</button>
                                    <button @click="extending = false" class="text-xs text-gray-400">Cancel</button>
                                </span>
                            </div>
                        </td>
                    </tr>
                </template>
            </table>
        </div>

        {{-- Budget utilization (loaded from /budget) --}}
        <div class="bg-white rounded-lg shadow-sm p-5" x-show="budgets.length > 0" x-cloak>
            <h3 class="font-semibold mb-3">Budget Utilization</h3>
            <template x-for="b in budgets" :key="b.client_id">
                <div class="mb-4">
                    <div class="flex justify-between text-sm mb-1">
                        <span x-text="b.client_name" :class="budgetColor(b.pct)" class="font-medium"></span>
                        <span class="text-gray-500 text-xs">
                            $<span x-text="Number(b.spent).toLocaleString()"></span>
                            / $<span x-text="Number(b.budget).toLocaleString()"></span>
                        </span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2">
                        <div :class="budgetColor(b.pct).replace('text-','bg-')"
                             class="h-2 rounded-full transition-all"
                             :style="'width:' + Math.min(b.pct, 100) + '%'"></div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <script>
    function dashboardPage() {
        return {
            stats: {}, alerts: [], budgets: [],
            async loadStats() {
                const [s, a, b] = await Promise.all([
                    apiFetch('/dashboard/stats').then(r => r.json()),
                    apiFetch('/consultants/end-date-alerts').then(r => r.json()),
                    apiFetch('/budget').then(r => r.json()),
                ]);
                // Compute daysLeft for alerts
                this.stats = s.stub ? {} : s;
                const today = new Date(); today.setHours(0,0,0,0);
                this.alerts = (Array.isArray(a) ? a : []).map(c => ({
                    ...c,
                    daysLeft: c.project_end_date
                        ? Math.round((new Date(c.project_end_date) - today) / 86400000)
                        : null
                })).filter(c => c.daysLeft !== null && c.daysLeft <= 30);
                this.budgets = Array.isArray(b) ? b.filter(x => x.budget > 0) : [];
            },
            async extendDate(id, newDate) {
                await apiFetch(`/consultants/${id}/extend-end-date`, {
                    method: 'PATCH', body: JSON.stringify({ end_date: newDate })
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
```

**BudgetController change needed:** `index()` currently returns only a JSON stub. Update it to return `budget:summary()` formatted data when called via AJAX (`/budget`), which is what dashboard uses. The `BudgetController::index()` should return rows with `client_id, client_name, budget, spent, pct, remaining`.

- [ ] **[Phase 2a] Update DashboardController::page(), update routes/web.php, write dashboard.blade.php**
- [ ] **[Phase 2a] Confirm BudgetController::index() returns usable summary for Alpine fetch**
- [ ] **[Phase 2a] Smoke: GET /dashboard → shows 4 cards, alerts panel loads**
- [ ] **Commit:** `feat: add dashboard Blade view with stats cards and alerts`

---

### Step 2 — Clients

**Files:**
- Create: `web/resources/views/clients/index.blade.php`
- Modify: `web/app/Http/Controllers/ClientController.php` — add Blade branch to `index()`

**Controller change (same pattern used for ALL table pages):**
```php
public function index(Request $request): JsonResponse|View
{
    $this->authorize('account_manager');
    $clients = Client::query()->orderBy('name')->get();

    if ($request->expectsJson()) {
        return response()->json($clients);
    }

    return view('clients.index', ['clients' => $clients]);
}
```

**Blade view — `clients/index.blade.php`:**

Page structure:
- Header slot: "Clients" + "Add Client" button (admin only, `@can('admin')`)
- Sortable table (client-side sort via Alpine — data embedded from PHP, no re-fetch needed)
- Columns: Name | Billing Contact | Email | Terms | Budget | Actions
- Add/Edit modal (Alpine `x-show`, one shared modal for create + edit)
- Deactivate confirmation modal

**Table row structure:**
```blade
@foreach($clients as $client)
<tr>
    <td x-text="c.name">{{ $client->name }}</td>
    <td>{{ $client->billing_contact_name ?? '—' }}</td>
    <td><a href="mailto:{{ $client->email }}">{{ $client->email }}</a></td>
    <td>{{ $client->payment_terms ?? 'Net 30' }}</td>
    <td>
        @if($client->total_budget > 0)
            {{-- Budget progress bar (spent loaded separately) --}}
        @else
            <span class="text-gray-400 text-xs">Not set</span>
        @endif
    </td>
    <td>
        @can('admin')
        <button @click="openEdit({{ $client->id }})">Edit</button>
        <button @click="confirmDeactivate({{ $client->id }})">Deactivate</button>
        @endcan
    </td>
</tr>
@endforeach
```

**Alpine component (embedded in view):**
```javascript
function clientsPage() {
    return {
        showModal: false, isEdit: false, saving: false,
        form: { id:null, name:'', billing_contact_name:'', billing_address:'',
                email:'', smtp_email:'', payment_terms:'Net 30',
                total_budget:'', po_number:'' },
        confirmId: null,

        openCreate() { this.isEdit=false; this.form={name:'',billing_contact_name:'',
            billing_address:'',email:'',smtp_email:'',payment_terms:'Net 30',
            total_budget:'',po_number:''}; this.showModal=true; },

        async openEdit(id) {
            const r = await apiFetch(`/clients/${id}`).then(x=>x.json());
            this.form = r; this.isEdit=true; this.showModal=true;
        },

        async save() {
            this.saving=true;
            const url = this.isEdit ? `/clients/${this.form.id}` : '/clients';
            const method = this.isEdit ? 'PUT' : 'POST';
            const res = await apiFetch(url, { method, body: JSON.stringify(this.form) });
            this.saving=false;
            if (res.ok) {
                window.dispatchEvent(new CustomEvent('toast',{detail:{message:'Saved'}}));
                this.showModal=false;
                window.location.reload();
            } else {
                const e = await res.json();
                window.dispatchEvent(new CustomEvent('toast',{detail:{message:e.message||'Error',type:'error'}}));
            }
        },

        confirmDeactivate(id) { this.confirmId=id; },
        async deactivate() {
            await apiFetch(`/clients/${this.confirmId}`, { method:'DELETE' });
            window.dispatchEvent(new CustomEvent('toast',{detail:{message:'Client deactivated'}}));
            this.confirmId=null;
            window.location.reload();
        },
    };
}
```

**Modal structure (reuse for Add + Edit):**
Fields: Client Name*, Billing Contact, Billing Address (textarea), Billing Email*, SMTP Email*, Payment Terms (select: Net 15 / Net 30 / Net 45 / Net 60 / Due on Receipt), Total Budget, Default PO #

- [ ] **[Phase 2a] Add `expectsJson()` branch to ClientController::index()**
- [ ] **[Phase 2a] Create `resources/views/clients/index.blade.php`**
- [ ] **[Phase 2a] Smoke: GET /clients → table renders, Add/Edit modal opens, save reloads**
- [ ] **Commit:** `feat: add clients Blade view with CRUD modal`

---

### Step 3 — Consultants

**Files:**
- Create: `web/resources/views/consultants/index.blade.php`
- Modify: `web/app/Http/Controllers/ConsultantController.php` — add Blade branch to `index()`

**Controller change:** Same `expectsJson()` pattern. Pass consultants + clients list to view.
```php
return view('consultants.index', [
    'consultants' => Consultant::with('client')->where('active', true)->orderBy('full_name')->get(),
    'clients' => Client::where('active', true)->orderBy('name')->get(['id', 'name']),
]);
```

**Table columns:**
| Column | Notes |
|--------|-------|
| Name | + W-9 badge (green "W-9 ✓" or gray "No W-9") |
| Client | client_name or "Unassigned" |
| State | badge |
| Pay Rate | `$XX.XX/hr` |
| Bill Rate | `$XX.XX/hr` |
| Start Date | MM/DD/YYYY |
| End Date | color-coded: red ≤7d, orange ≤14d, yellow ≤30d |
| Onboarding | `X/7` badge (green if 7/7) |
| Actions | Edit | Checklist | W-9 | Deactivate |

**Add/Edit modal fields:**
- Full Name*
- State* (select, US states)
- Industry Type (select: Other, Manufacturing, Factory, Mill)
- Pay Rate* (number, step 0.01)
- Bill Rate* (number, step 0.01)
- Margin display (live calculated): `((bill - pay) / bill * 100).toFixed(1) + '%'` via Alpine `:textContent`
- Client (select, nullable)
- Project Start Date (date, optional)
- Project End Date (date, optional, must be ≥ start)

**Onboarding checklist modal:**
The 7 items are fixed strings. Fetch from `GET /consultants/{id}/onboarding` → array of `{item, completed, completed_at}`.
Toggle via `PATCH /consultants/{id}/onboarding` with `{item, completed}`.
Progress bar: `width: X/7 * 100%`.

**W-9 upload:**
Use a `<form>` with `enctype="multipart/form-data"` POSTing to `POST /consultants/{id}/w9`.
On success, refresh table row (or page reload). W-9 link in table: if `w9_on_file`, show "View" → `GET /consultants/{id}/w9`.

**Deactivate:** Same pattern as Clients — confirm modal, `PATCH /consultants/{id}/deactivate`.

**End-date color helper (Alpine):**
```javascript
endDateClass(dateStr) {
    if (!dateStr) return '';
    const days = Math.round((new Date(dateStr) - new Date()) / 86400000);
    if (days <= 7) return 'text-red-600 font-semibold';
    if (days <= 14) return 'text-orange-500 font-semibold';
    if (days <= 30) return 'text-yellow-600';
    return 'text-gray-700';
}
```

- [ ] **[Phase 2a] Add `expectsJson()` branch to ConsultantController::index()**
- [ ] **[Phase 2a] Create `resources/views/consultants/index.blade.php`**
- [ ] **[Phase 2a] Smoke: table, add/edit, onboarding modal, deactivate all work**
- [ ] **Commit:** `feat: add consultants Blade view`

---

### Step 4 — Invoices + Ledger

#### 4a — Invoices

**Files:**
- Create: `web/resources/views/invoices/index.blade.php`
- Modify: `web/app/Http/Controllers/InvoiceController.php` — Blade branch in `index()`

**Controller:** Pass initial data + filter lists:
```php
return view('invoices.index', [
    'invoices' => Invoice::with(['consultant:id,full_name','client:id,name'])
                    ->orderByDesc('invoice_date')->get(),
    'clients' => Client::orderBy('name')->get(['id','name']),
    'consultants' => Consultant::where('active',true)->orderBy('full_name')->get(['id','full_name']),
]);
```

**Filter bar (form submit, server-side):**
- Status (All / Pending / Sent / Paid) — `<select name="status">`
- Client (All / ...) — `<select name="clientId">`
- Consultant (All / ...) — `<select name="consultantId">`
- Date From / Date To — `<input type="date">`
- Submit and Clear buttons

Wire filters: on form submit, reload with querystring. In controller, check `$request->input()` to filter.

**Table columns:**
| Invoice # | Date | Due | Consultant | Client | Amount | PO # | Status | Actions |
|-----------|------|-----|------------|--------|--------|------|--------|---------|

**PO # inline edit:** Click to show `<input>` + confirm/cancel. On confirm: `POST /invoices/update-po` with `{invoiceId, poNumber}`.

**Status badge colors:**
- `pending`: `bg-gray-100 text-gray-700`
- `sent`: `bg-blue-100 text-blue-700`
- `paid`: `bg-green-100 text-green-700`

**Actions per invoice:**
- Preview → opens modal with `<iframe src="/invoices/{id}/preview" class="w-full h-[70vh]">` + "Export PDF" button
- Export → `window.location = '/invoices/${id}/export'`
- Send Email → opens send modal
- Mark Sent → `PATCH /invoices/{id}/status` `{status: 'sent'}` (admin only)
- Mark Paid → `PATCH /invoices/{id}/status` `{status: 'paid'}` (admin only)

**Send Email modal fields:**
- To (pre-filled from `client.smtp_email ?? client.email`, editable)
- Subject (pre-filled: `Invoice #[num] — $[amount] due [date]`)
- Notes (textarea, optional)
- "Send" button → `POST /invoices/send` with `{invoiceId, recipientEmail, subject, note}`

#### 4b — Ledger

**Files:**
- Create: `web/resources/views/ledger/index.blade.php`
- Modify: `web/app/Http/Controllers/LedgerController.php` — Blade branch in `index()`

**View toggle (Alpine `activeView: 'detail'|'summary'`):**

**Detail view — table columns:**
Pay Period | Consultant | Client | Reg Hrs | OT Hrs | Consultant Cost | Client Billable | Margin $ | Margin % | Status | Action

Margin % color:
```javascript
marginClass(pct) {
    if (pct < 20) return 'text-red-600';
    if (pct < 30) return 'text-yellow-600';
    return 'text-green-600';
}
```

Footer row: totals + blended margin (computed in PHP before passing to view).

**"Create Invoice" action:** `POST /invoices/generate` with `{timesheetId}`.
On success: reload page (invoice is now visible in Invoices page). Show toast.

**Summary view** — three cards stacked:
1. By Pay Period (Period | # Timesheets | Total Cost | Billable | Margin $ | Margin %)
2. By Consultant
3. By Client

Data for summary: add `GET /ledger?view=summary` branch or pass both datasets to view.

**Filters (server-side form submit):**
- Date from/to, Consultant, Client, Invoice Status

- [ ] **[Phase 2a] Add `expectsJson()` branch to InvoiceController::index() and LedgerController::index()**
- [ ] **[Phase 2a] Create `resources/views/invoices/index.blade.php`**
- [ ] **[Phase 2a] Create `resources/views/ledger/index.blade.php`**
- [ ] **[Phase 2a] Smoke: invoices table renders, PDF preview iframe opens, send modal fires**
- [ ] **[Phase 2a] Smoke: ledger detail view + summary toggle + generate invoice action**
- [ ] **Commit:** `feat: add invoices and ledger Blade views`

---

## [Phase 2b] — Complex Pages

*Workstream handles: Timesheets (Livewire), Reports, Budget, Settings*

---

### Step 5 — Timesheets (Livewire)

This is the hardest page (1,445 lines JSX → Livewire). Treat it as two separate sub-tasks.

**Files:**
- Create: `web/app/Livewire/TimesheetWizard.php`
- Create: `web/resources/views/livewire/timesheet-wizard.blade.php`
- Create: `web/resources/views/timesheets/index.blade.php`
- Modify: `web/app/Http/Controllers/TimesheetController.php` — Blade branch in `index()`
- Fix: `web/app/Services/TimesheetParseService.php` — store uploaded file + populate `source_file_path` (carry-forward)
- Fix: `web/storage/app/templates/timesheet_template.xlsx` — add a minimal placeholder file

#### 5a — Timesheet List Page

**Controller change:**
```php
// TimesheetController::index()
if ($request->expectsJson()) { return response()->json($timesheets); }
return view('timesheets.index', [
    'timesheets' => Timesheet::with(['consultant:id,full_name','client:id,name'])
                    ->orderByDesc('pay_period_start')->get(),
    'consultants' => Consultant::where('active',true)->orderBy('full_name')->get(['id','full_name']),
    'clients' => Client::where('active',true)->orderBy('name')->get(['id','name']),
]);
```

**List view — table columns:**
Pay Period | Consultant | Client | Reg Hrs | OT Hrs | DT Hrs | Status | Actions

Actions: View | Delete (admin only)

Add "Import Timesheet" button → opens `<livewire:timesheet-wizard>` modal.

#### 5b — Livewire Wizard (the complex part)

The wizard has 4 steps:

**Step 1 — Upload file**
```php
// TimesheetWizard.php Livewire component

public $step = 1;
public $file;             // Livewire TemporaryUploadedFile
public $parsedRows = [];  // returned by TimesheetParseService
public $parseErrors = [];

public function uploadFile()
{
    $this->validate(['file' => 'required|file|mimes:xlsx,csv|max:10240']);
    $path = $this->file->getRealPath();
    $result = (new TimesheetParseService)->parse($this->file);
    if (isset($result['error'])) {
        $this->parseErrors = [$result['error']];
        return;
    }
    $this->parsedRows = $result;
    $this->step = 2;
}
```

**Step 2 — Review parsed rows (table preview)**
Display parsed rows: Consultant Name | Client Name | Pay Period | Total Hours | Source
Show validation errors (name not found, duplicate, etc.)
"Back" → step 1, "Import All" → step 3

**Step 3 — Processing / confirm**
Show progress. Call `POST /timesheets/save` with rows.
```php
public function importRows()
{
    $response = Http::withToken(null)
        ->withHeaders(['X-CSRF-TOKEN' => csrf_token()])
        ->post('/timesheets/save', ['rows' => $this->parsedRows]);

    if ($response->successful()) {
        $this->step = 4;
        $this->importResult = $response->json();
    } else {
        $this->parseErrors = [$response->json()['message'] ?? 'Import failed'];
    }
}
```

Note: For Livewire server-side calls, use `Http::post()` with the Laravel base URL, or better yet, call the service/controller method directly (no HTTP round-trip):
```php
use App\Http\Controllers\TimesheetController;

// Or call the service layer directly:
$saved = (new TimesheetController)->saveRows($this->parsedRows);
```

**Preferred approach:** Extract `TimesheetController::saveBatch(array $rows)` as a method callable from Livewire directly (no HTTP round-trip).

**Step 4 — Success screen**
"X timesheets imported. Y skipped (duplicates)."
"View Timesheets" button → closes modal, reloads page (Livewire `$dispatch('timesheet-imported')` event → Alpine listener refreshes).

**Manual Timesheet Entry (separate button in list view):**
Simple Blade form (NOT Livewire) that POSTs to `POST /timesheets` (resource store).
Fields:
- Consultant (select*)
- Client (select*)
- Pay Period Start (date*)
- Pay Period End (date*)
- **Week 1:** Mon–Sun hours (7 inputs, step 0.25)
- **Week 2:** Mon–Sun hours (7 inputs, step 0.25)
- State override (select, defaults to consultant's state)

Live OT preview via Alpine fetch:
```javascript
async previewOT() {
    const res = await apiFetch('/timesheets/preview-ot', {
        method: 'POST',
        body: JSON.stringify({ state: this.state, week1Hours: this.week1, week2Hours: this.week2, payRate: this.payRate })
    });
    // Display regular/OT/DT hours from response
}
```

Add `POST /timesheets/preview-ot` route → new `TimesheetController::previewOt()` method that runs `OvertimeCalculator` and returns JSON (no DB write).

**carry-forward fix — source_file_path:**
In `TimesheetController::upload()` (or `TimesheetParseService`), after parsing, persist the uploaded file:
```php
$storedPath = $request->file('timesheet')->storeAs(
    'uploads/timesheets',
    now()->format('Ymd_His') . '_' . $request->file('timesheet')->getClientOriginalName(),
    'local'
);
// Pass $storedPath to save() so it can be stored on each Timesheet record
```

- [ ] **[Phase 2b] Add Blade branch to TimesheetController::index()**
- [ ] **[Phase 2b] Create `resources/views/timesheets/index.blade.php`**
- [ ] **[Phase 2b] Create `app/Livewire/TimesheetWizard.php` + Livewire view**
- [ ] **[Phase 2b] Add `POST /timesheets/preview-ot` route + TimesheetController::previewOt()**
- [ ] **[Phase 2b] Fix source_file_path population in TimesheetController::upload()**
- [ ] **[Phase 2b] Drop a minimal `storage/app/templates/timesheet_template.xlsx` placeholder (3-column header: Consultant, Pay Period, Total Hours)**
- [ ] **[Phase 2b] Smoke: list page renders, wizard opens, upload CSV → parsed rows → import → success**
- [ ] **Commit:** `feat: add timesheets Blade view and Livewire upload wizard`

---

### Step 6 — Reports

**Files:**
- Create: `web/resources/views/reports/index.blade.php`
- Modify: `web/app/Http/Controllers/ReportController.php`:
  - Add Blade branch to `index()`
  - **Fix carry-forward:** replace `saveCsv()` generic rows with server-driven query (see below)

**Carry-forward fix — ReportController::saveCsv():**
Remove the `rows` passthrough entirely. Instead add two dedicated endpoints:
```php
// Replace saveCsv() with two specific methods:

public function downloadMonthlyCsv(Request $request): StreamedResponse
{
    $this->authorize('account_manager');
    $data = $request->validate(['year'=>'required|integer','month'=>'required|integer|between:1,12']);
    // Run same query as monthly() but return CSV
    $rows = $this->buildMonthlyRows($data['year'], $data['month']);
    return response()->streamDownload(function() use ($rows) {
        $f = fopen('php://output','w');
        fputcsv($f, array_keys($rows[0] ?? []));
        foreach ($rows as $r) fputcsv($f, $r);
        fclose($f);
    }, "monthly_{$data['year']}_{$data['month']}.csv", ['Content-Type'=>'text/csv']);
}
```

**Report page structure (3 cards):**

```blade
<x-app-layout>
    <div x-data="reportsPage()" class="space-y-6">

        {{-- Year selector --}}
        <div class="flex items-center gap-3">
            <label class="font-semibold">Fiscal Year</label>
            <select x-model="year" class="border rounded px-2 py-1">
                @for($y = date('Y') - 2; $y <= date('Y') + 1; $y++)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>
        </div>

        {{-- Card 1: Year-End PDF --}}
        <div class="bg-white rounded-lg shadow-sm p-5">
            <h3 class="font-semibold text-red-700">Year-End Summary</h3>
            <p class="text-sm text-gray-500 mt-1">PDF report with quarterly breakdowns and revenue by client.</p>
            <button @click="generateYearEnd()" :disabled="loadingYearEnd"
                    class="mt-3 px-4 py-2 bg-red-600 text-white rounded disabled:opacity-50">
                <span x-show="!loadingYearEnd">Generate <span x-text="year"></span> PDF</span>
                <span x-show="loadingYearEnd">Generating...</span>
            </button>
        </div>

        {{-- Card 2: QuickBooks CSV --}}
        <div class="bg-white rounded-lg shadow-sm p-5">
            <h3 class="font-semibold text-green-700">QuickBooks CSV Export</h3>
            <p class="text-sm text-yellow-700 bg-yellow-50 px-3 py-2 rounded mt-2 text-xs">
                Account names in the CSV must match your QuickBooks chart of accounts.
            </p>
            <button @click="exportQuickbooks()" :disabled="loadingQB"
                    class="mt-3 px-4 py-2 bg-green-600 text-white rounded disabled:opacity-50">
                <span x-show="!loadingQB">Export <span x-text="year"></span> QuickBooks CSV</span>
                <span x-show="loadingQB">Exporting...</span>
            </button>
        </div>

        {{-- Card 3: Monthly Report --}}
        <div class="bg-white rounded-lg shadow-sm p-5">
            <h3 class="font-semibold text-blue-700">Monthly Report</h3>
            <div class="flex items-center gap-3 mt-2">
                <select x-model="month" class="border rounded px-2 py-1">
                    @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] as $i => $m)
                        <option value="{{ $i + 1 }}">{{ $m }}</option>
                    @endforeach
                </select>
                <button @click="previewMonthly()" class="px-3 py-1.5 bg-blue-600 text-white rounded text-sm">Preview PDF</button>
                <button @click="downloadMonthlyCsv()" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded text-sm">Download CSV</button>
            </div>
        </div>
    </div>

    {{-- PDF preview modal (shared by year-end + monthly) --}}
    <div x-show="pdfUrl" x-cloak class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg w-full max-w-5xl h-[90vh] flex flex-col">
            <div class="flex items-center justify-between p-3 border-b">
                <h3 class="font-semibold">PDF Preview</h3>
                <div class="flex gap-2">
                    <a :href="pdfUrl" download class="px-3 py-1 bg-blue-600 text-white rounded text-sm">Download</a>
                    <button @click="pdfUrl=''" class="text-gray-500">✕</button>
                </div>
            </div>
            <iframe :src="pdfUrl" class="flex-1 w-full"></iframe>
        </div>
    </div>

    <script>
    function reportsPage() {
        return {
            year: {{ date('Y') }}, month: {{ date('n') }},
            pdfUrl: '', loadingYearEnd: false, loadingQB: false,
            async generateYearEnd() {
                this.loadingYearEnd = true;
                // Route: GET /reports/year-end?year=2025 returns PDF binary inline
                const res = await apiFetch(`/reports/year-end?year=${this.year}`);
                const blob = await res.blob();
                this.pdfUrl = URL.createObjectURL(blob);
                this.loadingYearEnd = false;
            },
            async exportQuickbooks() {
                this.loadingQB = true;
                window.location = `/reports/quickbooks?year=${this.year}`;
                this.loadingQB = false;
            },
            async previewMonthly() {
                const res = await apiFetch(`/reports/monthly?year=${this.year}&month=${this.month}`);
                const blob = await res.blob();
                this.pdfUrl = URL.createObjectURL(blob);
            },
            downloadMonthlyCsv() {
                window.location = `/reports/monthly-csv?year=${this.year}&month=${this.month}`;
            },
        };
    }
    </script>
</x-app-layout>
```

**Controller changes needed:**
- `monthly()` — update to return PDF binary stream (content-type: `application/pdf`) when `Accept` is `application/pdf` or has `?preview=1`. Keep JSON for backward compat.
- `yearEnd()` — same: return `response($pdf)->header('Content-Type','application/pdf')` for browser requests.
- Add `GET /reports/monthly-csv` route → new `ReportController::downloadMonthlyCsv()` (the carry-forward fix).
- Add `GET /reports/year-end` to route list if not already present.

Add route to `routes/web.php`:
```php
Route::get('reports/monthly-csv', [ReportController::class, 'downloadMonthlyCsv']);
```

- [ ] **[Phase 2b] Add Blade branch to ReportController::index()**
- [ ] **[Phase 2b] Fix ReportController::saveCsv() → add downloadMonthlyCsv() server-driven method (carry-forward)**
- [ ] **[Phase 2b] Update yearEnd() and monthly() to stream PDF binary for browser requests**
- [ ] **[Phase 2b] Add route for reports/monthly-csv**
- [ ] **[Phase 2b] Create `resources/views/reports/index.blade.php`**
- [ ] **[Phase 2b] Smoke: PDF preview modal opens with blob URL, CSV download triggers**
- [ ] **Commit:** `feat: add reports Blade view, fix saveCsv carry-forward`

---

### Step 7 — Budget + Settings

#### 7a — Budget (carry-forward + Blade view)

The budget page is currently served from the Reports section in the Electron app. In Laravel it has its own controller and routes. Embed the budget tracker into `reports/index.blade.php` (as the 4th card, below the 3 report cards) OR give it its own route at `/budget`. Either is acceptable — embed in Reports for simpler navigation.

**Carry-forward fix — BudgetController::alerts():**

In `BudgetController::alerts()`, after setting the warning/critical flags, add audit log:
```php
AppService::auditLog('clients', (int) $clientId, 'BUDGET_ALERT', [], [
    'type' => $alertType,  // 'warning' or 'critical'
    'pct' => $pct,
]);
```

**Budget tracker UI (embed in reports view or own page):**
- Annual budget input per client (fetched from `GET /budget/{year}`)
- Progress bar per client (same color coding as dashboard)
- "Save FY [YEAR] Budgets" button → `POST /budget/{year}`

#### 7b — Settings

**Files:**
- Create: `web/resources/views/settings/index.blade.php`
- Modify: `web/app/Http/Controllers/SettingsController.php` — add Blade branch to `index()`

**Controller change:**
```php
public function index(): JsonResponse|View
{
    $this->authorize('admin');
    $rows = DB::table('settings')->pluck('value', 'key');
    $seq = InvoiceSequence::query()->find(1);

    if (request()->expectsJson()) {
        return response()->json($rows);
    }
    return view('settings.index', ['settings' => $rows, 'sequence' => $seq]);
}
```

**Settings page structure — 6-tab layout (Alpine `activeTab`):**

```blade
<div x-data="settingsPage(@json($settings), @json($sequence))" class="flex gap-6">

    {{-- Left tab nav --}}
    <nav class="w-48 shrink-0 space-y-1">
        @foreach(['agency'=>'Agency Info','logo'=>'Logo','invoicing'=>'Invoice #','smtp'=>'SMTP','backup'=>'Backup','alerts'=>'Alerts'] as $tab => $label)
        <button @click="activeTab='{{ $tab }}'"
                :class="activeTab==='{{ $tab }}' ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-600 hover:bg-gray-50'"
                class="w-full text-left px-3 py-2 rounded text-sm">{{ $label }}</button>
        @endforeach
    </nav>

    {{-- Tab panels --}}
    <div class="flex-1">
        {{-- Agency Info --}}
        <div x-show="activeTab==='agency'">
            Agency Name: <input x-model="form.agency_name">
            Mailing Address: <textarea x-model="form.agency_address"></textarea>
            Phone: <input x-model="form.agency_phone">
            Email: <input x-model="form.agency_email">
            <button @click="save(['agency_name','agency_address','agency_phone','agency_email'])">Save</button>
        </div>

        {{-- Logo --}}
        <div x-show="activeTab==='logo'">
            <img x-show="form.agency_logo_base64" :src="form.agency_logo_base64" class="max-h-20 mb-4">
            <form method="POST" action="/settings/logo" enctype="multipart/form-data">
                @csrf @method('POST')
                <input type="file" name="logo" accept="image/*">
                <button type="submit">Upload Logo</button>
            </form>
        </div>

        {{-- Invoice Numbering --}}
        <div x-show="activeTab==='invoicing'">
            Prefix: <input x-model="seq.prefix">
            Next Number: <input type="number" x-model="seq.next_number">
            Preview: <code x-text="seq.prefix + String(seq.next_number).padStart(6,'0')"></code>
            <button @click="saveSequence()">Save</button>
        </div>

        {{-- SMTP --}}
        <div x-show="activeTab==='smtp'">
            Host: <input x-model="form.smtp_host">
            Port: <input type="number" x-model="form.smtp_port">
            Username: <input x-model="form.smtp_user">
            Password: <input type="password" x-model="form.smtp_password">
            Encryption: <select x-model="form.smtp_encryption"><option>tls</option><option>ssl</option></select>
            From Name: <input x-model="form.smtp_from_name">
            From Address: <input x-model="form.smtp_from_address">
            <button @click="testSmtp()">Test Connection</button>
            <button @click="save(['smtp_host','smtp_port','smtp_user','smtp_password','smtp_encryption','smtp_from_name','smtp_from_address'])">Save</button>
        </div>

        {{-- Backup --}}
        <div x-show="activeTab==='backup'">
            <button @click="createBackup()">Create Backup Now</button>
            {{-- Backup history table --}}
            <table class="w-full mt-4 text-sm">
                @foreach($backups as $b)
                <tr>
                    <td>{{ $b->created_at }}</td>
                    <td>{{ basename($b->file_path) }}</td>
                    <td><a href="/backups/{{ $b->id }}">Restore</a></td>
                </tr>
                @endforeach
            </table>
        </div>

        {{-- Alerts --}}
        <div x-show="activeTab==='alerts'">
            Critical (days): <input type="number" x-model="form.alert_threshold_critical">
            Warning (days): <input type="number" x-model="form.alert_threshold_warning">
            Notice (days): <input type="number" x-model="form.alert_threshold_notice">
            Budget Warning (%): <input type="number" x-model="form.budget_alert_threshold_warning">
            Budget Critical (%): <input type="number" x-model="form.budget_alert_threshold_critical">
            <button @click="save(['alert_threshold_critical','alert_threshold_warning','alert_threshold_notice','budget_alert_threshold_warning','budget_alert_threshold_critical'])">Save</button>
        </div>
    </div>
</div>

<script>
function settingsPage(settings, sequence) {
    return {
        activeTab: 'agency',
        form: settings,
        seq: sequence ?? { prefix: 'INV-', next_number: 1 },
        async save(keys) {
            for (const key of keys) {
                await apiFetch('/settings', { method: 'PUT', body: JSON.stringify({ key, value: this.form[key] }) });
            }
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Saved' } }));
        },
        async saveSequence() {
            await apiFetch('/invoice-sequence', { method: 'PUT', body: JSON.stringify({ prefix: this.seq.prefix, next_number: this.seq.next_number }) });
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Invoice numbering saved' } }));
        },
        async testSmtp() {
            const r = await apiFetch('/settings/test-smtp', { method: 'POST', body: '{}' }).then(x=>x.json());
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: r.ok ? 'SMTP OK' : r.error, type: r.ok ? 'success' : 'error' } }));
        },
        async createBackup() {
            const r = await apiFetch('/backups', { method: 'POST', body: '{}' }).then(x=>x.json());
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: r.ok ? 'Backup created' : r.error, type: r.ok ? 'success' : 'error' } }));
            window.location.reload();
        },
    };
}
</script>
```

Pass `$backups` to view: add to `SettingsController::index()`:
```php
'backups' => \App\Models\Backup::orderByDesc('created_at')->get()
```

Also need to pass `$sequence` (InvoiceSequence record) to settings view.

- [ ] **[Phase 2b] Fix BudgetController::alerts() — add audit log (carry-forward)**
- [ ] **[Phase 2b] Update SettingsController::index() to return Blade view**
- [ ] **[Phase 2b] Create `resources/views/settings/index.blade.php`**
- [ ] **[Phase 2b] Smoke: all 6 tabs render, SMTP test fires, backup creates**
- [ ] **Commit:** `feat: add settings Blade view with 6-tab layout, fix budget alerts audit log`

---

## Step 8 — Merge + Verification

After both Phase 2a and 2b are complete, merge workstreams and verify all 8 pages end-to-end.

**Checklist:**

- [ ] `GET /dashboard` → 4 stat cards render with real numbers
- [ ] `GET /clients` → table renders, Add modal saves, Edit modal loads + saves
- [ ] `GET /consultants` → table, onboarding modal (7 items), W-9 upload
- [ ] `GET /timesheets` → list renders; Import button opens Livewire wizard; upload → parse → import → success
- [ ] `GET /timesheets/template/download` → 200 (xlsx file downloaded)
- [ ] `GET /invoices` → table, Preview opens PDF iframe, Send modal fires email (check log)
- [ ] `GET /ledger` → detail view rows + summary toggle
- [ ] `POST /invoices/generate` from Ledger → invoice created, toast shown
- [ ] `GET /reports` → Year-end PDF preview opens, Monthly CSV downloads
- [ ] `GET /settings` → all 6 tabs render; SMTP test returns result
- [ ] Role gates: employee sees 403 on `/clients`, `/consultants`, `/timesheets`, `/invoices`, `/ledger`, `/reports`, `/settings`
- [ ] Sidebar links: all 8 visible for admin/account_manager; employee sees only Dashboard
- [ ] `php artisan test --filter=OvertimeCalculatorTest` still passes (no regression)

- [ ] **Commit:** `feat: Phase 2 complete — all 8 Blade views, Livewire wizard, sidebar nav`

---

## Acceptance Criteria

- [ ] All 8 pages load at GET endpoint with authenticated admin session
- [ ] Every table renders real data from the database
- [ ] Every modal (add/edit/deactivate/send/onboarding) saves and provides user feedback
- [ ] Timesheets Livewire wizard completes full upload → parse → import → success flow
- [ ] PDF preview renders in browser iframe (invoice + reports)
- [ ] Sidebar links are correct, active state highlights current page
- [ ] Role guards: account_manager sees all pages except Settings/Admin; employee sees only Dashboard
- [ ] `OvertimeCalculatorTest` still passes 45/45
- [ ] `php artisan route:list` shows all routes without errors
- [ ] SMTP settings saved in Settings page are used by invoice send (via AppService::applySmtpSettings)
- [ ] `timesheets.source_file_path` is populated after import
- [ ] `ReportController` no longer accepts arbitrary caller-supplied CSV rows (carry-forward fixed)
- [ ] `BudgetController::alerts()` writes to audit_log (carry-forward fixed)

## Files Planned

```
web/resources/views/dashboard.blade.php           ← update stub (Phase 2a)
web/resources/views/clients/index.blade.php       ← new (Phase 2a)
web/resources/views/consultants/index.blade.php   ← new (Phase 2a)
web/resources/views/invoices/index.blade.php      ← new (Phase 2a)
web/resources/views/ledger/index.blade.php        ← new (Phase 2a)
web/resources/views/timesheets/index.blade.php    ← new (Phase 2b)
web/resources/views/reports/index.blade.php       ← new (Phase 2b)
web/resources/views/settings/index.blade.php      ← new (Phase 2b)
web/resources/views/livewire/timesheet-wizard.blade.php ← new (Phase 2b)
web/app/Livewire/TimesheetWizard.php              ← new (Phase 2b)
web/app/Http/Controllers/DashboardController.php  ← add page(), carry-forward comment (Phase 2a)
web/app/Http/Controllers/ClientController.php     ← add Blade branch to index() (Phase 2a)
web/app/Http/Controllers/ConsultantController.php ← add Blade branch to index() (Phase 2a)
web/app/Http/Controllers/InvoiceController.php    ← add Blade branch to index() (Phase 2a)
web/app/Http/Controllers/LedgerController.php     ← add Blade branch to index() (Phase 2a)
web/app/Http/Controllers/TimesheetController.php  ← Blade branch + source_file_path fix (Phase 2b)
web/app/Http/Controllers/ReportController.php     ← Blade branch + saveCsv carry-forward (Phase 2b)
web/app/Http/Controllers/BudgetController.php     ← alerts() audit log carry-forward (Phase 2b)
web/app/Http/Controllers/SettingsController.php   ← Blade branch (Phase 2b)
web/routes/web.php                                ← add /dashboard page route, /reports/monthly-csv
web/storage/app/templates/timesheet_template.xlsx ← placeholder file (Phase 2b)
```

## Risks

| Risk | Mitigation |
|---|---|
| Livewire file upload on shared hosting | Test with real PHP memory limits; use `ini_set('memory_limit','256M')` in wizard |
| PDF preview in iframe blocked by Content-Security-Policy | Use `blob:` URL approach (URL.createObjectURL) not direct `/invoices/{id}/preview` URL in iframe |
| `window.location.reload()` on modal save is heavy | Acceptable for Phase 2; Phase 3 can switch to Livewire or fetch-refresh pattern if UX is poor |
| Livewire requires direct method call (no HTTP round-trip) | Extract `TimesheetController::saveBatch()` as callable service method |
| Alpine.js modal + Livewire same page | Use `x-ignore` on Livewire component root so Alpine doesn't interfere with Livewire's own DOM management |
