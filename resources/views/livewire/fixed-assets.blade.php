@php
    // The warehouses and fixed assets (finance request of 2026-11-28).
    $badge = fn (string $status): string => match ($status) {
        'in_use' => 'green',
        'in_store' => 'blue',
        'repair' => 'amber',
        default => 'zinc',
    };
@endphp

<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="assets">
    <x-ui.page-header :title="__('navigation.screens.assets')" :subtitle="__('assets.intro')" />

    <div class="flex flex-wrap gap-2" role="tablist">
        @foreach (\App\Livewire\FixedAssetsScreen::TABS as $name)
            <x-ui.button size="sm" :tone="$tab === $name ? 'primary' : 'secondary'" wire:click="$set('tab', '{{ $name }}')" role="tab" :aria-selected="$tab === $name ? 'true' : 'false'" data-test="tab-{{ $name }}">{{ __('assets.tabs.'.$name) }}</x-ui.button>
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

    @if (! $ready)
        <x-ui.empty-state icon="cube" :heading="__('assets.pending')" data-test="assets-pending" />
    @elseif ($tab === 'register' && $asset !== null)
        {{-- An asset's card: its details, what can be done with it, and its history. --}}
        <x-ui.panel :title="$asset->tag.' · '.$asset->name" data-test="asset-card">
            <x-slot:toolbar>
                <flux:badge size="sm" :color="$badge($asset->status)">{{ __('assets.statuses.'.$asset->status) }}</flux:badge>
                <x-ui.button size="sm" tone="ghost" icon="arrow-uturn-left" wire:click="closeAsset" data-test="asset-back">{{ __('assets.back') }}</x-ui.button>
            </x-slot:toolbar>
            <dl class="grid gap-x-6 gap-y-3 p-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    'category' => $asset->category?->name().' ('.$asset->account_code.')',
                    'project' => $projects[$asset->project_code] ?? $asset->project_code,
                    'center' => $asset->center,
                    'warehouse' => $asset->warehouse?->name,
                    'custodian' => $asset->custodian,
                    'serial' => $asset->serial,
                    'acquired_on' => $asset->acquired_on->format('d/m/Y'),
                    'source' => __('assets.sources.'.$asset->source),
                    'condition' => __('assets.conditions.'.$asset->condition),
                    'life' => __('assets.months', ['count' => $asset->life_months]).' · '.__('assets.from_month', ['month' => $asset->depreciation_from]),
                    'description' => $asset->description,
                    'voucher' => $asset->voucher_id,
                ] as $key => $value)
                    <div><dt class="text-ink-muted">{{ __('assets.fields.'.$key) }}</dt><dd class="font-medium">{{ $value ?: '—' }}</dd></div>
                @endforeach
            </dl>
            <div class="grid gap-3 border-t border-secondary-soft p-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-line">
                <x-ui.stat-tile :label="__('assets.fields.cost')" :value="$figure($asset->cost, 2).' '.$asset->currency" icon="banknotes" />
                <x-ui.stat-tile :label="__('assets.fields.cost_usd')" :value="$figure($asset->cost_usd, 2)" unit="USD" icon="currency-dollar" />
                <x-ui.stat-tile :label="__('assets.fields.accumulated')" :value="$figure($assetBook['accumulated'], 2)" unit="USD" icon="arrow-trending-down" />
                <x-ui.stat-tile :label="__('assets.fields.book')" :value="$figure($assetBook['book'], 2)" unit="USD" tone="good" icon="scale" />
            </div>

            @if ($asset->isOwned() && ($canCreate || $canEdit))
                <x-ui.action-bar>
                    @if ($canCreate)
                        <x-ui.button size="sm" icon="user-plus" wire:click="startAction('handover')" data-test="action-handover">{{ __('assets.actions.handover') }}</x-ui.button>
                        <x-ui.button size="sm" icon="arrow-down-tray" wire:click="startAction('return')" data-test="action-return">{{ __('assets.actions.return') }}</x-ui.button>
                        <x-ui.button size="sm" icon="arrows-right-left" wire:click="startAction('transfer')" data-test="action-transfer">{{ __('assets.actions.transfer') }}</x-ui.button>
                        <x-ui.button size="sm" icon="wrench-screwdriver" wire:click="startAction('repair')" data-test="action-repair">{{ __('assets.actions.repair') }}</x-ui.button>
                    @endif
                    @if ($canEdit)
                        <x-ui.button size="sm" icon="pencil-square" wire:click="startAction('edit')" data-test="action-edit">{{ __('assets.actions.edit') }}</x-ui.button>
                        <x-ui.button size="sm" icon="fire" wire:click="startAction('write_off')" data-test="action-write_off">{{ __('assets.actions.write_off') }}</x-ui.button>
                        <x-ui.button size="sm" icon="banknotes" wire:click="startAction('sell')" data-test="action-sell">{{ __('assets.actions.sell') }}</x-ui.button>
                    @endif
                </x-ui.action-bar>
            @endif
        </x-ui.panel>

        @if ($action !== '')
            <x-ui.panel :title="__('assets.actions.'.$action)" :subtitle="__('assets.action_hints.'.$action)" data-test="action-form">
                <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
                    @if ($action === 'edit')
                        <x-ui.form-input wire:model="actionForm.name" :label="__('assets.fields.name')" required />
                        <x-ui.form-input wire:model="actionForm.serial" :label="__('assets.fields.serial')" dir="ltr" />
                        <x-ui.form-input wire:model="actionForm.center" :label="__('assets.fields.center')" />
                        <x-ui.form-input wire:model="actionForm.salvage" inputmode="decimal" dir="ltr" :label="__('assets.fields.salvage')" :help="__('assets.salvage_help')" />
                        <x-ui.form-input wire:model="actionForm.life_months" inputmode="numeric" dir="ltr" :label="__('assets.fields.life_months')" />
                        <x-ui.form-select wire:model="actionForm.condition" :label="__('assets.fields.condition')">
                            @foreach (\App\Models\FixedAsset::CONDITIONS as $condition)
                                <option value="{{ $condition }}">{{ __('assets.conditions.'.$condition) }}</option>
                            @endforeach
                        </x-ui.form-select>
                        <div class="sm:col-span-2 lg:col-span-3"><x-ui.form-input wire:model="actionForm.description" :label="__('assets.fields.description')" /></div>
                    @else
                        <x-ui.form-input type="date" wire:model="actionForm.date" :label="__('assets.fields.date')" required />
                        @if (in_array($action, ['handover', 'return', 'transfer'], true))
                            <x-ui.form-select wire:model="actionForm.warehouse" :label="__('assets.fields.warehouse')" :required="$action !== 'handover'" data-test="action-warehouse">
                                <option value="">{{ __('assets.none') }}</option>
                                @foreach ($warehouses->where('active', true) as $place)
                                    <option value="{{ $place->id }}">{{ $place->name }}</option>
                                @endforeach
                            </x-ui.form-select>
                        @endif
                        @if ($action === 'handover')
                            <x-ui.form-input wire:model="actionForm.custodian" :label="__('assets.fields.custodian')" :help="__('assets.custodian_help')" required data-test="action-custodian" />
                        @endif
                        @if (in_array($action, ['handover', 'return', 'transfer', 'repair'], true))
                            <x-ui.form-select wire:model="actionForm.condition" :label="__('assets.fields.condition')">
                                @foreach (\App\Models\FixedAsset::CONDITIONS as $condition)
                                    <option value="{{ $condition }}">{{ __('assets.conditions.'.$condition) }}</option>
                                @endforeach
                            </x-ui.form-select>
                        @endif
                        @if ($action === 'write_off')
                            <x-ui.form-input wire:model="actionForm.reason" :label="__('assets.fields.reason')" :help="__('assets.reason_help')" required data-test="action-reason" />
                        @endif
                        @if ($action === 'sell')
                            <x-ui.form-select wire:model="actionForm.cash_account" :label="__('assets.fields.cash_account')" required data-test="action-cash">
                                <option value="">{{ __('assets.choose') }}</option>
                                @foreach ($cashAccounts as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </x-ui.form-select>
                            <x-ui.form-input wire:model="actionForm.price" inputmode="decimal" dir="ltr" :label="__('assets.fields.price')" required data-test="action-price" />
                            <x-ui.form-input wire:model="actionForm.rate" inputmode="decimal" dir="ltr" :label="__('assets.fields.rate')" :help="__('assets.rate_help')" />
                            <x-ui.form-input wire:model="actionForm.buyer" :label="__('assets.fields.buyer')" />
                        @endif
                        <x-ui.form-input wire:model="actionForm.reference" :label="__('assets.fields.reference')" />
                        @if ($action !== 'write_off')
                            <x-ui.form-input wire:model="actionForm.notes" :label="__('assets.fields.notes')" />
                        @endif
                    @endif
                    <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-3">
                        @if (in_array($action, ['write_off', 'sell'], true))
                            <x-ui.button tone="primary" icon="check" wire:click="runAction" wire:confirm="{{ __('assets.confirm_voucher') }}" data-test="action-save">{{ __('assets.save') }}</x-ui.button>
                        @else
                            <x-ui.button tone="primary" icon="check" wire:click="runAction" data-test="action-save">{{ __('assets.save') }}</x-ui.button>
                        @endif
                        <x-ui.button tone="ghost" wire:click="$set('action', '')">{{ __('assets.cancel') }}</x-ui.button>
                    </div>
                </div>
            </x-ui.panel>
        @endif

        <x-ui.panel :title="__('assets.history')" :count="$asset->movements->count()">
            <div class="fin-table-wrap">
                <table class="fin-table" data-test="asset-history">
                    <thead><tr><th>{{ __('assets.fields.date') }}</th><th>{{ __('assets.fields.kind') }}</th><th>{{ __('assets.fields.where') }}</th><th>{{ __('assets.fields.custodian') }}</th><th>{{ __('assets.fields.condition') }}</th><th class="fin-num">{{ __('assets.paper.amount') }}</th><th>{{ __('assets.fields.voucher') }}</th><th>{{ __('assets.fields.notes') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($asset->movements as $move)
                            <tr wire:key="move-{{ $move->id }}">
                                <td dir="ltr" class="text-start">{{ $move->happened_on->format('d/m/Y') }}</td>
                                <td>{{ __('assets.kinds.'.$move->kind) }}@if ($move->month) · <span dir="ltr">{{ $move->month }}</span>@endif</td>
                                <td>{{ $warehouses->firstWhere('id', $move->to_warehouse_id)?->name ?? $warehouses->firstWhere('id', $move->from_warehouse_id)?->name }}</td>
                                <td>{{ $move->to_custodian ?? $move->from_custodian }}</td>
                                <td>{{ $move->condition ? __('assets.conditions.'.$move->condition) : '' }}</td>
                                <td class="fin-num">@if ($move->amount_usd !== null)<x-money :amount="$move->amount_usd" />@endif</td>
                                <td dir="ltr" class="text-start">
                                    @if ($move->voucher_id)
                                        <a href="{{ route(\App\Support\Navigation::routeName('vouchers'), ['voucher' => $move->voucher_id]) }}" wire:navigate class="underline">{{ $move->voucher_id }}</a>
                                    @endif
                                </td>
                                <td>{{ collect([$move->reference, $move->notes])->filter()->implode(' · ') }}</td>
                                <td>
                                    @if ($move->kind !== 'depreciate')
                                        <x-ui.button size="xs" tone="ghost" icon="printer" :href="route('operations.assets.movement', ['movement' => $move->id])" target="_blank" :aria-label="__('assets.print')" data-test="print-move-{{ $move->id }}" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.panel>
    @elseif ($tab === 'register')
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.stat-tile :label="__('assets.totals.count')" :value="$totals['count']" icon="cube" />
            <x-ui.stat-tile :label="__('assets.totals.cost')" :value="$figure($totals['cost'], 2)" unit="USD" icon="currency-dollar" />
            <x-ui.stat-tile :label="__('assets.totals.in_use')" :value="$totals['in_use']" icon="user" />
            <x-ui.stat-tile :label="__('assets.totals.in_store')" :value="$totals['in_store']" icon="building-storefront" />
        </div>

        <x-ui.panel :title="__('assets.register_title')" :count="$list->count()">
            <x-slot:toolbar>
                @if ($canCreate)
                    <x-ui.button size="sm" icon="plus" wire:click="$set('tab', 'receive')" data-test="new-asset">{{ __('assets.new') }}</x-ui.button>
                @endif
            </x-slot:toolbar>
            <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.form-input wire:model.live.debounce.300ms="search" type="search" :label="__('assets.search')" :placeholder="__('assets.search_hint')" data-test="asset-search" />
                <x-ui.form-select wire:model.live="status" :label="__('assets.fields.status')" data-test="asset-status">
                    <option value="owned">{{ __('assets.owned') }}</option>
                    <option value="">{{ __('assets.all') }}</option>
                    @foreach (\App\Models\FixedAsset::STATUSES as $state)
                        <option value="{{ $state }}">{{ __('assets.statuses.'.$state) }}</option>
                    @endforeach
                </x-ui.form-select>
                <x-ui.form-select wire:model.live="warehouse" :label="__('assets.fields.warehouse')">
                    <option value="">{{ __('assets.all') }}</option>
                    @foreach ($warehouses as $place)
                        <option value="{{ $place->id }}">{{ $place->name }}</option>
                    @endforeach
                </x-ui.form-select>
                <x-ui.form-select wire:model.live="category" :label="__('assets.fields.category')">
                    <option value="">{{ __('assets.all') }}</option>
                    @foreach ($categories as $kind)
                        <option value="{{ $kind->id }}">{{ $kind->name() }}</option>
                    @endforeach
                </x-ui.form-select>
            </div>
            <x-ui.scroll-table>
                <table class="fin-table" data-test="asset-list">
                    <thead><tr><th>{{ __('assets.fields.tag') }}</th><th>{{ __('assets.fields.name') }}</th><th>{{ __('assets.fields.category') }}</th><th>{{ __('assets.fields.project') }}</th><th>{{ __('assets.fields.warehouse') }}</th><th>{{ __('assets.fields.custodian') }}</th><th>{{ __('assets.fields.status') }}</th><th class="fin-num">{{ __('assets.fields.book') }}</th></tr></thead>
                    <tbody>
                        @forelse ($list as $row)
                            <tr wire:key="asset-{{ $row->id }}" wire:click="openAsset({{ $row->id }})" class="cursor-pointer" data-test="asset-row-{{ $row->id }}">
                                <td dir="ltr" class="text-start font-medium tabular-nums">{{ $row->tag }}</td>
                                <td>{{ $row->name }}@if ($row->serial)<div class="text-xs text-ink-muted" dir="ltr">{{ $row->serial }}</div>@endif</td>
                                <td>{{ $row->category?->name() }}</td>
                                <td>{{ $row->project_code }}</td>
                                <td>{{ $row->warehouse?->name }}</td>
                                <td>{{ $row->custodian }}</td>
                                <td><flux:badge size="sm" :color="$badge($row->status)">{{ __('assets.statuses.'.$row->status) }}</flux:badge></td>
                                <td class="fin-num"><x-money :amount="$books[$row->id] ?? 0" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><x-ui.empty-state icon="cube" :heading="__('assets.none_found')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.scroll-table>
        </x-ui.panel>
    @elseif ($tab === 'receive')
        <x-ui.panel :title="__('assets.receive_title')" :subtitle="__('assets.receive_hints.'.$source)" data-test="receive-form">
            <div class="flex flex-col gap-4 p-4">
                <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="{{ __('assets.fields.source') }}">
                    @foreach (\App\Models\FixedAsset::SOURCES as $kind)
                        <x-ui.button size="sm" :tone="$source === $kind ? 'primary' : 'secondary'" wire:click="$set('source', '{{ $kind }}')" role="radio" :aria-checked="$source === $kind ? 'true' : 'false'" data-test="source-{{ $kind }}">{{ __('assets.sources.'.$kind) }}</x-ui.button>
                    @endforeach
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @if ($source === 'purchase')
                        <div class="sm:col-span-2 lg:col-span-3">
                            <x-ui.form-select wire:model.live="purchaseItem" :label="__('assets.fields.purchase_item')" required data-test="purchase-item">
                                <option value="">{{ __('assets.choose') }}</option>
                                @foreach ($toRegister as $row)
                                    <option value="{{ $row['item']->id }}">{{ $row['purchase']->number('PO') }} · {{ $row['item']->description }} · {{ __('assets.left', ['count' => $row['left']]) }} · {{ $row['unit_price'] }} {{ $row['purchase']->currency }}</option>
                                @endforeach
                            </x-ui.form-select>
                            @if ($toRegister === [])
                                <p class="mt-1 text-sm text-ink-muted">{{ __('assets.no_purchases') }}</p>
                            @endif
                        </div>
                        <x-ui.form-input wire:model="form.count" inputmode="numeric" dir="ltr" :label="__('assets.fields.count')" required data-test="r-count" />
                    @endif
                    <x-ui.form-input wire:model="form.name" :label="__('assets.fields.name')" required data-test="r-name" />
                    <x-ui.form-select wire:model="form.category" :label="__('assets.fields.category')" required data-test="r-category">
                        <option value="">{{ __('assets.choose') }}</option>
                        @foreach ($categories->where('active', true) as $kind)
                            <option value="{{ $kind->id }}">{{ $kind->account_code }} · {{ $kind->name() }} ({{ __('assets.months', ['count' => $kind->life_months]) }})</option>
                        @endforeach
                    </x-ui.form-select>
                    @if ($source !== 'purchase')
                        <x-ui.form-select wire:model="form.project" :label="__('assets.fields.project')" required data-test="r-project">
                            <option value="">{{ __('assets.choose') }}</option>
                            @foreach ($projects as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.form-select>
                    @endif
                    <x-ui.form-select wire:model="form.warehouse" :label="__('assets.fields.warehouse')" data-test="r-warehouse">
                        <option value="">{{ __('assets.none') }}</option>
                        @foreach ($warehouses->where('active', true) as $place)
                            <option value="{{ $place->id }}">{{ $place->name }}</option>
                        @endforeach
                    </x-ui.form-select>
                    <x-ui.form-input wire:model="form.center" :label="__('assets.fields.center')" :help="__('assets.center_help')" data-test="r-center" />
                    <x-ui.form-input wire:model="form.custodian" :label="__('assets.fields.custodian')" :help="__('assets.custodian_help')" />
                    @if ($source !== 'purchase')
                        <x-ui.form-input type="date" wire:model="form.acquired_on" :label="__('assets.fields.acquired_on')" required data-test="r-date" />
                        <x-ui.form-input wire:model="form.cost" inputmode="decimal" dir="ltr" :label="__('assets.fields.cost')" required data-test="r-cost" />
                        <x-ui.form-select wire:model.live="form.currency" :label="__('assets.fields.currency')">
                            <option value="USD">USD</option>
                            <option value="SYP">SYP</option>
                            <option value="EUR">EUR</option>
                        </x-ui.form-select>
                        @if (($form['currency'] ?? 'USD') !== 'USD')
                            <x-ui.form-input wire:model="form.rate" inputmode="decimal" dir="ltr" :label="__('assets.fields.rate')" :help="__('assets.rate_help')" />
                        @endif
                        <x-ui.form-input wire:model="form.serial" dir="ltr" :label="__('assets.fields.serial')" />
                    @endif
                    <x-ui.form-input wire:model="form.salvage" inputmode="decimal" dir="ltr" :label="__('assets.fields.salvage')" :help="__('assets.salvage_help')" />
                    <x-ui.form-input wire:model="form.life_months" inputmode="numeric" dir="ltr" :label="__('assets.fields.life_months')" :help="__('assets.life_help')" />
                    <x-ui.form-select wire:model="form.condition" :label="__('assets.fields.condition')">
                        @foreach (\App\Models\FixedAsset::CONDITIONS as $condition)
                            <option value="{{ $condition }}">{{ __('assets.conditions.'.$condition) }}</option>
                        @endforeach
                    </x-ui.form-select>
                    @if ($source === 'purchase')
                        <div class="sm:col-span-2 lg:col-span-3"><x-ui.form-textarea wire:model="form.serials" :label="__('assets.fields.serials')" :help="__('assets.serials_help')" rows="3" /></div>
                    @else
                        <div class="sm:col-span-2 lg:col-span-3"><x-ui.form-input wire:model="form.description" :label="__('assets.fields.description')" /></div>
                    @endif
                    <div class="sm:col-span-2 lg:col-span-3"><x-ui.form-input wire:model="form.notes" :label="__('assets.fields.notes')" /></div>
                </div>

                @if ($canCreate)
                    <x-ui.action-bar>
                        <x-ui.button tone="primary" icon="check" wire:click="register" data-test="register">{{ __('assets.register') }}</x-ui.button>
                    </x-ui.action-bar>
                @endif
            </div>
        </x-ui.panel>
    @elseif ($tab === 'counts' && $count !== null)
        @php($checked = $countLines->whereNotNull('found')->count())
        <x-ui.panel :title="__('assets.count.title', ['number' => $count->id])" :subtitle="($count->warehouse?->name ?? __('assets.all_warehouses')).' · '.$count->counted_on->format('d/m/Y').' · '.__('assets.count.progress', ['checked' => $checked, 'all' => $countLines->count()])" data-test="count-sheet">
            <x-slot:toolbar>
                <x-ui.button size="sm" tone="ghost" icon="printer" :href="route('operations.assets.count', ['count' => $count->id])" target="_blank">{{ __('assets.print') }}</x-ui.button>
                <x-ui.button size="sm" tone="ghost" icon="arrow-uturn-left" wire:click="$set('countId', null)">{{ __('assets.back') }}</x-ui.button>
            </x-slot:toolbar>
            <div class="fin-table-wrap">
                <table class="fin-table" data-test="count-lines">
                    <thead><tr><th>{{ __('assets.fields.tag') }}</th><th>{{ __('assets.fields.name') }}</th><th>{{ __('assets.fields.custodian') }}</th><th>{{ __('assets.count.found_q') }}</th><th>{{ __('assets.fields.condition') }}</th><th>{{ __('assets.fields.notes') }}</th></tr></thead>
                    <tbody>
                        @forelse ($countLines as $line)
                            <tr wire:key="count-line-{{ $line->id }}-{{ var_export($line->found, true) }}">
                                <td dir="ltr" class="text-start tabular-nums">{{ $line->asset->tag }}</td>
                                <td>{{ $line->asset->name }}@if ($line->asset->serial)<div class="text-xs text-ink-muted" dir="ltr">{{ $line->asset->serial }}</div>@endif</td>
                                <td>{{ $line->asset->custodian }}</td>
                                <td class="whitespace-nowrap">
                                    @if ($count->status === 'open' && $canCreate)
                                        <x-ui.button size="xs" :tone="$line->found === true ? 'primary' : 'secondary'" icon="check" wire:click="markLine({{ $line->id }}, '1')" data-test="found-{{ $line->id }}">{{ __('assets.count.found') }}</x-ui.button>
                                        <x-ui.button size="xs" :tone="$line->found === false ? 'primary' : 'secondary'" icon="x-mark" wire:click="markLine({{ $line->id }}, '0')" data-test="missing-{{ $line->id }}">{{ __('assets.count.missing') }}</x-ui.button>
                                    @else
                                        <flux:badge size="sm" :color="$line->found ? 'green' : 'red'">{{ $line->found ? __('assets.count.found') : __('assets.count.missing') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="min-w-36">
                                    <x-ui.form-select bare :label="__('assets.fields.condition').' '.$line->asset->tag" wire:change="lineCondition({{ $line->id }}, $event.target.value)" :disabled="$count->status !== 'open' || ! $canCreate">
                                        @foreach (\App\Models\FixedAsset::CONDITIONS as $condition)
                                            <option value="{{ $condition }}" @selected($line->condition === $condition)>{{ __('assets.conditions.'.$condition) }}</option>
                                        @endforeach
                                    </x-ui.form-select>
                                </td>
                                <td class="min-w-40">
                                    @if ($count->status === 'open' && $canCreate)
                                        <x-ui.form-input :label="__('assets.fields.notes').' '.$line->asset->tag" value="{{ $line->note }}" wire:change="lineNote({{ $line->id }}, $event.target.value)" class="[&_label]:sr-only" />
                                    @else
                                        {{ $line->note }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="fin-empty">{{ __('assets.none_found') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($count->status === 'open' && $canCreate)
                <x-ui.action-bar>
                    <x-ui.button size="sm" icon="check-circle" wire:click="markAllFound" data-test="all-found">{{ __('assets.count.all_found') }}</x-ui.button>
                    <x-ui.button tone="primary" icon="lock-closed" wire:click="closeCount" wire:confirm="{{ __('assets.count.close_confirm') }}" data-test="close-count">{{ __('assets.count.close') }}</x-ui.button>
                </x-ui.action-bar>
            @endif
        </x-ui.panel>
    @elseif ($tab === 'counts')
        @if ($canCreate)
            <x-ui.panel :title="__('assets.count.new')" :subtitle="__('assets.count.new_hint')">
                <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4" data-test="count-form">
                    <x-ui.form-select wire:model="countForm.warehouse" :label="__('assets.fields.warehouse')">
                        <option value="">{{ __('assets.all_warehouses') }}</option>
                        @foreach ($warehouses->where('active', true) as $place)
                            <option value="{{ $place->id }}">{{ $place->name }}</option>
                        @endforeach
                    </x-ui.form-select>
                    <x-ui.form-input type="date" wire:model="countForm.date" :label="__('assets.fields.date')" required />
                    <x-ui.form-input wire:model="countForm.by" :label="__('assets.count.by')" />
                    <div class="flex items-end"><x-ui.button tone="primary" icon="clipboard-document-check" wire:click="openCount" data-test="open-count">{{ __('assets.count.start') }}</x-ui.button></div>
                </div>
            </x-ui.panel>
        @endif
        <x-ui.panel :title="__('assets.count.list')" :count="$counts->count()">
            <div class="fin-table-wrap">
                <table class="fin-table" data-test="counts">
                    <thead><tr><th>#</th><th>{{ __('assets.fields.date') }}</th><th>{{ __('assets.fields.warehouse') }}</th><th>{{ __('assets.count.by') }}</th><th>{{ __('assets.fields.status') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($counts as $row)
                            <tr wire:key="count-{{ $row->id }}">
                                <td class="tabular-nums">{{ $row->id }}</td>
                                <td dir="ltr" class="text-start">{{ $row->counted_on->format('d/m/Y') }}</td>
                                <td>{{ $row->warehouse?->name ?? __('assets.all_warehouses') }}</td>
                                <td>{{ $row->counted_by_name }}</td>
                                <td><flux:badge size="sm" :color="$row->status === 'open' ? 'amber' : 'zinc'">{{ __('assets.count.states.'.$row->status) }}</flux:badge></td>
                                <td><x-ui.button size="xs" icon="eye" wire:click="showCount({{ $row->id }})" data-test="show-count-{{ $row->id }}">{{ __('assets.open') }}</x-ui.button></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="fin-empty">{{ __('assets.count.none') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.panel>
    @elseif ($tab === 'depreciation')
        <x-ui.panel :title="__('assets.depreciation.title')" :subtitle="__('assets.depreciation.hint')" data-test="depreciation">
            <div class="flex flex-wrap items-end gap-4 p-4">
                <div class="w-48"><x-ui.form-input type="month" wire:model.live="month" :label="__('assets.depreciation.month')" data-test="dep-month" /></div>
                <x-ui.stat-tile :label="__('assets.depreciation.total')" :value="$figure($depreciationTotal, 2)" unit="USD" icon="arrow-trending-down" />
                @if ($canEdit && $depreciation !== [])
                    <x-ui.button tone="primary" icon="check" wire:click="depreciate" wire:confirm="{{ __('assets.confirm_voucher') }}" data-test="book-depreciation">{{ __('assets.depreciation.book') }}</x-ui.button>
                @endif
            </div>
            <x-ui.scroll-table :paginate="false">
                <table class="fin-table" data-test="depreciation-rows">
                    <thead><tr><th>{{ __('assets.fields.tag') }}</th><th>{{ __('assets.fields.name') }}</th><th>{{ __('assets.fields.project') }}</th><th class="fin-num">{{ __('assets.fields.cost_usd') }}</th><th class="fin-num">{{ __('assets.depreciation.amount') }}</th></tr></thead>
                    <tbody>
                        @forelse ($depreciation as $row)
                            <tr wire:key="dep-{{ $row['asset']->id }}">
                                <td dir="ltr" class="text-start tabular-nums">{{ $row['asset']->tag }}</td>
                                <td>{{ $row['asset']->name }}</td>
                                <td>{{ $row['asset']->project_code }}</td>
                                <td class="fin-num"><x-money :amount="$row['asset']->cost_usd" /></td>
                                <td class="fin-num"><x-money :amount="$row['amount']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="fin-empty">{{ __('assets.depreciation.none') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.scroll-table>
        </x-ui.panel>
    @elseif ($tab === 'warehouses')
        @if ($warehouseEditing !== '')
            <x-ui.panel :title="$warehouseEditing === 'new' ? __('assets.warehouse_new') : __('assets.warehouse_edit')">
                <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-5" data-test="warehouse-form">
                    <x-ui.form-input wire:model="warehouseForm.name" :label="__('assets.fields.name')" required data-test="w-name" />
                    <x-ui.form-input wire:model="warehouseForm.city" :label="__('assets.fields.city')" />
                    <x-ui.form-input wire:model="warehouseForm.center" :label="__('assets.fields.center')" />
                    <x-ui.form-input wire:model="warehouseForm.keeper" :label="__('assets.fields.keeper')" />
                    <x-ui.form-select wire:model="warehouseForm.active" :label="__('assets.fields.active')">
                        <option value="1">{{ __('assets.yes') }}</option>
                        <option value="0">{{ __('assets.no') }}</option>
                    </x-ui.form-select>
                    <div class="flex gap-2 sm:col-span-2 lg:col-span-5">
                        <x-ui.button tone="primary" icon="check" wire:click="saveWarehouse" data-test="save-warehouse">{{ __('assets.save') }}</x-ui.button>
                        <x-ui.button tone="ghost" wire:click="$set('warehouseEditing', '')">{{ __('assets.cancel') }}</x-ui.button>
                    </div>
                </div>
            </x-ui.panel>
        @endif
        <x-ui.panel :title="__('assets.tabs.warehouses')" :count="$warehouses->count()">
            <x-slot:toolbar>
                @if ($canEdit)
                    <x-ui.button size="sm" icon="plus" wire:click="editWarehouse('new')" data-test="new-warehouse">{{ __('assets.warehouse_new') }}</x-ui.button>
                @endif
            </x-slot:toolbar>
            <div class="fin-table-wrap">
                <table class="fin-table" data-test="warehouses">
                    <thead><tr><th>{{ __('assets.fields.name') }}</th><th>{{ __('assets.fields.city') }}</th><th>{{ __('assets.fields.center') }}</th><th>{{ __('assets.fields.keeper') }}</th><th class="fin-num">{{ __('assets.totals.count') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($warehouses as $place)
                            <tr wire:key="warehouse-{{ $place->id }}" @class(['opacity-60' => ! $place->active])>
                                <td class="font-medium">{{ $place->name }}</td>
                                <td>{{ $place->city }}</td>
                                <td>{{ $place->center }}</td>
                                <td>{{ $place->keeper }}</td>
                                <td class="fin-num tabular-nums">{{ $place->assets()->whereIn('status', \App\Models\FixedAsset::OWNED)->count() }}</td>
                                <td>
                                    @if ($canEdit)
                                        <x-ui.button size="xs" tone="ghost" icon="pencil-square" wire:click="editWarehouse('{{ $place->id }}')" :aria-label="__('assets.warehouse_edit')" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-ui.empty-state icon="building-storefront" :heading="__('assets.no_warehouses')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.panel>
    @else
        @if ($categoryEditing !== '')
            <x-ui.panel :title="$categoryEditing === 'new' ? __('assets.category_new') : __('assets.category_edit')">
                <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-5" data-test="category-form">
                    <x-ui.form-input wire:model="categoryForm.name_ar" :label="__('assets.fields.name_ar')" required />
                    <x-ui.form-input wire:model="categoryForm.name_en" :label="__('assets.fields.name_en')" dir="ltr" />
                    <x-ui.form-input wire:model="categoryForm.account_code" dir="ltr" :label="__('assets.fields.account')" :help="__('assets.account_help')" required />
                    <x-ui.form-input wire:model="categoryForm.life_months" inputmode="numeric" dir="ltr" :label="__('assets.fields.life_months')" required />
                    <x-ui.form-select wire:model="categoryForm.active" :label="__('assets.fields.active')">
                        <option value="1">{{ __('assets.yes') }}</option>
                        <option value="0">{{ __('assets.no') }}</option>
                    </x-ui.form-select>
                    <div class="flex gap-2 sm:col-span-2 lg:col-span-5">
                        <x-ui.button tone="primary" icon="check" wire:click="saveCategory" data-test="save-category">{{ __('assets.save') }}</x-ui.button>
                        <x-ui.button tone="ghost" wire:click="$set('categoryEditing', '')">{{ __('assets.cancel') }}</x-ui.button>
                    </div>
                </div>
            </x-ui.panel>
        @endif
        <x-ui.panel :title="__('assets.tabs.categories')" :subtitle="__('assets.categories_hint')" :count="$categories->count()">
            <x-slot:toolbar>
                @if ($canEdit)
                    <x-ui.button size="sm" icon="plus" wire:click="editCategory('new')" data-test="new-category">{{ __('assets.category_new') }}</x-ui.button>
                @endif
            </x-slot:toolbar>
            <div class="fin-table-wrap">
                <table class="fin-table" data-test="categories">
                    <thead><tr><th>{{ __('assets.fields.account') }}</th><th>{{ __('assets.fields.name') }}</th><th class="fin-num">{{ __('assets.fields.life_months') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($categories as $kind)
                            <tr wire:key="category-{{ $kind->id }}" @class(['opacity-60' => ! $kind->active])>
                                <td dir="ltr" class="text-start tabular-nums">{{ $kind->account_code }}</td>
                                <td>{{ $kind->name() }}</td>
                                <td class="fin-num tabular-nums">{{ $kind->life_months }}</td>
                                <td>
                                    @if ($canEdit)
                                        <x-ui.button size="xs" tone="ghost" icon="pencil-square" wire:click="editCategory('{{ $kind->id }}')" :aria-label="__('assets.category_edit')" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.panel>
    @endif
</div>
