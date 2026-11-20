<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.donors')" :subtitle="__('donors.intro')" :count="count($donors)">
        <x-slot:actions>
            <livewire:excel-tools kind="donors" :key="'excel-donors'" />
            @if ($canSeeContracts)
                <x-ui.button :href="route('funding.agreements')" wire:navigate icon="document-text" data-test="open-contracts">{{ __('contracts.title') }}</x-ui.button>
            @endif
            @if ($canCreate)
                <x-ui.button tone="primary" icon="plus" wire:click="add" data-test="add-donor">{{ __('donors.add') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($notice)
        <div class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" role="status" data-test="notice">{{ $notice }}</div>
    @endif

    @if ($showForm)
        <form wire:submit="save" data-test="donor-form">
            <x-ui.panel :title="$editing === null ? __('donors.add') : __('donors.edit')" class="p-0">
                <div class="flex flex-col gap-4 p-4">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <x-ui.form-input wire:model="nameEn" :label="__('donors.name_en')" dir="ltr" required data-test="donor-name-en" />
                        <x-ui.form-input wire:model="nameAr" :label="__('donors.name_ar')" dir="rtl" required data-test="donor-name-ar" />
                        <x-ui.form-input wire:model="code" :label="__('donors.code')" dir="ltr" maxlength="16" required :help="__('donors.code_help')" data-test="donor-code" />
                        <x-ui.form-select wire:model="type" :label="__('donors.type')" :placeholder="__('donors.choose')" required data-test="donor-type">
                            <option value="">{{ __('donors.choose') }}</option>
                            @foreach ($types as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </x-ui.form-select>
                        <x-ui.form-input wire:model="country" :label="__('donors.country')" />
                        <x-ui.form-select wire:model="currency" :label="__('donors.currency')" :placeholder="__('donors.choose')">
                            <option value="">{{ __('donors.choose') }}</option>
                            @foreach ($currencyCodes as $code)
                                <option value="{{ $code }}">{{ $code }}</option>
                            @endforeach
                        </x-ui.form-select>
                    </div>

                    <x-ui.form-section :title="__('donors.contact')">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-ui.form-input wire:model="contactName" :label="__('donors.contact_name')" />
                            <x-ui.form-input wire:model="contactEmail" type="email" :label="__('donors.contact_email')" dir="ltr" />
                            <x-ui.form-input wire:model="contactPhone" :label="__('donors.contact_phone')" dir="ltr" />
                        </div>
                    </x-ui.form-section>

                    <x-ui.form-textarea wire:model="notes" :label="__('donors.notes')" rows="2" />

                    <flux:checkbox wire:model="isActive" :label="__('donors.active')" data-test="donor-active" />

                    <div class="flex gap-2">
                        <x-ui.button type="submit" tone="primary" data-test="save-donor">{{ __('donors.save') }}</x-ui.button>
                        <x-ui.button type="button" wire:click="cancel">{{ __('donors.cancel') }}</x-ui.button>
                    </div>
                </div>
            </x-ui.panel>
        </form>
    @endif

    <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-48 flex-1">
            <x-ui.form-input wire:model.live.debounce.300ms="search" :label="__('donors.search')" type="search" data-test="donor-search" />
        </div>
        <x-ui.form-select wire:model.live="typeFilter" :label="__('donors.type')">
            <option value="">{{ __('donors.all_types') }}</option>
            @foreach ($types as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </x-ui.form-select>
        <x-ui.form-select wire:model.live="statusFilter" :label="__('donors.status')">
            <option value="active">{{ __('donors.filter_active') }}</option>
            <option value="inactive">{{ __('donors.filter_inactive') }}</option>
            <option value="all">{{ __('donors.filter_all') }}</option>
        </x-ui.form-select>
    </div>

    <x-ui.scroll-table>
        <table class="fin-table" data-test="donors-table">
            <thead>
                <tr>
                    <th>{{ __('donors.code') }}</th>
                    <th>{{ __('donors.name') }}</th>
                    <th>{{ __('donors.type') }}</th>
                    <th>{{ __('donors.country') }}</th>
                    <th>{{ __('donors.currency') }}</th>
                    <th class="fin-num">{{ __('donors.running_contracts') }}</th>
                    <th class="fin-num">{{ __('donors.running_value') }}</th>
                    <th>{{ __('donors.status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($donors as $donor)
                    <tr wire:key="donor-{{ $donor->id }}">
                        <td class="font-medium text-secondary-dark dark:text-ink" dir="ltr">{{ $donor->code }}</td>
                        <td>
                            <div>{{ $donor->name_ar }}</div>
                            <div class="text-xs text-secondary-medium dark:text-ink-muted" dir="ltr">{{ $donor->name_en }}</div>
                        </td>
                        <td>{{ $donor->type->label() }}</td>
                        <td>{{ $donor->country ?? '—' }}</td>
                        <td dir="ltr">{{ $donor->currency ?? '—' }}</td>
                        <td class="fin-num">
                            @if ($canSeeContracts && ($runningCount[$donor->id] ?? 0) > 0)
                                <a href="{{ route('funding.agreements', ['donor' => $donor->id]) }}" wire:navigate class="underline">{{ $runningCount[$donor->id] }}</a>
                            @else
                                {{ $runningCount[$donor->id] ?? 0 }}
                            @endif
                        </td>
                        <td class="fin-num"><x-money :amount="$runningUsd[$donor->id] ?? 0" /></td>
                        <td>
                            @if ($donor->is_active)
                                <flux:badge size="sm" color="green">{{ __('donors.active') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('donors.inactive') }}</flux:badge>
                            @endif
                        </td>
                        <td>
                            <div class="flex items-center gap-1">
                                @if ($canEdit)
                                    <x-ui.button size="sm" wire:click="edit({{ $donor->id }})" data-test="edit-donor-{{ $donor->code }}">{{ __('donors.edit') }}</x-ui.button>
                                @endif
                                @if ($canDelete && ! isset($runningCount[$donor->id]))
                                    <x-ui.button size="sm" tone="ghost" icon="trash" wire:click="delete({{ $donor->id }})" wire:confirm="{{ __('donors.delete_confirm', ['name' => $donor->localizedName()]) }}" :aria-label="__('donors.delete')" data-test="delete-donor-{{ $donor->code }}" />
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="fin-empty">{{ __('donors.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.scroll-table>

    <p class="text-xs text-secondary-medium dark:text-ink-muted">{{ __('donors.running_value_note') }}</p>
</div>
