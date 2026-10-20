<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

<title>
    {{ filled($title ?? null) ? $title.' - '.__('common.app_name') : __('common.app_name') }}
</title>

<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<meta name="theme-color" content="#dcddde" media="(prefers-color-scheme: light)" data-theme-color="light">
<meta name="theme-color" content="#1c1d20" media="(prefers-color-scheme: dark)" data-theme-color="dark">

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
