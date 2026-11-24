@php
    $cell = 'w-full min-w-20 rounded-md border border-secondary-light bg-white px-2 py-1.5 text-sm text-secondary-dark disabled:opacity-60 dark:border-field-line dark:bg-surface-2 dark:text-ink';
    $service = ($current?->kind ?? ($form['kind'] ?? 'goods')) === \App\Models\Purchase::SERVICE;
@endphp

<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="purchases">
    <x-ui.page-header :title="__('navigation.screens.purchases')" :subtitle="__('purchases.intro')">
        <x-slot:actions>
            @if ($canCreate)
                <x-ui.button tone="primary" icon="plus" wire:click="newFile" data-test="new-purchase">{{ __('purchases.new') }}</x-ui.button>
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

    {{-- The files --}}
    <x-ui.panel :title="__('purchases.list_title')" :count="$list->count()">
        <div class="flex flex-wrap gap-4 p-4 pb-0">
            <div class="w-56">
                <x-ui.form-select wire:model.live="show" :label="__('purchases.show')" data-test="show">
                    @foreach (['open', ...\App\Models\Purchase::STAGES, 'cancelled', 'all'] as $option)
                        <option value="{{ $option }}">{{ __('purchases.shows.'.$option) }}</option>
                    @endforeach
                </x-ui.form-select>
            </div>
        </div>
        <x-ui.scroll-table :paginate="false">
            <table class="fin-table" data-test="purchases-table">
                <thead>
                    <tr>
                        <th>{{ __('purchases.number') }}</th>
                        <th>{{ __('purchases.project') }}</th>
                        <th>{{ __('purchases.title') }}</th>
                        <th>{{ __('purchases.supplier') }}</th>
                        <th>{{ __('purchases.stage') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($list as $row)
                        <tr wire:key="purchase-{{ $row->id }}" data-test="purchase-row-{{ $row->id }}" @class(['bg-primary-soft/40' => $current?->id === $row->id])>
                            <td dir="ltr" class="text-start">{{ $row->number('PR') }}</td>
                            <td>{{ $row->project_code }}</td>
                            <td class="font-medium">{{ $row->title }}</td>
                            <td>{{ $row->awardedQuote?->supplier ?? '—' }}</td>
                            <td><x-ui.status-badge :status="$row->stage === 'sourcing' ? 'draft' : $row->stage" :label="__('purchases.stages.'.$row->stage)" /></td>
                            <td><x-ui.button size="sm" tone="ghost" icon="folder-open" wire:click="select({{ $row->id }})" data-test="open-purchase-{{ $row->id }}">{{ __('purchases.open') }}</x-ui.button></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ui.empty-state icon="shopping-cart" :heading="__('purchases.none')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.scroll-table>
    </x-ui.panel>

    @if ($file !== '')
        <x-ui.panel :title="$current !== null ? $current->number('PR').' · '.$current->title : __('purchases.new')" :subtitle="$current !== null ? __('purchases.stages.'.$current->stage) : null" data-test="purchase-panel">
            <div class="flex flex-col gap-6 p-4">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($current !== null)
                        <span class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('purchases.print') }}:</span>
                        @foreach ($forms as $printForm)
                            <a href="{{ route('entries.purchases.print', ['purchase' => $current->id, 'form' => $printForm]) }}" target="_blank" rel="noopener" class="text-sm underline" data-test="print-{{ $printForm }}">{{ __('purchases.forms.'.$printForm) }}</a>
                        @endforeach
                    @endif
                    <div class="ms-auto"><x-ui.button size="sm" tone="ghost" icon="x-mark" wire:click="closeFile" :aria-label="__('purchases.close')" data-test="close-purchase" /></div>
                </div>

                @if ($current !== null && $current->stage !== \App\Models\Purchase::CANCELLED)
                    @php($stepIndex = array_search($current->stage, \App\Models\Purchase::STAGES, true))
                    <ol class="flex flex-wrap gap-2" data-test="purchase-steps">
                        @foreach (\App\Models\Purchase::STAGES as $index => $step)
                            <li @class([
                                'flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium',
                                'bg-status-good-soft text-status-good' => $index < $stepIndex,
                                'bg-primary text-white' => $index === $stepIndex,
                                'bg-secondary-soft text-secondary-medium dark:bg-surface-2 dark:text-ink-muted' => $index > $stepIndex,
                            ]) @if ($index === $stepIndex) aria-current="step" data-test="current-step" @endif>
                                <span class="tabular-nums">{{ $index + 1 }}</span>
                                {{ __('purchases.steps.'.$step) }}
                            </li>
                        @endforeach
                    </ol>
                    <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('purchases.next.'.$current->stage) }}</p>
                @endif

                @if ($current?->return_reason && $current->stage === \App\Models\Purchase::SOURCING)
                    <p class="text-sm text-status-critical" data-test="return-reason">{{ __('purchases.returned_because', ['reason' => $current->return_reason]) }}</p>
                @endif

                {{-- 1. The purchase request --}}
                <x-ui.form-section :title="__('purchases.request_title')" :hint="__('purchases.request_hint')">
                    <fieldset @disabled(! $editable || ! $canEdit) class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="sm:col-span-2">
                            <x-ui.form-select wire:model.live="form.request" :label="__('purchases.from_request')" :help="__('purchases.from_request_help')" :disabled="$current !== null" required data-test="f-request">
                                <option value="">{{ __('purchases.choose') }}</option>
                                @foreach ($requests as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                                @if ($current !== null && ! isset($requests[$current->request_id]))
                                    <option value="{{ $current->request_id }}">#{{ $current->request_id }} · {{ $current->project_code }}</option>
                                @endif
                            </x-ui.form-select>
                        </div>
                        <x-ui.form-select wire:model.live="form.kind" :label="__('purchases.kind')" data-test="f-kind">
                            <option value="goods">{{ __('purchases.kinds.goods') }}</option>
                            <option value="service">{{ __('purchases.kinds.service') }}</option>
                        </x-ui.form-select>
                        <x-ui.form-select wire:model="form.currency" :label="__('purchases.currency')" data-test="f-currency">
                            @foreach (\App\Services\Purchasing\PurchaseFiles::CURRENCIES as $code)
                                <option value="{{ $code }}">{{ $code }}</option>
                            @endforeach
                        </x-ui.form-select>
                        <div class="sm:col-span-2">
                            <x-ui.form-input wire:model="form.title" :label="__('purchases.title')" :help="__('purchases.title_help')" required data-test="f-title" />
                        </div>
                        <x-ui.form-input wire:model="form.location" :label="__('purchases.location')" data-test="f-location" />
                        <x-ui.form-input type="date" wire:model="form.needed_by" :label="__('purchases.needed_by')" data-test="f-needed-by" />
                        <div class="sm:col-span-2">
                            <x-ui.form-input wire:model="form.delivery_place" :label="__('purchases.delivery_place')" data-test="f-delivery-place" />
                        </div>
                    </fieldset>

                    <div class="mt-4">
                        <x-ui.scroll-table :paginate="false">
                            <table class="fin-table" data-test="items-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('purchases.budget_line') }}</th>
                                        <th>{{ __('purchases.item') }}</th>
                                        <th>{{ __('purchases.unit') }}</th>
                                        <th class="fin-num">{{ __('purchases.quantity') }}</th>
                                        <th class="fin-num">{{ __('purchases.estimated_price') }}</th>
                                        <th>{{ __('purchases.remarks') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $i => $item)
                                        <tr wire:key="item-{{ $i }}">
                                            <td class="tabular-nums">{{ $i + 1 }}</td>
                                            <td>
                                                <x-ui.form-select bare wire:model="items.{{ $i }}.budget_line" :label="__('purchases.budget_line').' '.($i + 1)" :disabled="! $editable || ! $canEdit" data-test="item-line-{{ $i }}">
                                                    <option value="">{{ __('purchases.choose') }}</option>
                                                    @foreach ($budgetLines as $key => $name)
                                                        <option value="{{ $key }}">{{ $name }}</option>
                                                    @endforeach
                                                </x-ui.form-select>
                                            </td>
                                            <td><input type="text" wire:model.blur="items.{{ $i }}.description" class="{{ $cell }}" @disabled(! $editable || ! $canEdit) aria-label="{{ __('purchases.item') }} {{ $i + 1 }}" data-test="item-description-{{ $i }}"></td>
                                            <td><input type="text" wire:model.blur="items.{{ $i }}.unit" class="{{ $cell }}" @disabled(! $editable || ! $canEdit) aria-label="{{ __('purchases.unit') }} {{ $i + 1 }}"></td>
                                            <td><input type="text" inputmode="decimal" dir="ltr" wire:model.blur="items.{{ $i }}.quantity" class="{{ $cell }} text-end" @disabled(! $editable || ! $canEdit) aria-label="{{ __('purchases.quantity') }} {{ $i + 1 }}" data-test="item-quantity-{{ $i }}"></td>
                                            <td><input type="text" inputmode="decimal" dir="ltr" wire:model.blur="items.{{ $i }}.estimated_price" class="{{ $cell }} text-end" @disabled(! $editable || ! $canEdit) aria-label="{{ __('purchases.estimated_price') }} {{ $i + 1 }}" data-test="item-price-{{ $i }}"></td>
                                            <td><input type="text" wire:model.blur="items.{{ $i }}.remarks" class="{{ $cell }}" @disabled(! $editable || ! $canEdit) aria-label="{{ __('purchases.remarks') }} {{ $i + 1 }}"></td>
                                            <td>
                                                @if ($editable && $canEdit)
                                                    <x-ui.button size="sm" tone="ghost" icon="trash" wire:click="removeItem({{ $i }})" :aria-label="__('purchases.remove_item')" />
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-ui.scroll-table>
                    </div>

                    @if ($editable && $canEdit)
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <x-ui.button size="sm" icon="plus" wire:click="addItem" data-test="add-item">{{ __('purchases.add_item') }}</x-ui.button>
                            <x-ui.button tone="primary" icon="check" wire:click="save" data-test="save-purchase">{{ __('purchases.save') }}</x-ui.button>
                            @if ($current !== null && $current->quotes()->exists())
                                <span class="text-xs text-secondary-medium dark:text-ink-muted">{{ __('purchases.items_locked') }}</span>
                            @endif
                        </div>
                    @endif
                    @if ($estimated !== null)
                        <p class="mt-2 text-sm" data-test="estimated">{{ __('purchases.estimated_total') }}: <x-money :amount="$estimated" :currency="$current->currency" /></p>
                    @endif
                </x-ui.form-section>

                @if ($current !== null)
                    {{-- 2. Quotes and the comparison of the bids --}}
                    <x-ui.form-section :title="__('purchases.quotes_title')" :hint="__('purchases.quotes_hint', ['count' => $quotesNeeded])">
                        @if ($comparison['quotes'] !== [])
                            <x-ui.scroll-table :paginate="false">
                                <table class="fin-table" data-test="comparison">
                                    <thead>
                                        <tr>
                                            <th>{{ __('purchases.item') }}</th>
                                            @foreach ($comparison['quotes'] as $row)
                                                <th class="fin-num" @if ($comparison['lowest'] === $row['quote']->id) data-test="lowest-quote" @endif>
                                                    {{ $row['quote']->supplier }}
                                                    @if ($comparison['lowest'] === $row['quote']->id)
                                                        <flux:badge size="sm" color="green">{{ __('purchases.lowest') }}</flux:badge>
                                                    @endif
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($current->items as $item)
                                            <tr wire:key="cmp-{{ $item->id }}">
                                                <td>{{ $item->description }} <span class="text-xs text-secondary-medium dark:text-ink-muted">× {{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}</span></td>
                                                @foreach ($comparison['quotes'] as $row)
                                                    <td class="fin-num">
                                                        @if (isset($row['prices'][$item->id]))
                                                            <x-money :amount="$row['prices'][$item->id]['total']" :currency="$current->currency" />
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td class="font-semibold">{{ __('purchases.total') }}</td>
                                            @foreach ($comparison['quotes'] as $row)
                                                <td class="fin-num font-semibold">
                                                    <x-money :amount="$row['total']" :currency="$current->currency" />
                                                    @if ($editable && $canEdit)
                                                        <x-ui.button size="sm" tone="ghost" icon="trash" wire:click="removeQuote({{ $row['quote']->id }})" wire:confirm="{{ __('purchases.remove_quote_confirm') }}" :aria-label="__('purchases.remove_quote')" />
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    </tfoot>
                                </table>
                            </x-ui.scroll-table>
                        @endif

                        @if ($editable && $canEdit)
                            <div class="mt-4 flex flex-col gap-4 rounded-lg border border-secondary-soft p-4 dark:border-line" data-test="quote-form">
                                <p class="font-semibold">{{ __('purchases.add_quote') }}</p>
                                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                    <x-ui.form-input wire:model="quote.supplier" :label="__('purchases.supplier')" required data-test="q-supplier" />
                                    <x-ui.form-input wire:model="quote.address" :label="__('purchases.address')" />
                                    <x-ui.form-input wire:model="quote.phone" dir="ltr" :label="__('purchases.phone')" />
                                    <x-ui.form-input type="date" wire:model="quote.quoted_on" :label="__('purchases.quoted_on')" />
                                    <x-ui.form-input wire:model="quote.valid_for" :label="__('purchases.valid_for')" />
                                </div>
                                <x-ui.scroll-table :paginate="false">
                                    <table class="fin-table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('purchases.item') }}</th>
                                                <th class="fin-num">{{ __('purchases.quantity') }}</th>
                                                <th class="fin-num">{{ __('purchases.unit_price') }}</th>
                                                <th>{{ __('purchases.brand') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($current->items as $item)
                                                <tr wire:key="qp-{{ $item->id }}">
                                                    <td>{{ $item->description }}</td>
                                                    <td class="fin-num">{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}</td>
                                                    <td><input type="text" inputmode="decimal" dir="ltr" wire:model="quotePrices.{{ $item->id }}.price" class="{{ $cell }} text-end" aria-label="{{ __('purchases.unit_price') }} {{ $item->line_no }}" data-test="q-price-{{ $item->line_no }}"></td>
                                                    <td><input type="text" wire:model="quotePrices.{{ $item->id }}.brand" class="{{ $cell }}" aria-label="{{ __('purchases.brand') }} {{ $item->line_no }}"></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </x-ui.scroll-table>
                                <div class="flex flex-wrap items-end gap-4">
                                    <div>
                                        <label class="text-sm font-medium" for="quote-scan">{{ __('purchases.quote_scan') }}</label>
                                        <input id="quote-scan" type="file" wire:model="quoteScan" accept=".pdf,.jpg,.jpeg,.png" class="block text-sm">
                                    </div>
                                    <x-ui.button icon="plus" wire:click="addQuote" data-test="add-quote">{{ __('purchases.add_quote') }}</x-ui.button>
                                </div>
                            </div>

                            @if ($comparison['quotes'] !== [])
                                <div class="mt-4 grid gap-4 rounded-lg border border-secondary-soft p-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-line" data-test="award-form">
                                    <x-ui.form-select wire:model="awardQuote" :label="__('purchases.award_to')" required data-test="award-quote">
                                        <option value="">{{ __('purchases.choose') }}</option>
                                        @foreach ($comparison['quotes'] as $row)
                                            <option value="{{ $row['quote']->id }}">{{ $row['quote']->supplier }}</option>
                                        @endforeach
                                    </x-ui.form-select>
                                    <div class="sm:col-span-2">
                                        <x-ui.form-input wire:model="awardReason" :label="__('purchases.award_reason')" required data-test="award-reason" />
                                    </div>
                                    <x-ui.form-input wire:model="otherCharges" inputmode="decimal" dir="ltr" :label="__('purchases.other_charges')" :help="__('purchases.other_charges_help')" data-test="other-charges" />
                                    <div class="sm:col-span-2 lg:col-span-4">
                                        <x-ui.button tone="primary" icon="paper-airplane" wire:click="submitOrder" data-test="submit-order">{{ __('purchases.submit_order') }}</x-ui.button>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </x-ui.form-section>

                    {{-- 3. The purchase order and its approvals --}}
                    @if ($orderTotal !== null && $current->stage !== \App\Models\Purchase::SOURCING)
                        <x-ui.form-section :title="__('purchases.order_title')" :hint="__('purchases.order_hint')">
                            <div class="flex flex-col gap-3 text-sm" data-test="order">
                                <p>{{ __('purchases.awarded', ['supplier' => $current->awardedQuote?->supplier]) }} · {{ __('purchases.total') }}: <x-money :amount="$orderTotal" :currency="$current->currency" /></p>
                                @if (($current->approvals ?? []) !== [])
                                    <ul class="flex flex-col gap-1" data-test="approvals">
                                        @foreach ($current->approvals as $approval)
                                            <li>✓ {{ \App\Enums\RoleId::tryFrom((int) $approval['role'])?->label() }} · {{ $approval['name'] }} · {{ $approval['at'] }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if ($nextRole !== null)
                                    <p data-test="waiting-for">{{ __('purchases.waiting_for', ['role' => $nextRole->label()]) }}</p>
                                @endif
                                @if ($mayApprove)
                                    <div class="flex flex-wrap items-end gap-3">
                                        <x-ui.button tone="primary" icon="check" wire:click="approve" data-test="approve-order">{{ __('purchases.approve') }}</x-ui.button>
                                        <div class="w-72"><x-ui.form-input wire:model="returnReason" :label="__('purchases.return_reason')" data-test="return-reason-input" /></div>
                                        <x-ui.button icon="arrow-uturn-left" wire:click="returnOrder" data-test="return-order">{{ __('purchases.return') }}</x-ui.button>
                                    </div>
                                @endif
                            </div>
                        </x-ui.form-section>
                    @endif

                    {{-- 4. Received, with the supplier's invoice --}}
                    @if (in_array($current->stage, ['ordered', 'received', 'paid'], true))
                        @php($canReceive = $canEdit && in_array($current->stage, ['ordered', 'received'], true))
                        <x-ui.form-section :title="__($service ? 'purchases.srn_title' : 'purchases.grn_title')" :hint="__('purchases.receive_hint')">
                            <fieldset @disabled(! $canReceive) class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-test="receive-form">
                                <x-ui.form-input type="date" wire:model="receipt.received_on" :label="__('purchases.received_on')" required data-test="r-received-on" />
                                <x-ui.form-input wire:model="receipt.receiving_location" :label="__('purchases.receiving_location')" />
                                <x-ui.form-input wire:model="receipt.invoice_number" dir="ltr" :label="__('purchases.invoice_number')" required data-test="r-invoice-number" />
                                <x-ui.form-input type="date" wire:model="receipt.invoice_date" :label="__('purchases.invoice_date')" />
                                <x-ui.form-input wire:model="receipt.invoice_amount" inputmode="decimal" dir="ltr" :label="__('purchases.invoice_amount')" :help="__('purchases.invoice_amount_help', ['total' => $orderTotal])" required data-test="r-invoice-amount" />
                                <x-ui.form-select wire:model="receipt.accepted" :label="__('purchases.accepted')" data-test="r-accepted">
                                    <option value="yes">{{ __('purchases.accepted_yes') }}</option>
                                    <option value="no">{{ __('purchases.accepted_no') }}</option>
                                </x-ui.form-select>
                                <div class="sm:col-span-2">
                                    <x-ui.form-input wire:model="receipt.remarks" :label="__('purchases.remarks')" />
                                </div>
                            </fieldset>
                            <div class="mt-4">
                                <x-ui.scroll-table :paginate="false">
                                    <table class="fin-table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('purchases.item') }}</th>
                                                @if ($service)
                                                    <th>{{ __('purchases.expected_on') }}</th>
                                                    <th>{{ __('purchases.completed_on') }}</th>
                                                    <th>{{ __('purchases.quality') }}</th>
                                                @else
                                                    <th class="fin-num">{{ __('purchases.expected_quantity') }}</th>
                                                    <th class="fin-num">{{ __('purchases.received_quantity') }}</th>
                                                    <th>{{ __('purchases.condition') }}</th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($current->items as $item)
                                                <tr wire:key="rcv-{{ $item->id }}">
                                                    <td>{{ $item->description }}</td>
                                                    @if ($service)
                                                        <td><input type="date" wire:model="received.{{ $item->id }}.expected_on" class="{{ $cell }}" @disabled(! $canReceive) aria-label="{{ __('purchases.expected_on') }} {{ $item->line_no }}"></td>
                                                        <td><input type="date" wire:model="received.{{ $item->id }}.completed_on" class="{{ $cell }}" @disabled(! $canReceive) aria-label="{{ __('purchases.completed_on') }} {{ $item->line_no }}" data-test="r-completed-{{ $item->line_no }}"></td>
                                                    @else
                                                        <td class="fin-num">{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}</td>
                                                        <td><input type="text" inputmode="decimal" dir="ltr" wire:model="received.{{ $item->id }}.received_quantity" class="{{ $cell }} text-end" @disabled(! $canReceive) aria-label="{{ __('purchases.received_quantity') }} {{ $item->line_no }}" data-test="r-quantity-{{ $item->line_no }}"></td>
                                                    @endif
                                                    <td><input type="text" wire:model="received.{{ $item->id }}.condition" class="{{ $cell }}" @disabled(! $canReceive) aria-label="{{ __($service ? 'purchases.quality' : 'purchases.condition') }} {{ $item->line_no }}"></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </x-ui.scroll-table>
                            </div>
                            @if ($canReceive)
                                <div class="mt-3"><x-ui.button tone="primary" icon="check" wire:click="receive" data-test="save-receipt">{{ __('purchases.save_receipt') }}</x-ui.button></div>
                            @endif
                        </x-ui.form-section>
                    @endif

                    {{-- 5. Payment --}}
                    @if ($current->stage === \App\Models\Purchase::PAID && $current->voucher_id !== null)
                        <x-ui.form-section :title="__('purchases.payment_title')">
                            <p class="text-sm" data-test="payment-voucher">
                                {{ __('purchases.paid_by', ['method' => __('purchases.methods.'.$current->payment_method)]) }}
                                <a href="{{ route(\App\Support\Navigation::routeName('vouchers'), ['voucher' => $current->voucher_id]) }}" wire:navigate class="font-semibold underline" dir="ltr">{{ $current->voucher_id }}</a>
                                · {{ $current->voucher?->status->label() }}
                            </p>
                        </x-ui.form-section>
                    @elseif ($current->stage === \App\Models\Purchase::RECEIVED && $mayPay)
                        <x-ui.form-section :title="__('purchases.payment_title')" :hint="__('purchases.payment_hint')">
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-test="pay-form">
                                <x-ui.form-select wire:model.live="pay.method" :label="__('purchases.method')" data-test="p-method">
                                    <option value="cash">{{ __('purchases.methods.cash') }}</option>
                                    <option value="advance">{{ __('purchases.methods.advance') }}</option>
                                </x-ui.form-select>
                                <x-ui.form-input type="date" wire:model="pay.date" :label="__('purchases.pay_date')" required data-test="p-date" />
                                @if (($pay['method'] ?? 'cash') === 'advance')
                                    <div class="sm:col-span-2">
                                        <x-ui.form-select wire:model="pay.advance" :label="__('purchases.advance')" :help="__('purchases.advance_help')" required data-test="p-advance">
                                            <option value="">{{ __('purchases.choose') }}</option>
                                            @foreach ($advances as $advance)
                                                <option value="{{ $advance['key'] }}">{{ $advance['person'] }} · {{ $advance['open'] }} {{ $advance['currency'] }} · {{ $advance['since'] }}</option>
                                            @endforeach
                                        </x-ui.form-select>
                                    </div>
                                @endif
                                <x-ui.form-select wire:model.live="pay.account" :label="__(($pay['method'] ?? 'cash') === 'advance' ? 'purchases.cash_for_rest' : 'purchases.paid_from')" data-test="p-account">
                                    <option value="">{{ __('purchases.choose') }}</option>
                                    @foreach ($cashAccounts as $code => $label)
                                        <option value="{{ $code }}">{{ $label }}</option>
                                    @endforeach
                                </x-ui.form-select>
                                @if (($pay['method'] ?? 'cash') === 'cash' && $current->currency !== 'USD')
                                    <x-ui.form-input wire:model="pay.rate" inputmode="decimal" dir="ltr" :label="__('purchases.rate')" required data-test="p-rate" />
                                @endif
                                <x-ui.form-input wire:model="pay.center" :label="__('purchases.center')" :help="__('purchases.center_help')" />
                                <div class="sm:col-span-2">
                                    <label class="text-sm font-medium" for="pay-files">{{ __('purchases.pay_files') }}</label>
                                    <input id="pay-files" type="file" multiple wire:model="payFiles" accept=".pdf,.jpg,.jpeg,.png" class="block text-sm">
                                </div>
                                <div class="sm:col-span-2 lg:col-span-4">
                                    <x-ui.button tone="primary" icon="banknotes" wire:click="payFile" data-test="pay">{{ __('purchases.pay', ['amount' => $orderTotal, 'currency' => $current->currency]) }}</x-ui.button>
                                </div>
                            </div>
                        </x-ui.form-section>
                    @endif

                    @if ($canEdit && ! in_array($current->stage, ['paid', 'cancelled'], true))
                        <div>
                            <x-ui.button size="sm" tone="ghost" icon="x-circle" wire:click="cancel" wire:confirm="{{ __('purchases.cancel_confirm') }}" data-test="cancel-purchase">{{ __('purchases.cancel') }}</x-ui.button>
                        </div>
                    @endif
                @endif
            </div>
        </x-ui.panel>
    @endif
</div>
