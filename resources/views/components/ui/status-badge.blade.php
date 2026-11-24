@props([
    // The record's status key: draft, submitted, approved, paid, rejected…
    'status',
    // The text to show (the screen's own translation of the status).
    'label',
    // True where "approved" is the last step (a voucher); elsewhere approved still waits for the next step (to pay).
    'final' => false,
    'size' => 'sm',
])

@php
    /*
     * One colour per state on every screen (finance decision of 2026-11-25): grey being prepared or stopped, amber
     * waiting for someone, blue approved and on its way, green done, red refused or sent back.
     */
    $color = match ($status) {
        'submitted', 'pending_approval', 'pending', 'due', 'waiting' => 'amber',
        'approved' => $final ? 'green' : 'blue',
        'ordered', 'received', 'ready' => 'blue',
        'paid', 'active', 'posted', 'closed', 'done' => 'green',
        'rejected', 'returned' => 'red',
        default => 'zinc',
    };
@endphp

<flux:badge :size="$size" :color="$color" {{ $attributes }}>{{ $label }}</flux:badge>
