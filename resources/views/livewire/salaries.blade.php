@php
    $cell = 'w-full min-w-20 rounded-md border border-secondary-light bg-white px-2 py-1.5 text-sm text-secondary-dark disabled:opacity-60 dark:border-field-line dark:bg-surface-2 dark:text-ink';
    $tabClass = fn (bool $active) => 'rounded-md px-3 py-1.5 text-sm font-medium '.($active ? 'bg-primary text-white' : 'text-secondary-medium hover:bg-secondary-soft dark:text-ink-muted dark:hover:bg-surface-2');
@endphp

<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="salaries">
    <x-ui.page-header :title="__('salaries.title')" :subtitle="__('salaries.intro')" />

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
        <button type="button" role="tab" wire:click="$set('tab', 'allocate')" class="{{ $tabClass($tab === 'allocate') }}" data-test="tab-allocate">{{ __('salaries.tabs.allocate') }}</button>
        <button type="button" role="tab" wire:click="$set('tab', 'splits')" class="{{ $tabClass($tab === 'splits') }}" data-test="tab-splits">{{ __('salaries.tabs.splits') }}</button>
    </div>

    @if ($tab === 'splits')
        <x-ui.panel class="grid gap-4 p-4 sm:grid-cols-3" data-test="split-header">
            <x-ui.form-input type="number" min="1" wire:model="employeeId" :label="__('salaries.employee_id')" required />
            <x-ui.form-input wire:model="employeeName" :label="__('salaries.employee_name')" />
            <x-ui.form-input type="date" wire:model="changeDate" :label="__('salaries.change_date')" :help="__('salaries.change_help')" required />
        </x-ui.panel>

        <x-ui.scroll-table :paginate="false">
            <table class="fin-table" data-test="split-rows">
                <thead>
                    <tr>
                        <th>#</th>
                        <th class="min-w-64">{{ __('salaries.budget_line') }}</th>
                        <th class="min-w-28">{{ __('salaries.percent') }}</th>
                        <th><span class="sr-only">{{ __('salaries.remove') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($splitRows as $i => $row)
                        <tr wire:key="split-{{ $i }}">
                            <td class="text-secondary-medium dark:text-ink-muted">{{ $i + 1 }}</td>
                            <td>
                                <select wire:model="splitRows.{{ $i }}.budget_line" class="{{ $cell }}" aria-label="{{ __('salaries.budget_line') }}">
                                    <option value="">—</option>
                                    @foreach ($budgetLines as $budgetLine)
                                        <option value="{{ $budgetLine->key }}">{{ $budgetLine->project_code }} · {{ $budgetLine->name }} · {{ $budgetLine->account_code }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="text" inputmode="decimal" dir="ltr" wire:model="splitRows.{{ $i }}.percent" class="{{ $cell }}" aria-label="{{ __('salaries.percent') }}"></td>
                            <td><x-ui.button tone="ghost" wire:click="removeSplitRow({{ $i }})" :aria-label="__('salaries.remove')">✕</x-ui.button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.scroll-table>

        <div class="flex flex-wrap gap-2">
            <x-ui.button wire:click="addSplitRow" icon="plus">{{ __('salaries.add_row') }}</x-ui.button>
            <x-ui.button wire:click="loadSplit" data-test="load-split">{{ __('salaries.splits.load') }}</x-ui.button>
            @if ($canCreate)
                <x-ui.button tone="primary" wire:click="saveSplit" data-test="save-split">{{ __('salaries.splits.save') }}</x-ui.button>
            @endif
        </div>
    @else
        <x-ui.panel class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4" data-test="allocation-header">
            <x-ui.form-select wire:model.live="mode" :label="__('salaries.mode')">
                <option value="salary">{{ __('salaries.modes.salary') }}</option>
                <option value="invoice">{{ __('salaries.modes.invoice') }}</option>
            </x-ui.form-select>
            @if ($mode === 'salary')
                <x-ui.form-input type="number" min="1" wire:model="employeeId" :label="__('salaries.employee_id')" required />
                <x-ui.form-input type="month" wire:model="month" :label="__('salaries.month')" required />
            @endif
            <x-ui.form-input type="date" wire:model.live="date" :label="__('salaries.date')" required />
            <x-ui.form-input type="text" inputmode="decimal" dir="ltr" wire:model.live.debounce.300ms="amount" :label="$mode === 'salary' ? __('salaries.salary') : __('salaries.invoice_total')" required />
            <x-ui.form-select wire:model.live="currency" :label="__('salaries.currency')">
                <option value="USD">USD</option>
                <option value="SYP">SYP</option>
            </x-ui.form-select>
            @if ($currency === 'SYP')
                <x-ui.form-input type="text" inputmode="decimal" dir="ltr" wire:model="rate" :label="__('salaries.rate')" required />
            @endif
            <x-ui.form-select wire:model="cashAccount" :label="__('salaries.cash_account')" :placeholder="__('salaries.choose')" required>
                @foreach ($cashAccounts as $account)
                    <option value="{{ $account->code }}">{{ $account->code }} · {{ app()->getLocale() === 'ar' ? $account->name_ar : $account->name_en }}</option>
                @endforeach
            </x-ui.form-select>
            <x-ui.form-select wire:model="fundSource" :label="__('salaries.fund_source')">
                @foreach ($fundSources as $source)
                    <option value="{{ $source->value }}">{{ $source->label() }}</option>
                @endforeach
            </x-ui.form-select>
            <x-ui.form-input wire:model="description" :label="__('salaries.description')" class="w-full sm:col-span-2" />
        </x-ui.panel>

        @if ($warnings !== [])
            <div class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" data-test="warnings">
                @foreach ($warnings as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        @endif

        <x-ui.scroll-table :paginate="false">
            <table class="fin-table" data-test="allocation-lines">
                <thead>
                    <tr>
                        <th>#</th>
                        <th class="min-w-64">{{ __('salaries.budget_line') }}</th>
                        <th class="min-w-24">{{ __('salaries.percent') }}</th>
                        <th class="min-w-32">{{ __('salaries.amount') }}</th>
                        <th><span class="sr-only">{{ __('salaries.remove') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $i => $line)
                        <tr wire:key="line-{{ $i }}">
                            <td class="text-secondary-medium dark:text-ink-muted">{{ $i + 1 }}</td>
                            <td>
                                <select wire:model.live="lines.{{ $i }}.budget_line" class="{{ $cell }}" aria-label="{{ __('salaries.budget_line') }}">
                                    <option value="">—</option>
                                    @foreach ($budgetLines as $budgetLine)
                                        <option value="{{ $budgetLine->key }}">{{ $budgetLine->project_code }} · {{ $budgetLine->name }} · {{ $budgetLine->account_code }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td dir="ltr" class="text-secondary-medium dark:text-ink-muted">{{ ($line['percent'] ?? '') !== '' ? $line['percent'].'%' : '' }}</td>
                            <td><input type="text" inputmode="decimal" dir="ltr" wire:model.live.debounce.300ms="lines.{{ $i }}.amount" class="{{ $cell }}" aria-label="{{ __('salaries.amount') }}"></td>
                            <td><x-ui.button tone="ghost" wire:click="removeLine({{ $i }})" :aria-label="__('salaries.remove')">✕</x-ui.button></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="font-semibold">{{ __('salaries.lines_total') }}</td>
                        <td dir="ltr" class="font-semibold" data-test="lines-total">{{ $total }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </x-ui.scroll-table>

        <div class="flex flex-wrap gap-2">
            <x-ui.button wire:click="addLine" icon="plus">{{ __('salaries.add_row') }}</x-ui.button>
            @if ($canCreate)
                @if ($mode === 'salary')
                    <x-ui.button wire:click="propose" data-test="propose">{{ __('salaries.propose') }}</x-ui.button>
                @endif
                <x-ui.button tone="primary" wire:click="post" data-test="post-allocation">{{ __('salaries.post') }}</x-ui.button>
            @endif
        </div>
    @endif
</div>
