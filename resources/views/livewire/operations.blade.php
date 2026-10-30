<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" x-data="unsavedGuard" data-unsaved-message="{{ __('drafts.leave_warning') }}" x-on:form-clean.window="clean()">
    <x-ui.page-header :title="__('navigation.screens.single')" :subtitle="__('operations.intro')" />

    <x-ui.saved-notice />
    <x-ui.form-draft :saved-at="$draftSavedAt" :notice="$draftNotice" />

    {{-- Step 1: the type of operation. --}}
    <section class="flex flex-col gap-3" aria-labelledby="op-step-type">
        <h2 id="op-step-type" class="op-step"><span class="op-step-no">1</span>{{ __('operations.step_type') }}</h2>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" role="radiogroup" aria-labelledby="op-step-type" data-test="operation-types">
            @foreach ($types as $option)
                <button type="button" wire:click="pick('{{ $option }}')" role="radio" aria-checked="{{ $type === $option ? 'true' : 'false' }}"
                    @class(['op-type', 'op-type-on' => $type === $option]) data-test="type-{{ $option }}">
                    <span class="op-type-code" dir="ltr">{{ $option }}</span>
                    <span class="flex min-w-0 flex-col gap-0.5 text-start">
                        <span class="font-semibold text-secondary-dark dark:text-ink">{{ __('operations.types.'.$option.'.name') }}</span>
                        <span class="text-sm text-ink-muted">{{ __('operations.types.'.$option.'.hint') }}</span>
                    </span>
                </button>
            @endforeach
        </div>
        @if ($types === [])
            <p class="text-sm text-ink-muted" data-test="no-types">{{ __('operations.no_types') }}</p>
        @endif
    </section>

    @if ($type === 'JV')
        <x-ui.panel class="flex flex-wrap items-center justify-between gap-4 p-4" data-test="jv-panel">
            <p class="max-w-prose text-sm">{{ __('operations.jv_note') }}</p>
            <x-ui.button tone="primary" :href="route('entries.vouchers.create')" wire:navigate data-test="open-builder">{{ __('operations.open_builder') }}</x-ui.button>
        </x-ui.panel>
    @elseif ($type !== '')
        @if ($failures !== [])
            <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="failures">
                @foreach ($failures as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        @endif

        {{-- Step 2: the form of the type. Only lists the type needs; no debit or credit account is asked for. --}}
        <section class="flex flex-col gap-3" aria-labelledby="op-step-form">
            <h2 id="op-step-form" class="op-step"><span class="op-step-no">2</span>{{ __('operations.step_form', ['type' => __('operations.types.'.$type.'.name')]) }}</h2>

            <x-ui.panel class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3" data-test="operation-form">
                @if (count($modes) > 1)
                    <x-ui.form-select wire:model.live="mode" :label="__('operations.mode')" required data-test="mode">
                        @foreach ($modes as $option)
                            <option value="{{ $option }}">{{ __('operations.modes.'.$option) }}</option>
                        @endforeach
                    </x-ui.form-select>
                @endif
                <x-ui.form-input type="date" wire:model.live="date" :label="__('ledger.builder.date')" required />

                @if (in_array($type, ['TRF', 'ALC', 'EXG'], true))
                    <x-ui.form-select wire:model.live="city" :label="__('ledger.transfers.city')" required>
                        @foreach ($cities as $name)
                            <option value="{{ $name }}">{{ __('ledger.treasury.cities.'.$name) }}</option>
                        @endforeach
                    </x-ui.form-select>
                    @if ($type === 'TRF')
                        <x-ui.form-select wire:model.live="currency" :label="__('ledger.treasury.currency')" required>
                            <option value="USD">USD</option>
                            <option value="SYP">SYP</option>
                        </x-ui.form-select>
                    @endif
                @endif

                @if ($type !== 'EXG')
                    <x-ui.form-select wire:model.live="project" :label="$mode === 'between_projects' || $mode === 'return' ? __('ledger.transfers.from_project') : __('ledger.transfers.project')" :required="$type !== 'CRV' && $type !== 'CPV'" data-test="project">
                        <option value="">—</option>
                        @foreach ($projects as $option)
                            <option value="{{ $option->code }}">{{ $option->code }} · {{ $option->name }}</option>
                        @endforeach
                    </x-ui.form-select>
                @endif
                @if ($mode === 'between_projects')
                    <x-ui.form-select wire:model.live="otherProject" :label="__('ledger.transfers.to_project')" required data-test="other-project">
                        <option value="">—</option>
                        @foreach ($projects as $option)
                            <option value="{{ $option->code }}">{{ $option->code }} · {{ $option->name }}</option>
                        @endforeach
                    </x-ui.form-select>
                @endif

                @if ($mode === 'expense')
                    <x-ui.form-select wire:model.live="budgetLine" :label="__('ledger.builder.budget_line')" :help="__('operations.budget_line_help')" data-test="budget-line">
                        <option value="">—</option>
                        @foreach ($budgetLines as $option)
                            <option value="{{ $option->key }}">{{ $option->key }}</option>
                        @endforeach
                    </x-ui.form-select>
                    <x-ui.form-select wire:model.live="account" :label="__('operations.expense_kind')" :disabled="$budgetLine !== ''" :locked-reason="$budgetLine !== '' ? __('operations.filled_from_line') : null" required data-test="account">
                        <option value="">—</option>
                        @foreach ($expenseAccounts as $option)
                            <option value="{{ $option->code }}">{{ $option->code }} · {{ app()->getLocale() === 'ar' ? $option->name_ar : $option->name_en }}</option>
                        @endforeach
                    </x-ui.form-select>
                @endif
                @if ($mode === 'income')
                    <x-ui.form-select wire:model.live="account" :label="__('operations.income_kind')" required data-test="account">
                        <option value="">—</option>
                        @foreach ($incomeAccounts as $option)
                            <option value="{{ $option->code }}">{{ $option->code }} · {{ app()->getLocale() === 'ar' ? $option->name_ar : $option->name_en }}</option>
                        @endforeach
                    </x-ui.form-select>
                @endif

                <x-ui.form-select wire:model.live="cash" :label="$type === 'EXG' ? __('ledger.entry.cash_given') : ($type === 'CRV' ? __('operations.cash_in') : ($type === 'CPV' ? __('operations.cash_out') : __('ledger.transfers.cash_account')))" required data-test="cash">
                    <option value="">—</option>
                    @foreach ($cashAccounts as $option)
                        <option value="{{ $option->code }}">{{ $option->code }} · {{ app()->getLocale() === 'ar' ? $option->name_ar : $option->name_en }}</option>
                    @endforeach
                </x-ui.form-select>
                @if ($type === 'EXG')
                    <x-ui.form-select wire:model.live="sypCash" :label="__('ledger.transfers.syp_cash_account')" required data-test="syp-cash">
                        @foreach ($sypCashAccounts as $option)
                            <option value="{{ $option->code }}">{{ $option->code }} · {{ app()->getLocale() === 'ar' ? $option->name_ar : $option->name_en }}</option>
                        @endforeach
                    </x-ui.form-select>
                @endif

                <x-ui.form-input wire:model.live.debounce.400ms="amount" :label="$type === 'EXG' ? __('ledger.entry.amount_given', ['currency' => $cashCurrency ?? '']) : __('ledger.entry.amount', ['currency' => $cashCurrency ?? ''])" dir="ltr" inputmode="decimal" required data-test="amount" />

                @if ($type === 'EXG')
                    <x-ui.form-input wire:model.live.debounce.400ms="rate" :label="__('ledger.transfers.exchange_rate')" dir="ltr" inputmode="decimal" required data-test="rate" />
                    <x-ui.form-input wire:model.live.debounce.400ms="received" :label="__('ledger.transfers.received')" :help="__('ledger.transfers.received_help')" dir="ltr" inputmode="decimal" />
                    <x-ui.form-input wire:model.live.debounce.400ms="commission" :label="__('ledger.transfers.commission')" dir="ltr" inputmode="decimal" />
                @elseif (! in_array($type, ['TRF', 'ALC'], true) && $cashCurrency === 'SYP')
                    <x-ui.form-input wire:model.live.debounce.400ms="rate" :label="__('ledger.builder.rate')" :help="$mode === 'expense' ? __('ledger.entry.rate_help') : null" dir="ltr" inputmode="decimal" data-test="rate" />
                @endif

                @if ($mode === 'expense')
                    <x-ui.form-input wire:model.live.debounce.400ms="document" :label="__('ledger.entry.document')" :help="__('ledger.entry.document_help')" />
                @endif
                @if (in_array($mode, ['income', 'expense', 'advance', 'repayment', 'syp_to_project'], true))
                    <x-ui.form-input wire:model.live.debounce.400ms="contact"
                        :label="match ($mode) { 'income' => __('operations.contact_from'), 'expense' => __('ledger.entry.contact_payee'), 'syp_to_project' => __('ledger.transfers.contact'), default => __('ledger.entry.contact_person') }"
                        :help="$mode === 'syp_to_project' ? __('ledger.transfers.contact_help') : null"
                        :required="in_array($mode, ['advance', 'repayment'], true)" data-test="contact" />
                @endif
                @if ($type === 'CRV' || $type === 'CPV')
                    <x-ui.form-select wire:model.live="fundSource" :label="__('ledger.builder.fund_source')">
                        @foreach ($fundSources as $source)
                            <option value="{{ $source->value }}">{{ $source->label() }}</option>
                        @endforeach
                    </x-ui.form-select>
                @endif

                <div class="sm:col-span-2 lg:col-span-3">
                    <x-ui.form-textarea wire:model.live.debounce.400ms="description" :label="__('ledger.builder.description')" rows="2" />
                </div>
            </x-ui.panel>

            <div class="flex flex-col gap-2" data-test="operation-attachments">
                <h3 class="text-sm font-semibold text-secondary-dark dark:text-ink">{{ __('ledger.builder.attachments') }}</h3>
                <input type="file" wire:model="attachments" multiple accept=".pdf,.jpg,.jpeg,.png" class="text-sm" data-test="operation-files">
                @error('attachments*')
                    <p class="text-sm text-status-critical">{{ $message }}</p>
                @enderror
                @foreach ($attachments as $n => $file)
                    <p class="text-sm">{{ $file->getClientOriginalName() }}
                        <button type="button" wire:click="removeAttachment({{ $n }})" class="text-status-critical hover:underline">{{ __('ledger.builder.remove_file') }}</button>
                    </p>
                @endforeach
            </div>
        </section>

        {{-- Step 3: the lines the program writes, read-only, then save. --}}
        <section class="flex flex-col gap-3" aria-labelledby="op-step-review" data-test="operation-preview">
            <h2 id="op-step-review" class="op-step"><span class="op-step-no">3</span>{{ __('operations.step_review') }}</h2>

            @if ($draft !== null)
                <x-ui.scroll-table :paginate="false">
                    <table class="fin-table" data-test="preview-lines">
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
                            @foreach ($draft->lines as $line)
                                @php($name = $accountNames->get($line->accountCode))
                                <tr>
                                    <td class="fin-wrap"><span dir="ltr" class="font-medium">{{ $line->accountCode }}</span>
                                        @if ($name)
                                            <span class="text-ink-muted">· {{ app()->getLocale() === 'ar' ? $name->name_ar : $name->name_en }}</span>
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
            @elseif ($amount === '')
                <p class="text-sm text-ink-muted" data-test="preview-empty">{{ __('operations.preview_empty') }}</p>
            @endif

            @if ($messages !== [])
                <ul class="list-disc ps-5 text-sm text-status-critical" data-test="operation-messages">
                    @foreach ($messages as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @elseif ($ready)
                <p class="text-sm text-status-success" data-test="ready">{{ __('ledger.builder.all_good') }}</p>
            @endif

            <div class="flex flex-wrap items-center gap-3">
                <x-ui.button tone="primary" wire:click="save" x-on:click="leaving()" data-shortcut="save" aria-keyshortcuts="Control+S" data-test="save-operation">{{ __('operations.save') }}</x-ui.button>
                <x-ui.button wire:click="saveDraft" data-test="save-draft">{{ __('drafts.save') }}</x-ui.button>
            </div>
        </section>
    @endif
</div>
