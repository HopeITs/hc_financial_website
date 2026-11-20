<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

<title>
    {{ filled($title ?? null) ? $title.' - '.__('common.app_name') : __('common.app_name') }}
</title>

<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<meta name="theme-color" content="#dcddde" media="(prefers-color-scheme: light)" data-theme-color="light">
<meta name="theme-color" content="#1c1d20" media="(prefers-color-scheme: dark)" data-theme-color="dark">

{{-- Before the first paint: the chart sidebar's remembered width, or its folded rail (resources/js/sidebar-resize.js keeps them across pages). --}}
<script>
    try {
        const root = document.documentElement;
        const width = Number(localStorage.getItem('sidebar-width'));
        if (localStorage.getItem('sidebar-collapsed') === '1') {
            root.dataset.treeCollapsed = '1';
            root.style.setProperty('--sidebar-w', '56px');
        } else if (width) {
            root.style.setProperty('--sidebar-w', Math.min(640, Math.max(240, Math.round(width))) + 'px');
        }
    } catch (e) {}
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
