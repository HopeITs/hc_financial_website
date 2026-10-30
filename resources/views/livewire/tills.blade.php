<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.tills')" :subtitle="__('tills.intro')" />

    @if ($limited)
        <p class="text-sm text-secondary-medium dark:text-ink-muted" data-test="tills-limited">{{ __('tills.limited') }}</p>
    @endif

    @forelse ($tills as $till)
        <section class="flex flex-col gap-3 rounded-lg border border-secondary-light p-4 dark:border-ink-muted/30" data-test="till-{{ $till['code'] }}">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-lg font-semibold"><span dir="ltr">{{ $till['code'] }}</span> · {{ app()->getLocale() === 'ar' ? $till['name_ar'] : $till['name_en'] }}@if ($till['city']) · {{ $till['city'] }}@endif</h2>
                @unless ($limited)
                    <p class="text-lg font-semibold tabular-nums" dir="ltr" data-test="till-balance">{{ number_format((float) $till['balance'], 2) }} {{ $till['currency'] }} <span class="text-sm font-normal text-secondary-medium dark:text-ink-muted">(USD {{ number_format((float) $till['balance_usd'], 2) }})</span></p>
                @endunless
            </div>

            <x-ui.scroll-table :paginate="false">
                <table class="fin-table" data-test="till-owners-{{ $till['code'] }}">
                    <thead>
                        <tr>
                            <th>{{ __('tills.owner') }}</th>
                            <th class="fin-num">{{ $till['currency'] }}</th>
                            <th class="fin-num">USD</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($till['owners'] as $owner)
                            <tr wire:key="owner-{{ $till['code'] }}-{{ $owner['project_code'] }}">
                                <td><span dir="ltr">{{ $owner['project_code'] }}</span> · {{ $owner['project_name'] }}@if ($owner['general']) <span class="text-secondary-medium dark:text-ink-muted">({{ __('tills.general') }})</span>@endif</td>
                                <td class="fin-num" dir="ltr">{{ number_format((float) $owner['amount'], 2) }}</td>
                                <td class="fin-num" dir="ltr">{{ number_format((float) $owner['usd'], 2) }}</td>
                            </tr>
                        @endforeach
                        @unless ($limited)
                            @if (bccomp($till['unowned'], '0', 2) !== 0 || bccomp($till['unowned_usd'], '0', 2) !== 0)
                                <tr data-test="till-unowned-{{ $till['code'] }}">
                                    <td class="text-status-critical">{{ __('tills.unowned') }}</td>
                                    <td class="fin-num" dir="ltr">{{ number_format((float) $till['unowned'], 2) }}</td>
                                    <td class="fin-num" dir="ltr">{{ number_format((float) $till['unowned_usd'], 2) }}</td>
                                </tr>
                            @endif
                        @endunless
                    </tbody>
                </table>
            </x-ui.scroll-table>
        </section>
    @empty
        <p class="fin-empty" data-test="tills-empty">{{ __('tills.none') }}</p>
    @endforelse
</div>
