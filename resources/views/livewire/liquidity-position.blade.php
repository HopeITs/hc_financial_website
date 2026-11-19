<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="liquidity">
    <x-ui.page-header :title="__('navigation.screens.treasury')" :subtitle="__('treasury.position_intro')" />

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

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.form-input type="date" wire:model.live="asAt" :label="__('treasury.as_at')" data-test="as-at" />
        <x-ui.stat-tile :label="__('treasury.total_value')" :value="$totals['value']" unit="USD" icon="banknotes" />
        <x-ui.stat-tile :label="__('treasury.total_book')" :value="$totals['book']" unit="USD" icon="book-open" />
    </div>

    @if ($position['missing_rates'] !== [])
        <p class="text-sm text-status-warn" data-test="missing-rates">{{ __('treasury.position_missing_rates', ['currencies' => implode(', ', $position['missing_rates'])]) }}</p>
    @endif

    @forelse ($position['groups'] as $group)
        <x-ui.panel :title="match (true) { $group['key'] === 'banks' => __('treasury.group_banks'), $group['key'] === 'company' => __('treasury.group_company'), default => __('treasury.group_city', ['city' => substr($group['key'], 5)]) }" wire:key="group-{{ md5($group['key']) }}">
            <div class="fin-table-wrap">
                <table class="fin-table" data-test="group-{{ md5($group['key']) }}">
                    <thead>
                        <tr>
                            <th>{{ __('treasury.account') }}</th>
                            <th class="fin-num">{{ __('treasury.balance') }}</th>
                            <th class="fin-num">{{ __('treasury.book_usd') }}</th>
                            <th class="fin-num">{{ __('treasury.value_usd') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group['rows'] as $row)
                            <tr wire:key="position-{{ $row['code'] }}" data-test="position-{{ $row['code'] }}">
                                <td>
                                    <span dir="ltr">{{ $row['code'] }}</span> · {{ $row['name'] }}
                                    @if (bccomp($row['pending'], '0', 2) !== 0)
                                        <div class="text-xs text-status-warn">{{ __('treasury.book_pending') }} <x-money :amount="$row['pending']" :currency="$row['currency']" /></div>
                                    @endif
                                </td>
                                <td class="fin-num"><x-money :amount="$row['orig']" :currency="$row['currency']" /></td>
                                <td class="fin-num"><x-money :amount="$row['usd']" /></td>
                                <td class="fin-num">@if ($row['value'] !== null)<x-money :amount="$row['value']" />@else — @endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-semibold">
                            <td colspan="3">{{ __('treasury.total') }}</td>
                            <td class="fin-num"><x-money :amount="$group['value']" currency="USD" /></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-ui.panel>
    @empty
        <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('treasury.no_money') }}</p>
    @endforelse

    <x-ui.panel :title="__('treasury.reval_title')" :subtitle="__('treasury.reval_intro')" data-test="reval">
        <div class="flex flex-col gap-4 p-4">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.form-input type="month" wire:model.live="month" :label="__('treasury.month')" data-test="reval-month" />
            </div>
            @if ($reval !== null)
                @foreach ($reval['missing_rates'] as $currency)
                    <p class="text-sm text-status-warn">{{ __('treasury.no_valuation_rate', ['currency' => $currency]) }}</p>
                @endforeach
                @foreach ($reval['provisional'] as $currency)
                    <p class="text-sm text-status-warn" data-test="reval-provisional">{{ __('treasury.reval_provisional', ['currency' => $currency]) }}</p>
                @endforeach
                <div class="fin-table-wrap">
                    <table class="fin-table" data-test="reval-table">
                        <thead>
                            <tr>
                                <th>{{ __('treasury.account') }}</th>
                                <th class="fin-num">{{ __('treasury.balance') }}</th>
                                <th class="fin-num">{{ __('treasury.book_usd') }}</th>
                                <th class="fin-num">{{ __('treasury.rate') }}</th>
                                <th class="fin-num">{{ __('treasury.value_usd') }}</th>
                                <th class="fin-num">{{ __('treasury.difference') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reval['rows'] as $row)
                                <tr wire:key="reval-{{ $row['code'] }}" data-test="reval-{{ $row['code'] }}">
                                    <td><span dir="ltr">{{ $row['code'] }}</span> · {{ $row['name'] }}</td>
                                    <td class="fin-num"><x-money :amount="$row['orig']" :currency="$row['currency']" /></td>
                                    <td class="fin-num"><x-money :amount="$row['usd']" /></td>
                                    <td class="fin-num" dir="ltr">{{ $row['rate'] !== null ? rtrim(rtrim($row['rate'], '0'), '.') : '—' }}</td>
                                    <td class="fin-num">@if ($row['value'] !== null)<x-money :amount="$row['value']" />@else — @endif</td>
                                    <td class="fin-num">@if ($row['difference'] !== null)<x-money :amount="$row['difference']" />@else — @endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="fin-empty">{{ __('treasury.reval_none') }}</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold">
                                <td colspan="5">{{ __('treasury.reval_total') }}</td>
                                <td class="fin-num" data-test="reval-difference"><x-money :amount="$reval['difference']" currency="USD" /></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @foreach ($reval['vouchers'] as $voucher)
                    <p class="text-sm" wire:key="reval-voucher-{{ $voucher['id'] }}">
                        {{ __('treasury.reval_voucher') }}
                        <a href="{{ route(\App\Support\Navigation::routeName('vouchers'), ['voucher' => $voucher['id']]) }}" wire:navigate class="underline" dir="ltr">{{ $voucher['id'] }}</a>
                        <flux:badge size="sm" :color="$voucher['status'] === \App\Enums\VoucherStatus::Approved ? 'green' : 'amber'">{{ $voucher['status']->label() }}</flux:badge>
                    </p>
                @endforeach
                @if ($canRevalue)
                    <div>
                        <x-ui.button tone="primary" icon="arrow-path" wire:click="revalue" wire:confirm="{{ __('treasury.reval_confirm') }}" data-test="revalue">{{ __('treasury.reval_prepare') }}</x-ui.button>
                    </div>
                @endif
            @endif
        </div>
    </x-ui.panel>
</div>
