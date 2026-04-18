<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 — IHRP</title>
    <link rel="stylesheet" href="/css/tokens.css">
    <link rel="stylesheet" href="/css/hr-command.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { corePlugins: { preflight: false } }
    </script>
</head>
<body>
    <div class="auth-shell">
        <div class="auth-wrap">
            <div class="auth-card" style="text-align:center;">
                <p class="mono-num" style="font-size:72px;font-weight:700;color:var(--fg-4);margin:0;">403</p>
                <h1 style="margin-top:12px;font-size:28px;font-weight:700;color:var(--fg-1)">Access Denied</h1>
                <p style="margin-top:8px;font-size:13px;color:var(--fg-3)">
            @if(!empty($exception->getMessage()))
                {{ $exception->getMessage() }}
            @else
                You don't have permission to view this page. If you believe this is a mistake, contact your administrator.
            @endif
                </p>
                <a href="/login" class="btn btn-primary" style="margin-top:20px;">
                    ← Back to login
                </a>
            </div>
        </div>
    </div>
</body>
</html>
