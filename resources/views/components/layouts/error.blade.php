@props(['code'])

@php
    /*
     * An error page (U-02). It can render before the locale middleware ran (an unknown address never reaches the
     * route's middleware), so it picks the language itself: the session's choice when there is a session, else the
     * browser's preferred language.
     */
    $chosen = request()->hasSession() ? \App\Enums\Locale::tryFrom((string) request()->session()->get(\App\Http\Middleware\SetLocale::SessionKey)) : null;
    $locale = $chosen ?? \App\Enums\Locale::tryFrom((string) request()->getPreferredLanguage(['en', 'ar'])) ?? \App\Enums\Locale::current();
    app()->setLocale($locale->value);

    $key = in_array((string) $code, ['403', '404', '419', '500', '503'], true) ? (string) $code : '500';
    $signIn = $key === '419';
@endphp

<!DOCTYPE html>
<html lang="{{ $locale->value }}" dir="{{ $locale->direction() }}">
    <head>
        @include('partials.head', ['title' => __('errors.'.$key.'.title')])
    </head>
    <body class="min-h-screen bg-auth-ground antialiased dark:bg-surface-0">
        <main class="flex min-h-svh flex-col items-center justify-center gap-8 p-6 md:p-10" data-test="error-page" data-code="{{ $key }}">
            <img src="{{ asset('logo.png') }}" alt="{{ __('common.app_name') }}" class="h-14 w-auto" data-no-dim />

            <div class="w-full max-w-md overflow-hidden rounded-2xl border border-secondary-soft bg-white shadow-card dark:border-line dark:bg-surface-1">
                <div class="flex items-center gap-4 border-b border-secondary-soft bg-surface-2 px-8 py-5 dark:border-line dark:bg-white/[0.03]">
                    <span class="text-5xl font-bold leading-none tracking-tight text-secondary-dark tabular-nums dark:text-ink" dir="ltr">{{ $key }}</span>
                    <span class="h-10 w-px bg-secondary-soft dark:bg-line" aria-hidden="true"></span>
                    <span class="text-xs font-semibold text-secondary-medium dark:text-ink-muted">{{ __('errors.label') }}</span>
                </div>

                <div class="flex flex-col gap-3 px-8 py-6">
                    <h1 class="text-xl font-bold text-secondary-dark dark:text-ink">{{ __('errors.'.$key.'.title') }}</h1>
                    <p class="text-sm leading-relaxed text-secondary-medium dark:text-ink-muted">{{ __('errors.'.$key.'.body') }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-3 px-8 pb-8">
                    @if ($signIn)
                        <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary px-5 text-sm font-semibold text-white hover:bg-brand-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary dark:bg-brand" data-test="error-sign-in">{{ __('errors.sign_in') }}</a>
                    @else
                        <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary px-5 text-sm font-semibold text-white hover:bg-brand-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary dark:bg-brand" data-test="error-home">{{ __('errors.home') }}</a>
                        <button type="button" onclick="history.back()" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-secondary-light px-5 text-sm font-semibold text-secondary-dark hover:bg-secondary-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary dark:border-line-strong dark:text-ink dark:hover:bg-surface-3">{{ __('errors.back') }}</button>
                    @endif
                </div>
            </div>

            <p class="text-center text-xs text-secondary-medium dark:text-ink-muted">{{ __('common.copyright_notice', ['year' => now()->year]) }}</p>
        </main>
    </body>
</html>
