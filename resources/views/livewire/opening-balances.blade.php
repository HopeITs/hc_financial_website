@php($cell = 'w-full min-w-20 rounded-md border border-secondary-light bg-white px-2 py-1.5 text-sm text-secondary-dark disabled:opacity-60 dark:border-field-line dark:bg-surface-2 dark:text-ink')
<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" x-data="unsavedGuard" data-unsaved-message="{{ __('drafts.leave_warning') }}" x-on:form-clean.window="clean()">
    <x-ui.page-header :title="__('navigation.screens.opening')" :subtitle="__('ledger.opening.intro')" />

    <x-ui.form-draft :saved-at="$draftSavedAt" :notice="$draftNotice" />

    @if ($failures !== [])
        <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="failures">
            @foreach ($failures as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    <x-ui.panel class="grid gap-4 p-4 sm:grid-cols-2" data-test="opening-header">
        <x-ui.form-select wire:model.live="city" :label="__('ledger.transfers.city')" required>
            @foreach ($cities as $name)
                <option value="{{ $name }}">{{ __('ledger.treasury.cities.'.$name) }}</option>
            @endforeach
        </x-ui.form-select>
        <x-ui.form-input type="date" wire:model.live="date" :label="__('ledger.opening.date')" :help="__('ledger.opening.date_help')" required />
    </x-ui.panel>

    @if ($existing)
        <div class="rounded-lg border border-secondary-light px-4 py-3 text-sm dark:border-line-strong" data-test="opening-exists">
            {{ __('ledger.opening.exists', ['voucher' => $existing->id]) }}
            <a href="{{ route('entries.vouchers', ['voucher' => $existing->id]) }}" wire:navigate class="ms-1 font-medium text-primary hover:underline dark:text-brand-text">{{ __('ledger.opening.open_voucher') }}</a>
        </div>
    @else
        <section class="flex flex-col gap-3" aria-labelledby="opening-rows">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="opening-rows" class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('ledger.opening.rows') }}</h2>
                <p class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('ledger.opening.rows_help') }}</p>
            </div>

            <x-ui.scroll-table :paginate="false">
                <table class="fin-table" data-test="opening-rows">
                    <thead>
                        <tr>
                            <th>{{ __('ledger.builder.account') }}</th>
                            <th>{{ __('operations.owner') }}</th>
                            <th class="fin-num">{{ __('ledger.opening.balance') }}</th>
                            <th class="fin-num">{{ __('ledger.builder.rate') }}</th>
                            <th>{{ __('ledger.opening.contact') }}</th>
                            <th>{{ __('ledger.opening.center') }}</th>
                            <th><span class="sr-only">{{ __('ledger.builder.remove_line') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $n => $row)
                            @php($currency = $currencies[$row['account']] ?? null)
                            <tr wire:key="opening-row-{{ $n }}">
                                <td class="min-w-56">
                                    <select wire:model.live="rows.{{ $n }}.account" class="{{ $cell }}" aria-label="{{ __('ledger.builder.account') }} {{ $n + 1 }}" data-test="row-account-{{ $n }}">
                                        <option value="">—</option>
                                        @foreach ($accounts as $option)
                                            <option value="{{ $option->code }}">{{ $option->code }} · {{ $option->localizedName() }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="min-w-44">
                                    <select wire:model.live="rows.{{ $n }}.project" class="{{ $cell }}" aria-label="{{ __('operations.owner') }} {{ $n + 1 }}" data-test="row-project-{{ $n }}">
                                        <option value="">—</option>
                                        @foreach ($projects as $option)
                                            <option value="{{ $option->code }}">{{ $option->code }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="min-w-36">
                                    <div class="flex items-center gap-1.5">
                                        <input type="text" wire:model.live.debounce.400ms="rows.{{ $n }}.amount" inputmode="decimal" dir="ltr" class="{{ $cell }} text-end" aria-label="{{ __('ledger.opening.balance') }} {{ $n + 1 }}" data-test="row-amount-{{ $n }}">
                                        <span class="w-8 text-xs text-ink-muted" dir="ltr">{{ $currency && $currency !== 'Any' ? $currency : 'USD' }}</span>
                                    </div>
                                </td>
                                <td class="min-w-28">
                                    @if ($currency && ! in_array($currency, ['USD', 'Any'], true))
                                        <input type="text" wire:model.live.debounce.400ms="rows.{{ $n }}.rate" inputmode="decimal" dir="ltr" class="{{ $cell }} text-end" aria-label="{{ __('ledger.builder.rate') }} {{ $n + 1 }}" data-test="row-rate-{{ $n }}">
                                    @else
                                        <span class="text-ink-muted">—</span>
                                    @endif
                                </td>
                                <td class="min-w-32"><input type="text" wire:model.live.debounce.400ms="rows.{{ $n }}.contact" class="{{ $cell }}" aria-label="{{ __('ledger.opening.contact') }} {{ $n + 1 }}"></td>
                                <td class="min-w-28"><input type="text" wire:model.live.debounce.400ms="rows.{{ $n }}.center" class="{{ $cell }}" aria-label="{{ __('ledger.opening.center') }} {{ $n + 1 }}"></td>
                                <td>
                                    <button type="button" wire:click="removeRow({{ $n }})" class="text-sm text-status-critical hover:underline" data-test="remove-row-{{ $n }}">{{ __('ledger.builder.remove_line') }}</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.scroll-table>
            <div>
                <x-ui.button wire:click="addRow" data-shortcut="add-line" data-test="add-row">{{ __('ledger.builder.add_line') }}</x-ui.button>
            </div>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="opening-review" data-test="opening-preview">
            <h2 id="opening-review" class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('operations.step_review') }}</h2>

            @if ($built?->draft)
                <x-ui.scroll-table :paginate="false">
                    <table class="fin-table" data-test="opening-lines">
                        <thead>
                            <tr>
                                <th>{{ __('ledger.builder.account') }}</th>
                                <th>{{ __('operations.owner') }}</th>
                                <th>{{ __('ledger.builder.currency') }}</th>
                                <th class="fin-num">{{ __('vouchers.debit') }}</th>
                                <th class="fin-num">{{ __('vouchers.credit') }}</th>
                                <th class="fin-num">{{ __('vouchers.debit') }} USD</th>
                                <th class="fin-num">{{ __('vouchers.credit') }} USD</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($built->draft->lines as $line)
                                @php($name = $accountNames->get($line->accountCode))
                                <tr @class(['font-medium' => str_starts_with($line->accountCode, '3')])>
                                    <td class="fin-wrap"><span dir="ltr" class="font-medium">{{ $line->accountCode }}</span>
                                        @if ($name)
                                            <span class="text-ink-muted">· {{ $name->localizedName() }}</span>
                                        @endif
                                    </td>
                                    <td dir="ltr" class="text-start">{{ $line->projectCode ?? '—' }}</td>
                                    <td dir="ltr" class="text-start">{{ $line->currency }}</td>
                                    <td class="fin-num"><x-money :amount="$line->debitOrig" /></td>
                                    <td class="fin-num"><x-money :amount="$line->creditOrig" /></td>
                                    <td class="fin-num"><x-money :amount="$line->debitUsd" /></td>
                                    <td class="fin-num"><x-money :amount="$line->creditUsd" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-ui.scroll-table>
                <x-net-assets :net="['total' => bcadd($built->netAssets['unrestricted'], $built->netAssets['restricted'], 2), 'unrestricted' => $built->netAssets['unrestricted'], 'restricted' => $built->netAssets['restricted']]" :note="__('ledger.opening.net_note')" />
            @elseif ($built === null)
                <p class="text-sm text-ink-muted" data-test="opening-empty">{{ __('ledger.opening.empty') }}</p>
            @endif

            @if ($messages !== [])
                <ul class="list-disc ps-5 text-sm text-status-critical" data-test="opening-messages">
                    @foreach ($messages as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @elseif ($ready)
                <p class="text-sm text-status-success" data-test="ready">{{ __('ledger.builder.all_good') }}</p>
            @endif

            <div class="flex flex-wrap items-center gap-3">
                <x-ui.button tone="primary" wire:click="save" x-on:click="leaving()" data-shortcut="save" aria-keyshortcuts="Control+S" data-test="save-opening">{{ __('ledger.opening.save') }}</x-ui.button>
                <x-ui.button wire:click="saveDraft" data-test="save-draft">{{ __('drafts.save') }}</x-ui.button>
            </div>
        </section>
    @endif
</div>
