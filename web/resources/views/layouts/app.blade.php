<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <script src="https://cdn.tailwindcss.com"></script>

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
                    <a class="block rounded px-3 py-2 hover:bg-gray-800" href="{{ route('dashboard') }}">Dashboard</a>
                    <a class="block rounded px-3 py-2 hover:bg-gray-800" href="#">Clients</a>
                    <a class="block rounded px-3 py-2 hover:bg-gray-800" href="#">Consultants</a>
                    <a class="block rounded px-3 py-2 hover:bg-gray-800" href="#">Timesheets</a>
                    <a class="block rounded px-3 py-2 hover:bg-gray-800" href="#">Invoices</a>
                    <a class="block rounded px-3 py-2 hover:bg-gray-800" href="#">Reports</a>
                    <a class="block rounded px-3 py-2 hover:bg-gray-800" href="#">Ledger</a>
                    @can('admin')
                        <a class="block rounded px-3 py-2 hover:bg-gray-800" href="{{ route('admin.users.index') }}">Admin Users</a>
                        <a class="block rounded px-3 py-2 hover:bg-gray-800" href="#">Settings</a>
                    @endcan
                </nav>

                <div class="mt-8 border-t border-gray-700 pt-4 text-sm">
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

                @isset($header)
                    <header class="mb-6 rounded bg-white p-4 shadow">
                        {{ $header }}
                    </header>
                @endisset

                {{ $slot }}
            </main>
        </div>
    </body>
</html>
