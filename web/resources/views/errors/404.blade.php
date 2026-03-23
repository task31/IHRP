<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 — IHRP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-50 px-4">
    <div class="w-full max-w-md text-center">
        <p class="text-8xl font-bold text-gray-200">404</p>
        <h1 class="mt-4 text-2xl font-semibold text-gray-800">Page Not Found</h1>
        <p class="mt-2 text-sm text-gray-500">
            @if(!empty($exception->getMessage()))
                {{ $exception->getMessage() }}
            @else
                The page you're looking for doesn't exist or has been moved.
            @endif
        </p>
        <a href="/login" class="mt-6 inline-block rounded-md bg-gray-900 px-5 py-2 text-sm font-medium text-white hover:bg-gray-700">
            ← Back to login
        </a>
    </div>
</body>
</html>
