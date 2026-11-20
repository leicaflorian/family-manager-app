<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($page['props']['theme'] ?? null) === 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Draws the page in the theme of the account before the first paint, so it never flashes. The server sets `dark` on <html> for a dark theme; only `system` (or no theme, as for a guest) lets the device decide. --}}
        <script>
            if (@js($page['props']['theme'] ?? 'system') === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark');
            }
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: var(--canvas);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- The app on the Home screen. iPhone and iPad read these headers too; app.ts adds the manifest, with one of its own for iPhone and iPad (see addManifestLink in lib/pwa/install.ts). --}}
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="theme-color" content="#f4f6fa" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0f141b" media="(prefers-color-scheme: dark)">

        @fonts

        {{-- What search engines and link previews read without running the pages: see App\\Support\\PageMeta. --}}
        <meta name="description" content="{{ $head->description }}">
        @if ($head->indexable)
            <link rel="canonical" href="{{ $head->canonical }}">
        @else
            <meta name="robots" content="noindex, nofollow">
        @endif
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $head->title }}">
        <meta property="og:description" content="{{ $head->description }}">
        @if ($head->canonical)
            <meta property="og:url" content="{{ $head->canonical }}">
        @endif
        <meta property="og:image" content="{{ $head->image }}">
        <meta property="og:image:width" content="{{ App\Support\PageMeta::IMAGE_WIDTH }}">
        <meta property="og:image:height" content="{{ App\Support\PageMeta::IMAGE_HEIGHT }}">
        <meta property="og:image:alt" content="{{ config('app.name') }}">
        <meta property="og:locale" content="{{ $head->locale }}">
        <meta name="twitter:card" content="summary_large_image">

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ $head->title }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
