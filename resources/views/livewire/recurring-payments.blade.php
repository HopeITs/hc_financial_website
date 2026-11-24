<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="recurring">
    <x-ui.page-header :title="__('navigation.screens.recurring')" :subtitle="__('recurring.intro')">
        <x-slot:actions>
            @if ($canCreate)
                <x-ui.button tone="primary" icon="plus" wire:click="add" data-test="add-recurring">{{ __('recurring.add') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

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

    @if ($editing !== '')
        <x-ui.panel :title="$editing === 'new' ? __('recurring.add') : __('recurring.edit')" data-test="recurring-form">
            <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2">
                    <x-ui.form-select wire:model="form.budget_line" :label="__('recurring.budget_line')" :help="__('recurring.budget_line_help')" required data-test="r-budget-line">
                        <option value="">{{ __('recurring.choose') }}</option>
                        @foreach ($budgetLines as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </x-ui.form-select>
                </div>
                <x-ui.form-input wire:model="form.payee" :label="__('recurring.payee')" required data-test="r-payee" />
                <x-ui.form-input wire:model="form.description" :label="__('recurring.description')" :help="__('recurring.description_help')" data-test="r-description" />
                <x-ui.form-select wire:model.live="form.currency" :label="__('recurring.currency')" data-test="r-currency">
                    <option value="USD">USD</option>
                    <option value="SYP">SYP</option>
                </x-ui.form-select>
                <x-ui.form-input wire:model="form.amount" inputmode="decimal" dir="ltr" :label="__('recurring.amount')" required data-test="r-amount" />
                <x-ui.form-select wire:model="form.every_months" :label="__('recurring.every')" data-test="r-every">
                    @foreach (\App\Models\RecurringPayment::EVERY as $months)
                        <option value="{{ $months }}">{{ trans_choice('recurring.every_months', $months, ['count' => $months]) }}</option>
                    @endforeach
                </x-ui.form-select>
                <x-ui.form-input type="date" wire:model="form.next_due" :label="__('recurring.next_due')" required data-test="r-next-due" />
                <x-ui.form-input type="date" wire:model="form.ends_on" :label="__('recurring.ends_on')" :help="__('recurring.ends_on_help')" data-test="r-ends-on" />
                <div class="sm:col-span-2">
                    <x-ui.form-select wire:model="form.cash_account" :label="__('recurring.cash_account')" required data-test="r-cash">
                        <option value="">{{ __('recurring.choose') }}</option>
                        @foreach ($cashAccounts as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </x-ui.form-select>
                </div>
                <x-ui.form-input wire:model="form.center" :label="__('recurring.center')" :help="__('recurring.center_help')" />
                <div class="sm:col-span-2">
                    <x-ui.form-select wire:model="form.request" :label="__('recurring.request')" :help="__('recurring.request_help')" data-test="r-request">
                        <option value="">{{ __('recurring.no_request') }}</option>
                        @foreach ($requests as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </x-ui.form-select>
                </div>
                <div class="flex items-end gap-3 sm:col-span-2 lg:col-span-4">
                    <x-ui.button tone="primary" icon="check" wire:click="save" data-test="save-recurring">{{ __('recurring.save') }}</x-ui.button>
                    <x-ui.button tone="ghost" wire:click="cancelEdit">{{ __('recurring.cancel') }}</x-ui.button>
                </div>
            </div>
        </x-ui.panel>
    @endif

    <x-ui.panel :title="__('recurring.list_title')" :count="$payments->count()">
        <x-ui.scroll-table :paginate="false">
            <table class="fin-table" data-test="recurring-table">
                <thead>
                    <tr>
                        <th>{{ __('recurring.payee') }}</th>
                        <th>{{ __('recurring.budget_line') }}</th>
                        <th class="fin-num">{{ __('recurring.amount') }}</th>
                        <th>{{ __('recurring.every') }}</th>
                        <th>{{ __('recurring.next_due') }}</th>
                        <th>{{ __('recurring.last') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        @php($due = $payment->active && $payment->next_due->lte($today))
                        <tr wire:key="recurring-{{ $payment->id }}" data-test="recurring-row-{{ $payment->id }}" @class(['opacity-60' => ! $payment->active])>
                            <td class="font-medium">
                                {{ $payment->payee }}
                                @if ($payment->description)
                                    <span class="block text-xs text-secondary-medium dark:text-ink-muted">{{ $payment->description }}</span>
                                @endif
                            </td>
                            <td>{{ $payment->project_code }} · {{ $payment->budgetLine?->name ?? $payment->budget_line_key }}</td>
                            <td class="fin-num"><x-money :amount="$payment->amount" :currency="$payment->currency" /></td>
                            <td>{{ trans_choice('recurring.every_months', $payment->every_months, ['count' => $payment->every_months]) }}</td>
                            <td>
                                @if ($payment->active)
                                    <span dir="ltr">{{ $payment->next_due->format('d/m/Y') }}</span>
                                    @if ($due)
                                        <x-ui.status-badge status="due" :label="__('recurring.due')" />
                                    @endif
                                @else
                                    <x-ui.status-badge status="stopped" :label="__('recurring.stopped')" />
                                @endif
                            </td>
                            <td>
                                @if ($payment->last_voucher_id)
                                    <a href="{{ route(\App\Support\Navigation::routeName('vouchers'), ['voucher' => $payment->last_voucher_id]) }}" wire:navigate class="underline" dir="ltr">{{ $payment->last_voucher_id }}</a>
                                @endif
                                @if ($payment->last_problem)
                                    <span class="block text-xs text-status-critical" data-test="problem-{{ $payment->id }}">{{ $payment->last_problem }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">
                                @if ($due && $canPay)
                                    <x-ui.button size="sm" tone="primary" icon="banknotes" wire:click="payNow({{ $payment->id }})" data-test="pay-now-{{ $payment->id }}">{{ __('recurring.pay_now') }}</x-ui.button>
                                @endif
                                @if ($canEdit)
                                    <x-ui.button size="sm" tone="ghost" icon="pencil-square" wire:click="edit({{ $payment->id }})" :aria-label="__('recurring.edit')" />
                                    <x-ui.button size="sm" tone="ghost" :icon="$payment->active ? 'pause' : 'play'" wire:click="toggle({{ $payment->id }})" :aria-label="$payment->active ? __('recurring.stop') : __('recurring.restart')" data-test="toggle-{{ $payment->id }}" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty-state icon="arrow-path" :heading="__('recurring.none')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.scroll-table>
    </x-ui.panel>
</div>
