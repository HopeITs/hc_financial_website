@props(['current' => null])

@php
    // Home > the menu group > the screen, then the page itself when it is a sub-page of the screen
    // (a new project, a voucher form). The group has no page of its own, so it is plain text.
    $screen = request()->route('screen');
    $routeName = request()->route()?->getName();
    $group = $routeName === 'chart' ? 'chart' : collect((array) config('navigation.groups'))->filter(fn (array $screens): bool => $screen !== null && in_array($screen, $screens, true))->keys()->first();

    $items = [['label' => __('home.title'), 'url' => $routeName === 'home' ? null : route('home')]];

    if ($group !== null && $group !== 'chart') {
        $items[] = ['label' => __('navigation.groups.'.$group), 'url' => null];
    }

    if ($group !== null) {
        $isScreenPage = $routeName === \App\Support\Navigation::routeName($screen ?? 'chart') || $routeName === 'chart';
        $items[] = ['label' => __('navigation.screens.'.($routeName === 'chart' ? 'chart' : $screen)), 'url' => $isScreenPage ? null : route(\App\Support\Navigation::routeName($screen))];

        if (! $isScreenPage && $current !== null) {
            $items[] = ['label' => $current, 'url' => null];
        }
    }
@endphp

<nav aria-label="{{ __('common.breadcrumb') }}" data-test="breadcrumb" {{ $attributes }}>
    <ol class="page-crumb flex flex-wrap items-center gap-x-1.5 gap-y-0.5">
        @foreach ($items as $item)
            <li class="flex items-center gap-1.5">
                @if (! $loop->first)
                    <flux:icon.chevron-right variant="micro" class="size-3 opacity-60 rtl:rotate-180" aria-hidden="true" />
                @endif
                @if ($item['url'] !== null)
                    <a href="{{ $item['url'] }}" wire:navigate class="hover:text-secondary-dark hover:underline dark:hover:text-ink">{{ $item['label'] }}</a>
                @else
                    <span @if ($loop->last) aria-current="page" @endif>{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
