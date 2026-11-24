@php
    $cell = 'w-full min-w-20 rounded-md border border-secondary-light bg-white px-2 py-1.5 text-sm text-secondary-dark disabled:opacity-60 dark:border-field-line dark:bg-surface-2 dark:text-ink';
    $payment = $kind === \App\Services\Entries\CashVouchers::PAYMENT;
@endphp

<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="cash-voucher-{{ $kind }}">
    <x-ui.page-header :title="$title" :subtitle="__('entries.intro.'.$kind)">
        <x-slot:actions>
            <x-ui.button :href="$listUrl" wire:navigate icon="list-bullet">{{ __('entries.all_vouchers') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($notice)
        <div class="flex flex-wrap items-center gap-3 rounded-lg border border-status-good bg-status-good-soft px-4 py-2 text-sm text-status-good" role="status" data-test="notice">
            <span>{{ $notice }}</span>
            @if ($savedVoucher)
                <a href="{{ route(\App\Support\Navigation::routeName('vouchers'), ['voucher' => $savedVoucher]) }}" wire:navigate class="font-semibold underline" dir="ltr">{{ __('entries.open_voucher', ['voucher' => $savedVoucher]) }}</a>
            @endif
            @if ($lastSaved !== [])
                <x-ui.button size="sm" icon="arrow-path" wire:click="repeatLast" data-test="repeat-last">{{ __('entries.repeat_last') }}</x-ui.button>
            @endif
        </div>
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

    <form wire:submit="save" class="flex flex-col gap-6" data-test="voucher-form">
        <x-ui.panel :title="__('entries.header')" data-test="voucher-header">
            <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.form-input type="date" wire:model.live="date" :label="__('entries.date')" required data-test="date" />
                <x-ui.form-select wire:model.live="account" :label="$payment ? __('entries.paid_from') : __('entries.received_in')" :placeholder="__('entries.choose')" searchable required data-test="cash-account">
                    <option value="">{{ __('entries.choose') }}</option>
                    @foreach ($cashAccounts as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </x-ui.form-select>
                <x-ui.form-input wire:model.blur="party" :label="$payment ? __('entries.payee') : __('entries.payer')" :help="$payment ? __('entries.payee_help') : __('entries.payer_help')" required data-test="party" />
                <x-ui.form-input wire:model.blur="reference" :label="$payment ? __('entries.invoice_number') : __('entries.receipt_number')" dir="ltr" data-test="reference" />

                @if ($chosen !== null && $chosen->currency !== 'USD')
                    <x-ui.form-input wire:model.live.debounce.400ms="rate" type="number" step="0.000001" min="0" :label="__('entries.rate', ['currency' => $chosen->currency])" dir="ltr" required data-test="rate"
                        :help="$defaultRate !== null
                            ? __($defaultRate['provisional'] ? 'entries.rate_help_provisional' : 'entries.rate_help_monthly', ['rate' => rtrim(rtrim($defaultRate['rate'], '0'), '.'), 'month' => \Carbon\CarbonImmutable::parse($defaultRate['month'].'-01')->format('m/Y')])
                            : __('entries.rate_help_none')" />
                @endif
                @if ($needsCenter)
                    <x-ui.form-input wire:model.blur="center" :label="__('entries.center')" required data-test="center" />
                @endif
                @if ($payment)
                    <x-ui.form-select wire:model.live="request" :label="__('entries.request')" :help="__('entries.request_help')" :placeholder="__('entries.no_request')" searchable data-test="request">
                        <option value="">{{ __('entries.no_request') }}</option>
                        @foreach ($requests as $open)
                            <option value="{{ $open->id }}">#{{ $open->id }} · {{ $open->project_code }} · {{ $open->amount }} USD</option>
                        @endforeach
                    </x-ui.form-select>
                @endif
                <div class="sm:col-span-2 lg:col-span-4">
                    <x-ui.form-input wire:model.blur="description" :label="__('entries.description')" :help="__('entries.description_help')" data-test="description" />
                </div>
            </div>
        </x-ui.panel>

        <x-ui.panel :title="$payment ? __('entries.lines_payment') : __('entries.lines_receipt')" :subtitle="__('entries.lines_hint')">
            <x-ui.scroll-table :paginate="false">
                <table class="fin-table" data-test="voucher-lines">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th class="min-w-40">{{ __('entries.project') }}</th>
                            <th class="min-w-44">{{ __('entries.budget_line') }}</th>
                            <th class="min-w-64">{{ __('entries.account') }}</th>
                            <th class="min-w-48">{{ __('entries.line_description') }}</th>
                            <th class="min-w-36 fin-num">{{ __('entries.amount') }}@if ($currency) ({{ $currency }})@endif</th>
                            <th><span class="sr-only">{{ __('entries.remove_line') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $i => $line)
                            <tr wire:key="line-{{ $i }}" data-test="line-{{ $i }}">
                                <td class="text-secondary-medium dark:text-ink-muted">{{ $i + 1 }}</td>
                                <td>
                                    <x-ui.form-select bare searchable wire:model.live="lines.{{ $i }}.project" :label="__('entries.project').' '.($i + 1)" data-test="project-{{ $i }}">
                                        <option value="">{{ __('entries.no_project') }}</option>
                                        @foreach ($projects as $code => $label)
                                            <option value="{{ $code }}">{{ $label }}</option>
                                        @endforeach
                                    </x-ui.form-select>
                                </td>
                                <td>
                                    @if ($line['project'] !== '')
                                        <x-ui.form-select bare searchable wire:model.live="lines.{{ $i }}.budget_line" :label="__('entries.budget_line').' '.($i + 1)" data-test="budget-line-{{ $i }}">
                                            <option value="">{{ __('entries.choose') }}</option>
                                            @foreach ($budgetLines[$line['project']] ?? [] as $key => $name)
                                                <option value="{{ $key }}">{{ $name }}</option>
                                            @endforeach
                                        </x-ui.form-select>
                                    @else
                                        <span class="text-sm text-secondary-medium dark:text-ink-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($line['budget_line'] !== '')
                                        <span class="text-sm text-secondary-dark dark:text-ink" data-test="account-{{ $i }}">{{ $accounts[$line['account']] ?? $line['account'] }}</span>
                                    @else
                                        <x-ui.form-select bare searchable wire:model.live="lines.{{ $i }}.account" :label="__('entries.account').' '.($i + 1)" data-test="account-{{ $i }}">
                                            <option value="">{{ __('entries.choose_account') }}</option>
                                            @foreach ($accounts as $code => $label)
                                                <option value="{{ $code }}">{{ $label }}</option>
                                            @endforeach
                                        </x-ui.form-select>
                                    @endif
                                </td>
                                <td><input type="text" wire:model.blur="lines.{{ $i }}.description" class="{{ $cell }}" aria-label="{{ __('entries.line_description') }} {{ $i + 1 }}" data-test="line-description-{{ $i }}"></td>
                                <td><input type="text" inputmode="decimal" dir="ltr" wire:model.live.debounce.400ms="lines.{{ $i }}.amount" class="{{ $cell }} text-end" aria-label="{{ __('entries.amount') }} {{ $i + 1 }}" data-test="amount-{{ $i }}"></td>
                                <td>
                                    <x-ui.button size="sm" tone="ghost" icon="x-mark" wire:click="removeLine({{ $i }})" :aria-label="__('entries.remove_line').' '.($i + 1)" data-test="remove-line-{{ $i }}" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-semibold">
                            <td colspan="5" class="text-end">{{ __('entries.total') }}</td>
                            <td class="fin-num" data-test="total">
                                <x-money :amount="$total" :currency="$currency" />
                                @if ($currency !== null && $currency !== 'USD')
                                    <div class="text-xs font-normal text-secondary-medium dark:text-ink-muted" data-test="total-usd">= <x-money :amount="$totalUsd" currency="USD" /></div>
                                @endif
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </x-ui.scroll-table>
            <div class="border-t border-secondary-soft p-4 dark:border-line">
                <x-ui.button wire:click="addLine" icon="plus" data-test="add-line">{{ __('entries.add_line') }}</x-ui.button>
            </div>
        </x-ui.panel>

        @if ($warnings !== [])
            <div class="rounded-lg border border-status-warn bg-status-warn-soft px-4 py-2 text-sm text-status-warn" role="status" data-test="warnings">
                @foreach ($warnings as $warning)
                    <p>⚠ {{ $warning }}</p>
                @endforeach
            </div>
        @endif

        <x-ui.panel :title="__('entries.attachments')" :subtitle="__('entries.attachments_help')" data-test="attachments">
            <div class="flex flex-col gap-3 p-4">
                <input type="file" multiple wire:model="attachments" accept=".pdf,.jpg,.jpeg,.png" class="text-sm" aria-label="{{ __('entries.attachments') }}" data-test="file-input">
                @error('attachments')<p class="text-sm text-status-critical" role="alert">{{ $message }}</p>@enderror
                @error('attachments.*')<p class="text-sm text-status-critical" role="alert">{{ $message }}</p>@enderror
                @foreach ($attachments as $n => $file)
                    <p class="text-sm" wire:key="file-{{ $n }}" dir="ltr">{{ $file->getClientOriginalName() }}</p>
                @endforeach
            </div>
        </x-ui.panel>

        @if ($canCreate)
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.button type="submit" tone="primary" icon="check" data-test="save-voucher">{{ __('entries.save') }}</x-ui.button>
                <span class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('entries.save_hint') }}</span>
            </div>
        @endif
    </form>
</div>
