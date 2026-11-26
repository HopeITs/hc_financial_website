@php
    // The point of sale (finance request of 2026-11-27): tables, an order, and the add-items window. Quantity and note
    // cells on an order are small inline fields, like the takings lines of the revenues screen.
    $cell = 'w-full min-w-0 rounded-md border border-secondary-light bg-white px-2 py-1.5 text-sm text-secondary-dark dark:border-field-line dark:bg-surface-2 dark:text-ink';
    $card = 'rounded-xl border border-secondary-soft bg-board dark:border-line dark:bg-surface-1';
@endphp

<div class="flex min-h-screen flex-col gap-4 p-3 lg:p-4" data-test="pos">
    {{-- Top bar: back to the program, the screen's name and the shift. --}}
    <header class="flex flex-wrap items-center justify-between gap-3 {{ $card }} px-4 py-2.5">
        <div class="flex items-center gap-3">
            <a href="{{ route('operations.revenues') }}" wire:navigate class="shrink-0 border-e border-secondary-soft pe-3 dark:border-line" data-test="pos-exit">
                <img src="{{ asset('logo.png') }}" alt="{{ __('common.app_name') }}" class="h-8 w-auto" data-no-dim />
            </a>
            <h1 class="text-lg font-bold">{{ $order !== null ? __('revenues.pos.order_title', ['number' => $order->number]) : ($shift !== null ? __('revenues.pos.tables') : __('revenues.pos.title')) }}</h1>
            @if ($shift !== null)
                <span class="rounded-md border border-secondary-soft px-2 py-0.5 text-sm dark:border-line">{{ $project?->name ?? $shift->project_code }}</span>
            @endif
        </div>
        <x-ui.button size="sm" tone="ghost" icon="arrow-uturn-left" :href="route('operations.revenues')" wire:navigate>{{ __('revenues.pos.exit') }}</x-ui.button>
    </header>

    @if ($notice)
        <div class="rounded-lg border border-status-good bg-status-good-soft px-4 py-2 text-sm text-status-good" role="status" data-test="pos-notice">{{ $notice }}</div>
    @endif
    @if ($problems !== [])
        <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="pos-problems">
            <ul class="list-disc ps-5">
                @foreach ($problems as $problem)
                    <li>{{ $problem }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <main class="flex flex-1 flex-col gap-4">
        @if (! $ready)
            <x-ui.empty-state icon="computer-desktop" :heading="__('revenues.pos.pending')" data-test="pos-pending" />
        @elseif ($shift === null)
            {{-- No shift: open one, or carry on with one still open. --}}
            <div class="grid gap-4 lg:grid-cols-2">
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
                        @if ($canSell)
                            <x-ui.button tone="primary" icon="play" wire:click="openShift" data-test="pos-open-shift">{{ __('revenues.pos.open') }}</x-ui.button>
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
                                        <td><x-ui.button size="sm" icon="arrow-right-end-on-rectangle" wire:click="resumeShift({{ $open->id }})" data-test="pos-resume-{{ $open->id }}">{{ __('revenues.pos.resume') }}</x-ui.button></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="fin-empty">{{ __('revenues.pos.no_open') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-ui.panel>
            </div>
        @elseif ($order === null)
            {{-- The tables: every open order of the shift as a card. --}}
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="w-48">
                    <x-ui.form-select wire:model.live="service" :label="__('revenues.pos.show')" data-test="pos-filter">
                        <option value="">{{ __('revenues.pos.all') }}</option>
                        @foreach (\App\Models\RevenueTicket::SERVICES as $kind)
                            <option value="{{ $kind }}">{{ __('revenues.pos.services.'.$kind) }}</option>
                        @endforeach
                    </x-ui.form-select>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-ui.button icon="archive-box" wire:click="$toggle('archive')" data-test="pos-archive">{{ __('revenues.pos.archive') }}</x-ui.button>
                    <x-ui.button icon="document-chart-bar" :href="route('operations.revenues.pos.report', ['shift' => $shift->id])" target="_blank" data-test="pos-day-end">{{ __('revenues.pos.day_end') }}</x-ui.button>
                    @if ($canSell)
                        <x-ui.button icon="lock-closed" wire:click="$toggle('closing')" data-test="pos-close-toggle">{{ __('revenues.pos.close') }}</x-ui.button>
                        <x-ui.button tone="primary" icon="plus" wire:click="$toggle('newTable')" data-test="pos-new-table">{{ __('revenues.pos.new_table') }}</x-ui.button>
                    @endif
                </div>
            </div>

            @if ($newTable)
                <x-ui.panel :title="__('revenues.pos.new_table')">
                    <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-5" data-test="pos-table-form">
                        <x-ui.form-select wire:model.live="table.service" :label="__('revenues.pos.service')" data-test="pos-table-service">
                            @foreach (\App\Models\RevenueTicket::SERVICES as $kind)
                                <option value="{{ $kind }}">{{ __('revenues.pos.services.'.$kind) }}</option>
                            @endforeach
                        </x-ui.form-select>
                        <x-ui.form-input wire:model="table.table" dir="ltr" :label="__('revenues.pos.table')" :required="($table['service'] ?? 'hall') === 'hall'" wire:keydown.enter="openTable" autofocus data-test="pos-table-number" />
                        <x-ui.form-input wire:model="table.persons" inputmode="numeric" dir="ltr" :label="__('revenues.pos.persons')" />
                        <x-ui.form-input wire:model="table.waiter" :label="__('revenues.pos.waiter')" />
                        <div class="flex items-end gap-2">
                            <x-ui.button tone="primary" icon="check" wire:click="openTable" data-test="pos-open-table">{{ __('revenues.pos.open_table') }}</x-ui.button>
                            <x-ui.button tone="ghost" wire:click="$set('newTable', false)">{{ __('revenues.cancel') }}</x-ui.button>
                        </div>
                    </div>
                </x-ui.panel>
            @endif

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

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5" data-test="pos-tables">
                @forelse ($orders as $open)
                    <button type="button" wire:click="showOrder({{ $open->id }})" wire:key="table-{{ $open->id }}" class="flex min-h-24 flex-col justify-between overflow-hidden text-start {{ $card }} hover:border-status-good focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" data-test="pos-table-{{ $open->id }}">
                        <span class="flex items-start justify-between gap-2">
                            <span class="inline-flex items-center gap-2 rounded-ee-xl bg-status-good px-3 py-1.5 text-lg font-bold text-white">
                                <flux:icon :name="$open->service === 'hall' ? 'square-3-stack-3d' : ($open->service === 'delivery' ? 'truck' : 'shopping-bag')" variant="micro" class="size-4" />
                                <span dir="ltr">{{ $open->table_label ?? __('revenues.pos.services.'.$open->service).' '.$open->number }}</span>
                            </span>
                            <x-money :amount="$open->net" class="px-3 py-1.5 text-lg font-bold" />
                        </span>
                        <span class="flex items-end justify-between gap-2 px-3 pb-2 text-sm text-ink-muted">
                            <span>{{ collect([$open->waiter, $open->persons ? __('revenues.pos.persons_count', ['count' => $open->persons]) : null, $open->customer])->filter()->implode(' · ') ?: '———' }}</span>
                            @if ($open->printed_at)
                                <flux:icon.printer variant="micro" class="size-4 text-status-good" aria-label="{{ __('revenues.pos.printed') }}" />
                            @endif
                        </span>
                    </button>
                @empty
                    <div class="col-span-full"><x-ui.empty-state icon="square-3-stack-3d" :heading="__('revenues.pos.no_tables')" :body="__('revenues.pos.no_tables_hint')" /></div>
                @endforelse
            </div>

            @if ($archive)
                <x-ui.panel :title="__('revenues.pos.archive')" :count="$archived->count()">
                    <div class="fin-table-wrap">
                        <table class="fin-table" data-test="pos-archived">
                            <thead><tr><th>{{ __('revenues.pos.invoice') }}</th><th>{{ __('revenues.pos.table') }}</th><th>{{ __('revenues.pos.waiter') }}</th><th>{{ __('revenues.pos.time') }}</th><th class="fin-num">{{ __('revenues.pos.gross') }}</th><th class="fin-num">{{ __('revenues.pos.discount') }}</th><th class="fin-num">{{ __('revenues.pos.net') }}</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($archived as $done)
                                    <tr wire:key="archived-{{ $done->id }}">
                                        <td class="tabular-nums">{{ $done->number }}</td>
                                        <td dir="ltr" class="text-start">{{ $done->table_label ?? __('revenues.pos.services.'.$done->service) }}</td>
                                        <td>{{ $done->waiter }}</td>
                                        <td dir="ltr" class="text-start tabular-nums">{{ $done->closed_at?->format('H:i') }}</td>
                                        <td class="fin-num"><x-money :amount="$done->gross" /></td>
                                        <td class="fin-num"><x-money :amount="$done->discount" /></td>
                                        <td class="fin-num"><x-money :amount="$done->net" /></td>
                                        <td class="whitespace-nowrap">
                                            <x-ui.button size="xs" tone="ghost" icon="printer" :href="route('operations.revenues.pos.receipt', ['order' => $done->id])" target="_blank" :aria-label="__('revenues.pos.print')" />
                                            @if ($canSell)
                                                <x-ui.button size="xs" tone="ghost" icon="trash" wire:click="cancelOrder({{ $done->id }})" wire:confirm="{{ __('revenues.pos.void_confirm', ['number' => $done->number]) }}" :aria-label="__('revenues.pos.void')" data-test="pos-cancel-{{ $done->id }}" />
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="fin-empty">{{ __('revenues.pos.no_sales') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-ui.panel>
            @endif
        @else
            {{-- An order: actions, its lines, its details, and the totals bar. --}}
            <div class="grid flex-1 gap-4 lg:grid-cols-12" data-test="pos-order">
                <nav class="flex flex-row gap-3 overflow-x-auto lg:col-span-2 lg:flex-col" aria-label="{{ __('revenues.pos.actions') }}">
                    @if ($canSell)
                        <x-revenues.pos-action primary icon="plus-circle" :label="__('revenues.pos.add_items')" wire:click="startAdding" data-test="pos-add-items" />
                    @endif
                    <flux:dropdown position="bottom" align="start" class="w-full">
                        <x-revenues.pos-action icon="truck" :label="__('revenues.pos.service').': '.__('revenues.pos.services.'.($details['service'] ?? 'hall'))" data-test="pos-service" />
                        <flux:menu>
                            @foreach (\App\Models\RevenueTicket::SERVICES as $kind)
                                <flux:menu.item wire:click="setService('{{ $kind }}')">{{ __('revenues.pos.services.'.$kind) }}</flux:menu.item>
                            @endforeach
                        </flux:menu>
                    </flux:dropdown>
                    <x-revenues.pos-action icon="printer" :label="__('revenues.pos.print_preview')" wire:click="printOrder" data-test="pos-print" />
                    @if ($canSell)
                        <x-revenues.pos-action icon="banknotes" :label="__('revenues.pos.close_table')" wire:click="startPaying" :disabled="$orderLines->isEmpty()" data-test="pos-close-table" />
                        <x-revenues.pos-action icon="trash" :label="__('revenues.pos.cancel_order')" wire:click="cancelOrder({{ $order->id }})" wire:confirm="{{ __('revenues.pos.void_confirm', ['number' => $order->number]) }}" data-test="pos-cancel-order" />
                    @endif
                    <x-revenues.pos-action icon="arrow-uturn-left" :label="__('revenues.pos.back')" wire:click="backToTables" data-test="pos-back" />
                </nav>

                <section class="flex min-w-0 flex-col overflow-hidden lg:col-span-7 {{ $card }}" aria-label="{{ __('revenues.pos.lines') }}">
                    <div class="fin-table-wrap flex-1">
                        <table class="fin-table" data-test="pos-lines">
                            <thead><tr><th>#</th><th>{{ __('revenues.pos.item_count', ['count' => $orderLines->count()]) }}</th><th class="fin-num">{{ __('revenues.quantity') }}</th><th class="fin-num">{{ __('revenues.unit_price') }}</th><th class="fin-num">{{ __('revenues.pos.line_total') }}</th><th>{{ __('revenues.pos.note') }}</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($orderLines as $line)
                                    <tr wire:key="line-{{ $line->id }}-{{ $line->quantity }}">
                                        <td class="tabular-nums">{{ $loop->iteration }}</td>
                                        <td class="font-medium">{{ $line->description }}</td>
                                        <td class="fin-num">
                                            <div class="inline-flex items-center gap-1">
                                                @if ($canSell)
                                                    <x-ui.button size="xs" tone="ghost" icon="minus" wire:click="lineStep({{ $line->id }}, -1)" :aria-label="__('revenues.pos.less')" />
                                                @endif
                                                <input type="text" inputmode="decimal" dir="ltr" value="{{ rtrim(rtrim((string) $line->quantity, '0'), '.') }}" wire:change="lineQuantity({{ $line->id }}, $event.target.value)" @disabled(! $canSell) class="{{ $cell }} w-16! text-center" aria-label="{{ __('revenues.quantity') }} {{ $line->description }}" data-test="pos-line-quantity-{{ $line->id }}">
                                                @if ($canSell)
                                                    <x-ui.button size="xs" tone="ghost" icon="plus" wire:click="lineStep({{ $line->id }}, 1)" :aria-label="__('revenues.pos.more')" />
                                                @endif
                                            </div>
                                        </td>
                                        <td class="fin-num"><x-money :amount="$line->unit_price" /></td>
                                        <td class="fin-num"><x-money :amount="$line->amount" /></td>
                                        <td><input type="text" value="{{ $line->note }}" wire:change="lineNote({{ $line->id }}, $event.target.value)" @disabled(! $canSell) class="{{ $cell }} min-w-28" aria-label="{{ __('revenues.pos.note') }} {{ $line->description }}"></td>
                                        <td>
                                            @if ($canSell)
                                                <x-ui.button size="xs" tone="ghost" icon="trash" wire:click="lineRemove({{ $line->id }})" :aria-label="__('revenues.remove_line')" data-test="pos-line-remove-{{ $line->id }}" />
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="fin-empty">{{ __('revenues.pos.order_empty') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <aside class="flex flex-col gap-3 p-4 lg:col-span-3 {{ $card }}" aria-label="{{ __('revenues.pos.details') }}" data-test="pos-details">
                    <dl class="grid grid-cols-2 gap-2 text-sm">
                        <dt class="text-ink-muted">{{ __('revenues.pos.invoice') }}</dt><dd class="text-end font-semibold tabular-nums">{{ $order->number }}</dd>
                        <dt class="text-ink-muted">{{ __('revenues.day') }}</dt><dd class="text-end tabular-nums" dir="ltr">{{ $shift->day->format('Y-m-d') }}</dd>
                    </dl>
                    <x-ui.form-input wire:model.blur="details.table" dir="ltr" :label="__('revenues.pos.table')" data-test="pos-detail-table" />
                    <x-ui.form-input wire:model.blur="details.customer" :label="__('revenues.pos.customer')" data-test="pos-detail-customer" />
                    <x-ui.form-input wire:model.blur="details.persons" inputmode="numeric" dir="ltr" :label="__('revenues.pos.persons')" />
                    <x-ui.form-select wire:model.live="details.payment_method" :label="__('revenues.pos.payment')" data-test="pos-detail-payment">
                        @foreach (\App\Models\RevenueTicket::PAYMENTS as $method)
                            <option value="{{ $method }}">{{ __('revenues.pos.payments.'.$method) }}</option>
                        @endforeach
                    </x-ui.form-select>
                    <x-ui.form-input wire:model.blur="details.waiter" :label="__('revenues.pos.waiter')" />
                    <x-ui.form-input wire:model.blur="details.note" :label="__('revenues.pos.note')" />
                </aside>
            </div>

            {{-- The totals bar. --}}
            <div class="grid gap-3 p-3 sm:grid-cols-2 lg:grid-cols-4 {{ $card }}" data-test="pos-totals">
                <div class="flex flex-col justify-end gap-1">
                    <span class="text-sm text-ink-muted">{{ __('revenues.pos.gross') }}</span>
                    <x-money :amount="$order->gross" :currency="$shift->currency" class="rounded-md border border-secondary-soft px-3 py-2 text-lg dark:border-line" />
                </div>
                <x-ui.form-input wire:model.blur="details.discount_percent" inputmode="decimal" dir="ltr" :label="__('revenues.pos.discount_percent')" suffix="%" data-test="pos-discount-percent" />
                <x-ui.form-input wire:model.blur="details.discount" inputmode="decimal" dir="ltr" :label="__('revenues.pos.discount')" data-test="pos-discount" />
                <div class="flex items-center justify-center rounded-xl bg-status-good-soft px-4 py-3" data-test="pos-net">
                    <x-money :amount="$order->net" :currency="$shift->currency" class="text-3xl font-bold text-status-good!" />
                </div>
            </div>
        @endif
    </main>

    {{-- The status bar: the shift, the screen, the day and the time. --}}
    <footer class="flex flex-wrap items-center justify-between gap-3 px-4 py-2 text-sm {{ $card }}">
        <span>{{ __('revenues.pos.shift_of', ['name' => $cashier]) }}@if ($shift) · {{ __('revenues.pos.shift_total') }}: <x-money :amount="$shift->total" :currency="$shift->currency" />@endif</span>
        <span class="text-ink-muted">{{ __('revenues.pos.title') }} · {{ __('common.app_name') }}</span>
        <span class="flex gap-2 tabular-nums" dir="ltr" x-data="{ now: new Date() }" x-init="setInterval(() => now = new Date(), 1000)">
            <span class="rounded border border-secondary-soft px-2 dark:border-line">{{ now()->format('Y-m-d') }}</span>
            <span class="rounded border border-secondary-soft px-2 dark:border-line" x-text="now.toLocaleTimeString('en-GB')"></span>
        </span>
    </footer>

    {{-- The add-items window. --}}
    @if ($adding && $order !== null)
        <div class="fixed inset-0 z-50 bg-black/50 p-2 sm:p-6" role="dialog" aria-modal="true" aria-label="{{ __('revenues.pos.add_items') }}" wire:keydown.escape.window="closeAdding" data-test="pos-adding">
            <div class="flex h-full flex-col gap-4 overflow-hidden p-4 {{ $card }}">
                <div class="flex flex-wrap items-end gap-3">
                    <span class="inline-flex items-center gap-2 rounded-lg border-2 border-status-good px-4 py-2 font-bold">
                        <flux:icon.book-open variant="outline" class="size-5" /> {{ __('revenues.pos.menu') }}
                    </span>
                    <x-ui.form-input wire:model="quick" wire:keydown.enter="quickAdd" dir="ltr" :label="__('revenues.pos.quick')" :placeholder="__('revenues.pos.quick_hint')" class="w-64" autofocus data-test="pos-quick" />
                    <x-ui.form-input wire:model.live.debounce.250ms="find" type="search" :label="__('revenues.pos.find')" class="w-56" data-test="pos-find" />
                    <x-ui.button tone="ghost" icon="x-mark" class="ms-auto" wire:click="closeAdding" data-test="pos-adding-cancel">{{ __('revenues.cancel') }}</x-ui.button>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6" role="tablist" data-test="pos-categories">
                    <x-ui.button :tone="$category === '' ? 'primary' : 'secondary'" class="justify-center" wire:click="$set('category', '')" role="tab" :aria-selected="$category === '' ? 'true' : 'false'">{{ __('revenues.pos.all') }}</x-ui.button>
                    @foreach ($categories as $name)
                        <x-ui.button :tone="$category === $name ? 'primary' : 'secondary'" class="justify-center" wire:click="$set('category', {{ \Illuminate\Support\Js::from($name) }})" role="tab" :aria-selected="$category === $name ? 'true' : 'false'" wire:key="category-{{ $loop->index }}">{{ $name }}</x-ui.button>
                    @endforeach
                </div>

                <div class="grid min-h-0 flex-1 gap-4 lg:grid-cols-3">
                    <div class="grid content-start gap-3 overflow-y-auto rounded-xl border border-secondary-soft p-3 sm:grid-cols-2 lg:col-span-2 xl:grid-cols-4 dark:border-line" data-test="pos-tiles">
                        @forelse ($tiles as $tile)
                            <x-revenues.pos-tile
                                wire:click="tapItem({{ $tile->id }})"
                                wire:key="tile-{{ $tile->id }}"
                                :name="$tile->name"
                                :price="$tile->unit_price"
                                :currency="$tile->currency"
                                :tone="$tileTones[$tile->category ?: __('revenues.kinds.'.$tile->kind)] ?? 1"
                                :count="$pending[$tile->id] ?? null"
                                data-test="pos-tile-{{ $tile->id }}"
                            />
                        @empty
                            <div class="col-span-full"><x-ui.empty-state icon="tag" :heading="__('revenues.pos.no_items')" :body="__('revenues.pos.no_items_hint')" /></div>
                        @endforelse
                    </div>

                    <div class="flex min-h-0 flex-col gap-3 rounded-xl border border-secondary-soft p-3 dark:border-line" data-test="pos-pending-list">
                        <div class="fin-table-wrap min-h-0 flex-1 overflow-y-auto">
                            <table class="fin-table">
                                <thead><tr><th>#</th><th>{{ __('revenues.item_name') }}</th><th class="fin-num">{{ __('revenues.quantity') }}</th><th class="fin-num">{{ __('revenues.unit_price') }}</th><th></th></tr></thead>
                                <tbody>
                                    @forelse ($pendingRows as $row)
                                        <tr wire:key="pending-{{ $row['id'] }}">
                                            <td class="tabular-nums">{{ $loop->iteration }}</td>
                                            <td>{{ $row['name'] }}</td>
                                            <td class="fin-num"><input type="text" inputmode="decimal" dir="ltr" wire:model.live.debounce.400ms="pending.{{ $row['id'] }}" class="{{ $cell }} w-16! text-center" aria-label="{{ __('revenues.quantity') }} {{ $row['name'] }}" data-test="pos-pending-{{ $row['id'] }}"></td>
                                            <td class="fin-num"><x-money :amount="$row['price']" /></td>
                                            <td><x-ui.button size="xs" tone="ghost" icon="x-mark" wire:click="dropPending({{ $row['id'] }})" :aria-label="__('revenues.remove_line')" /></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="fin-empty">{{ __('revenues.pos.basket_empty') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="flex gap-2">
                            <x-ui.button icon="trash" wire:click="clearPending" :aria-label="__('revenues.pos.clear')" />
                            <x-ui.button tone="primary" icon="check" class="flex-1 justify-center" wire:click="savePending" :disabled="$pendingRows === []" data-test="pos-save-items">{{ __('revenues.save') }}</x-ui.button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Closing the table: what is due, what was paid, the change. --}}
    @if ($paying && $order !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-label="{{ __('revenues.pos.close_table') }}" wire:keydown.escape.window="$set('paying', false)" data-test="pos-paying">
            <div class="flex w-full max-w-md flex-col gap-4 p-6 {{ $card }}">
                <h2 class="text-lg font-bold">{{ __('revenues.pos.close_table') }} · {{ $order->table_label ?? __('revenues.pos.services.'.$order->service) }}</h2>
                <div class="flex items-center justify-between rounded-xl bg-status-good-soft px-4 py-3 text-status-good">
                    <span class="font-semibold">{{ __('revenues.pos.net') }}</span>
                    <x-money :amount="$order->net" :currency="$shift->currency" class="text-3xl font-bold text-status-good!" />
                </div>
                <x-ui.form-input wire:model.live.debounce.300ms="paid" inputmode="decimal" dir="ltr" :label="__('revenues.pos.paid')" :help="__('revenues.pos.paid_help')" autofocus wire:keydown.enter="pay" data-test="pos-paid" />
                <div class="flex items-center justify-between text-sm">
                    <span class="text-ink-muted">{{ __('revenues.pos.change') }}</span>
                    @if ($change !== null)
                        <x-money :amount="$change" :currency="$shift->currency" class="text-xl font-bold" data-test="pos-change" />
                    @else
                        <span>—</span>
                    @endif
                </div>
                <div class="flex gap-2">
                    <x-ui.button tone="primary" icon="check" class="flex-1 justify-center" wire:click="pay" data-test="pos-pay">{{ __('revenues.pos.pay') }}</x-ui.button>
                    <x-ui.button tone="ghost" wire:click="$set('paying', false)">{{ __('revenues.cancel') }}</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
