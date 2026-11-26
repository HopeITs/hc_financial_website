@php
    $cell = 'w-full min-w-16 rounded-md border border-secondary-light bg-white px-2 py-1.5 text-sm text-secondary-dark dark:border-field-line dark:bg-surface-2 dark:text-ink';
@endphp

<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="revenues">
    <x-ui.page-header :title="__('navigation.screens.revenues')" :subtitle="__('revenues.intro')">
        @if ($posReady)
            <x-slot:actions>
                <x-ui.button tone="primary" icon="computer-desktop" :href="route('operations.revenues.pos')" wire:navigate data-test="open-pos">{{ __('revenues.pos.open_screen') }}</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <div class="flex gap-2" role="tablist">
        @foreach (['record', 'list', 'items'] as $name)
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

    @if ($tab === 'record')
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
                    @if ($posReady)
                        <x-ui.form-input wire:model="item.code" dir="ltr" :label="__('revenues.pos.code')" :help="__('revenues.pos.code_help')" data-test="i-code" />
                        <x-ui.form-input wire:model="item.category" :label="__('revenues.pos.category')" :help="__('revenues.pos.category_help')" list="revenue-categories" data-test="i-category" />
                        <datalist id="revenue-categories">
                            @foreach ($itemCategories as $name)
                                <option value="{{ $name }}"></option>
                            @endforeach
                        </datalist>
                    @endif
                    <x-ui.form-select wire:model="item.kind" :label="__('revenues.kind')" data-test="i-kind">
                        @foreach (\App\Models\RevenueItem::KINDS as $kind)
                            <option value="{{ $kind }}">{{ __('revenues.kinds.'.$kind) }}</option>
                        @endforeach
                    </x-ui.form-select>
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

        {{-- The price list beside its categories, as on the point of sale's items screen. --}}
        <div @class(['grid gap-6', 'lg:grid-cols-4' => $posReady])>
            @if ($posReady)
                <x-ui.panel :title="__('revenues.pos.categories')" data-test="item-categories">
                    <nav class="flex flex-col p-2" aria-label="{{ __('revenues.pos.categories') }}">
                        <x-ui.button tone="ghost" :class="'justify-start! '.($itemCategory === '' ? 'bg-secondary-soft! dark:bg-surface-3!' : '')" wire:click="$set('itemCategory', '')">{{ __('revenues.pos.all') }}</x-ui.button>
                        @foreach ($itemCategories as $name)
                            <x-ui.button tone="ghost" :class="'justify-start! '.($itemCategory === $name ? 'bg-secondary-soft! dark:bg-surface-3!' : '')" wire:click="$set('itemCategory', {{ \Illuminate\Support\Js::from($name) }})" wire:key="item-category-{{ $loop->index }}">{{ $name }}</x-ui.button>
                        @endforeach
                    </nav>
                </x-ui.panel>
            @endif

            <div @class(['lg:col-span-3' => $posReady])>
                <x-ui.panel :title="$itemCategory !== '' ? $itemCategory : __('revenues.items_title')" :subtitle="__('revenues.items_hint')" :count="$items->count()">
                    <x-slot:toolbar>
                        @if ($canEdit)
                            <x-ui.button size="sm" icon="plus" wire:click="newItem" data-test="new-item">{{ __('revenues.item_new') }}</x-ui.button>
                        @endif
                    </x-slot:toolbar>
                    <x-ui.scroll-table :paginate="false">
                        <table class="fin-table" data-test="items">
                            <thead><tr><th>{{ __('revenues.project') }}</th>@if ($posReady)<th>{{ __('revenues.pos.code') }}</th>@endif<th>{{ __('revenues.item_name') }}</th><th>{{ __('revenues.kind') }}</th>@if ($posReady)<th>{{ __('revenues.pos.category') }}</th>@endif<th class="fin-num">{{ __('revenues.unit_price') }}</th><th>{{ __('revenues.active') }}</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($items as $entry)
                                    <tr wire:key="item-{{ $entry->id }}" @class(['opacity-60' => ! $entry->active])>
                                        <td>{{ $entry->project_code }}</td>
                                        @if ($posReady)
                                            <td dir="ltr" class="text-start tabular-nums">{{ $entry->code ?? $entry->id }}</td>
                                        @endif
                                        <td class="font-medium">{{ $entry->name }}</td>
                                        <td>{{ __('revenues.kinds.'.$entry->kind) }}</td>
                                        @if ($posReady)
                                            <td>{{ $entry->category }}</td>
                                        @endif
                                        <td class="fin-num"><x-money :amount="$entry->unit_price" :currency="$entry->currency" /></td>
                                        <td><flux:badge size="sm" :color="$entry->active ? 'green' : 'zinc'">{{ $entry->active ? __('revenues.yes') : __('revenues.no') }}</flux:badge></td>
                                        <td>
                                            @if ($canEdit)
                                                <x-ui.button size="sm" tone="ghost" icon="pencil-square" wire:click="editItem({{ $entry->id }})" :aria-label="__('revenues.item_edit')" />
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8"><x-ui.empty-state icon="tag" :heading="__('revenues.items_none')" /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </x-ui.scroll-table>
                </x-ui.panel>
            </div>
        </div>
    @endif
</div>
