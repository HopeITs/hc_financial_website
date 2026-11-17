<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('contracts.title')" :subtitle="__('contracts.intro')" :count="count($contracts)">
        <x-slot:actions>
            <x-ui.button :href="route('funding.donors')" wire:navigate icon="users">{{ __('navigation.screens.donors') }}</x-ui.button>
            @if ($canCreate)
                <x-ui.button tone="primary" icon="plus" wire:click="add" data-test="add-contract">{{ __('contracts.add') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($showForm)
        <form wire:submit="save" data-test="contract-form">
            <x-ui.panel :title="__('contracts.add')" :subtitle="__('contracts.add_hint')" class="p-0">
                <div class="flex flex-col gap-4 p-4">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <x-ui.form-select wire:model.live="donorId" :label="__('contracts.donor')" :placeholder="__('contracts.choose')" required data-test="contract-donor">
                            <option value="">{{ __('contracts.choose') }}</option>
                            @foreach ($donors as $option)
                                <option value="{{ $option->id }}">{{ $option->localizedName() }} ({{ $option->code }})</option>
                            @endforeach
                        </x-ui.form-select>
                        <x-ui.form-input wire:model="title" :label="__('contracts.contract_title')" required data-test="contract-title" />
                        <x-ui.form-input wire:model="donorReference" :label="__('contracts.donor_reference')" dir="ltr" />
                        <x-ui.form-input wire:model="signedOn" type="date" :label="__('contracts.signed_on')" />
                        <x-ui.form-input wire:model="startsOn" type="date" :label="__('contracts.starts_on')" required data-test="contract-starts" />
                        <x-ui.form-input wire:model="endsOn" type="date" :label="__('contracts.ends_on')" required data-test="contract-ends" />
                        <x-ui.form-select wire:model.live="currency" :label="__('contracts.currency')" required data-test="contract-currency">
                            @foreach ($currencyCodes as $code)
                                <option value="{{ $code }}">{{ $code }}</option>
                            @endforeach
                        </x-ui.form-select>
                        <x-ui.form-input wire:model="amount" type="number" step="0.01" min="0" :label="__('contracts.amount')" dir="ltr" required data-test="contract-amount" />
                        @if ($currency !== 'USD')
                            <x-ui.form-input wire:model="fixedRate" type="number" step="0.000001" min="0" :label="__('contracts.fixed_rate')" :help="__('contracts.fixed_rate_help')" dir="ltr" required data-test="contract-rate" />
                        @endif
                        <x-ui.form-select wire:model="restriction" :label="__('contracts.restriction')" required>
                            @foreach ($restrictions as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </x-ui.form-select>
                    </div>

                    <flux:checkbox wire:model="isYearlyGeneral" :label="__('contracts.yearly_general')" :description="__('contracts.yearly_general_help')" />

                    <x-ui.form-textarea wire:model="notes" :label="__('contracts.notes')" rows="2" />

                    <div class="flex gap-2">
                        <x-ui.button type="submit" tone="primary" data-test="save-contract">{{ __('contracts.save_draft') }}</x-ui.button>
                        <x-ui.button type="button" wire:click="cancel">{{ __('contracts.cancel') }}</x-ui.button>
                    </div>
                </div>
            </x-ui.panel>
        </form>
    @endif

    <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-48 flex-1">
            <x-ui.form-input wire:model.live.debounce.300ms="search" :label="__('contracts.search')" type="search" data-test="contract-search" />
        </div>
        <x-ui.form-select wire:model.live="donor" :label="__('contracts.donor')">
            <option value="">{{ __('contracts.all_donors') }}</option>
            @foreach ($filterDonors as $option)
                <option value="{{ $option->id }}">{{ $option->localizedName() }}</option>
            @endforeach
        </x-ui.form-select>
        <x-ui.form-select wire:model.live="statusFilter" :label="__('contracts.status')">
            <option value="open">{{ __('contracts.filter_open') }}</option>
            <option value="closed">{{ __('contracts.filter_closed') }}</option>
            <option value="all">{{ __('contracts.filter_all') }}</option>
        </x-ui.form-select>
    </div>

    <x-ui.scroll-table>
        <table class="fin-table" data-test="contracts-table">
            <thead>
                <tr>
                    <th>{{ __('contracts.number') }}</th>
                    <th>{{ __('contracts.contract_title') }}</th>
                    <th>{{ __('contracts.donor') }}</th>
                    <th>{{ __('contracts.period') }}</th>
                    <th class="fin-num">{{ __('contracts.amount') }}</th>
                    <th class="fin-num">{{ __('contracts.amount_usd') }}</th>
                    <th>{{ __('contracts.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contracts as $contract)
                    <tr wire:key="contract-{{ $contract->id }}">
                        <td dir="ltr"><a href="{{ route('funding.agreements.show', ['contract' => $contract->id]) }}" wire:navigate class="font-medium underline">{{ $contract->number }}</a></td>
                        <td>{{ $contract->title }}</td>
                        <td>{{ $contract->donor->localizedName() }}</td>
                        <td dir="ltr">{{ $contract->starts_on->format('d/m/Y') }} – {{ $contract->ends_on->format('d/m/Y') }}</td>
                        <td class="fin-num"><x-money :amount="$contract->amount" :currency="$contract->currency" /></td>
                        <td class="fin-num"><x-money :amount="$usd[$contract->id]" /></td>
                        <td><x-contract-status :contract="$contract" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="fin-empty">{{ __('contracts.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.scroll-table>
</div>
