@props(['contract', 'needsMatching' => false])

{{-- An agreement's state: its status, "Waiting for approval" or "Closing requested" when that applies, and "Needs budget matching" when it was approved before its budget matched its shares. --}}
<span {{ $attributes->class('inline-flex flex-wrap items-center gap-1') }}>
    <flux:badge size="sm" :color="$contract->status->badgeColor()">{{ $contract->status->label() }}</flux:badge>
    @if (! $contract->isApproved() && $contract->isOpen())
        <flux:badge size="sm" color="amber">{{ __('contracts.waiting_approval') }}</flux:badge>
    @elseif ($contract->isClosingRequested())
        <flux:badge size="sm" color="amber">{{ __('contracts.closing_requested') }}</flux:badge>
    @endif
    @if ($needsMatching)
        <flux:badge size="sm" color="amber" data-test="needs-matching">{{ __('contracts.budget_needs_matching') }}</flux:badge>
    @endif
</span>
