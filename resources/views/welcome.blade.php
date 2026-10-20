<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="min-h-screen bg-white text-secondary-dark dark:bg-surface-0 dark:text-ink">
        <main class="mx-auto flex min-h-screen max-w-[90rem] items-center justify-center p-8">
            <h1 class="type-heading text-primary">{{ config('app.name') }}</h1>
        </main>
        @fluxScripts
    </body>
</html>
