@props(['items'])

@php
    // Each kind of waiting work: its icon and the status colour it takes when something is there.
    $looks = [
        'approve' => ['icon' => 'clock', 'tone' => 'warn'],
        'rejected' => ['icon' => 'x-mark', 'tone' => 'critical'],
        'decide' => ['icon' => 'clipboard-document-check', 'tone' => 'warn'],
        'my_requests' => ['icon' => 'paper-airplane', 'tone' => 'active'],
    ];
    $tones = [
        'warn' => 'bg-status-warn-soft text-status-warn',
        'critical' => 'bg-status-critical-soft text-status-critical',
        'active' => 'bg-status-active-soft text-status-active',
    ];
    $total = collect($items)->sum('count');
@endphp

{{-- U-03: what waits for the signed-in person, one cell per kind their role deals with; each opens the filtered list. The 1px gap on a line-coloured grid draws the dividers. --}}
<x-ui.panel :title="__('home.waiting.title')" :subtitle="$total > 0 ? __('home.waiting.subtitle') : __('home.waiting.all_clear')" data-test="home-waiting">
    <ul role="list" @class([
        'grid gap-px bg-secondary-soft dark:bg-line',
        'sm:grid-cols-2' => count($items) > 1,
        'xl:grid-cols-3' => count($items) === 3,
        'xl:grid-cols-4' => count($items) === 4,
    ])>
        @foreach ($items as $item)
            @php($look = $looks[$item['key']])
            <li wire:key="waiting-{{ $item['key'] }}" class="bg-white sm:last:odd:col-span-2 xl:last:odd:col-span-1 dark:bg-surface-1">
                <a href="{{ $item['url'] }}" wire:navigate class="group flex h-full min-h-24 items-center gap-4 px-5 py-4 hover:bg-surface-2 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary dark:hover:bg-white/[0.04]" data-test="waiting-{{ $item['key'] }}">
                    <span @class([
                        'flex size-10 shrink-0 items-center justify-center rounded-lg',
                        $tones[$look['tone']] => $item['count'] > 0,
                        'bg-surface-2 text-secondary-light dark:bg-surface-3 dark:text-ink-faint' => $item['count'] === 0,
                    ]) aria-hidden="true">
                        <flux:icon :name="$look['icon']" variant="mini" class="size-5" />
                    </span>
                    <span class="flex min-w-0 flex-1 flex-col">
                        <span @class([
                            'text-2xl font-bold leading-tight tabular-nums',
                            'text-secondary-dark dark:text-ink' => $item['count'] > 0,
                            'text-secondary-light dark:text-ink-faint' => $item['count'] === 0,
                        ]) data-test="waiting-count">{{ number_format($item['count']) }}</span>
                        <span class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('home.waiting.items.'.$item['key'], ['days' => \App\Services\Home\WaitingWork::REJECTED_DAYS]) }}</span>
                    </span>
                    <flux:icon.chevron-right variant="micro" class="size-4 shrink-0 text-secondary-light group-hover:text-secondary-dark rtl:rotate-180 dark:text-ink-faint dark:group-hover:text-ink" />
                </a>
            </li>
        @endforeach
    </ul>
</x-ui.panel>
