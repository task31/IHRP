<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'HR Command') }}</title>

        <style>[x-cloak] { display: none !important; }</style>
        <link rel="stylesheet" href="/css/tokens.css">
        <link rel="stylesheet" href="/css/hr-command.css">
    </head>
    <body>
        <div class="auth-shell">
            <div class="auth-wrap">
                <div class="auth-brand">
                    <img src="{{ asset('images/matchpointe-logo.png') }}" alt="Matchpointe Group">
                </div>

                <div class="auth-card">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
