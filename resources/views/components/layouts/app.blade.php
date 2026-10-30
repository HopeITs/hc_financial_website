<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Enums\Locale::current()->direction() }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-ground antialiased dark:bg-surface-0">
        <x-navigation.topbar />
        <x-navigation.sidebar />

        <flux:main class="bg-ground text-secondary-dark dark:bg-surface-0 dark:text-ink">
            <div class="mx-auto flex min-h-full w-full max-w-[90rem] min-[150rem]:max-w-[110rem] flex-1 flex-col pt-4">
                {{ $slot }}
            </div>
        </flux:main>

        <flux:footer class="border-t border-secondary-soft bg-white py-4 text-center dark:border-line dark:bg-surface-1">
            <p class="text-xs text-secondary-medium dark:text-ink-muted">
                {{ __('common.copyright_notice', ['year' => now()->year]) }}
            </p>
        </flux:footer>

        @auth
            <x-shortcuts-help />

            @persist('assistant')
                <livewire:assistant-panel />
            @endpersist
        @endauth

        @persist('toast')
            <flux:toast.group position="center">
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
