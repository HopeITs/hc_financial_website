@php
    $tabClass = fn (bool $active) => 'rounded-md px-3 py-1.5 text-sm font-medium '.($active ? 'bg-primary text-white' : 'text-secondary-medium hover:bg-secondary-soft dark:text-ink-muted dark:hover:bg-surface-2');
@endphp

<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="micro-loans">
    <x-ui.page-header :title="__('loans.title')" :subtitle="__('loans.intro')" />

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

    <div class="flex gap-2" role="tablist">
        <button type="button" role="tab" wire:click="$set('tab', 'loans')" class="{{ $tabClass($tab === 'loans') }}" data-test="tab-loans">{{ __('loans.tabs.loans') }}</button>
        <button type="button" role="tab" wire:click="$set('tab', 'payments')" class="{{ $tabClass($tab === 'payments') }}" data-test="tab-payments">{{ __('loans.tabs.payments') }}</button>
    </div>

    @if ($tab === 'payments')
        <x-ui.panel class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4" data-test="payment-form">
            <x-ui.form-select wire:model.live="loanId" :label="__('loans.loan')" :placeholder="__('loans.choose')" required>
                @foreach ($rows as $row)
                    <option value="{{ $row['loan']->id }}">{{ $row['loan']->project_code }} · {{ $row['loan']->beneficiary }} ({{ $row['loan']->currency }})</option>
                @endforeach
            </x-ui.form-select>
            <x-ui.form-input type="date" wire:model="date" :label="__('loans.payment_date')" required />
            <x-ui.form-input type="text" inputmode="decimal" dir="ltr" wire:model="paymentAmount" :label="__('loans.payment_amount')" required />
            <x-ui.form-input type="text" inputmode="decimal" dir="ltr" wire:model="rate" :label="__('loans.rate')" :help="__('loans.rate_help')" />
            <x-ui.form-select wire:model="cashAccount" :label="__('loans.cash_account')" :placeholder="__('loans.choose')" required>
                @foreach ($cashAccounts as $account)
                    <option value="{{ $account->code }}">{{ $account->code }} · {{ app()->getLocale() === 'ar' ? $account->name_ar : $account->name_en }}</option>
                @endforeach
            </x-ui.form-select>
        </x-ui.panel>
        @if ($canCreate)
            <div><x-ui.button tone="primary" wire:click="receive" data-test="receive">{{ __('loans.receive') }}</x-ui.button></div>
        @endif
    @else
        <x-ui.panel class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4" data-test="loan-form">
            <x-ui.form-input wire:model="beneficiary" :label="__('loans.beneficiary')" required />
            <x-ui.form-select wire:model.live="project" :label="__('loans.project')" :placeholder="__('loans.choose')" required>
                @foreach ($projects as $option)
                    <option value="{{ $option->code }}">{{ $option->code }} · {{ $option->name }}</option>
                @endforeach
            </x-ui.form-select>
            <x-ui.form-input type="text" inputmode="decimal" dir="ltr" wire:model="loanAmount" :label="__('loans.loan_amount')" />
            <x-ui.form-input type="text" inputmode="decimal" dir="ltr" wire:model="grantAmount" :label="__('loans.grant_amount')" />
            <x-ui.form-select wire:model.live="currency" :label="__('loans.currency')">
                <option value="USD">USD</option>
                <option value="SYP">SYP</option>
            </x-ui.form-select>
            @if ($currency === 'SYP')
                <x-ui.form-input type="text" inputmode="decimal" dir="ltr" wire:model="rate" :label="__('loans.rate')" required />
            @endif
            <x-ui.form-input type="date" wire:model="date" :label="__('loans.date')" required />
            <x-ui.form-input type="number" min="1" max="120" wire:model="instalments" :label="__('loans.instalments')" required />
            <x-ui.form-input type="date" wire:model="firstDue" :label="__('loans.first_due')" required />
            <x-ui.form-select wire:model="budgetLine" :label="__('loans.budget_line')" :placeholder="__('loans.choose')" :help="__('loans.budget_line_help')">
                @foreach ($budgetLines as $line)
                    <option value="{{ $line->key }}">{{ $line->name }} · {{ $line->account_code }}</option>
                @endforeach
            </x-ui.form-select>
            <x-ui.form-select wire:model="cashAccount" :label="__('loans.paid_from')" :placeholder="__('loans.choose')" required>
                @foreach ($cashAccounts as $account)
                    <option value="{{ $account->code }}">{{ $account->code }} · {{ app()->getLocale() === 'ar' ? $account->name_ar : $account->name_en }}</option>
                @endforeach
            </x-ui.form-select>
            <x-ui.form-input type="number" min="1" wire:model="requestId" :label="__('loans.request')" :help="__('loans.request_help')" />
            <x-ui.form-select wire:model="fundSource" :label="__('loans.fund_source')">
                @foreach ($fundSources as $source)
                    <option value="{{ $source->value }}">{{ $source->label() }}</option>
                @endforeach
            </x-ui.form-select>
        </x-ui.panel>
        @if ($canCreate)
            <div><x-ui.button tone="primary" wire:click="open" data-test="open-loan">{{ __('loans.open') }}</x-ui.button></div>
        @endif
    @endif

    <x-ui.scroll-table :paginate="false">
        <table class="fin-table" data-test="loan-list">
            <thead>
                <tr>
                    <th>{{ __('loans.beneficiary') }}</th>
                    <th>{{ __('loans.project') }}</th>
                    <th>{{ __('loans.currency') }}</th>
                    <th class="fin-num">{{ __('loans.loan_amount') }}</th>
                    <th class="fin-num">{{ __('loans.grant_amount') }}</th>
                    <th class="fin-num">{{ __('loans.arrears') }}</th>
                    <th class="fin-num">{{ __('loans.days_late') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr wire:key="loan-{{ $row['loan']->id }}">
                        <td>{{ $row['loan']->beneficiary }}</td>
                        <td><span dir="ltr">{{ $row['loan']->project_code }}</span></td>
                        <td><span dir="ltr">{{ $row['loan']->currency }}</span></td>
                        <td class="fin-num"><x-money :amount="$row['loan']->loan_amount" /></td>
                        <td class="fin-num"><x-money :amount="$row['loan']->grant_amount" /></td>
                        <td class="fin-num" data-test="arrears"><x-money :amount="$row['arrears']['amount']" /></td>
                        <td class="fin-num">{{ $row['arrears']['days'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary-medium dark:text-ink-muted">{{ __('loans.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.scroll-table>
</div>
