@php
    $cell = 'w-full min-w-16 rounded-md border border-secondary-light bg-white px-2 py-1.5 text-sm text-secondary-dark dark:border-field-line dark:bg-surface-2 dark:text-ink';
@endphp

<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="revenues">
    <x-ui.page-header :title="__('navigation.screens.revenues')" :subtitle="__('revenues.intro')" />

    <div class="flex gap-2" role="tablist">
        @foreach ($posReady ? ['pos', 'record', 'list', 'items'] : ['record', 'list', 'items'] as $name)
            <x-ui.button size="sm" :tone="$tab === $name ? 'primary' : 'secondary'" wire:click="$set('tab', '{{ $name }}')" role="tab" :aria-selected="$tab === $name ? 'true' : 'false'" data-test="tab-{{ $name }}">{{ __('revenues.tabs.'.$name) }}</x-ui.button>
        @endforeach
    </div>

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

    @if ($tab === 'pos' && ! $posReady)
        <x-ui.empty-state icon="shopping-cart" :heading="__('revenues.pos.pending')" data-test="pos-pending" />
    @elseif ($tab === 'pos' && $shift === null)
        {{-- Point of sale (finance request of 2026-11-26): open a shift, or carry on with one still open. --}}
        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.panel :title="__('revenues.pos.open_title')" :subtitle="__('revenues.pos.open_hint')">
                <div class="flex flex-col gap-4 p-4" data-test="pos-open">
                    <x-ui.form-select wire:model="opening.project" :label="__('revenues.project')" required data-test="pos-project">
                        <option value="">{{ __('revenues.choose') }}</option>
                        @foreach ($projects as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </x-ui.form-select>
                    <x-ui.form-select wire:model="opening.cash_account" :label="__('revenues.received_in')" required data-test="pos-cash">
                        <option value="">{{ __('revenues.choose') }}</option>
                        @foreach ($cashAccounts as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </x-ui.form-select>
                    <x-ui.form-input type="date" wire:model="opening.day" :label="__('revenues.day')" required data-test="pos-day" />
                    @if ($canCreate)
                        <x-ui.action-bar>
                            <x-ui.button tone="primary" icon="play" wire:click="openShift" data-test="pos-open-shift">{{ __('revenues.pos.open') }}</x-ui.button>
                        </x-ui.action-bar>
                    @endif
                </div>
            </x-ui.panel>

            <x-ui.panel :title="__('revenues.pos.open_shifts')" :count="$openShifts->count()">
                <div class="fin-table-wrap">
                    <table class="fin-table" data-test="pos-open-shifts">
                        <thead><tr><th>{{ __('revenues.day') }}</th><th>{{ __('revenues.project') }}</th><th>{{ __('revenues.received_in') }}</th><th class="fin-num">{{ __('revenues.amount') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($openShifts as $open)
                                <tr wire:key="open-shift-{{ $open->id }}">
                                    <td dir="ltr" class="text-start">{{ $open->day->format('d/m/Y') }}</td>
                                    <td>{{ $open->project_code }}</td>
                                    <td dir="ltr" class="text-start">{{ $open->cash_account }}</td>
                                    <td class="fin-num"><x-money :amount="$open->total" :currency="$open->currency" /></td>
                                    <td><x-ui.button size="sm" icon="arrow-uturn-left" wire:click="resumeShift({{ $open->id }})" data-test="pos-resume-{{ $open->id }}">{{ __('revenues.pos.resume') }}</x-ui.button></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="fin-empty">{{ __('revenues.pos.no_open') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.panel>
        </div>
    @elseif ($tab === 'pos')
        {{-- The sale screen: categories and item tiles, the basket with its totals, and the shift's sales. --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-secondary-soft bg-surface-2 px-4 py-3 dark:border-line" data-test="pos-shift">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm text-secondary-dark dark:text-ink">
                <span><strong>{{ __('revenues.project') }}:</strong> {{ $projects[$shift->project_code] ?? $shift->project_code }}</span>
                <span><strong>{{ __('revenues.received_in') }}:</strong> <span dir="ltr">{{ $shift->cash_account }}</span> ({{ $shift->currency }})</span>
                <span><strong>{{ __('revenues.day') }}:</strong> <span dir="ltr">{{ $shift->day->format('d/m/Y') }}</span></span>
                <span><strong>{{ __('revenues.pos.shift_total') }}:</strong> <x-money :amount="$shift->total" :currency="$shift->currency" /> · {{ __('revenues.pos.sales_count', ['count' => $tickets->count()]) }}</span>
            </div>
            <div class="flex gap-2">
                <x-ui.button size="sm" tone="ghost" icon="arrow-left" wire:click="leaveShift" data-test="pos-leave">{{ __('revenues.pos.leave') }}</x-ui.button>
                @if ($canCreate)
                    <x-ui.button size="sm" icon="lock-closed" wire:click="$toggle('closing')" data-test="pos-close-toggle">{{ __('revenues.pos.close') }}</x-ui.button>
                @endif
            </div>
        </div>

        @if ($closing)
            <x-ui.panel :title="__('revenues.pos.close_title')" :subtitle="__('revenues.pos.close_hint')">
                <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4" data-test="pos-close-form">
                    <x-ui.form-input wire:model="close.rate" inputmode="decimal" dir="ltr" :label="__('revenues.rate')" :help="__('revenues.rate_help')" />
                    <x-ui.form-input wire:model="close.center" :label="__('revenues.center')" :help="__('revenues.center_help')" />
                    <x-ui.form-input wire:model="close.notes" :label="__('revenues.notes')" />
                    <div class="flex items-end gap-2">
                        <x-ui.button tone="primary" icon="check" wire:click="closeShift" wire:confirm="{{ __('revenues.pos.close_confirm') }}" data-test="pos-close">{{ __('revenues.pos.close_now') }}</x-ui.button>
                        <x-ui.button tone="ghost" wire:click="$set('closing', false)">{{ __('revenues.cancel') }}</x-ui.button>
                    </div>
                </div>
            </x-ui.panel>
        @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <section class="flex min-w-0 flex-col gap-4 xl:col-span-2" aria-label="{{ __('revenues.pos.items') }}">
                <div class="flex flex-wrap gap-2" role="tablist" data-test="pos-categories">
                    <x-ui.button size="sm" :tone="$category === '' ? 'primary' : 'secondary'" wire:click="$set('category', '')" role="tab" :aria-selected="$category === '' ? 'true' : 'false'">{{ __('revenues.pos.all') }}</x-ui.button>
                    @foreach ($categories as $name)
                        <x-ui.button size="sm" :tone="$category === $name ? 'primary' : 'secondary'" wire:click="$set('category', {{ \Illuminate\Support\Js::from($name) }})" role="tab" :aria-selected="$category === $name ? 'true' : 'false'" wire:key="category-{{ $loop->index }}">{{ $name }}</x-ui.button>
                    @endforeach
                </div>

                <x-ui.form-input wire:model.live.debounce.250ms="find" :label="__('revenues.pos.find')" type="search" data-test="pos-find" />

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" data-test="pos-tiles">
                    @forelse ($tiles as $tile)
                        <x-revenues.pos-tile
                            wire:click="addItem({{ $tile->id }})"
                            wire:key="tile-{{ $tile->id }}"
                            :name="$tile->name"
                            :price="$tile->unit_price"
                            :currency="$tile->currency"
                            :tone="$tileTones[$tile->category ?: __('revenues.kinds.'.$tile->kind)] ?? 1"
                            :count="$basket[$tile->id] ?? null"
                            data-test="pos-tile-{{ $tile->id }}"
                        />
                    @empty
                        <div class="col-span-full"><x-ui.empty-state icon="tag" :heading="__('revenues.pos.no_items')" :body="__('revenues.pos.no_items_hint')" /></div>
                    @endforelse
                </div>
            </section>

            <x-ui.panel :title="__('revenues.pos.basket')" :count="count($basketRows)">
                <div class="flex flex-col gap-4 p-4" data-test="pos-basket">
                    <div class="fin-table-wrap">
                        <table class="fin-table">
                            <thead><tr><th>{{ __('revenues.item_name') }}</th><th class="fin-num">{{ __('revenues.quantity') }}</th><th class="fin-num">{{ __('revenues.amount') }}</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($basketRows as $row)
                                    <tr wire:key="basket-{{ $row['id'] }}">
                                        <td class="font-medium">{{ $row['name'] }}<div class="text-xs text-ink-muted"><x-money :amount="$row['price']" /></div></td>
                                        <td class="fin-num">
                                            <div class="inline-flex items-center gap-1">
                                                <x-ui.button size="xs" tone="ghost" icon="minus" wire:click="lessItem({{ $row['id'] }})" :aria-label="__('revenues.pos.less')" />
                                                <input type="text" inputmode="decimal" dir="ltr" wire:model.live.debounce.400ms="basket.{{ $row['id'] }}" class="{{ $cell }} w-16! min-w-0! text-center" aria-label="{{ __('revenues.quantity') }} {{ $row['name'] }}" data-test="pos-quantity-{{ $row['id'] }}">
                                                <x-ui.button size="xs" tone="ghost" icon="plus" wire:click="addItem({{ $row['id'] }})" :aria-label="__('revenues.pos.more')" />
                                            </div>
                                        </td>
                                        <td class="fin-num"><x-money :amount="$row['amount']" /></td>
                                        <td><x-ui.button size="xs" tone="ghost" icon="trash" wire:click="dropItem({{ $row['id'] }})" :aria-label="__('revenues.remove_line')" /></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="fin-empty">{{ __('revenues.pos.basket_empty') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <dt class="text-ink-muted">{{ __('revenues.pos.gross') }}</dt>
                        <dd class="text-end"><x-money :amount="$gross" :currency="$shift->currency" /></dd>
                    </dl>
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.form-input wire:model.live.debounce.400ms="payment.discount_percent" inputmode="decimal" dir="ltr" :label="__('revenues.pos.discount_percent')" suffix="%" data-test="pos-discount-percent" />
                        <x-ui.form-input wire:model.live.debounce.400ms="payment.discount" inputmode="decimal" dir="ltr" :label="__('revenues.pos.discount')" data-test="pos-discount" />
                    </div>
                    <div class="flex items-center justify-between rounded-xl bg-status-good-soft px-4 py-3 text-status-good" data-test="pos-net">
                        <span class="text-sm font-semibold">{{ __('revenues.pos.net') }}</span>
                        <x-money :amount="$net" :currency="$shift->currency" class="text-2xl font-bold text-status-good!" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.form-input wire:model.live.debounce.400ms="payment.paid" inputmode="decimal" dir="ltr" :label="__('revenues.pos.paid')" data-test="pos-paid" />
                        <div class="flex flex-col justify-end text-sm">
                            <span class="text-ink-muted">{{ __('revenues.pos.change') }}</span>
                            @if ($change !== null)
                                <x-money :amount="$change" :currency="$shift->currency" class="text-lg font-bold" data-test="pos-change" />
                            @else
                                <span class="text-ink-faint">—</span>
                            @endif
                        </div>
                    </div>
                    <x-ui.form-input wire:model="payment.note" :label="__('revenues.pos.note')" />

                    @if ($canCreate)
                        <div class="flex gap-2">
                            <x-ui.button tone="primary" icon="check" class="flex-1 justify-center" wire:click="sell" :disabled="$basketRows === []" data-test="pos-sell">{{ __('revenues.pos.sell') }}</x-ui.button>
                            <x-ui.button tone="ghost" icon="x-mark" wire:click="clearBasket" data-test="pos-clear">{{ __('revenues.pos.clear') }}</x-ui.button>
                        </div>
                    @endif
                </div>
            </x-ui.panel>
        </div>

        <x-ui.panel :title="__('revenues.pos.sales')" :count="$tickets->count()">
            <div class="fin-table-wrap">
                <table class="fin-table" data-test="pos-sales">
                    <thead><tr><th>#</th><th>{{ __('revenues.pos.time') }}</th><th>{{ __('revenues.pos.what') }}</th><th class="fin-num">{{ __('revenues.pos.gross') }}</th><th class="fin-num">{{ __('revenues.pos.discount') }}</th><th class="fin-num">{{ __('revenues.pos.net') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($tickets as $ticket)
                            <tr wire:key="ticket-{{ $ticket->id }}">
                                <td class="tabular-nums">{{ $ticket->number }}</td>
                                <td dir="ltr" class="text-start tabular-nums">{{ $ticket->created_at?->format('H:i') }}</td>
                                <td>{{ $ticket->lines->map(fn ($line) => $line->description.' × '.rtrim(rtrim((string) $line->quantity, '0'), '.'))->implode('، ') }}</td>
                                <td class="fin-num"><x-money :amount="$ticket->gross" /></td>
                                <td class="fin-num"><x-money :amount="$ticket->discount" /></td>
                                <td class="fin-num"><x-money :amount="$ticket->net" /></td>
                                <td>
                                    @if ($canCreate)
                                        <x-ui.button size="xs" tone="ghost" icon="trash" wire:click="voidTicket({{ $ticket->id }})" wire:confirm="{{ __('revenues.pos.void_confirm', ['number' => $ticket->number]) }}" :aria-label="__('revenues.pos.void')" data-test="pos-void-{{ $ticket->id }}" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="fin-empty">{{ __('revenues.pos.no_sales') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.panel>
    @elseif ($tab === 'record')
        <x-ui.panel :title="__('revenues.record_title')" :subtitle="__('revenues.record_hint')">
            <div class="flex flex-col gap-4 p-4" data-test="record-form">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="sm:col-span-2">
                        <x-ui.form-select wire:model.live="sheet.project" :label="__('revenues.project')" required data-test="r-project">
                            <option value="">{{ __('revenues.choose') }}</option>
                            @foreach ($projects as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.form-select>
                    </div>
                    <x-ui.form-input type="date" wire:model="sheet.day" :label="__('revenues.day')" required data-test="r-day" />
                    <x-ui.form-select wire:model="sheet.cash_account" :label="__('revenues.received_in')" required data-test="r-cash">
                        <option value="">{{ __('revenues.choose') }}</option>
                        @foreach ($cashAccounts as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </x-ui.form-select>
                </div>

                <x-ui.scroll-table :paginate="false">
                    <table class="fin-table" data-test="revenue-lines">
                        <thead>
                            <tr>
                                <th>{{ __('revenues.item') }}</th>
                                <th>{{ __('revenues.description') }}</th>
                                <th class="fin-num">{{ __('revenues.quantity') }}</th>
                                <th class="fin-num">{{ __('revenues.unit_price') }}</th>
                                <th class="fin-num">{{ __('revenues.amount') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $i => $line)
                                <tr wire:key="revenue-line-{{ $i }}">
                                    <td class="min-w-48">
                                        <x-ui.form-select bare wire:model.live="lines.{{ $i }}.item" :label="__('revenues.item').' '.($i + 1)" data-test="line-item-{{ $i }}">
                                            <option value="">{{ __('revenues.free_line') }}</option>
                                            @foreach ($priceList as $entry)
                                                <option value="{{ $entry->id }}">{{ $entry->name }} · {{ $entry->unit_price }} {{ $entry->currency }}</option>
                                            @endforeach
                                        </x-ui.form-select>
                                    </td>
                                    <td><input type="text" wire:model.blur="lines.{{ $i }}.description" class="{{ $cell }}" aria-label="{{ __('revenues.description') }} {{ $i + 1 }}" data-test="line-description-{{ $i }}"></td>
                                    <td><input type="text" inputmode="decimal" dir="ltr" wire:model.live.debounce.400ms="lines.{{ $i }}.quantity" class="{{ $cell }} text-end" aria-label="{{ __('revenues.quantity') }} {{ $i + 1 }}" data-test="line-quantity-{{ $i }}"></td>
                                    <td><input type="text" inputmode="decimal" dir="ltr" wire:model.live.debounce.400ms="lines.{{ $i }}.unit_price" class="{{ $cell }} text-end" aria-label="{{ __('revenues.unit_price') }} {{ $i + 1 }}" data-test="line-price-{{ $i }}"></td>
                                    <td class="fin-num">
                                        @if (is_numeric(str_replace(',', '', $line['quantity'])) && is_numeric(str_replace(',', '', $line['unit_price'])))
                                            <x-money :amount="(float) str_replace(',', '', $line['quantity']) * (float) str_replace(',', '', $line['unit_price'])" />
                                        @endif
                                    </td>
                                    <td><x-ui.button size="sm" tone="ghost" icon="trash" wire:click="removeLine({{ $i }})" :aria-label="__('revenues.remove_line')" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-ui.scroll-table>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.form-input wire:model="sheet.rate" inputmode="decimal" dir="ltr" :label="__('revenues.rate')" :help="__('revenues.rate_help')" />
                    <x-ui.form-input wire:model="sheet.center" :label="__('revenues.center')" :help="__('revenues.center_help')" />
                    <div class="sm:col-span-2"><x-ui.form-input wire:model="sheet.notes" :label="__('revenues.notes')" /></div>
                </div>

                @if ($canCreate)
                    <x-ui.action-bar>
                        <x-ui.button size="sm" icon="plus" wire:click="addLine" data-test="add-line">{{ __('revenues.add_line') }}</x-ui.button>
                        <x-ui.button tone="primary" icon="check" wire:click="record" data-test="record">{{ __('revenues.record') }}</x-ui.button>
                    </x-ui.action-bar>
                @endif
            </div>
        </x-ui.panel>
    @elseif ($tab === 'list')
        <div class="w-48"><x-ui.form-input type="month" wire:model.live="month" :label="__('revenues.month')" data-test="month" /></div>

        <x-ui.panel :title="__('revenues.summary_title')" data-test="summary">
            <x-ui.scroll-table :paginate="false">
                <table class="fin-table">
                    <thead><tr><th>{{ __('revenues.project') }}</th><th>{{ __('revenues.kind') }}</th><th class="fin-num">{{ __('revenues.amount') }}</th></tr></thead>
                    <tbody>
                        @forelse ($summary as $row)
                            <tr><td>{{ $row['project'] }}</td><td>{{ __('revenues.kinds.'.$row['kind']) }}</td><td class="fin-num"><x-money :amount="$row['total']" :currency="$row['currency']" /></td></tr>
                        @empty
                            <tr><td colspan="3"><x-ui.empty-state icon="banknotes" :heading="__('revenues.none')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.scroll-table>
        </x-ui.panel>

        <x-ui.panel :title="__('revenues.sheets_title')" :count="$sheets->count()">
            <x-ui.scroll-table :paginate="false">
                <table class="fin-table" data-test="sheets">
                    <thead><tr><th>{{ __('revenues.day') }}</th><th>{{ __('revenues.project') }}</th><th class="fin-num">{{ __('revenues.amount') }}</th><th>{{ __('revenues.voucher') }}</th></tr></thead>
                    <tbody>
                        @foreach ($sheets as $row)
                            <tr wire:key="sheet-{{ $row->id }}" @class(['opacity-60 line-through' => $row->status === 'rejected'])>
                                <td dir="ltr" class="text-start">{{ $row->day->format('d/m/Y') }}</td>
                                <td>{{ $row->project_code }}</td>
                                <td class="fin-num"><x-money :amount="$row->total" :currency="$row->currency" /></td>
                                <td>
                                    @if ($row->voucher_id)
                                        <a href="{{ route(\App\Support\Navigation::routeName('vouchers'), ['voucher' => $row->voucher_id]) }}" wire:navigate class="underline" dir="ltr">{{ $row->voucher_id }}</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.scroll-table>
        </x-ui.panel>
    @else
        @if ($itemEditing !== '')
            <x-ui.panel :title="$itemEditing === 'new' ? __('revenues.item_new') : __('revenues.item_edit')" data-test="item-form">
                <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-ui.form-select wire:model="item.project" :label="__('revenues.project')" required data-test="i-project">
                        <option value="">{{ __('revenues.choose') }}</option>
                        @foreach ($projects as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </x-ui.form-select>
                    <x-ui.form-input wire:model="item.name" :label="__('revenues.item_name')" required data-test="i-name" />
                    <x-ui.form-select wire:model="item.kind" :label="__('revenues.kind')" data-test="i-kind">
                        @foreach (\App\Models\RevenueItem::KINDS as $kind)
                            <option value="{{ $kind }}">{{ __('revenues.kinds.'.$kind) }}</option>
                        @endforeach
                    </x-ui.form-select>
                    @if ($posReady)
                        <x-ui.form-input wire:model="item.category" :label="__('revenues.pos.category')" :help="__('revenues.pos.category_help')" data-test="i-category" />
                    @endif
                    <x-ui.form-input wire:model="item.unit_price" inputmode="decimal" dir="ltr" :label="__('revenues.unit_price')" required data-test="i-price" />
                    <x-ui.form-select wire:model="item.currency" :label="__('revenues.currency')" data-test="i-currency">
                        <option value="SYP">{{ __('revenues.currencies.SYP') }}</option>
                        <option value="USD">{{ __('revenues.currencies.USD') }}</option>
                    </x-ui.form-select>
                    <x-ui.form-select wire:model="item.active" :label="__('revenues.active')">
                        <option value="1">{{ __('revenues.yes') }}</option>
                        <option value="0">{{ __('revenues.no') }}</option>
                    </x-ui.form-select>
                    <div class="flex gap-3">
                        <x-ui.button tone="primary" icon="check" wire:click="saveItem" data-test="save-item">{{ __('revenues.save') }}</x-ui.button>
                        <x-ui.button tone="ghost" wire:click="cancelItem">{{ __('revenues.cancel') }}</x-ui.button>
                    </div>
                </div>
            </x-ui.panel>
        @endif

        <x-ui.panel :title="__('revenues.items_title')" :subtitle="__('revenues.items_hint')" :count="$items->count()">
            <x-slot:toolbar>
                @if ($canEdit)
                    <x-ui.button size="sm" icon="plus" wire:click="newItem" data-test="new-item">{{ __('revenues.item_new') }}</x-ui.button>
                @endif
            </x-slot:toolbar>
            <x-ui.scroll-table :paginate="false">
                <table class="fin-table" data-test="items">
                    <thead><tr><th>{{ __('revenues.project') }}</th><th>{{ __('revenues.item_name') }}</th><th>{{ __('revenues.kind') }}</th><th>{{ __('revenues.pos.category') }}</th><th class="fin-num">{{ __('revenues.unit_price') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($items as $entry)
                            <tr wire:key="item-{{ $entry->id }}" @class(['opacity-60' => ! $entry->active])>
                                <td>{{ $entry->project_code }}</td>
                                <td class="font-medium">{{ $entry->name }}</td>
                                <td>{{ __('revenues.kinds.'.$entry->kind) }}</td>
                                <td>{{ $entry->category }}</td>
                                <td class="fin-num"><x-money :amount="$entry->unit_price" :currency="$entry->currency" /></td>
                                <td>
                                    @if ($canEdit)
                                        <x-ui.button size="sm" tone="ghost" icon="pencil-square" wire:click="editItem({{ $entry->id }})" :aria-label="__('revenues.item_edit')" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-ui.empty-state icon="tag" :heading="__('revenues.items_none')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.scroll-table>
        </x-ui.panel>
    @endif
</div>
