{{-- Admin panel shell (Inertia root view, from the React starter kit). Follows the system light/dark setting. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">

        <script>
            if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark');
            }
        </script>

        <link rel="icon" href="/favicon.ico" sizes="any">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>Admin · {{ config('app.name') }}</title>
        </x-inertia::head>
    </head>
    <body class="bg-background font-sans text-foreground antialiased">
        <x-inertia::app />
    </body>
</html>
