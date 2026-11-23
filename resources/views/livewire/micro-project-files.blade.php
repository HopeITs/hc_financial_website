<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="micro-files">
    <x-ui.page-header :title="__('navigation.screens.microfiles')" :subtitle="__('micro.intro')">
        @if ($canEdit)
            <x-slot:actions>
                <x-ui.button tone="primary" icon="plus" wire:click="newFile" data-test="new-file">{{ __('micro.new_file') }}</x-ui.button>
            </x-slot:actions>
        @endif
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

    <x-ui.panel :title="__('micro.files_title')" :count="$list->count()">
        <div class="flex flex-wrap gap-4 p-4 pb-0">
            <div class="w-56">
                <x-ui.form-select wire:model.live="show" :label="__('micro.show')" data-test="show">
                    @foreach (['open', 'draft', 'submitted', 'approved', 'ready', 'active', 'cancelled', 'all'] as $option)
                        <option value="{{ $option }}">{{ __('micro.shows.'.$option) }}</option>
                    @endforeach
                </x-ui.form-select>
            </div>
            <div class="w-72">
                <x-ui.form-input type="search" wire:model.live.debounce.400ms="search" :label="__('micro.search')" :placeholder="__('micro.search_hint')" data-test="search" />
            </div>
        </div>
        <x-ui.scroll-table :paginate="false">
            <table class="fin-table" data-test="files-table">
                <thead>
                    <tr>
                        <th>{{ __('micro.project_number') }}</th>
                        <th>{{ __('loans.beneficiary') }}</th>
                        <th>{{ __('micro.profession') }}</th>
                        <th>{{ __('micro.funding') }}</th>
                        <th class="fin-num">{{ __('micro.requested_amount') }}</th>
                        <th>{{ __('micro.stage') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($list as $row)
                        <tr wire:key="file-{{ $row->id }}" data-test="file-row-{{ $row->id }}" @class(['bg-primary-soft/40' => $current?->id === $row->id])>
                            <td dir="ltr" class="text-start">{{ $row->project_number ?? '—' }}</td>
                            <td class="font-medium">{{ $row->beneficiary }}</td>
                            <td>{{ $row->profession ?? '—' }}</td>
                            <td>{{ $row->label() }}</td>
                            <td class="fin-num"><x-money :amount="$row->requested_amount" :currency="$row->currency" /></td>
                            <td><flux:badge size="sm" :color="match ($row->stage) { 'approved' => 'blue', 'active' => 'green', 'cancelled' => 'zinc', 'returned' => 'red', 'submitted' => 'amber', default => 'zinc' }">{{ __('micro.stages.'.$row->stage) }}</flux:badge></td>
                            <td><x-ui.button size="sm" tone="ghost" icon="folder-open" wire:click="select({{ $row->id }})" data-test="open-file-{{ $row->id }}">{{ __('micro.open') }}</x-ui.button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty-state icon="folder" :heading="__('micro.none')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.scroll-table>
    </x-ui.panel>

    @if ($file !== '')
        <x-ui.panel :title="$current !== null ? __('micro.file_title', ['name' => $current->beneficiary]) : __('micro.new_file')" :subtitle="$current !== null ? __('micro.stages.'.$current->stage) : null" data-test="file-panel">
            <div class="flex flex-col gap-6 p-4">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($current !== null)
                        <span class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('micro.print') }}:</span>
                        @foreach ($forms as $printForm)
                            @if ($printForm !== 'card' || $current->stage === \App\Models\MicroLoan::ACTIVE)
                                <a href="{{ route('entries.micro-files.print', ['file' => $current->id, 'form' => $printForm]) }}" target="_blank" rel="noopener" class="text-sm underline" data-test="print-{{ $printForm }}">{{ __('micro.forms.'.$printForm) }}</a>
                            @endif
                        @endforeach
                    @endif
                    <div class="ms-auto"><x-ui.button size="sm" tone="ghost" icon="x-mark" wire:click="closeFile" :aria-label="__('micro.close_file')" data-test="close-file" /></div>
                </div>

                @if ($currentStep !== null)
                    {{-- Where the file is: the steps done, the one it is at, and those to come --}}
                    @php($stepIndex = array_search($currentStep, \App\Services\Loans\MicroOverview::STEPS, true))
                    <ol class="flex flex-wrap gap-2" data-test="file-steps">
                        @foreach (\App\Services\Loans\MicroOverview::STEPS as $index => $step)
                            <li @class([
                                'flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium',
                                'bg-status-good-soft text-status-good' => $index < $stepIndex,
                                'bg-primary text-white' => $index === $stepIndex,
                                'bg-secondary-soft text-secondary-medium dark:bg-surface-2 dark:text-ink-muted' => $index > $stepIndex,
                            ]) @if ($index === $stepIndex) aria-current="step" data-test="current-step" @endif>
                                <span class="tabular-nums">{{ $index + 1 }}</span>
                                {{ __('micro_home.steps.'.$step.'.title') }}
                            </li>
                        @endforeach
                    </ol>
                    <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('micro.next.'.$currentStep) }}</p>
                @endif

                @if ($current?->return_reason && in_array($current->stage, ['returned', 'cancelled'], true))
                    <p class="text-sm text-status-critical" data-test="return-reason">{{ __('micro.reason_was', ['reason' => $current->return_reason]) }}</p>
                @endif

                {{-- 1. The beneficiary and the payment order's amounts --}}
                <x-ui.form-section :title="__('micro.details_title')">
                    <fieldset @disabled(! $editable || ! $canEdit) class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.form-input wire:model="form.beneficiary" :label="__('micro.beneficiary_ar')" required data-test="f-beneficiary" />
                        <x-ui.form-input wire:model="form.name_en" dir="ltr" :label="__('micro.beneficiary_en')" data-test="f-name-en" />
                        <x-ui.form-input wire:model="form.national_number" dir="ltr" :label="__('loans.national_number')" data-test="f-national" />
                        <x-ui.form-input wire:model="form.mobile" dir="ltr" :label="__('loans.mobile')" data-test="f-mobile" />
                        <x-ui.form-input wire:model="form.project_number" dir="ltr" :label="__('micro.project_number')" data-test="f-number" />
                        <x-ui.form-input wire:model="form.profession" :label="__('micro.profession')" data-test="f-profession" />
                        <x-ui.form-input wire:model="form.address" :label="__('micro.address')" data-test="f-address" />
                        <x-ui.form-input wire:model="form.partners" :label="__('loans.partners')" />
                        <div class="sm:col-span-2">
                            <x-ui.form-select wire:model="form.funding" :label="__('micro.funding')" :help="__('micro.funding_help')" required data-test="f-funding">
                                <option value="">{{ __('loans.choose') }}</option>
                                @foreach ($fundings as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </x-ui.form-select>
                        </div>
                        <x-ui.form-select wire:model.live="form.currency" :label="__('micro.currency')" :help="__('micro.currency_help')" data-test="f-currency">
                            @foreach ($currencies as $code)
                                <option value="{{ $code }}">{{ $code }}</option>
                            @endforeach
                        </x-ui.form-select>
                        @if (($form['currency'] ?? '') === 'SYP')
                            <x-ui.form-input wire:model="form.rate" inputmode="decimal" dir="ltr" :label="__('micro.rate')" :help="__('micro.rate_help')" required data-test="f-rate" />
                        @endif
                        <x-ui.form-input wire:model.blur="form.requested_amount" inputmode="decimal" dir="ltr" :label="__('micro.requested_amount')" required data-test="f-requested" />
                        <x-ui.form-input wire:model.blur="form.seizure_fee" inputmode="decimal" dir="ltr" :label="__('micro.seizure_fee')" :help="__('micro.seizure_help')" data-test="f-seizure" />
                        <x-ui.form-input wire:model="form.recovery_percent" inputmode="decimal" dir="ltr" :label="__('micro.recovery_percent')" :help="__('micro.recovery_help')" data-test="f-recovery" />
                        <x-ui.form-input wire:model="form.instalment_amount" inputmode="decimal" dir="ltr" :label="__('micro.instalment_amount')" :help="__('micro.instalment_help')" data-test="f-instalment" />
                        <x-ui.form-input type="number" min="1" max="12" wire:model="form.interval" :label="__('loans.interval')" />
                    </fieldset>
                    @if ($needsSeizure)
                        <p class="mt-2 text-sm text-status-warn" data-test="seizure-warning">{{ __('micro.seizure_warning') }}</p>
                    @endif
                </x-ui.form-section>

                {{-- 2. The purchase list --}}
                <x-ui.form-section :title="__('micro.items_title')" :hint="__('micro.items_hint')">
                    <x-ui.scroll-table :paginate="false">
                        <table class="fin-table" data-test="items-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ __('micro.item') }}</th>
                                    <th>{{ __('micro.item_kind') }}</th>
                                    <th>{{ __('micro.quantity') }}</th>
                                    <th>{{ __('micro.condition') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $index => $item)
                                    <tr wire:key="item-{{ $index }}">
                                        <td>{{ $index + 1 }}</td>
                                        <td><x-ui.input wire:model="items.{{ $index }}.name" :disabled="! $editable" :aria-label="__('micro.item')" data-test="item-name-{{ $index }}" /></td>
                                        <td><x-ui.input wire:model="items.{{ $index }}.kind" :disabled="! $editable" :aria-label="__('micro.item_kind')" /></td>
                                        <td class="w-24"><x-ui.input wire:model="items.{{ $index }}.quantity" inputmode="decimal" dir="ltr" :disabled="! $editable" :aria-label="__('micro.quantity')" /></td>
                                        <td class="w-32">
                                            <x-ui.select wire:model="items.{{ $index }}.condition" :disabled="! $editable" :aria-label="__('micro.condition')">
                                                <option value="new">{{ __('micro.conditions.new') }}</option>
                                                <option value="used">{{ __('micro.conditions.used') }}</option>
                                            </x-ui.select>
                                        </td>
                                        <td>
                                            @if ($editable)
                                                <x-ui.button size="sm" tone="ghost" icon="trash" wire:click="removeItem({{ $index }})" :aria-label="__('micro.remove_item')" />
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-ui.scroll-table>
                    @if ($editable && $canEdit)
                        <div class="mt-3 flex flex-wrap gap-2">
                            <x-ui.button size="sm" icon="plus" wire:click="addItem" data-test="add-item">{{ __('micro.add_item') }}</x-ui.button>
                            <x-ui.button tone="primary" icon="check" wire:click="saveFile" data-test="save-file">{{ __('micro.save') }}</x-ui.button>
                        </div>
                    @endif
                </x-ui.form-section>

                @if ($current !== null)
                    {{-- 3. The price quotes --}}
                    <x-ui.form-section :title="__('micro.quotes_title')" :hint="__('micro.quotes_hint', ['min' => \App\Models\MicroQuote::MIN_QUOTES])">
                        <x-ui.scroll-table :paginate="false">
                            <table class="fin-table" data-test="quotes-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('micro.supplier') }}</th>
                                        <th>{{ __('micro.source') }}</th>
                                        <th>{{ __('micro.date') }}</th>
                                        <th class="fin-num">{{ __('micro.amount') }}</th>
                                        <th>{{ __('micro.scan') }}</th>
                                        <th>{{ __('micro.chosen') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($current->quotes as $quote)
                                        <tr wire:key="quote-{{ $quote->id }}" data-test="quote-{{ $quote->id }}">
                                            <td>{{ $quote->supplier }}</td>
                                            <td>{{ __('micro.sources.'.$quote->source) }}</td>
                                            <td dir="ltr" class="text-start">{{ $quote->quoted_on?->format('d/m/Y') ?? '—' }}</td>
                                            <td class="fin-num"><x-money :amount="$quote->amount" :currency="$quote->currency" /></td>
                                            <td>
                                                @if ($quote->stored_path)
                                                    <a href="{{ route('entries.micro-files.document', ['kind' => 'quote', 'id' => $quote->id]) }}" class="underline">{{ $quote->original_name }}</a>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>
                                                @if ($quote->chosen)
                                                    <flux:badge size="sm" color="green">{{ __('micro.chosen') }}</flux:badge>
                                                @elseif ($editable && $canEdit)
                                                    <x-ui.button size="sm" tone="ghost" wire:click="chooseQuote({{ $quote->id }})" data-test="choose-{{ $quote->id }}">{{ __('micro.choose') }}</x-ui.button>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($editable && $canEdit)
                                                    <x-ui.button size="sm" tone="ghost" icon="trash" wire:click="removeQuote({{ $quote->id }})" wire:confirm="{{ __('micro.remove_quote_confirm') }}" :aria-label="__('micro.remove_quote')" />
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="fin-empty">{{ __('micro.no_quotes') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </x-ui.scroll-table>
                        @if ($editable && $canEdit)
                            <form wire:submit="addQuote" class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-6" data-test="quote-form">
                                <x-ui.form-input wire:model="quoteSupplier" :label="__('micro.supplier')" required data-test="q-supplier" />
                                <x-ui.form-select wire:model="quoteSource" :label="__('micro.source')" data-test="q-source">
                                    <option value="beneficiary">{{ __('micro.sources.beneficiary') }}</option>
                                    <option value="merchant">{{ __('micro.sources.merchant') }}</option>
                                </x-ui.form-select>
                                <x-ui.form-input type="date" wire:model="quoteDate" :label="__('micro.date')" />
                                <x-ui.form-input wire:model="quoteAmount" inputmode="decimal" dir="ltr" :label="__('micro.amount')" required data-test="q-amount" />
                                <x-ui.form-select wire:model="quoteCurrency" :label="__('micro.currency')">
                                    @foreach ($currencies as $code)
                                        <option value="{{ $code }}">{{ $code }}</option>
                                    @endforeach
                                </x-ui.form-select>
                                <x-ui.form-field :label="__('micro.scan')" for="quote-scan">
                                    <input id="quote-scan" type="file" wire:model="quoteScan" accept=".pdf,.jpg,.jpeg,.png" class="text-sm" data-test="q-scan">
                                </x-ui.form-field>
                                <div class="sm:col-span-2 lg:col-span-6"><x-ui.button type="submit" icon="plus" data-test="add-quote">{{ __('micro.add_quote') }}</x-ui.button></div>
                            </form>
                        @endif
                    </x-ui.form-section>

                    {{-- 4. The payment order and its approvals --}}
                    <x-ui.form-section :title="__('micro.order_title')" :hint="__('micro.order_hint')">
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <x-ui.stat-tile :label="__('micro.order_total')" :value="$figures['order_total']" :unit="$current->currency" icon="document-text" data-test="order-total" />
                            <x-ui.stat-tile :label="__('micro.usd_value')" :value="$figures['usd_value']" unit="USD" icon="banknotes" />
                            <x-ui.stat-tile :label="__('micro.recovery_percent')" :value="$figures['recovery']" icon="arrow-path" />
                            <x-ui.stat-tile :label="__('micro.instalment_amount')" :value="$figures['instalment']" :unit="$current->currency" icon="calendar" />
                        </div>
                        @if (($current->approvals ?? []) !== [])
                            <ul class="mt-3 list-disc ps-5 text-sm" data-test="approvals">
                                @foreach ($current->approvals as $step)
                                    <li>{{ __('micro.approved_by', ['role' => \App\Enums\RoleId::tryFrom((int) $step['role'])?->label() ?? '', 'name' => $step['name'], 'at' => $step['at']]) }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if ($nextRole !== null)
                            <p class="mt-2 text-sm text-status-warn" data-test="waiting-for">{{ __('micro.waiting_for', ['role' => $nextRole->label()]) }}</p>
                        @endif

                        <div class="mt-3 flex flex-wrap items-end gap-2">
                            @if ($editable && $canEdit)
                                <x-ui.button tone="primary" icon="paper-airplane" wire:click="submit" wire:confirm="{{ __('micro.submit_confirm') }}" data-test="submit">{{ __('micro.submit') }}</x-ui.button>
                            @endif
                            @if ($canApprove)
                                <x-ui.button tone="primary" icon="check" wire:click="approve" wire:confirm="{{ __('micro.approve_confirm') }}" data-test="approve">{{ __('micro.approve') }}</x-ui.button>
                            @endif
                            @if ($canApprove || (in_array($current->stage, ['draft', 'returned', 'submitted', 'approved'], true) && $canEdit))
                                <div class="w-64"><x-ui.form-input wire:model="reason" :label="__('micro.reason')" data-test="reason" /></div>
                            @endif
                            @if ($canApprove)
                                <x-ui.button icon="arrow-uturn-left" wire:click="sendBack" data-test="send-back">{{ __('micro.send_back') }}</x-ui.button>
                            @endif
                            @if (in_array($current->stage, ['draft', 'returned', 'submitted', 'approved'], true) && $canEdit && $purchaseRows->isEmpty())
                                <x-ui.button tone="neutral" icon="x-circle" wire:click="cancelFile" wire:confirm="{{ __('micro.cancel_confirm') }}" data-test="cancel-file">{{ __('micro.cancel') }}</x-ui.button>
                            @endif
                        </div>
                    </x-ui.form-section>

                    {{-- 5. The purchases --}}
                    @if (in_array($current->stage, ['approved', 'active'], true))
                        <x-ui.form-section :title="__('micro.purchases_title')" :hint="__('micro.purchases_hint')">
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <x-ui.stat-tile :label="__('micro.approved_total')" :value="$figures['approved']" :unit="$current->currency" icon="document-check" />
                                <x-ui.stat-tile :label="__('micro.invoiced')" :value="$figures['invoiced']" :unit="$current->currency" icon="receipt-percent" data-test="invoiced" />
                                <x-ui.stat-tile :label="__('micro.difference')" :value="$figures['share']" icon="scale" />
                                <x-ui.stat-tile :label="__('micro.open_advances')" :value="$figures['open_advances']" unit="USD" icon="user" />
                            </div>
                            @if ($figures['over_ten'] !== null)
                                <p class="mt-2 text-sm text-status-warn" data-test="variance-warning">{{ $figures['over_ten'] === 'over' ? __('micro.over_ten') : __('micro.under_ten') }}</p>
                            @endif

                            <x-ui.scroll-table :paginate="false">
                                <table class="fin-table mt-3" data-test="purchases-table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('micro.date') }}</th>
                                            <th>{{ __('micro.kind') }}</th>
                                            <th>{{ __('micro.merchant') }}</th>
                                            <th>{{ __('micro.invoice_number') }}</th>
                                            <th>{{ __('micro.description') }}</th>
                                            <th class="fin-num">{{ __('micro.amount') }}</th>
                                            <th class="fin-num">{{ __('micro.in_loan') }}</th>
                                            <th>{{ __('loans.voucher') }}</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($purchaseRows as $purchase)
                                            <tr wire:key="purchase-{{ $purchase->id }}" data-test="purchase-{{ $purchase->id }}">
                                                <td dir="ltr" class="text-start">{{ $purchase->purchase_date->format('d/m/Y') }}</td>
                                                <td>{{ __('micro.kinds.'.$purchase->kind) }}@if ($purchase->is_seizure) · {{ __('micro.seizure_fee') }}@endif @if ($purchase->person) · {{ $purchase->person }}@endif</td>
                                                <td>{{ $purchase->merchant ?? '—' }}</td>
                                                <td dir="ltr" class="text-start">{{ $purchase->number ?? '—' }}</td>
                                                <td>
                                                    {{ $purchase->description_ar ?? '' }}
                                                    @if ($purchase->stored_path)
                                                        · <a href="{{ route('entries.micro-files.document', ['kind' => 'invoice', 'id' => $purchase->id]) }}" class="underline">{{ __('micro.scan') }}</a>
                                                    @endif
                                                </td>
                                                <td class="fin-num"><x-money :amount="$purchase->amount" :currency="$purchase->currency" /></td>
                                                <td class="fin-num">
                                                    @if ($purchase->kind === 'invoice')
                                                        <x-money :amount="$purchase->loan_amount" />
                                                    @endif
                                                </td>
                                                <td dir="ltr">
                                                    <a href="{{ route(\App\Support\Navigation::routeName('vouchers'), ['voucher' => $purchase->voucher_id]) }}" wire:navigate class="underline">{{ $purchase->voucher_id }}</a>
                                                </td>
                                                <td>
                                                    @if ($current->stage === 'approved' && $canEdit && $purchase->voucher?->status === \App\Enums\VoucherStatus::PendingApproval)
                                                        <x-ui.button size="sm" tone="ghost" icon="trash" wire:click="removePurchase({{ $purchase->id }})" wire:confirm="{{ __('micro.remove_purchase_confirm') }}" :aria-label="__('micro.remove_purchase')" data-test="remove-purchase-{{ $purchase->id }}" />
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="9" class="fin-empty">{{ __('micro.no_purchases') }}</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </x-ui.scroll-table>

                            @if ($current->stage === 'approved' && $canEdit)
                                {{-- An invoice, paid from the cash or from an advance --}}
                                <form wire:submit="payInvoice" class="mt-4 flex flex-col gap-4 rounded-lg border border-secondary-soft p-4 dark:border-line" data-test="invoice-form">
                                    <h3 class="font-semibold">{{ __('micro.invoice_title') }}</h3>
                                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <x-ui.form-select wire:model.live="invoice.source" :label="__('micro.paid_from')" data-test="i-source">
                                            <option value="cash">{{ __('micro.paid_direct') }}</option>
                                            @foreach ($advances as $row)
                                                @if ((float) $row['open'] > 0)
                                                    <option value="advance:{{ $row['advance']->id }}">{{ __('micro.paid_from_advance', ['person' => $row['advance']->person, 'open' => $row['open_label'], 'currency' => $row['advance']->currency]) }}</option>
                                                @endif
                                            @endforeach
                                        </x-ui.form-select>
                                        <x-ui.form-input type="date" wire:model="invoice.date" :label="__('micro.invoice_date')" required data-test="i-date" />
                                        @if (($invoice['source'] ?? 'cash') === 'cash')
                                            <x-ui.form-select wire:model="invoice.cash_account" :label="__('loans.paid_from')" required data-test="i-cash">
                                                <option value="">{{ __('loans.choose') }}</option>
                                                @foreach ($cashAccounts as $code => $label)
                                                    <option value="{{ $code }}">{{ $label }}</option>
                                                @endforeach
                                            </x-ui.form-select>
                                            <x-ui.form-input wire:model="invoice.rate" inputmode="decimal" dir="ltr" :label="__('micro.invoice_rate')" :help="__('micro.invoice_rate_help')" />
                                        @endif
                                        <x-ui.form-input wire:model="invoice.amount" inputmode="decimal" dir="ltr" :label="__('micro.amount')" required data-test="i-amount" />
                                        <x-ui.form-input wire:model="invoice.merchant" :label="__('micro.merchant')" required data-test="i-merchant" />
                                        <x-ui.form-input wire:model="invoice.number" dir="ltr" :label="__('micro.invoice_number')" required data-test="i-number" />
                                        <x-ui.form-select wire:model="invoice.is_seizure" :label="__('micro.invoice_kind')">
                                            <option value="0">{{ __('micro.kinds.invoice') }}</option>
                                            <option value="1">{{ __('micro.seizure_fee') }}</option>
                                        </x-ui.form-select>
                                        <div class="sm:col-span-2"><x-ui.form-input wire:model="invoice.description_ar" :label="__('micro.description_ar')" required data-test="i-desc-ar" /></div>
                                        <div class="sm:col-span-2"><x-ui.form-input wire:model="invoice.description_en" dir="ltr" :label="__('micro.description_en')" data-test="i-desc-en" /></div>
                                        <x-ui.form-field :label="__('micro.scan')" for="invoice-scan">
                                            <input id="invoice-scan" type="file" wire:model="invoiceScan" accept=".pdf,.jpg,.jpeg,.png" class="text-sm" data-test="i-scan">
                                        </x-ui.form-field>
                                    </div>
                                    <div><x-ui.button type="submit" tone="primary" icon="receipt-percent" wire:confirm="{{ __('micro.invoice_confirm') }}" data-test="save-invoice">{{ __('micro.save_invoice') }}</x-ui.button></div>
                                </form>

                                {{-- An advance to the purchasing officer --}}
                                <form wire:submit="giveAdvance" class="mt-4 flex flex-col gap-4 rounded-lg border border-secondary-soft p-4 dark:border-line" data-test="advance-form">
                                    <h3 class="font-semibold">{{ __('micro.advance_title') }}</h3>
                                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                        <x-ui.form-input wire:model="advance.person" :label="__('micro.person')" required data-test="a-person" />
                                        <x-ui.form-input type="date" wire:model="advance.date" :label="__('micro.date')" required />
                                        <x-ui.form-select wire:model="advance.cash_account" :label="__('loans.paid_from')" required data-test="a-cash">
                                            <option value="">{{ __('loans.choose') }}</option>
                                            @foreach ($cashAccounts as $code => $label)
                                                <option value="{{ $code }}">{{ $label }}</option>
                                            @endforeach
                                        </x-ui.form-select>
                                        <x-ui.form-input wire:model="advance.amount" inputmode="decimal" dir="ltr" :label="__('micro.amount')" required data-test="a-amount" />
                                        <x-ui.form-input wire:model="advance.rate" inputmode="decimal" dir="ltr" :label="__('micro.invoice_rate')" :help="__('micro.invoice_rate_help')" />
                                    </div>
                                    <div><x-ui.button type="submit" icon="user" wire:confirm="{{ __('micro.advance_confirm') }}" data-test="save-advance">{{ __('micro.give_advance') }}</x-ui.button></div>
                                </form>

                                @foreach ($advances as $row)
                                    @if ((float) $row['open'] > 0)
                                        <div class="mt-3 flex flex-wrap items-end gap-2" wire:key="return-{{ $row['advance']->id }}">
                                            <span class="text-sm">{{ __('micro.advance_open', ['person' => $row['advance']->person, 'open' => $row['open_label'], 'currency' => $row['advance']->currency]) }}</span>
                                            <div class="w-40"><x-ui.form-input wire:model="returnAmount.{{ $row['advance']->id }}" inputmode="decimal" dir="ltr" :label="__('micro.return_amount')" /></div>
                                            <x-ui.button size="sm" icon="arrow-uturn-left" wire:click="returnAdvance({{ $row['advance']->id }})" data-test="return-{{ $row['advance']->id }}">{{ __('micro.return') }}</x-ui.button>
                                        </div>
                                    @endif
                                @endforeach

                                {{-- The extra amount --}}
                                <div class="mt-4 flex flex-wrap items-end gap-2">
                                    <div class="w-40"><x-ui.form-input wire:model="extraAmount" inputmode="decimal" dir="ltr" :label="__('micro.extra_amount')" /></div>
                                    <div class="w-72"><x-ui.form-input wire:model="extraReason" :label="__('micro.extra_reason')" /></div>
                                    <x-ui.button size="sm" icon="check" wire:click="saveExtra" data-test="save-extra">{{ __('micro.save_extra') }}</x-ui.button>
                                </div>
                                @if ($extraNeedsEmail)
                                    <p class="mt-2 text-sm text-status-warn">{{ __('micro.extra_email') }}</p>
                                @endif
                            @endif
                        </x-ui.form-section>
                    @endif

                    {{-- 6. The receipt protocol: the loan opens --}}
                    @if ($current->stage === 'approved' && $canEdit)
                        <x-ui.form-section :title="__('micro.close_title')" :hint="__('micro.close_hint')">
                            <form wire:submit="close" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-test="close-form">
                                <x-ui.form-input type="date" wire:model.live="signedOn" :label="__('micro.signed_on')" required data-test="c-signed" />
                                <x-ui.form-input type="date" wire:model="firstDue" :label="__('micro.first_due')" :help="__('micro.first_due_help')" data-test="c-first-due" />
                                <x-ui.form-input wire:model="trustBond" inputmode="decimal" dir="ltr" :label="__('micro.trust_bond')" :help="__('micro.trust_bond_help')" :placeholder="$figures['invoiced_plain']" data-test="c-bond" />
                                <div class="flex items-end"><x-ui.button type="submit" tone="primary" icon="lock-closed" wire:confirm="{{ __('micro.close_confirm') }}" data-test="close-purchases">{{ __('micro.close') }}</x-ui.button></div>
                            </form>
                        </x-ui.form-section>
                    @endif

                    @if ($current->stage === 'active')
                        <p class="text-sm" data-test="active-note">
                            {{ __('micro.active_note', ['loan' => $figures['loan'], 'grant' => $figures['grant'], 'currency' => $current->currency, 'first' => $current->first_payment_date?->format('d/m/Y') ?? '—']) }}
                            <a href="{{ route(\App\Support\Navigation::routeName('payments'), ['card' => $current->id]) }}" wire:navigate class="underline">{{ __('micro.open_loan') }}</a>
                            · <a href="{{ route(\App\Support\Navigation::routeName('payments'), ['loanId' => $current->id]) }}" wire:navigate class="underline" data-test="receive-for-file">{{ __('micro_home.receive') }}</a>
                        </p>
                    @endif
                @endif
            </div>
        </x-ui.panel>
    @endif
</div>
