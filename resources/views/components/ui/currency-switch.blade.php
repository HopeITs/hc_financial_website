{{-- The report currency switch: USD, or SYP at a typed rate. Needs the HasReportCurrency trait on the Livewire component. --}}
<div class="grid items-end gap-4 sm:grid-cols-2" data-test="currency-switch">
    <x-ui.form-select wire:model.live="currency" :label="__('ledger.report.currency')">
        <option value="USD">USD</option>
        <option value="SYP">SYP</option>
    </x-ui.form-select>
    @if ($this->currency === 'SYP')
        <x-ui.form-input type="number" step="0.000001" min="0" dir="ltr" wire:model.live.debounce.500ms="rate" :label="__('ledger.report.rate')" />
    @endif
</div>
