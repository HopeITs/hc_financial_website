@php
    $cell = 'w-full min-w-28 rounded-md border border-secondary-light bg-white px-2 py-1.5 text-sm text-secondary-dark dark:border-field-line dark:bg-surface-2 dark:text-ink';
@endphp

<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="daily-count">
    <x-ui.page-header :title="__('navigation.screens.tills')" :subtitle="__('treasury.count_intro')">
        <x-slot:actions>
            <livewire:excel-tools kind="cash_counts" :context="['day' => $day, 'city' => $city]" :key="'excel-counts-'.md5($day.'|'.$city)" />
        </x-slot:actions>
    </x-ui.page-header>

    @if ($notice)
        <div class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" role="status" data-test="notice">{{ $notice }}</div>
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

    <x-ui.panel class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.form-input type="date" wire:model.live="day" :label="__('treasury.date')" data-test="count-day" />
        <x-ui.form-select wire:model.live="city" :label="__('treasury.city')" data-test="count-city">
            @foreach ($cities as $option)
                <option value="{{ $option }}">{{ $option === \App\Services\Treasury\CashCounts::NO_CITY ? __('treasury.no_city') : $option }}</option>
            @endforeach
        </x-ui.form-select>
    </x-ui.panel>

    <x-ui.scroll-table :paginate="false">
        <table class="fin-table" data-test="count-table">
            <thead>
                <tr>
                    <th>{{ __('treasury.account') }}</th>
                    <th class="fin-num">{{ __('treasury.book') }}</th>
                    <th class="min-w-36">{{ __('treasury.counted') }}</th>
                    <th class="fin-num">{{ __('treasury.difference') }}</th>
                    <th class="min-w-56">{{ __('treasury.explanation') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr wire:key="count-{{ $row['code'] }}" data-test="count-{{ $row['code'] }}">
                        <td>
                            <span dir="ltr">{{ $row['code'] }}</span> · {{ $row['name'] }}
                            @if (bccomp($row['pending'], '0', 2) !== 0)
                                <div class="text-xs text-status-warn">{{ __('treasury.book_pending') }} <x-money :amount="$row['pending']" :currency="$row['currency']" /></div>
                            @endif
                        </td>
                        <td class="fin-num"><x-money :amount="$row['book']" :currency="$row['currency']" /></td>
                        <td>
                            @if ($canCount && $row['voucher'] === null)
                                <input type="text" inputmode="decimal" dir="ltr" wire:model="counted.{{ $row['code'] }}" class="{{ $cell }} text-end" aria-label="{{ __('treasury.counted') }} {{ $row['code'] }}" data-test="counted-{{ $row['code'] }}">
                            @elseif ($row['counted'] !== null)
                                <x-money :amount="$row['counted']" :currency="$row['currency']" />
                            @endif
                        </td>
                        <td class="fin-num" data-test="difference-{{ $row['code'] }}">
                            @if ($row['difference'] !== null)
                                @if (bccomp($row['difference'], '0', 2) === 0)
                                    <flux:badge size="sm" color="green">{{ __('treasury.matches') }}</flux:badge>
                                @else
                                    <x-money :amount="$row['difference']" :currency="$row['currency']" />
                                @endif
                            @endif
                        </td>
                        <td>
                            @if ($canCount && $row['voucher'] === null)
                                <input type="text" wire:model="explanation.{{ $row['code'] }}" class="{{ $cell }}" aria-label="{{ __('treasury.explanation') }} {{ $row['code'] }}" data-test="explanation-{{ $row['code'] }}">
                            @else
                                {{ $row['explanation'] }}
                            @endif
                        </td>
                        <td class="whitespace-nowrap">
                            @if ($row['voucher'] !== null)
                                <a href="{{ route(\App\Support\Navigation::routeName('vouchers'), ['voucher' => $row['voucher']]) }}" wire:navigate class="underline" dir="ltr">{{ $row['voucher'] }}</a>
                                <flux:badge size="sm" :color="$row['voucher_status'] === \App\Enums\VoucherStatus::Approved ? 'green' : 'amber'">{{ $row['voucher_status']?->label() }}</flux:badge>
                            @elseif ($canCount)
                                <x-ui.button size="sm" wire:click="saveCount('{{ $row['code'] }}')" data-test="save-count-{{ $row['code'] }}">{{ __('treasury.save_count') }}</x-ui.button>
                                @if ($row['difference'] !== null && bccomp($row['difference'], '0', 2) !== 0)
                                    <x-ui.button size="sm" tone="primary" wire:click="adjust('{{ $row['code'] }}')" wire:confirm="{{ __('treasury.adjust_confirm') }}" data-test="adjust-{{ $row['code'] }}">{{ __('treasury.adjust') }}</x-ui.button>
                                @endif
                                @if ($row['counted'] !== null)
                                    <x-ui.button size="sm" tone="ghost" icon="trash" wire:click="removeCount('{{ $row['code'] }}')" wire:confirm="{{ __('treasury.count_remove_confirm') }}" :aria-label="__('treasury.remove')" data-test="remove-count-{{ $row['code'] }}" />
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="fin-empty">{{ __('treasury.no_cash_accounts') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.scroll-table>
</div>
