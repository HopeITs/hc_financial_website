{{-- The report currency switch: USD, or another active currency at a typed rate. Needs the HasReportCurrency trait on the Livewire component. --}}
@php($currencies = app(\App\Services\Ledger\Currencies::class))
<div class="grid items-end gap-4 sm:grid-cols-2" data-test="currency-switch">
    <x-ui.form-select wire:model.live="currency" :label="__('ledger.report.currency')">
        @foreach ($currencies->codes() as $code)
            <option value="{{ $code }}">{{ $code }}</option>
        @endforeach
    </x-ui.form-select>
    @if ($this->currency !== 'USD' && $currencies->isActive($this->currency))
        <x-ui.form-input type="number" step="0.000001" min="0" dir="ltr" wire:model.live.debounce.500ms="rate" :label="$currencies->isUsdPerUnit($this->currency) ? __('ledger.report.rate_usd_per_unit', ['currency' => $this->currency]) : __('ledger.report.rate_per_usd', ['currency' => $this->currency])" />
    @endif
</div>
