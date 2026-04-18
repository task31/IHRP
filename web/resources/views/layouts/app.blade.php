<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'HR Command') }}</title>

        <style>[x-cloak] { display: none !important; }</style>

        <!-- Design system -->
        <link rel="stylesheet" href="/css/tokens.css">
        <link rel="stylesheet" href="/css/hr-command.css">
        <!-- Mono font for tabular data -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
        <!-- Tailwind CDN kept for any utility needs in existing views -->
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = { corePlugins: { preflight: false } }
        </script>

        <script>
            function apiFetch(url, options = {}) {
                const meta = document.querySelector('meta[name="csrf-token"]');
                const token = meta ? meta.content : '';
                const { headers: optHeaders = {}, ...rest } = options;
                return fetch(url, {
                    ...rest,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                        ...optHeaders,
                    },
                });
            }
        </script>

        @livewireStyles
        @stack('styles')
    </head>
    <body>
        @php
            $user = auth()->user();
            $userName = $user->name ?? 'HR Admin';
            $userEmail = $user->email ?? '';
            $nameParts = explode(' ', $userName);
            $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
            $avatarHash = array_sum(array_map('ord', str_split($userName))) % 6;
            $avatarGrads = [
                ['#22D3EE','#4f6bff'], ['#EC4899','#8B5CF6'], ['#F59E0B','#F97316'],
                ['#10B981','#22D3EE'], ['#8B5CF6','#EC4899'], ['#F97316','#EF4444'],
            ];
            [$avatarC1, $avatarC2] = $avatarGrads[$avatarHash];
        @endphp

        <div class="app">

            {{-- ===== SIDEBAR ===== --}}
            <aside class="sidebar">
                <nav class="nav">
                    @unless($user?->role === 'account_manager')
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        Dashboard
                        @if(request()->routeIs('dashboard'))<span class="dot"></span>@endif
                    </a>
                    @endunless

                    <a href="{{ route('calls.index') }}" class="{{ request()->routeIs('calls.*') ? 'active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        Call Activity
                        @if(request()->routeIs('calls.*'))<span class="dot"></span>@endif
                    </a>

                    <a href="{{ route('resume.redact.index') }}" class="{{ request()->routeIs('resume.redact.*') ? 'active' : '' }}" style="padding-left:28px;font-size:12px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                        Resume Redact
                        @if(request()->routeIs('resume.redact.*'))<span class="dot"></span>@endif
                    </a>

                    @can('account_manager')
                    <a href="{{ route('placements.index') }}" class="{{ request()->routeIs('placements.*') ? 'active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>
                        Placements
                        @if(request()->routeIs('placements.*'))<span class="dot"></span>@endif
                    </a>
                    <a href="{{ route('payroll.index') }}" class="{{ request()->routeIs('payroll.*') ? 'active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        Payroll
                        @if(request()->routeIs('payroll.*'))<span class="dot"></span>@endif
                    </a>
                    @endcan

                    @can('admin')
                    <a href="{{ route('clients.index') }}" class="{{ request()->routeIs('clients.*') ? 'active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                        Clients
                        @if(request()->routeIs('clients.*'))<span class="dot"></span>@endif
                    </a>
                    <a href="{{ route('consultants.index') }}" class="{{ request()->routeIs('consultants.*') ? 'active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Consultants
                        @if(request()->routeIs('consultants.*'))<span class="dot"></span>@endif
                    </a>
                    <a href="{{ route('timesheets.index') }}" class="{{ request()->routeIs('timesheets.*') ? 'active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        Timesheets
                        @if(request()->routeIs('timesheets.*'))<span class="dot"></span>@endif
                    </a>
                    <a href="{{ route('invoices.index') }}" class="{{ request()->routeIs('invoices.*') ? 'active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                        Invoices
                        @if(request()->routeIs('invoices.*'))<span class="dot"></span>@endif
                    </a>
                    <a href="{{ route('ledger.index') }}" class="{{ request()->routeIs('ledger.*') ? 'active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                        Ledger
                        @if(request()->routeIs('ledger.*'))<span class="dot"></span>@endif
                    </a>
                    <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.*') ? 'active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                        Reports
                        @if(request()->routeIs('reports.*'))<span class="dot"></span>@endif
                    </a>

                    <div class="group-label">Admin</div>
                    <a href="{{ route('settings.index') }}" class="{{ request()->routeIs('settings.*') ? 'active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        Settings
                        @if(request()->routeIs('settings.*'))<span class="dot"></span>@endif
                    </a>
                    <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Users
                        @if(request()->routeIs('admin.users.*'))<span class="dot"></span>@endif
                    </a>
                    @endcan
                </nav>

                <div class="sidebar-foot">
                    <div class="avatar" style="width:32px;height:32px;background:linear-gradient(135deg,{{ $avatarC1 }},{{ $avatarC2 }});font-size:13px;">{{ $initials }}</div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:12px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $userName }}</div>
                        <div style="font-size:10px;color:var(--fg-3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $userEmail }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="icon-btn" style="width:28px;height:28px;" title="Log out">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
                        </button>
                    </form>
                </div>
            </aside>

            {{-- ===== RIGHT COLUMN ===== --}}
            <div style="display:flex;flex-direction:column;min-width:0;overflow:hidden;">

                {{-- Topbar --}}
                <div class="topbar">
                    @isset($pageTitle)
                    <h1 style="font-size:20px;font-weight:700;letter-spacing:-0.01em;">{{ $pageTitle }}</h1>
                    @endisset
                    <div class="spacer"></div>
                    <div class="user-chip">
                        <div class="avatar" style="width:28px;height:28px;background:linear-gradient(135deg,{{ $avatarC1 }},{{ $avatarC2 }});font-size:11px;">{{ $initials }}</div>
                        <div>
                            <div class="name">{{ $userName }}</div>
                            <div class="email">{{ $userEmail }}</div>
                        </div>
                    </div>
                </div>

                {{-- Flash messages --}}
                @if(session('success') || session('error'))
                <div style="padding:12px 32px 0;">
                    @if(session('success'))
                    <div style="background:var(--success-bg);border:1px solid rgba(52,211,153,0.25);border-radius:var(--radius-md);padding:10px 16px;font-size:13px;color:var(--success-400);margin-bottom:8px;">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                    <div style="background:var(--danger-bg);border:1px solid rgba(239,68,68,0.25);border-radius:var(--radius-md);padding:10px 16px;font-size:13px;color:var(--danger-400);margin-bottom:8px;">{{ session('error') }}</div>
                    @endif
                </div>
                @endif

                {{-- Page content --}}
                <main class="main">
                    @isset($header)
                    <div style="margin-bottom:20px;">{{ $header }}</div>
                    @endisset
                    {{ $slot }}
                </main>
            </div>
        </div>

        {{-- Toast notifications --}}
        <div
            x-data="toastManager()"
            x-on:toast.window="add($event.detail)"
            style="position:fixed;bottom:20px;right:20px;z-index:50;display:flex;flex-direction:column;gap:8px;"
        >
            <template x-for="t in toasts" :key="t.id">
                <div
                    x-show="t.show"
                    x-transition
                    style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:var(--radius-md);font-size:13px;color:#fff;box-shadow:var(--shadow-lg);"
                    :style="t.type === 'error' ? 'background:var(--brand-500);' : 'background:var(--success-500);'"
                >
                    <span x-text="t.message"></span>
                    <button type="button" x-on:click="remove(t.id)" style="margin-left:auto;opacity:0.7;background:none;border:none;color:#fff;cursor:pointer;font-size:14px;" aria-label="Dismiss">✕</button>
                </div>
            </template>
        </div>

        @livewireScripts
        @stack('scripts')

        <script>
            function toastManager() {
                return {
                    toasts: [],
                    add({ message, type = 'success', duration = 3500 }) {
                        const id = Date.now() + Math.random();
                        this.toasts.push({ id, message, type, show: true });
                        setTimeout(() => this.remove(id), duration);
                    },
                    remove(id) {
                        this.toasts = this.toasts.filter((t) => t.id !== id);
                    },
                };
            }
        </script>
    </body>
</html>
