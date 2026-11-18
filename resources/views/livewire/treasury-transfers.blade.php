<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.transfers')" :subtitle="__('treasury.transfers_intro')" />

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

    @if ($canCreate)
        <form wire:submit="save" data-test="move-form">
            <x-ui.panel :title="__('treasury.kind.'.$kind)" :subtitle="__('treasury.kind_hint.'.$kind)">
                <x-slot:toolbar>
                    <x-ui.form-segmented wire:model.live="kind" :label="__('treasury.kind_label')" :options="collect($kinds)->map(fn ($key) => ['value' => $key, 'label' => __('treasury.kind.'.$key), 'test' => 'kind-'.$key])->all()" data-test="move-kind" />
                </x-slot:toolbar>
                <div class="flex flex-col gap-4 p-4">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <x-ui.form-select wire:model.live="from" :label="__('treasury.from')" :placeholder="__('treasury.choose')" searchable required data-test="move-from">
                            <option value="">{{ __('treasury.choose') }}</option>
                            @foreach ($accounts as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.form-select>
                        <x-ui.form-select wire:model.live="to" :label="__('treasury.to')" :placeholder="__('treasury.choose')" searchable required data-test="move-to">
                            <option value="">{{ __('treasury.choose') }}</option>
                            @foreach ($targets as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.form-select>
                        <x-ui.form-input wire:model.live="day" type="date" :label="__('treasury.date')" required data-test="move-day" />
                        <x-ui.form-input wire:model.live.blur="amount" type="number" step="0.01" min="0" :label="$kind === 'exchange' ? __('treasury.given', ['currency' => $source?->currency ?? '']) : __('treasury.sent', ['currency' => $source?->currency ?? ''])" dir="ltr" required data-test="move-amount" />
                        @if ($kind === 'exchange')
                            <x-ui.form-input wire:model.live.blur="received" type="number" step="0.01" min="0" :label="__('treasury.received_in', ['currency' => $target?->currency ?? ''])" dir="ltr" required data-test="move-received" />
                        @elseif ($withCompany)
                            <x-ui.form-input wire:model="received" type="number" step="0.01" min="0" :label="__('treasury.arrived')" :help="__('treasury.arrived_help')" dir="ltr" data-test="move-received" />
                        @endif
                        @if ($withCompany)
                            <x-ui.form-input wire:model="contact" :label="__('treasury.contact')" :help="__('treasury.contact_help')" data-test="move-contact" />
                        @endif
                        @if ($needsCenter)
                            <x-ui.form-input wire:model="center" :label="__('treasury.center')" required data-test="move-center" />
                        @endif
                        <x-ui.form-input wire:model="note" :label="__('treasury.note')" data-test="move-note" />
                    </div>

                    @if ($kind === 'transfer' && $source !== null && $source->currency !== 'USD')
                        <p class="text-sm text-secondary-medium dark:text-ink-muted" data-test="transfer-rate">
                            @if ($rate !== null)
                                {{ __('treasury.valued_at', ['rate' => rtrim(rtrim($rate->rate, '0'), '.'), 'currency' => $source->currency, 'date' => $rate->day->format('d/m/Y')]) }}
                            @else
                                <span class="text-status-warn">{{ __('treasury.no_rate', ['currency' => $source->currency, 'date' => \Carbon\CarbonImmutable::parse($day)->format('d/m/Y')]) }}</span>
                            @endif
                        </p>
                    @endif

                    @if ($exchangeRates !== null)
                        <p class="text-sm text-secondary-medium dark:text-ink-muted" data-test="exchange-rate">
                            {{ __('treasury.actual_rate', ['rate' => rtrim(rtrim($exchangeRates['actual'], '0'), '.'), 'currency' => $exchangeRates['currency']]) }}
                            @if ($exchangeRates['approved'] !== null)
                                · {{ __('treasury.approved_rate', ['rate' => rtrim(rtrim($exchangeRates['approved'], '0'), '.')]) }}
                            @endif
                        </p>
                    @endif

                    <div><x-ui.button type="submit" tone="primary" icon="arrows-right-left" data-test="save-move">{{ __('treasury.save') }}</x-ui.button></div>
                </div>
            </x-ui.panel>
        </form>
    @endif

    <x-ui.panel :title="__('treasury.recent')">
        <div class="fin-table-wrap">
            <table class="fin-table" data-test="recent-moves">
                <thead>
                    <tr>
                        <th>{{ __('treasury.voucher') }}</th>
                        <th>{{ __('treasury.date') }}</th>
                        <th>{{ __('treasury.from') }}</th>
                        <th>{{ __('treasury.to') }}</th>
                        <th>{{ __('treasury.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recent as $voucher)
                        @php($out = $voucher->lines->first(fn ($line) => (float) $line->credit_orig > 0))
                        @php($in = $voucher->lines->first(fn ($line) => (float) $line->debit_orig > 0 && $line->account_code !== \App\Services\Ledger\Rules\HawalaReceiptIsConsistent::COMMISSION_ACCOUNT))
                        <tr wire:key="move-{{ $voucher->id }}">
                            <td dir="ltr" class="text-start"><a href="{{ route(\App\Support\Navigation::routeName('vouchers'), ['voucher' => $voucher->id]) }}" wire:navigate class="underline">{{ $voucher->id }}</a></td>
                            <td dir="ltr" class="text-start">{{ $voucher->date->format('d/m/Y') }}</td>
                            <td dir="ltr" class="text-start">@if ($out) {{ $out->account_code }} · <x-money :amount="$out->credit_orig" :currency="$out->currency" /> @endif</td>
                            <td dir="ltr" class="text-start">@if ($in) {{ $in->account_code }} · <x-money :amount="$in->debit_orig" :currency="$in->currency" /> @endif</td>
                            <td><flux:badge size="sm" :color="$voucher->status === \App\Enums\VoucherStatus::Approved ? 'green' : 'amber'">{{ $voucher->status->label() }}</flux:badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="fin-empty">{{ __('treasury.no_moves') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.panel>
</div>
