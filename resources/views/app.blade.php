<!DOCTYPE html>
<html lang="fa" dir="rtl" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f766e">
    <title inertia>{{ config('app.name') }}</title>

    {{-- Apply the stored theme before first paint so there is no flash. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('mosannen.theme') || 'system';
                var dark = t === 'dark' || (t === 'system' &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {}
        })();
    </script>

    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="h-full bg-surface-50 font-sans text-ink-900 antialiased dark:bg-surface-950 dark:text-ink-50">
    @inertia
</body>
</html>
