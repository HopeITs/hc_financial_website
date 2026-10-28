<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" x-data="unsavedGuard" data-unsaved-message="{{ __('drafts.leave_warning') }}" x-on:form-clean.window="clean()">
    <x-ui.page-header :title="__('navigation.screens.single')" :subtitle="__('ledger.entry.intro')" />

    <x-ui.form-draft :saved-at="$draftSavedAt" :notice="$draftNotice" />

    @if ($failures !== [])
        <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="failures">
            @foreach ($failures as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    <x-ui.panel class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3" data-test="entry-form">
        <x-ui.form-select wire:model.live="kind" :label="__('ledger.entry.kind')" required>
            @foreach ($kinds as $option)
                <option value="{{ $option }}">{{ __('ledger.entry.kinds.'.$option) }}</option>
            @endforeach
        </x-ui.form-select>
        <x-ui.form-input type="date" wire:model.live="date" :label="__('ledger.builder.date')" required />
        <x-ui.form-select wire:model.live="project" :label="__('ledger.transfers.project')">
            <option value="">—</option>
            @foreach ($projects as $option)
                <option value="{{ $option->code }}">{{ $option->code }} · {{ $option->name }}</option>
            @endforeach
        </x-ui.form-select>

        <x-ui.form-select wire:model.live="cash" :label="$kind === 'exchange' ? __('ledger.entry.cash_given') : __('ledger.transfers.cash_account')" required>
            <option value="">—</option>
            @foreach ($cashAccounts as $option)
                <option value="{{ $option->code }}">{{ $option->code }} · {{ app()->getLocale() === 'ar' ? $option->name_ar : $option->name_en }}</option>
            @endforeach
        </x-ui.form-select>

        @if ($kind === 'exchange')
            <x-ui.form-select wire:model.live="toCash" :label="__('ledger.entry.cash_received')" required>
                <option value="">—</option>
                @foreach ($cashAccounts as $option)
                    <option value="{{ $option->code }}">{{ $option->code }} · {{ app()->getLocale() === 'ar' ? $option->name_ar : $option->name_en }}</option>
                @endforeach
            </x-ui.form-select>
        @endif

        @if ($kind === 'income')
            <x-ui.form-select wire:model.live="account" :label="__('ledger.entry.income_account')" required>
                <option value="">—</option>
                @foreach ($incomeAccounts as $option)
                    <option value="{{ $option->code }}">{{ $option->code }} · {{ app()->getLocale() === 'ar' ? $option->name_ar : $option->name_en }}</option>
                @endforeach
            </x-ui.form-select>
        @endif

        @if ($kind === 'expense')
            <x-ui.form-select wire:model.live="budgetLine" :label="__('ledger.builder.budget_line')">
                <option value="">—</option>
                @foreach ($budgetLines as $option)
                    <option value="{{ $option->key }}">{{ $option->key }}</option>
                @endforeach
            </x-ui.form-select>
            <x-ui.form-select wire:model.live="account" :label="__('ledger.entry.expense_account')" required>
                <option value="">—</option>
                @foreach ($expenseAccounts as $option)
                    <option value="{{ $option->code }}">{{ $option->code }} · {{ app()->getLocale() === 'ar' ? $option->name_ar : $option->name_en }}</option>
                @endforeach
            </x-ui.form-select>
            <x-ui.form-input wire:model.live.debounce.400ms="document" :label="__('ledger.entry.document')" :help="__('ledger.entry.document_help')" />
        @endif

        @if (in_array($kind, ['expense', 'advance', 'repayment'], true))
            <x-ui.form-input wire:model.live.debounce.400ms="contact" :label="$kind === 'expense' ? __('ledger.entry.contact_payee') : __('ledger.entry.contact_person')" :required="$kind !== 'expense'" />
        @endif

        <x-ui.form-input wire:model.live.debounce.400ms="amount" :label="$kind === 'exchange' ? __('ledger.entry.amount_given', ['currency' => $cashCurrency ?? '']) : __('ledger.entry.amount', ['currency' => $cashCurrency ?? ''])" dir="ltr" inputmode="decimal" required />

        @if ($cashCurrency === 'SYP' || $kind === 'exchange')
            <x-ui.form-input wire:model.live.debounce.400ms="rate" :label="__('ledger.builder.rate')" :help="$kind === 'expense' ? __('ledger.entry.rate_help') : null" dir="ltr" inputmode="decimal" />
        @endif

        <x-ui.form-select wire:model.live="fundSource" :label="__('ledger.builder.fund_source')">
            @foreach ($fundSources as $source)
                <option value="{{ $source->value }}">{{ $source->label() }}</option>
            @endforeach
        </x-ui.form-select>

        <div class="sm:col-span-2 lg:col-span-3">
            <x-ui.form-textarea wire:model.live.debounce.400ms="description" :label="__('ledger.builder.description')" rows="2" />
        </div>
    </x-ui.panel>

    <section class="flex flex-col gap-3" data-test="entry-attachments">
        <h2 class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('ledger.builder.attachments') }}</h2>
        <input type="file" wire:model="attachments" multiple accept=".pdf,.jpg,.jpeg,.png" class="text-sm" data-test="entry-files">
        @error('attachments*')
            <p class="text-sm text-status-critical">{{ $message }}</p>
        @enderror
        @foreach ($attachments as $n => $file)
            <p class="text-sm">{{ $file->getClientOriginalName() }}
                <button type="button" wire:click="removeAttachment({{ $n }})" class="text-status-critical hover:underline">{{ __('ledger.builder.remove_file') }}</button>
            </p>
        @endforeach
    </section>

    @if ($built !== null)
        <section class="flex flex-col gap-3" data-test="entry-preview">
            <h2 class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('ledger.entry.preview') }}</h2>
            @if ($built->draft !== null)
                <x-ui.scroll-table :paginate="false">
                    <table class="fin-table" data-test="preview-lines">
                        <thead>
                            <tr>
                                <th>{{ __('ledger.builder.account') }}</th>
                                <th>{{ __('ledger.builder.project') }}</th>
                                <th>{{ __('ledger.builder.currency') }}</th>
                                <th class="fin-num">{{ __('vouchers.debit') }}</th>
                                <th class="fin-num">{{ __('vouchers.credit') }}</th>
                                <th class="fin-num">{{ __('vouchers.debit') }} USD</th>
                                <th class="fin-num">{{ __('vouchers.credit') }} USD</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($built->draft->lines as $line)
                                <tr>
                                    <td><span dir="ltr">{{ $line->accountCode }}</span></td>
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
            @endif
            @if ($messages !== [])
                <ul class="list-disc ps-5 text-sm text-status-critical" data-test="entry-messages">
                    @foreach ($messages as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-status-success" data-test="ready">{{ __('ledger.builder.all_good') }}</p>
            @endif
        </section>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <x-ui.button tone="primary" wire:click="save" x-on:click="leaving()" data-test="save-entry">{{ __('ledger.builder.save') }}</x-ui.button>
        <x-ui.button wire:click="saveDraft" data-test="save-draft">{{ __('drafts.save') }}</x-ui.button>
    </div>
</div>
