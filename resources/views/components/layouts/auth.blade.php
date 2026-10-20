<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Enums\Locale::current()->direction() }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-auth-ground antialiased dark:bg-surface-0">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col items-center gap-6">
                <img src="{{ asset('logo.png') }}" alt="{{ __('common.app_name') }}" class="h-16 w-auto" data-no-dim />

                <div class="w-full rounded-2xl border border-secondary-soft bg-white p-8 shadow-card dark:border-line dark:bg-surface-1">
                    {{ $slot }}
                </div>

                <div class="flex flex-col items-center gap-3">
                    <x-locale-toggle />

                    <p class="text-center text-xs text-secondary-medium dark:text-ink-muted">
                        {{ __('common.copyright_notice', ['year' => now()->year]) }}
                    </p>
                </div>
            </div>
        </div>

        @fluxScripts
    </body>
</html>
