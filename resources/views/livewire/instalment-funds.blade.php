<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="instalment-funds">
    <x-ui.page-header :title="__('navigation.screens.loanfunds')" :subtitle="__('funds.intro')" />

    @if ($notice)
        <div class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" role="status" data-test="notice">{{ $notice }}</div>
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

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <x-ui.stat-tile :label="__('funds.total_value')" :value="$totalValue" unit="USD" icon="banknotes" data-test="funds-total" />
        <x-ui.stat-tile :label="__('funds.today_rate')" :value="$todayRate !== '' ? $todayRate : '—'" unit="SYP / USD" icon="calendar" />
    </div>
    @if ($todayRate === '')
        <p class="text-sm text-status-warn">{{ __('funds.no_rate') }}</p>
    @endif

    <x-ui.panel :title="__('funds.balances_title')" :subtitle="__('funds.balances_hint')">
        <x-ui.scroll-table :paginate="false">
            <table class="fin-table" data-test="funds-table">
                <thead>
                    <tr>
                        <th>{{ __('funds.funder') }}</th>
                        <th>{{ __('funds.city') }}</th>
                        <th class="fin-num">{{ __('funds.cash_syp') }}</th>
                        <th class="fin-num">{{ __('funds.cash_usd') }}</th>
                        <th class="fin-num">{{ __('funds.owed_syp') }}</th>
                        <th class="fin-num">{{ __('funds.owed_usd') }}</th>
                        <th class="fin-num">{{ __('funds.fixed') }}</th>
                        <th class="fin-num">{{ __('funds.value_today') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="fund-{{ md5($row['funder'].'|'.$row['city']) }}" data-test="fund-row">
                            <td class="font-medium">{{ $row['funder'] }}</td>
                            <td>{{ $row['city'] !== '' ? $row['city'] : '—' }}</td>
                            <td class="fin-num" dir="ltr">{{ $row['labels']['syp'] }}</td>
                            <td class="fin-num" dir="ltr">{{ $row['labels']['usd'] }}</td>
                            <td class="fin-num" dir="ltr">{{ $row['labels']['owed_syp'] }}</td>
                            <td class="fin-num" dir="ltr">{{ $row['labels']['owed_usd'] }}</td>
                            <td class="fin-num" dir="ltr">
                                @if ((float) $row['fixed_syp'] > 0)
                                    {{ $row['labels']['fixed_syp'] }} SYP → {{ $row['labels']['fixed_usd'] }} USD
                                @else
                                    —
                                @endif
                            </td>
                            <td class="fin-num font-semibold" dir="ltr" data-test="fund-value">{{ $row['value_label'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-ui.empty-state icon="banknotes" :heading="__('funds.none')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.scroll-table>
    </x-ui.panel>

    @if ($canExchange)
        <x-ui.panel :title="__('funds.exchange_title')" :subtitle="__('funds.exchange_hint')">
            <form wire:submit="exchange" class="flex flex-col gap-4 p-4" data-test="exchange-form">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.form-select wire:model.live="fund" :label="__('funds.fund')" :placeholder="__('funds.choose')" required data-test="exchange-fund">
                        <option value="">{{ __('funds.choose') }}</option>
                        @foreach ($funds as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </x-ui.form-select>
                    <x-ui.form-select wire:model="kind" :label="__('funds.kind')" data-test="exchange-kind">
                        <option value="{{ \App\Models\FundExchange::FIX }}">{{ __('funds.kinds.fix') }}</option>
                        <option value="{{ \App\Models\FundExchange::EXCHANGE }}">{{ __('funds.kinds.exchange') }}</option>
                    </x-ui.form-select>
                    <x-ui.form-input type="text" inputmode="decimal" dir="ltr" wire:model="pounds" :label="__('funds.pounds')" required data-test="exchange-pounds" />
                    <x-ui.form-input type="text" inputmode="decimal" dir="ltr" wire:model="rate" :label="__('funds.rate')" :help="__('funds.rate_help')" required data-test="exchange-rate" />
                    <x-ui.form-input type="date" wire:model.live="date" :label="__('funds.date')" required data-test="exchange-date" />
                    <x-ui.form-select wire:model="poundAccount" :label="__('funds.pound_account')" :placeholder="__('funds.choose')" required data-test="exchange-pound-account">
                        <option value="">{{ __('funds.choose') }}</option>
                        @foreach ($poundAccounts as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </x-ui.form-select>
                    <x-ui.form-select wire:model="dollarAccount" :label="__('funds.dollar_account')" :placeholder="__('funds.choose')" required data-test="exchange-dollar-account">
                        <option value="">{{ __('funds.choose') }}</option>
                        @foreach ($dollarAccounts as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </x-ui.form-select>
                </div>
                <div><x-ui.button type="submit" tone="primary" icon="arrows-right-left" wire:confirm="{{ __('funds.exchange_confirm') }}" data-test="save-exchange">{{ __('funds.exchange') }}</x-ui.button></div>
            </form>
        </x-ui.panel>
    @endif

    <x-ui.panel :title="__('funds.open_title')" :subtitle="__('funds.open_hint')" :count="count($open)">
        <x-ui.scroll-table :paginate="false">
            <table class="fin-table" data-test="open-fixes">
                <thead>
                    <tr>
                        <th>{{ __('funds.funder') }}</th>
                        <th>{{ __('funds.city') }}</th>
                        <th>{{ __('funds.date') }}</th>
                        <th class="fin-num">{{ __('funds.pounds') }}</th>
                        <th class="fin-num">{{ __('funds.fixed_usd') }}</th>
                        <th class="fin-num">{{ __('funds.rate') }}</th>
                        <th class="fin-num">{{ __('funds.saving_today') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($open as $item)
                        <tr wire:key="fix-{{ $item['fix']->id }}" data-test="fix-{{ $item['fix']->id }}">
                            <td>{{ $item['fix']->funder }}</td>
                            <td>{{ $item['fix']->city }}</td>
                            <td dir="ltr" class="text-start">{{ $item['fix']->fixed_on->format('d/m/Y') }}</td>
                            <td class="fin-num" dir="ltr">{{ $item['syp'] }}</td>
                            <td class="fin-num" dir="ltr">{{ $item['usd'] }}</td>
                            <td class="fin-num" dir="ltr">{{ $item['rate'] }}</td>
                            <td class="fin-num" dir="ltr">{{ $item['saving'] }}</td>
                            <td class="whitespace-nowrap">
                                @if ($canExchange)
                                    <div class="flex items-end gap-2">
                                        <div class="w-28"><x-ui.form-input type="text" inputmode="decimal" dir="ltr" wire:model="backRate.{{ $item['fix']->id }}" :label="__('funds.back_rate')" :placeholder="$todayRate" /></div>
                                        <x-ui.button size="sm" icon="arrow-uturn-left" wire:click="giveBack({{ $item['fix']->id }})" wire:confirm="{{ __('funds.give_back_confirm') }}" data-test="give-back-{{ $item['fix']->id }}">{{ __('funds.give_back') }}</x-ui.button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="fin-empty">{{ __('funds.no_open') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.scroll-table>
    </x-ui.panel>

    @if ($history->isNotEmpty())
        <x-ui.panel :title="__('funds.history_title')">
            <x-ui.scroll-table :paginate="false">
                <table class="fin-table" data-test="fund-history">
                    <thead>
                        <tr>
                            <th>{{ __('funds.date') }}</th>
                            <th>{{ __('funds.kind') }}</th>
                            <th>{{ __('funds.funder') }}</th>
                            <th>{{ __('funds.city') }}</th>
                            <th class="fin-num">{{ __('funds.pounds') }}</th>
                            <th class="fin-num">{{ __('funds.fixed_usd') }}</th>
                            <th>{{ __('funds.vouchers') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($history as $item)
                            <tr wire:key="history-{{ $item->id }}">
                                <td dir="ltr" class="text-start">{{ $item->fixed_on->format('d/m/Y') }}</td>
                                <td>{{ __('funds.kinds.'.$item->kind) }}@if ($item->returned_on) · {{ __('funds.returned_on', ['date' => $item->returned_on->format('d/m/Y')]) }}@endif</td>
                                <td>{{ $item->funder }}</td>
                                <td>{{ $item->city }}</td>
                                <td class="fin-num"><x-money :amount="$item->syp_amount" /></td>
                                <td class="fin-num"><x-money :amount="$item->usd_amount" /></td>
                                <td dir="ltr">
                                    <a href="{{ route(\App\Support\Navigation::routeName('vouchers'), ['voucher' => $item->voucher_id]) }}" wire:navigate class="underline">{{ $item->voucher_id }}</a>
                                    @if ($item->return_voucher_id)
                                        · <a href="{{ route(\App\Support\Navigation::routeName('vouchers'), ['voucher' => $item->return_voucher_id]) }}" wire:navigate class="underline">{{ $item->return_voucher_id }}</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.scroll-table>
        </x-ui.panel>
    @endif
</div>
