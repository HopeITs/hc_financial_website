{{-- The shared month picker: pair it with the HasMonthFilter trait on the Livewire component. --}}
<x-ui.form-input type="month" wire:model.live="month" :label="__('common.month')" {{ $attributes }} />
