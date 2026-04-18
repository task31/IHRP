<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'HR Command') }}</title>

        <link rel="stylesheet" href="/css/tokens.css">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <script>tailwind.config = { corePlugins: { preflight: false } }</script>

        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            html, body {
                min-height: 100vh;
                background: var(--grad-app-bg);
                color: var(--fg-1);
                font-family: var(--font-sans);
                font-size: var(--fs-base);
                -webkit-font-smoothing: antialiased;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-wrap {
                width: 100%;
                max-width: 400px;
                padding: 20px;
            }
            .login-brand {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
                margin-bottom: 32px;
            }
            .login-mark {
                width: 40px; height: 40px;
                border-radius: 12px;
                background: linear-gradient(135deg, #22d3ee 0%, #0891b2 100%);
                display: grid; place-items: center;
                box-shadow: 0 8px 20px rgba(34,211,238,0.30), inset 0 1px 0 rgba(255,255,255,0.35);
            }
            .login-card {
                background: var(--bg-3);
                border: 1px solid var(--border-2);
                border-radius: var(--radius-lg);
                padding: 32px;
                box-shadow: var(--shadow-lg);
            }
            .field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
            .field label {
                font-size: var(--fs-micro); font-weight: 600;
                letter-spacing: var(--tracking-widest); text-transform: uppercase;
                color: var(--fg-3);
            }
            .field input[type=email],
            .field input[type=password],
            .field input[type=text] {
                background: var(--bg-2);
                border: 1px solid var(--border-2);
                border-radius: var(--radius-md);
                padding: 10px 12px;
                color: var(--fg-1);
                font-family: var(--font-sans);
                font-size: var(--fs-sm);
                outline: none;
                width: 100%;
                transition: border-color 120ms, box-shadow 120ms;
            }
            .field input:focus {
                border-color: var(--accent-400);
                box-shadow: 0 0 0 3px rgba(34,211,238,0.18);
            }
            .field-row {
                display: flex; align-items: center;
                justify-content: space-between; margin-bottom: 20px; font-size: 13px;
            }
            .field-row label { display: flex; align-items: center; gap: 8px; color: var(--fg-2); cursor: pointer; }
            .field-row a { color: var(--accent-400); text-decoration: none; font-size: 12px; }
            .field-row a:hover { text-decoration: underline; }
            .btn-login {
                width: 100%;
                padding: 11px;
                border-radius: var(--radius-md);
                background: linear-gradient(180deg, #22D3EE 0%, #06B6D4 100%);
                color: var(--fg-on-accent);
                font-family: var(--font-sans);
                font-size: var(--fs-sm);
                font-weight: 700;
                border: none;
                cursor: pointer;
                box-shadow: 0 0 0 1px rgba(34,211,238,0.35), 0 6px 18px rgba(34,211,238,0.22);
                transition: background 120ms;
            }
            .btn-login:hover { background: linear-gradient(180deg, #67E8F9 0%, #22D3EE 100%); }
            .flash-error {
                background: var(--danger-bg);
                border: 1px solid rgba(239,68,68,0.25);
                border-radius: var(--radius-md);
                padding: 10px 14px;
                font-size: 13px;
                color: var(--danger-400);
                margin-bottom: 16px;
            }
            .input-error { font-size: 12px; color: var(--danger-400); margin-top: 4px; }
        </style>
    </head>
    <body>
        <div class="login-wrap">
            <div class="login-brand">
                <div class="login-mark">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h7l-1 8 10-12h-7l1-8z"/></svg>
                </div>
                <div>
                    <div style="font-size:18px;font-weight:700;letter-spacing:-0.01em;">HR Command</div>
                    <div style="font-size:10px;color:var(--fg-3);letter-spacing:0.14em;text-transform:uppercase;margin-top:2px;">Matchpointe Group</div>
                </div>
            </div>

            <div class="login-card">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
