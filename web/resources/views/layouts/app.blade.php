<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <style>[x-cloak] { display: none !important; }</style>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <script src="https://cdn.tailwindcss.com"></script>

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

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <!-- Alpine.js (temporary via CDN; no npm required in Phase 0) -->
        <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    </head>
    <body class="font-sans antialiased bg-gray-100">
        <div class="min-h-screen md:flex">
            <aside class="w-full md:w-64 bg-gray-900 text-gray-100 p-5">
                <h1 class="text-lg font-bold tracking-wide">Matchpointe HR</h1>
                <p class="mt-1 text-xs text-gray-400">Internal Payroll Portal</p>

                <nav class="mt-6 space-y-2 text-sm">
                    @unless(auth()->user()?->role === 'account_manager')
                    <a href="{{ route('dashboard') }}" @class([
                        'block rounded px-3 py-2',
                        'bg-gray-800 font-medium text-white' => request()->routeIs('dashboard'),
                        'text-gray-300 hover:bg-gray-800' => ! request()->routeIs('dashboard'),
                    ])>Dashboard</a>
                    @endunless

                    <a href="{{ route('calls.index') }}" @class([
                        'block rounded px-3 py-2',
                        'bg-gray-800 font-medium text-white' => request()->routeIs('calls.*'),
                        'text-gray-300 hover:bg-gray-800' => ! request()->routeIs('calls.*'),
                    ])>Calls</a>

                    @can('admin')
                        <a href="{{ route('clients.index') }}" @class([
                            'block rounded px-3 py-2',
                            'bg-gray-800 font-medium text-white' => request()->routeIs('clients.*'),
                            'text-gray-300 hover:bg-gray-800' => ! request()->routeIs('clients.*'),
                        ])>Clients</a>
                        <a href="{{ route('consultants.index') }}" @class([
                            'block rounded px-3 py-2',
                            'bg-gray-800 font-medium text-white' => request()->routeIs('consultants.*'),
                            'text-gray-300 hover:bg-gray-800' => ! request()->routeIs('consultants.*'),
                        ])>Consultants</a>
                        <a href="{{ route('timesheets.index') }}" @class([
                            'block rounded px-3 py-2',
                            'bg-gray-800 font-medium text-white' => request()->routeIs('timesheets.*'),
                            'text-gray-300 hover:bg-gray-800' => ! request()->routeIs('timesheets.*'),
                        ])>Timesheets</a>
                        <a href="{{ route('invoices.index') }}" @class([
                            'block rounded px-3 py-2',
                            'bg-gray-800 font-medium text-white' => request()->routeIs('invoices.*'),
                            'text-gray-300 hover:bg-gray-800' => ! request()->routeIs('invoices.*'),
                        ])>Invoices</a>
                        <a href="{{ route('ledger.index') }}" @class([
                            'block rounded px-3 py-2',
                            'bg-gray-800 font-medium text-white' => request()->routeIs('ledger.*'),
                            'text-gray-300 hover:bg-gray-800' => ! request()->routeIs('ledger.*'),
                        ])>Ledger</a>
                        <a href="{{ route('reports.index') }}" @class([
                            'block rounded px-3 py-2',
                            'bg-gray-800 font-medium text-white' => request()->routeIs('reports.*'),
                            'text-gray-300 hover:bg-gray-800' => ! request()->routeIs('reports.*'),
                        ])>Reports</a>
                    @endcan

                    @can('account_manager')
                        <a href="{{ route('placements.index') }}" @class([
                            'block rounded px-3 py-2',
                            'bg-gray-800 font-medium text-white' => request()->routeIs('placements.*'),
                            'text-gray-300 hover:bg-gray-800' => ! request()->routeIs('placements.*'),
                        ])>Placements</a>
                    @endcan

                    @can('admin')
                        <a href="{{ route('settings.index') }}" @class([
                            'block rounded px-3 py-2',
                            'bg-gray-800 font-medium text-white' => request()->routeIs('settings.*'),
                            'text-gray-300 hover:bg-gray-800' => ! request()->routeIs('settings.*'),
                        ])>Settings</a>
                        <a href="{{ route('admin.users.index') }}" @class([
                            'block rounded px-3 py-2',
                            'bg-gray-800 font-medium text-white' => request()->routeIs('admin.users.*'),
                            'text-gray-300 hover:bg-gray-800' => ! request()->routeIs('admin.users.*'),
                        ])>Admin</a>
                    @endcan
                </nav>

                <div class="mt-6 border-t border-gray-700 pt-4 text-sm">
                    <div class="text-gray-300">{{ auth()->user()->name ?? '' }}</div>
                    <div class="text-xs text-gray-400">{{ auth()->user()->email ?? '' }}</div>
                    <div class="mt-3">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded bg-gray-800 px-3 py-2 text-xs font-semibold hover:bg-gray-700">
                                Log Out
                            </button>
                        </form>
                    </div>
                </div>
            </aside>

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                @isset($header)
                    <div class="mb-4">
                        {{ $header }}
                    </div>
                @endisset

                @if (session('success'))
                    <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ session('error') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>

        <div
            x-data="toastManager()"
            x-on:toast.window="add($event.detail)"
            class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"
        >
            <template x-for="t in toasts" :key="t.id">
                <div
                    x-show="t.show"
                    x-transition
                    class="flex items-center gap-2 rounded px-4 py-3 text-sm text-white shadow-lg"
                    :class="t.type === 'error' ? 'bg-red-600' : 'bg-green-600'"
                >
                    <span x-text="t.message"></span>
                    <button type="button" @click="remove(t.id)" class="ml-auto opacity-70 hover:opacity-100" aria-label="Dismiss">✕</button>
                </div>
            </template>
        </div>

        @livewireScripts

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
