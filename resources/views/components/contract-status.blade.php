@props(['contract'])

{{-- A funding contract's state: its status, and "Waiting for approval" or "Closing requested" when that applies. --}}
<span {{ $attributes->class('inline-flex flex-wrap items-center gap-1') }}>
    <flux:badge size="sm" :color="$contract->status->badgeColor()">{{ $contract->status->label() }}</flux:badge>
    @if (! $contract->isApproved() && $contract->isOpen())
        <flux:badge size="sm" color="amber">{{ __('contracts.waiting_approval') }}</flux:badge>
    @elseif ($contract->isClosingRequested())
        <flux:badge size="sm" color="amber">{{ __('contracts.closing_requested') }}</flux:badge>
    @endif
</span>
