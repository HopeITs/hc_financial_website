<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.alloc')" :subtitle="__('treasury.rates_intro')" />

    @if ($notice)
        <div class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" role="status" data-test="notice">{{ $notice }}</div>
    @endif

    @if ($problems !== [])
        <div class="rounded-lg border border-status-warn bg-status-warn-soft px-4 py-2 text-sm text-status-warn" role="alert" data-test="problems">
            <ul class="list-disc ps-5">
                @foreach ($problems as $problem)
                    <li>{{ $problem }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-ui.panel :title="__('treasury.monthly_title')" :subtitle="__('treasury.monthly_intro')">
        <div class="flex flex-col gap-4 p-4">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($currencies as $code)
                    @php($monthRate = $currentMonthly[$code])
                    <div data-test="month-now-{{ $code }}">
                        <x-ui.stat-tile
                            :label="__('treasury.monthly_now', ['currency' => $code])"
                            :value="$monthRate !== null ? rtrim(rtrim($monthRate['rate'], '0'), '.') : '—'"
                            :unit="in_array($code, $usdPerUnit, true) ? 'USD / '.$code : $code.' / USD'"
                            icon="calendar" />
                        @if ($monthRate === null)
                            <p class="mt-1 text-xs text-status-warn">{{ __('treasury.monthly_none_yet') }}</p>
                        @elseif ($monthRate['provisional'])
                            <p class="mt-1 text-xs text-status-warn">{{ __('treasury.monthly_from_earlier', ['month' => \Carbon\CarbonImmutable::parse($monthRate['month'].'-01')->format('m/Y')]) }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($canApprove)
                <form wire:submit="setMonthly" class="flex flex-col gap-3" data-test="monthly-form">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.form-select wire:model.live="monthlyCurrency" :label="__('treasury.currency')" required data-test="monthly-currency">
                            @foreach ($currencies as $code)
                                <option value="{{ $code }}">{{ $code }}</option>
                            @endforeach
                        </x-ui.form-select>
                        <x-ui.form-input wire:model.live="month" type="month" :label="__('treasury.month')" required data-test="monthly-month" />
                        <x-ui.form-input wire:model="monthlyRate" type="number" step="0.000001" min="0" :label="__('treasury.rate')" :help="in_array($monthlyCurrency, $usdPerUnit, true) ? __('treasury.rate_help_usd_per_unit', ['currency' => $monthlyCurrency]) : __('treasury.rate_help_per_usd', ['currency' => $monthlyCurrency])" dir="ltr" required data-test="monthly-rate" />
                        <x-ui.form-input wire:model="monthlyNote" :label="__('treasury.note')" data-test="monthly-note" />
                    </div>
                    <p class="text-sm text-secondary-medium dark:text-ink-muted" data-test="monthly-exchanged">
                        @if ($exchangedChosen !== null)
                            {{ __('treasury.exchanged_in_month', ['rate' => rtrim(rtrim($exchangedChosen['rate'], '0'), '.'), 'count' => $exchangedChosen['count'], 'usd' => $exchangedChosen['usd_label']]) }}
                        @else
                            {{ __('treasury.exchanged_none') }}
                        @endif
                    </p>
                    <div><x-ui.button type="submit" tone="primary" icon="check" data-test="save-monthly">{{ __('treasury.monthly_save') }}</x-ui.button></div>
                </form>
            @endif

            <div class="fin-table-wrap">
                <table class="fin-table" data-test="monthly-table">
                    <thead>
                        <tr>
                            <th>{{ __('treasury.month') }}</th>
                            <th>{{ __('treasury.currency') }}</th>
                            <th class="fin-num">{{ __('treasury.rate') }}</th>
                            <th class="fin-num">{{ __('treasury.exchanged_rate') }}</th>
                            <th>{{ __('treasury.set_by') }}</th>
                            <th>{{ __('treasury.note') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($months as $row)
                            <tr wire:key="month-{{ $row->id }}">
                                <td dir="ltr" class="text-start">{{ \Carbon\CarbonImmutable::parse($row->month.'-01')->format('m/Y') }}</td>
                                <td dir="ltr">{{ $row->currency }}</td>
                                <td class="fin-num" dir="ltr">{{ rtrim(rtrim($row->rate, '0'), '.') }}</td>
                                <td class="fin-num" dir="ltr">{{ $exchanged[$row->id] !== null ? rtrim(rtrim($exchanged[$row->id]['rate'], '0'), '.') : '—' }}</td>
                                <td>{{ $people[$row->set_by] ?? '' }}</td>
                                <td>{{ $row->note }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="fin-empty">{{ __('treasury.no_monthly') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-ui.panel>

    <flux:heading size="lg" level="2">{{ __('treasury.daily_title') }}</flux:heading>
    <p class="-mt-4 text-sm text-secondary-medium dark:text-ink-muted">{{ __('treasury.daily_intro') }}</p>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($currencies as $code)
            @php($rate = $current[$code])
            <div data-test="today-{{ $code }}">
                <x-ui.stat-tile
                    :label="__('treasury.rate_today', ['currency' => $code])"
                    :value="$rate !== null ? rtrim(rtrim($rate->rate, '0'), '.') : '—'"
                    :unit="in_array($code, $usdPerUnit, true) ? 'USD / '.$code : $code.' / USD'"
                    icon="currency-dollar" />
                @if ($rate === null)
                    <p class="mt-1 text-xs text-status-warn">{{ __('treasury.rate_none_yet') }}</p>
                @elseif (! $rate->day->isSameDay($today))
                    <p class="mt-1 text-xs text-status-warn">{{ __('treasury.rate_from_earlier', ['date' => $rate->day->format('d/m/Y')]) }}</p>
                @endif
            </div>
        @endforeach
    </div>

    @if ($canPropose)
        <form wire:submit="propose" data-test="rate-form">
            <x-ui.panel :title="$canApprove ? __('treasury.rate_enter') : __('treasury.rate_propose')" :subtitle="$canApprove ? __('treasury.rate_enter_hint') : __('treasury.rate_propose_hint')">
                <div class="flex flex-col gap-4 p-4">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.form-select wire:model.live="currency" :label="__('treasury.currency')" required data-test="rate-currency">
                            @foreach ($currencies as $code)
                                <option value="{{ $code }}">{{ $code }}</option>
                            @endforeach
                        </x-ui.form-select>
                        <x-ui.form-input wire:model="day" type="date" :label="__('treasury.date')" required data-test="rate-day" />
                        <x-ui.form-input wire:model="rate" type="number" step="0.000001" min="0" :label="__('treasury.rate')" :help="in_array($currency, $usdPerUnit, true) ? __('treasury.rate_help_usd_per_unit', ['currency' => $currency]) : __('treasury.rate_help_per_usd', ['currency' => $currency])" dir="ltr" required data-test="rate-value" />
                        <x-ui.form-input wire:model="note" :label="__('treasury.note')" data-test="rate-note" />
                    </div>
                    <div><x-ui.button type="submit" tone="primary" icon="check" data-test="save-rate">{{ $canApprove ? __('treasury.rate_save_approved') : __('treasury.rate_send') }}</x-ui.button></div>
                </div>
            </x-ui.panel>
        </form>
    @endif

    <x-ui.scroll-table>
        <table class="fin-table" data-test="rates-table">
            <thead>
                <tr>
                    <th>{{ __('treasury.date') }}</th>
                    <th>{{ __('treasury.currency') }}</th>
                    <th class="fin-num">{{ __('treasury.rate') }}</th>
                    <th>{{ __('treasury.status') }}</th>
                    <th>{{ __('treasury.proposed_by') }}</th>
                    <th>{{ __('treasury.decided_by') }}</th>
                    <th>{{ __('treasury.note') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rates as $row)
                    <tr wire:key="rate-{{ $row->id }}" data-test="rate-row-{{ $row->id }}">
                        <td dir="ltr" class="text-start">{{ $row->day->format('d/m/Y') }}</td>
                        <td dir="ltr">{{ $row->currency }}</td>
                        <td class="fin-num" dir="ltr">{{ rtrim(rtrim($row->rate, '0'), '.') }}</td>
                        <td>
                            <flux:badge size="sm" :color="match ($row->status) { 'approved' => 'green', 'pending' => 'amber', 'rejected' => 'red', default => 'zinc' }">{{ __('treasury.rate_status.'.$row->status) }}</flux:badge>
                        </td>
                        <td>{{ $people[$row->proposed_by] ?? '' }}</td>
                        <td>{{ $row->decided_by !== null ? ($people[$row->decided_by] ?? '') : '' }}</td>
                        <td>{{ $row->note }}</td>
                        <td class="whitespace-nowrap">
                            @if ($canApprove && $row->status === 'pending' && $row->proposed_by !== $me)
                                <x-ui.button size="sm" tone="primary" wire:click="approve({{ $row->id }})" data-test="approve-rate-{{ $row->id }}">{{ __('treasury.approve') }}</x-ui.button>
                                <x-ui.button size="sm" wire:click="reject({{ $row->id }})" wire:confirm="{{ __('treasury.reject_confirm') }}" data-test="reject-rate-{{ $row->id }}">{{ __('treasury.reject') }}</x-ui.button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="fin-empty">{{ __('treasury.no_rates') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.scroll-table>
</div>
