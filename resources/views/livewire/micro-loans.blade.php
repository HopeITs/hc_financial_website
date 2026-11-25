<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="micro-loans" x-data="unsavedGuard" data-unsaved-message="{{ __('drafts.leave_warning') }}" x-on:form-clean.window="clean()">
    <x-ui.page-header :title="__('loans.title')" :subtitle="__('loans.intro')">
        <x-slot:actions>
            <livewire:excel-tools kind="micro_loans" :key="'excel-micro-loans'" />
            <livewire:excel-tools kind="loan_schedule" :key="'excel-loan-schedule'" />
            <livewire:legacy-micro-import :key="'legacy-micro-import'" />
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.form-draft :saved-at="$draftSavedAt" :notice="$draftNotice" />

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

    <p class="text-sm text-secondary-medium dark:text-ink-muted" data-test="files-pointer">
        {{ __('loans.opened_from_files') }}
        <a href="{{ route(\App\Support\Navigation::routeName('microfiles')) }}" wire:navigate class="underline">{{ __('navigation.screens.microfiles') }}</a>
    </p>

    <x-ui.panel class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4" data-test="payment-form">
            <x-ui.form-select wire:model.live="loanId" :label="__('loans.loan')" :placeholder="__('loans.choose')" required>
                @foreach ($rows as $row)
                    <option value="{{ $row['loan']->id }}">{{ $row['loan']->label() }} · {{ $row['loan']->beneficiary }} ({{ $row['loan']->currency }})</option>
                @endforeach
            </x-ui.form-select>
            <x-ui.form-input type="date" wire:model.live="date" :label="__('loans.payment_date')" required />
            <div class="flex flex-col gap-1">
                <x-ui.form-input type="text" inputmode="decimal" dir="ltr" wire:model="paymentAmount" :label="__('loans.payment_amount')" required />
                @if ($loanId !== '')
                    <div><x-ui.button tone="ghost" size="sm" icon="banknotes" wire:click="fillOwed" data-test="fill-owed">{{ __('loans.fill_owed') }}</x-ui.button></div>
                @endif
            </div>
            @if ($dailyRate !== null)
                <div class="flex flex-col justify-end gap-1 text-sm" data-test="payment-daily-rate">
                    <span class="font-medium text-secondary-dark dark:text-ink">{{ __('loans.rate') }}</span>
                    <span class="text-secondary-medium dark:text-ink-muted">{{ $dailyRate['rate'] !== null ? __('loans.daily_rate_used', ['rate' => $dailyRate['rate'], 'day' => $dailyRate['day']]) : __('loans.daily_rate_missing', ['currency' => $dailyRate['currency']]) }}</span>
                </div>
            @endif
            <x-ui.form-select wire:model="cashAccount" :label="__('loans.cash_account')" :placeholder="__('loans.choose')" required>
                @foreach ($cashAccounts as $account)
                    <option value="{{ $account->code }}">{{ $account->code }} · {{ app()->getLocale() === 'ar' ? $account->name_ar : $account->name_en }}</option>
                @endforeach
            </x-ui.form-select>
        </x-ui.panel>
        @if ($canCreate)
            <div class="flex flex-wrap gap-2">
                <x-ui.button tone="primary" wire:click="receive" x-on:click="leaving()" data-test="receive">{{ __('loans.receive') }}</x-ui.button>
                <x-ui.button wire:click="saveDraft" data-test="save-draft">{{ __('drafts.save') }}</x-ui.button>
            </div>
        @endif

    @if ($cardLoan !== null)
        <x-ui.panel :title="__('loans.card_title', ['beneficiary' => $cardLoan->beneficiary])" :subtitle="$cardLoan->label().' · '.$cardLoan->currency" data-test="loan-card">
            <x-slot:toolbar>
                <x-ui.button size="sm" tone="ghost" icon="x-mark" wire:click="closeCard" :aria-label="__('loans.close_card')" data-test="close-card" />
            </x-slot:toolbar>

            <div class="flex flex-col gap-5 p-4">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.stat-tile :label="__('loans.loan_amount')" :value="$cardFigures['loan']" :unit="$cardLoan->currency" />
                    <x-ui.stat-tile :label="__('loans.grant_amount')" :value="$cardFigures['grant']" :unit="$cardLoan->currency" />
                    <x-ui.stat-tile :label="__('loans.owed')" :value="$cardFigures['owed']" :unit="$cardLoan->currency" data-test="card-owed" />
                    <x-ui.stat-tile :label="__('loans.arrears')" :value="$cardFigures['arrears']" :unit="$cardLoan->currency" :tone="(float) $cardArrears['amount'] > 0 ? 'warn' : null" />
                </div>

                @if ($cardLoan->disbursement !== null)
                    <p class="text-sm text-secondary-medium dark:text-ink-muted">
                        {{ __('loans.disbursed_by') }}
                        <a href="{{ route(\App\Support\Navigation::routeName('vouchers'), ['voucher' => $cardLoan->disbursement->id]) }}" wire:navigate class="underline" dir="ltr">{{ $cardLoan->disbursement->id }}</a>
                        <flux:badge size="sm" :color="$cardLoan->disbursement->trashed() ? 'red' : ($cardLoan->disbursement->status === \App\Enums\VoucherStatus::Approved ? 'green' : 'amber')">{{ $cardLoan->disbursement->trashed() ? __('vouchers.status.rejected') : $cardLoan->disbursement->status->label() }}</flux:badge>
                    </p>
                @endif

                {{-- The beneficiary's details. --}}
                <form wire:submit="saveDetails" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" data-test="card-details">
                    <x-ui.form-input wire:model="cardBeneficiary" :label="__('loans.beneficiary')" required />
                    <x-ui.form-input dir="ltr" wire:model="cardNationalNumber" :label="__('loans.national_number')" maxlength="30" />
                    <x-ui.form-input type="tel" dir="ltr" wire:model="cardMobile" :label="__('loans.mobile')" maxlength="50" />
                    <x-ui.form-input wire:model="cardPartners" :label="__('loans.partners')" maxlength="255" />
                    @if ($canCreate)
                        <div class="flex items-end"><x-ui.button type="submit" icon="check" data-test="save-details">{{ __('loans.save_details') }}</x-ui.button></div>
                    @endif
                </form>

                {{-- The schedule. --}}
                <x-ui.scroll-table :paginate="false">
                    <table class="fin-table" data-test="card-schedule">
                        <thead>
                            <tr>
                                <th>{{ __('loans.due') }}</th>
                                <th class="fin-num">{{ __('loans.instalment_amount') }}</th>
                                <th class="fin-num">{{ __('loans.paid') }}</th>
                                <th class="fin-num">{{ __('loans.written_off') }}</th>
                                <th class="fin-num">{{ __('loans.owed') }}</th>
                                <th>{{ __('loans.state') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cardLoan->instalments as $instalment)
                                @php($owing = $instalment->outstanding())
                                <tr wire:key="card-instalment-{{ $instalment->id }}">
                                    <td dir="ltr" class="text-start">{{ $instalment->due_date->format('d/m/Y') }}</td>
                                    <td class="fin-num"><x-money :amount="$instalment->amount" /></td>
                                    <td class="fin-num"><x-money :amount="$instalment->paid_amount" /></td>
                                    <td class="fin-num"><x-money :amount="$instalment->written_off_amount ?? '0'" /></td>
                                    <td class="fin-num"><x-money :amount="$owing" /></td>
                                    <td>
                                        @if (bccomp($owing, '0', 2) <= 0)
                                            <flux:badge size="sm" color="green">{{ (float) ($instalment->written_off_amount ?? 0) > 0 ? __('loans.state_written_off') : __('loans.state_paid') }}</flux:badge>
                                        @elseif ($instalment->due_date->lt($today))
                                            <flux:badge size="sm" color="red">{{ __('loans.state_late') }}</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc">{{ __('loans.state_due') }}</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-ui.scroll-table>

                {{-- The repayments and write-offs, with their vouchers. --}}
                @if ($cardLoan->payments->isNotEmpty())
                    <x-ui.scroll-table :paginate="false">
                        <table class="fin-table" data-test="card-payments">
                            <thead>
                                <tr>
                                    <th>{{ __('loans.voucher') }}</th>
                                    <th>{{ __('loans.kind') }}</th>
                                    <th class="fin-num">{{ __('loans.payment_amount') }}</th>
                                    <th class="fin-num">{{ __('loans.book_usd') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cardLoan->payments as $payment)
                                    <tr wire:key="card-payment-{{ $payment->id }}">
                                        <td><a href="{{ route(\App\Support\Navigation::routeName('vouchers'), ['voucher' => $payment->voucher_id]) }}" wire:navigate class="underline" dir="ltr">{{ $payment->voucher_id }}</a>
                                            @if ($payment->voucher !== null)
                                                <flux:badge size="sm" :color="$payment->voucher->status === \App\Enums\VoucherStatus::Approved ? 'green' : 'amber'">{{ $payment->voucher->status->label() }}</flux:badge>
                                            @endif
                                        </td>
                                        <td>{{ __('loans.kinds.'.$payment->kind) }}</td>
                                        <td class="fin-num"><x-money :amount="$payment->amount" :currency="$cardLoan->currency" /></td>
                                        <td class="fin-num"><x-money :amount="$payment->usd" currency="USD" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-ui.scroll-table>
                @endif

                @if ($canCreate && bccomp($cardOwed, '0', 2) > 0)
                    <div class="grid gap-4 lg:grid-cols-3">
                        {{-- Pay off early. --}}
                        <div class="flex flex-col gap-2 rounded-lg border border-secondary-soft p-3 dark:border-line">
                            <p class="text-sm font-semibold text-secondary-dark dark:text-ink">{{ __('loans.pay_off_title') }}</p>
                            <p class="text-xs text-secondary-medium dark:text-ink-muted">{{ __('loans.pay_off_hint') }}</p>
                            <div><x-ui.button tone="primary" icon="banknotes" wire:click="payOff" data-test="pay-off">{{ __('loans.pay_off') }}</x-ui.button></div>
                        </div>

                        {{-- Reschedule what is still owed. --}}
                        <form wire:submit="reschedule" class="flex flex-col gap-2 rounded-lg border border-secondary-soft p-3 dark:border-line" data-test="reschedule-form">
                            <p class="text-sm font-semibold text-secondary-dark dark:text-ink">{{ __('loans.reschedule_title') }}</p>
                            <div class="grid grid-cols-3 gap-2">
                                <x-ui.form-input type="number" min="1" max="120" wire:model="rescheduleCount" :label="__('loans.instalments')" required />
                                <x-ui.form-input type="date" wire:model="rescheduleFirst" :label="__('loans.first_due')" required />
                                <x-ui.form-input type="number" min="1" max="12" wire:model="rescheduleInterval" :label="__('loans.interval')" required />
                            </div>
                            <div><x-ui.button type="submit" icon="calendar" wire:confirm="{{ __('loans.reschedule_confirm') }}" data-test="reschedule">{{ __('loans.reschedule') }}</x-ui.button></div>
                        </form>

                        {{-- Write off what is left. --}}
                        <form wire:submit="writeOff" class="flex flex-col gap-2 rounded-lg border border-secondary-soft p-3 dark:border-line" data-test="write-off-form">
                            <p class="text-sm font-semibold text-secondary-dark dark:text-ink">{{ __('loans.write_off_title') }}</p>
                            <p class="text-xs text-secondary-medium dark:text-ink-muted">{{ __('loans.write_off_hint') }}</p>
                            <x-ui.form-input wire:model="writeOffReason" :label="__('loans.write_off_reason')" required data-test="write-off-reason" />
                            <div><x-ui.button type="submit" icon="gift" wire:confirm="{{ __('loans.write_off_confirm') }}" data-test="write-off">{{ __('loans.write_off') }}</x-ui.button></div>
                        </form>
                    </div>
                @endif

                @if ($canCreate && $cardLoan->payments->isEmpty() && $cardLoan->disbursement !== null && ($cardLoan->disbursement->trashed() || $cardLoan->disbursement->status === \App\Enums\VoucherStatus::PendingApproval))
                    <form wire:submit="deleteLoan" class="flex flex-wrap items-end gap-3 rounded-lg border border-status-critical/40 p-3" data-test="delete-loan-form">
                        <div class="min-w-64 flex-1"><x-ui.form-input wire:model="deleteReason" :label="__('loans.delete_reason')" required data-test="delete-loan-reason" /></div>
                        <x-ui.button type="submit" icon="trash" wire:confirm="{{ __('loans.delete_confirm') }}" data-test="delete-loan">{{ __('loans.delete') }}</x-ui.button>
                    </form>
                @endif
            </div>
        </x-ui.panel>
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
                    <th class="fin-num">{{ __('loans.late_payments') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr wire:key="loan-{{ $row['loan']->id }}" @if ($row['flagged']) class="bg-status-critical-soft" data-late-flag @endif>
                        <td><x-ui.button tone="ghost" size="sm" wire:click="openCard({{ $row['loan']->id }})" :title="__('loans.open_card')" data-test="open-card-{{ $row['loan']->id }}">{{ $row['loan']->beneficiary }}</x-ui.button>@if ($row['loan']->mobile) <span class="type-meta text-secondary-medium dark:text-ink-muted" dir="ltr">· {{ $row['loan']->mobile }}</span>@endif</td>
                        <td><span dir="auto">{{ $row['loan']->label() }}</span></td>
                        <td><span dir="ltr">{{ $row['loan']->currency }}</span></td>
                        <td class="fin-num"><x-money :amount="$row['loan']->loan_amount" /></td>
                        <td class="fin-num"><x-money :amount="$row['loan']->grant_amount" /></td>
                        <td class="fin-num" data-test="arrears"><x-money :amount="$row['arrears']['amount']" /></td>
                        <td class="fin-num">{{ $row['arrears']['days'] }}</td>
                        <td class="fin-num" data-test="late">{{ $row['late'] }}@if ($row['flagged']) <flux:badge size="sm" color="red" title="{{ __('loans.late_hint') }}">!</flux:badge>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary-medium dark:text-ink-muted">{{ __('loans.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.scroll-table>
</div>
