<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ur' ? 'rtl' : 'ltr' }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #f7f6f1;
            }

            html.dark {
                background-color: #0b1425;
            }
        </style>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        <link rel="preload" href="/fonts/manrope-variable.ttf" as="font" type="font/ttf" crossorigin>
        @if (app()->getLocale() === 'ur')
            <link rel="preload" href="/fonts/noto-nastaliq-urdu-variable.ttf" as="font" type="font/ttf" crossorigin>
        @endif

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body @class(['font-sans' => app()->getLocale() !== 'ur', 'antialiased'])>
        <x-inertia::app />
    </body>
</html>
