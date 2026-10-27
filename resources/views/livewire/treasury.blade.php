<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.treasury')" :subtitle="__('ledger.treasury.intro')" />

    <div class="grid items-end gap-4 sm:grid-cols-2" data-test="treasury-filters">
        <x-ui.form-input type="date" wire:model.live="asAt" :label="__('ledger.treasury.as_at')" />
    </div>

    <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" data-test="treasury-totals">
        @foreach ([
            'tile_usd' => ['usd', number_format((float) $report->totalUsdHeld(), 2)],
            'tile_syp' => ['syp', number_format((float) $report->totalSypHeld(), 2)],
            'tile_transit' => ['transit', number_format((float) $report->totalInTransitUsd(), 2)],
            'tile_company' => ['company', number_format((float) $report->transferCompanyUsd(), 2)],
            'tile_reserve' => ['reserve', number_format((float) $report->totalReserve(), 2)],
        ] as $label => [$key, $value])
            <div class="rounded-lg border border-secondary-light p-4 dark:border-ink-muted/30" data-test="tile-{{ $key }}">
                <dt class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('ledger.treasury.'.$label) }}</dt>
                <dd class="text-xl font-semibold tabular-nums" dir="ltr">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    @foreach ($report->cities as $city)
        <section class="flex flex-col gap-3 rounded-lg border border-secondary-light p-4 dark:border-ink-muted/30" data-test="city-{{ $city->city }}">
            <h2 class="text-lg font-semibold">{{ $city->label() }}</h2>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <div class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('ledger.treasury.general_usd') }} · <span dir="ltr">{{ $city->usdProject }}</span></div>
                    <div class="text-lg font-semibold tabular-nums" dir="ltr" data-test="usd-held">{{ number_format((float) $city->usdHeld, 2) }}</div>
                </div>
                <div>
                    <div class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('ledger.treasury.general_syp') }} · <span dir="ltr">{{ $city->sypProject }}</span></div>
                    <div class="text-lg font-semibold tabular-nums" dir="ltr" data-test="syp-held">{{ number_format((float) $city->sypHeld, 2) }}</div>
                    <div class="type-meta text-secondary-medium dark:text-ink-muted">
                        {{ __('ledger.treasury.book_usd') }}: <span dir="ltr">{{ number_format((float) $city->sypBookUsd, 2) }}</span>
                        · {{ __('ledger.treasury.average_rate') }}: <span dir="ltr" data-test="syp-rate">{{ $city->sypAverageRate !== null ? number_format((float) $city->sypAverageRate, 2) : __('ledger.treasury.none') }}</span>
                    </div>
                </div>
            </div>

            <div class="type-meta text-secondary-dark dark:text-ink" data-test="purchases">
                <span class="font-semibold">{{ __('ledger.treasury.purchases') }}:</span>
                @if ($city->purchaseCount > 0)
                    {{ __('ledger.treasury.purchase_count') }} {{ $city->purchaseCount }}
                    · {{ __('ledger.treasury.pounds_bought') }} <span dir="ltr">{{ number_format((float) $city->purchasedSyp, 2) }}</span>
                    · {{ __('ledger.treasury.usd_paid') }} <span dir="ltr">{{ number_format((float) $city->purchasedUsd, 2) }}</span>
                    · {{ __('ledger.treasury.purchase_rate') }} <span dir="ltr">{{ $city->purchaseRate !== null ? number_format((float) $city->purchaseRate, 2) : __('ledger.treasury.none') }}</span>
                    · {{ __('ledger.treasury.last_purchase') }} <span dir="ltr">{{ $city->lastPurchase }}</span>
                @else
                    {{ __('ledger.treasury.no_purchases') }}
                @endif
            </div>

            <x-ui.scroll-table>
                <table class="fin-table" data-test="safes-{{ $city->city }}">
                    <thead>
                        <tr>
                            <th>{{ __('ledger.treasury.account') }}</th>
                            <th>{{ __('ledger.treasury.currency') }}</th>
                            <th class="fin-num">{{ __('ledger.treasury.balance') }}</th>
                            <th class="fin-num">{{ __('ledger.treasury.usd') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($city->safes as $row)
                            <tr>
                                <td><span dir="ltr">{{ $row->code }}</span> <a href="{{ route('reports.account-statement', ['account' => $row->code]) }}" class="underline">{{ $row->name() }}</a></td>
                                <td>{{ $row->currency }}</td>
                                <td class="fin-num" dir="ltr">{{ number_format((float) $row->orig, 2) }}</td>
                                <td class="fin-num" dir="ltr">{{ number_format((float) $row->usd, 2) }}</td>
                            </tr>
                        @endforeach
                        @foreach ($city->inTransit as $row)
                            <tr class="text-secondary-medium dark:text-ink-muted" data-test="transit-{{ $row->code }}">
                                <td><span dir="ltr">{{ $row->code }}</span> {{ $row->name() }} ({{ __('ledger.treasury.in_transit') }})</td>
                                <td>{{ $row->currency }}</td>
                                <td class="fin-num" dir="ltr">{{ number_format((float) $row->orig, 2) }}</td>
                                <td class="fin-num" dir="ltr">{{ number_format((float) $row->usd, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.scroll-table>

            <div class="type-meta text-secondary-medium dark:text-ink-muted">
                {{ __('ledger.treasury.reserve') }}: <span dir="ltr" data-test="reserve">{{ number_format((float) $city->reserve, 2) }}</span> USD
            </div>
        </section>
    @endforeach

    @if ($report->other !== [])
        <section class="flex flex-col gap-3" data-test="other-cash">
            <h2 class="text-lg font-semibold">{{ __('ledger.treasury.other_cash') }}</h2>
            <x-ui.scroll-table>
                <table class="fin-table">
                    <tbody>
                        @foreach ($report->other as $row)
                            <tr data-test="other-{{ $row->code }}">
                                <td><span dir="ltr">{{ $row->code }}</span> <a href="{{ route('reports.account-statement', ['account' => $row->code]) }}" class="underline">{{ $row->name() }}</a></td>
                                <td>{{ $row->currency }}</td>
                                <td class="fin-num" dir="ltr">{{ number_format((float) $row->orig, 2) }}</td>
                                <td class="fin-num" dir="ltr">{{ number_format((float) $row->usd, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.scroll-table>
        </section>
    @endif
</div>
