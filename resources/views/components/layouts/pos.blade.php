<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Enums\Locale::current()->direction() }}">
    <head>
        @include('partials.head')
    </head>
    {{-- The point of sale's own full-screen frame (finance request of 2026-11-27): no menus or footer, only the work. --}}
    <body class="min-h-screen bg-ground text-secondary-dark antialiased dark:bg-surface-0 dark:text-ink">
        {{ $slot }}

        @persist('toast')
            <flux:toast.group position="center">
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
