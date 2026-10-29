<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.currency')" :subtitle="__('currencies.intro')" :count="count($rows)">
        @if ($canCreate)
            <x-slot:actions>
                <x-ui.button tone="primary" icon="plus" wire:click="add" data-test="add-currency">{{ __('currencies.add') }}</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <x-ui.form-draft :saved-at="$draftSavedAt" :notice="$draftNotice" />

    @if ($notice)
        <div class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" role="status" data-test="notice">{{ $notice }}</div>
    @endif

    @if ($showForm)
        <div x-data="unsavedGuard" data-unsaved-message="{{ __('drafts.leave_warning') }}" x-on:form-clean.window="clean()">
        <form wire:submit="save" data-test="currency-form">
            <x-ui.panel :title="$editing === null ? __('currencies.add') : __('currencies.edit')" class="p-0">
                <div class="flex flex-col gap-4 p-4">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-ui.form-input wire:model.live.blur="code" :label="__('currencies.code')" dir="ltr" maxlength="3" :disabled="$editing !== null" required data-test="currency-code" />
                        <x-ui.form-input wire:model="nameEn" :label="__('currencies.name_en')" dir="ltr" required />
                        <x-ui.form-input wire:model="nameAr" :label="__('currencies.name_ar')" dir="rtl" required />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1">
                            <x-ui.form-select wire:model.live="quote" :label="__('currencies.quote')">
                                <option value="per_usd">{{ __('currencies.quote_per_usd') }}</option>
                                <option value="usd_per_unit">{{ __('currencies.quote_usd_per_unit') }}</option>
                            </x-ui.form-select>
                            <p class="text-xs text-secondary-dark dark:text-ink" data-test="quote-example">
                                {{ __('currencies.quote_example') }}
                                <span dir="ltr" class="font-medium">{{ $quote === 'usd_per_unit' ? '1 '.($code !== '' ? strtoupper($code) : 'XXX').' = 1.27 USD' : '1 USD = 150 '.($code !== '' ? strtoupper($code) : 'XXX') }}</span>
                            </p>
                        </div>
                        <x-ui.form-select wire:model="active" :label="__('currencies.status')">
                            <option value="1">{{ __('currencies.active') }}</option>
                            <option value="0">{{ __('currencies.inactive') }}</option>
                        </x-ui.form-select>
                    </div>

                    <div class="flex gap-2">
                        <x-ui.button type="submit" tone="primary" x-on:click="leaving()">{{ __('currencies.save') }}</x-ui.button>
                        <x-ui.button wire:click="saveDraft" data-test="save-draft">{{ __('drafts.save') }}</x-ui.button>
                        <x-ui.button type="button" wire:click="cancel">{{ __('currencies.cancel') }}</x-ui.button>
                    </div>
                </div>
            </x-ui.panel>
        </form>
        </div>
    @endif

    <x-ui.scroll-table>
        <table class="fin-table" data-test="currencies-table">
            <thead>
                <tr>
                    <th>{{ __('currencies.code') }}</th>
                    <th>{{ __('currencies.name') }}</th>
                    <th>{{ __('currencies.quote') }}</th>
                    <th>{{ __('currencies.status') }}</th>
                    <th class="fin-num">{{ __('currencies.lines') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr wire:key="currency-{{ $row->code }}">
                        <td class="font-medium text-secondary-dark dark:text-ink" dir="ltr">{{ $row->code }}</td>
                        <td>{{ $row->localizedName() }}@if ($row->is_base) <flux:badge size="sm" class="ms-2">{{ __('currencies.base') }}</flux:badge>@endif</td>
                        <td>{{ $row->is_base ? '—' : ($row->quote === 'usd_per_unit' ? __('currencies.quote_usd_per_unit') : __('currencies.quote_per_usd')) }}</td>
                        <td>{{ $row->is_active || $row->is_base ? __('currencies.active') : __('currencies.inactive') }}</td>
                        <td class="fin-num">{{ $used[$row->code] ?? 0 }}</td>
                        <td>
                            @if ($canEdit && ! $row->is_base)
                                <x-ui.button size="sm" wire:click="edit('{{ $row->code }}')" data-test="edit-{{ $row->code }}">{{ __('currencies.edit') }}</x-ui.button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.scroll-table>
</div>
