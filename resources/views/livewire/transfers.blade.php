<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.alloc')" :subtitle="__('ledger.transfers.intro')" />

    @if ($notice)
        <div class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" role="status" data-test="notice">{{ $notice }}</div>
    @endif

    @if ($failures !== [])
        <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="failures">
            @foreach ($failures as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    <section class="flex flex-col gap-3" data-test="rates">
        <h2 class="text-lg font-semibold">{{ __('ledger.transfers.rates') }}</h2>

        @if ($canCreate)
            <x-ui.panel class="grid items-end gap-4 p-4 sm:grid-cols-2 lg:grid-cols-5" data-test="rate-form">
                <x-ui.form-select wire:model="rateCity" :label="__('ledger.transfers.city')">
                    @foreach ($cities as $name)
                        <option value="{{ $name }}">{{ __('ledger.treasury.cities.'.$name) }}</option>
                    @endforeach
                </x-ui.form-select>
                <x-ui.form-input wire:model="rateValue" :label="__('ledger.transfers.rate')" dir="ltr" inputmode="decimal" />
                <x-ui.form-input type="date" wire:model="rateFrom" :label="__('ledger.transfers.effective_from')" />
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="rateAverageCost" data-test="rate-average-cost">
                    {{ __('ledger.transfers.average_cost') }}
                </label>
                <x-ui.button tone="primary" wire:click="propose" data-test="propose-rate">{{ __('ledger.transfers.propose') }}</x-ui.button>
            </x-ui.panel>
        @endif

        <x-ui.scroll-table>
            <table class="fin-table" data-test="rates-table">
                <thead>
                    <tr>
                        <th>{{ __('ledger.transfers.city') }}</th>
                        <th class="fin-num">{{ __('ledger.transfers.rate') }}</th>
                        <th>{{ __('ledger.transfers.effective_from') }}</th>
                        <th>{{ __('ledger.transfers.status') }}</th>
                        <th>{{ __('ledger.transfers.proposed_by') }}</th>
                        <th>{{ __('ledger.transfers.decided_by') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rates as $rate)
                        <tr data-test="rate-{{ $rate->id }}">
                            <td>{{ __('ledger.treasury.cities.'.$rate->city) }}</td>
                            <td class="fin-num" dir="ltr">{{ number_format((float) $rate->rate, 2) }}</td>
                            <td dir="ltr">{{ $rate->effective_from->toDateString() }}</td>
                            <td>
                                {{ __('ledger.transfers.status_'.$rate->status) }}
                                @if ($rate->allocate_at_average_cost)
                                    <span class="type-meta">· {{ __('ledger.transfers.average_cost') }}</span>
                                @endif
                            </td>
                            <td>{{ $names[$rate->proposed_by] ?? '' }}</td>
                            <td>{{ $rate->approved_by !== null ? ($names[$rate->approved_by] ?? '') : '' }}</td>
                            <td>
                                @if ($rate->status === 'pending' && $canApprove && $rate->proposed_by !== $userId)
                                    <x-ui.button tone="primary" wire:click="approve({{ $rate->id }})" data-test="approve-rate-{{ $rate->id }}">{{ __('ledger.transfers.approve') }}</x-ui.button>
                                    <x-ui.button wire:click="reject({{ $rate->id }})" data-test="reject-rate-{{ $rate->id }}">{{ __('ledger.transfers.reject') }}</x-ui.button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-secondary-medium dark:text-ink-muted">{{ __('ledger.transfers.no_rates') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.scroll-table>
    </section>

    @if ($canCreate)
        <section class="flex flex-col gap-3" data-test="transfer">
            <h2 class="text-lg font-semibold">{{ __('ledger.transfers.new') }}</h2>

            <x-ui.panel class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4" data-test="transfer-form">
                <x-ui.form-select wire:model.live="kind" :label="__('ledger.transfers.kind_label')" required>
                    @foreach ($kinds as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </x-ui.form-select>
                <x-ui.form-input type="date" wire:model.live="date" :label="__('ledger.builder.date')" required />
                <x-ui.form-select wire:model.live="city" :label="__('ledger.transfers.city')" required>
                    @foreach ($cities as $name)
                        <option value="{{ $name }}">{{ __('ledger.treasury.cities.'.$name) }}</option>
                    @endforeach
                </x-ui.form-select>
                @if ($showCurrency)
                    <x-ui.form-select wire:model.live="currency" :label="__('ledger.treasury.currency')" required>
                        <option value="USD">USD</option>
                        <option value="SYP">SYP</option>
                    </x-ui.form-select>
                @endif
                <x-ui.form-select wire:model.live="project" :label="$showOther ? __('ledger.transfers.from_project') : __('ledger.transfers.project')" required>
                    <option value="">—</option>
                    @foreach ($projects as $option)
                        <option value="{{ $option->code }}">{{ $option->code }} · {{ $option->name }}</option>
                    @endforeach
                </x-ui.form-select>
                @if ($showOther)
                    <x-ui.form-select wire:model.live="otherProject" :label="__('ledger.transfers.to_project')" required>
                        <option value="">—</option>
                        @foreach ($projects as $option)
                            <option value="{{ $option->code }}">{{ $option->code }} · {{ $option->name }}</option>
                        @endforeach
                    </x-ui.form-select>
                @endif
                <x-ui.form-input wire:model.live.debounce.400ms="amount" :label="__('ledger.transfers.amount', ['currency' => $effectiveCurrency])" dir="ltr" inputmode="decimal" required />
                <x-ui.form-select wire:model.live="cashAccount" :label="__('ledger.transfers.cash_account')" required>
                    @foreach ($cashAccounts as $option)
                        <option value="{{ $option->code }}">{{ $option->code }} · {{ app()->getLocale() === 'ar' ? $option->name_ar : $option->name_en }}</option>
                    @endforeach
                </x-ui.form-select>
                @if ($selected === \App\Enums\TransferKind::SypToProject)
                    <x-ui.form-input wire:model.live.debounce.400ms="contact" :label="__('ledger.transfers.contact')" :help="__('ledger.transfers.contact_help')" />
                @endif
                <div class="sm:col-span-2 lg:col-span-4">
                    <x-ui.form-textarea wire:model.live.debounce.400ms="description" :label="__('ledger.builder.description')" rows="2" />
                </div>
            </x-ui.panel>

            @if ($preview !== null)
                <div class="rounded-lg border border-secondary-light p-4 text-sm dark:border-ink-muted/30" data-test="preview">
                    @if ($preview->problems !== [])
                        @foreach ($preview->problems as $message)
                            <p class="text-status-critical" data-test="preview-problem">{{ $message }}</p>
                        @endforeach
                    @else
                        <p>{{ __('ledger.transfers.preview_rate') }}: <span dir="ltr" data-test="preview-rate">{{ number_format((float) $preview->rate, 2) }}</span>
                            · {{ __('ledger.transfers.preview_usd') }}: <span dir="ltr" data-test="preview-usd">{{ number_format((float) $preview->usd, 2) }}</span>
                            @if ($preview->difference !== null && bccomp($preview->difference, '0', 2) !== 0)
                                · {{ __('ledger.transfers.preview_difference') }}: <span dir="ltr" data-test="preview-difference">{{ number_format((float) $preview->difference, 2) }}</span>
                            @endif
                        </p>
                    @endif
                </div>
            @endif

            <div>
                <x-ui.button tone="primary" wire:click="save" data-test="save-transfer">{{ __('ledger.transfers.save') }}</x-ui.button>
            </div>
        </section>
    @endif
</div>
