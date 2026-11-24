<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="approvals-inbox">
    <x-ui.page-header :title="__('navigation.screens.inbox')" :subtitle="__('approvals.intro')" />

    @if ($notice)
        <div class="rounded-lg border border-status-good bg-status-good-soft px-4 py-2 text-sm text-status-good" role="status" data-test="notice">{{ $notice }}</div>
    @endif
    @if ($problems !== [])
        <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="problems">
            <ul class="list-disc ps-5">
                @foreach ($problems as $problem)
                    <li>{{ $problem }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @forelse ($inbox as $kind => $items)
        <x-ui.panel :title="__('approvals.kinds.'.$kind)" :count="count($items)" data-test="kind-{{ $kind }}">
            <x-slot:toolbar>
                @if (count($items) > 1)
                    <x-ui.button size="sm" icon="check" wire:click="approveAll('{{ $kind }}')" wire:confirm="{{ __('approvals.approve_all_confirm', ['count' => count($items)]) }}" data-test="approve-all-{{ $kind }}">{{ __('approvals.approve_all') }}</x-ui.button>
                @endif
            </x-slot:toolbar>
            <ul role="list" class="divide-y divide-secondary-soft dark:divide-line">
                @foreach ($items as $item)
                    <li class="flex flex-wrap items-center gap-3 px-4 py-3" wire:key="{{ $kind }}-{{ $item['id'] }}" data-test="item-{{ $kind }}-{{ $item['id'] }}">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-secondary-dark dark:text-ink" dir="auto">{{ $item['title'] }}</p>
                            <p class="truncate text-sm text-secondary-medium dark:text-ink-muted">{{ $item['detail'] }}</p>
                        </div>
                        <span class="text-sm font-semibold tabular-nums"><x-money :amount="$item['amount']" :currency="$item['currency']" /></span>
                        <x-ui.button size="sm" tone="ghost" icon="eye" :href="$item['url']" wire:navigate>{{ __('approvals.open') }}</x-ui.button>
                        <x-ui.button size="sm" tone="primary" icon="check" wire:click="approve('{{ $kind }}', '{{ $item['id'] }}')" data-test="approve-{{ $kind }}-{{ $item['id'] }}">{{ __('approvals.approve') }}</x-ui.button>
                    </li>
                @endforeach
            </ul>
        </x-ui.panel>
    @empty
        <x-ui.empty-state icon="check-circle" :heading="__('approvals.empty')" :body="__('approvals.empty_hint')" data-test="inbox-empty" />
    @endforelse
</div>
