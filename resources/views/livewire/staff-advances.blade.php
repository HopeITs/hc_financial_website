@php
    $cell = 'w-full min-w-20 rounded-md border border-secondary-light bg-white px-2 py-1.5 text-sm text-secondary-dark disabled:opacity-60 dark:border-field-line dark:bg-surface-2 dark:text-ink';
@endphp

<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="staff-advances">
    <x-ui.page-header :title="__('navigation.screens.advance')" :subtitle="__('advances.intro')" :count="count($open)">
        <x-slot:actions>
            <livewire:excel-tools kind="advances" :key="'excel-advances'" />
            @if ($canCreate && $mode === '')
                <x-ui.button tone="primary" icon="plus" wire:click="startGive" data-test="start-give">{{ __('advances.give') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

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

    @if ($mode === 'give')
        <form wire:submit="give" data-test="give-form">
            <x-ui.panel :title="__('advances.give')" :subtitle="__('advances.give_hint')">
                <div class="flex flex-col gap-4 p-4">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.form-input type="date" wire:model.live="giveDate" :label="__('entries.date')" required data-test="give-date" />
                        <x-ui.form-select wire:model.live="giveAccount" :label="__('entries.paid_from')" :placeholder="__('entries.choose')" searchable required data-test="give-account">
                            <option value="">{{ __('entries.choose') }}</option>
                            @foreach ($cashAccounts as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.form-select>
                        <x-ui.form-input wire:model="givePerson" :label="__('advances.person')" :help="__('advances.person_help')" required data-test="give-person" />
                        <x-ui.form-select wire:model="giveProject" :label="__('entries.project')" :placeholder="__('entries.choose')" searchable required data-test="give-project">
                            <option value="">{{ __('entries.choose') }}</option>
                            @foreach ($projects as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.form-select>
                        <x-ui.form-input wire:model="giveAmount" type="text" inputmode="decimal" :label="__('entries.amount').($chosen ? ' ('.$chosen->currency.')' : '')" dir="ltr" required data-test="give-amount" />
                        @if ($chosen !== null && $chosen->currency !== 'USD')
                            <x-ui.form-input wire:model.live.debounce.400ms="giveRate" type="number" step="0.000001" min="0" :label="__('entries.rate', ['currency' => $chosen->currency])" dir="ltr" required data-test="give-rate"
                                :help="$defaultRate !== null
                                    ? __($defaultRate['provisional'] ? 'entries.rate_help_provisional' : 'entries.rate_help_monthly', ['rate' => rtrim(rtrim($defaultRate['rate'], '0'), '.'), 'month' => \Carbon\CarbonImmutable::parse($defaultRate['month'].'-01')->format('m/Y')])
                                    : __('entries.rate_help_none')" />
                        @endif
                        @if ($giveNeedsCenter)
                            <x-ui.form-input wire:model="giveCenter" :label="__('entries.center')" required data-test="give-center" />
                        @endif
                        <x-ui.form-select wire:model="giveRequest" :label="__('entries.request')" :help="__('entries.request_help')" :placeholder="__('entries.no_request')" searchable data-test="give-request">
                            <option value="">{{ __('entries.no_request') }}</option>
                            @foreach ($requests as $openRequest)
                                <option value="{{ $openRequest->id }}">#{{ $openRequest->id }} · {{ $openRequest->project_code }} · {{ $openRequest->amount }} USD</option>
                            @endforeach
                        </x-ui.form-select>
                        <div class="sm:col-span-2 lg:col-span-4">
                            <x-ui.form-input wire:model="givePurpose" :label="__('advances.purpose')" :help="__('advances.purpose_help')" required data-test="give-purpose" />
                        </div>
                    </div>
                    <input type="file" multiple wire:model="attachments" accept=".pdf,.jpg,.jpeg,.png" class="text-sm" aria-label="{{ __('entries.attachments') }}" data-test="give-files">
                    @error('attachments.*')<p class="text-sm text-status-critical" role="alert">{{ $message }}</p>@enderror
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button type="submit" tone="primary" icon="check" data-test="save-give">{{ __('entries.save') }}</x-ui.button>
                        <x-ui.button wire:click="cancel">{{ __('advances.cancel') }}</x-ui.button>
                    </div>
                </div>
            </x-ui.panel>
        </form>
    @endif

    @if ($mode === 'settle' && $advance !== null)
        <form wire:submit="settle" data-test="settle-form">
            <x-ui.panel :title="__('advances.settle_title', ['person' => $advance['person']])" :subtitle="__('advances.settle_hint')">
                <div class="flex flex-col gap-4 p-4">
                    <div class="grid gap-4 sm:grid-cols-3" data-test="settle-summary">
                        <x-ui.stat-tile :label="__('advances.open')" :value="$figures['open']" :unit="$advance['currency']" icon="banknotes" />
                        <x-ui.stat-tile :label="__('advances.billed')" :value="$figures['billed']" :unit="$advance['currency']" icon="document-text" />
                        @if (bccomp($preview['extra'], '0', 2) === 1)
                            <x-ui.stat-tile :label="__('advances.extra')" :value="$figures['extra']" :unit="$advance['currency']" tone="warn" icon="arrow-up-tray" />
                        @elseif (bccomp($preview['back'], '0', 2) === 1)
                            <x-ui.stat-tile :label="__('advances.back')" :value="$figures['back']" :unit="$advance['currency']" tone="good" icon="arrow-down-tray" />
                        @else
                            <x-ui.stat-tile :label="__('advances.left')" :value="$figures['left']" :unit="$advance['currency']" icon="clock" />
                        @endif
                    </div>
                    <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('advances.rate_note', ['project' => $advance['project'], 'rate' => rtrim(rtrim($advance['rate'], '0'), '.'), 'currency' => $advance['currency']]) }}</p>

                    <x-ui.scroll-table :paginate="false">
                        <table class="fin-table" data-test="bills">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th class="min-w-40">{{ __('entries.project') }}</th>
                                    <th class="min-w-44">{{ __('entries.budget_line') }}</th>
                                    <th class="min-w-64">{{ __('entries.account') }}</th>
                                    <th class="min-w-48">{{ __('entries.line_description') }}</th>
                                    <th class="min-w-36 fin-num">{{ __('entries.amount') }} ({{ $advance['currency'] }})</th>
                                    <th><span class="sr-only">{{ __('entries.remove_line') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($bills as $i => $bill)
                                    <tr wire:key="bill-{{ $i }}">
                                        <td class="text-secondary-medium dark:text-ink-muted">{{ $i + 1 }}</td>
                                        <td>
                                            <x-ui.form-select bare searchable wire:model.live="bills.{{ $i }}.project" :label="__('entries.project').' '.($i + 1)" data-test="bill-project-{{ $i }}">
                                                @foreach ($projects as $code => $label)
                                                    <option value="{{ $code }}">{{ $label }}</option>
                                                @endforeach
                                            </x-ui.form-select>
                                        </td>
                                        <td>
                                            <x-ui.form-select bare searchable wire:model.live="bills.{{ $i }}.budget_line" :label="__('entries.budget_line').' '.($i + 1)" data-test="bill-budget-line-{{ $i }}">
                                                <option value="">{{ __('entries.choose') }}</option>
                                                @foreach ($budgetLines[$bill['project']] ?? [] as $key => $name)
                                                    <option value="{{ $key }}">{{ $name }}</option>
                                                @endforeach
                                            </x-ui.form-select>
                                        </td>
                                        <td>
                                            @if ($bill['budget_line'] !== '')
                                                <span class="text-sm">{{ $accounts[$bill['account']] ?? $bill['account'] }}</span>
                                            @else
                                                <x-ui.form-select bare searchable wire:model.live="bills.{{ $i }}.account" :label="__('entries.account').' '.($i + 1)" data-test="bill-account-{{ $i }}">
                                                    <option value="">{{ __('entries.choose_account') }}</option>
                                                    @foreach ($accounts as $code => $accountLabel)
                                                        <option value="{{ $code }}">{{ $accountLabel }}</option>
                                                    @endforeach
                                                </x-ui.form-select>
                                            @endif
                                        </td>
                                        <td><input type="text" wire:model.live.debounce.400ms="bills.{{ $i }}.description" class="{{ $cell }}" aria-label="{{ __('entries.line_description') }} {{ $i + 1 }}"></td>
                                        <td><input type="text" inputmode="decimal" dir="ltr" wire:model.live.debounce.400ms="bills.{{ $i }}.amount" class="{{ $cell }} text-end" aria-label="{{ __('entries.amount') }} {{ $i + 1 }}" data-test="bill-amount-{{ $i }}"></td>
                                        <td><x-ui.button size="sm" tone="ghost" icon="x-mark" wire:click="removeBill({{ $i }})" :aria-label="__('entries.remove_line').' '.($i + 1)" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-ui.scroll-table>
                    <div><x-ui.button wire:click="addBill" icon="plus" data-test="add-bill">{{ __('advances.add_bill') }}</x-ui.button></div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.form-input type="date" wire:model.live="settleDate" :label="__('entries.date')" required data-test="settle-date" />
                        <x-ui.form-select wire:model.live="rest" :label="__('advances.rest')" data-test="settle-rest">
                            <option value="now">{{ __('advances.rest_now') }}</option>
                            <option value="open">{{ __('advances.rest_open') }}</option>
                        </x-ui.form-select>
                        @if (bccomp($preview['back'], '0', 2) === 1 || bccomp($preview['extra'], '0', 2) === 1)
                            <x-ui.form-select wire:model.live="settleAccount" :label="bccomp($preview['back'], '0', 2) === 1 ? __('advances.back_into') : __('advances.extra_from')" :placeholder="__('entries.choose')" searchable required data-test="settle-account">
                                <option value="">{{ __('entries.choose') }}</option>
                                @foreach ($settleCashAccounts as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </x-ui.form-select>
                        @endif
                        <x-ui.form-input wire:model.live.debounce.400ms="settleCenter" :label="__('entries.center')" :help="__('advances.center_help')" data-test="settle-center" />
                        <div class="sm:col-span-2 lg:col-span-4">
                            <x-ui.form-input wire:model="settleNote" :label="__('entries.description')" :help="__('entries.description_help')" data-test="settle-note" />
                        </div>
                    </div>
                    <input type="file" multiple wire:model="attachments" accept=".pdf,.jpg,.jpeg,.png" class="text-sm" aria-label="{{ __('entries.attachments') }}" data-test="settle-files">
                    @error('attachments.*')<p class="text-sm text-status-critical" role="alert">{{ $message }}</p>@enderror
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button type="submit" tone="primary" icon="check" data-test="save-settle">{{ __('advances.save_settle') }}</x-ui.button>
                        <x-ui.button wire:click="cancel">{{ __('advances.cancel') }}</x-ui.button>
                    </div>
                </div>
            </x-ui.panel>
        </form>
    @endif

    <x-ui.panel :title="__('advances.open_title')" :subtitle="__('advances.open_hint')">
        <div class="fin-table-wrap">
            <table class="fin-table" data-test="open-advances">
                <thead>
                    <tr>
                        <th>{{ __('advances.person') }}</th>
                        <th>{{ __('entries.project') }}</th>
                        <th class="fin-num">{{ __('advances.advanced') }}</th>
                        <th class="fin-num">{{ __('advances.settled_col') }}</th>
                        <th class="fin-num">{{ __('advances.open') }}</th>
                        <th>{{ __('advances.since') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($open as $row)
                        <tr wire:key="advance-{{ $row['key'] }}" data-test="advance-{{ $row['key'] }}">
                            <td>{{ $row['person'] }} @if ($row['pending']) <flux:badge size="sm" color="amber">{{ __('advances.pending') }}</flux:badge> @endif</td>
                            <td dir="ltr" class="text-start">{{ $row['project'] }}</td>
                            <td class="fin-num"><x-money :amount="$row['advanced']" :currency="$row['currency']" /></td>
                            <td class="fin-num"><x-money :amount="$row['settled']" :currency="$row['currency']" /></td>
                            <td class="fin-num font-semibold"><x-money :amount="$row['open']" :currency="$row['currency']" /></td>
                            <td>
                                <span dir="ltr">{{ \Carbon\CarbonImmutable::parse($row['since'])->format('d/m/Y') }}</span>
                                <flux:badge size="sm" :color="$row['days'] > 30 ? 'red' : ($row['days'] > 14 ? 'amber' : 'zinc')" class="ms-1">{{ trans_choice('advances.days', $row['days'], ['days' => $row['days']]) }}</flux:badge>
                            </td>
                            <td>
                                @if ($canCreate)
                                    <x-ui.button size="sm" wire:click="startSettle('{{ $row['key'] }}')" data-test="settle-{{ $row['key'] }}">{{ __('advances.settle') }}</x-ui.button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="fin-empty">{{ __('advances.none_open') }}</td></tr>
                    @endforelse
                </tbody>
                @if ($open !== [])
                    <tfoot>
                        <tr class="font-semibold">
                            <td colspan="4">{{ __('advances.total_usd') }}</td>
                            <td class="fin-num"><x-money :amount="$totalsUsd" currency="USD" /></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-ui.panel>
</div>
