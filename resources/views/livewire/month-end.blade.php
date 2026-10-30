<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.monthend')" :subtitle="__('ledger.monthend.intro')" />

    @if ($notice)
        <div class="rounded-lg border border-status-success px-4 py-2 text-sm text-status-success" role="status" data-test="notice">{{ $notice }}</div>
    @endif

    @if ($failures !== [])
        <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="failures">
            @foreach ($failures as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    <div class="grid items-end gap-4 sm:grid-cols-3" data-test="monthend-filters">
        <x-ui.form-input type="month" wire:model.live="month" :label="__('ledger.monthend.month')" />
        <x-ui.form-select wire:model.live="city" :label="__('ledger.monthend.city')">
            <option value="">{{ __('ledger.monthend.all_cities') }}</option>
            @foreach ($cities as $option)
                <option value="{{ $option }}">{{ $option === \App\Services\Ledger\MonthEnd::NO_CITY ? __('ledger.monthend.no_city') : $option }}</option>
            @endforeach
        </x-ui.form-select>
        <p class="text-sm font-semibold {{ $closed ? 'text-status-success' : 'text-secondary-medium dark:text-ink-muted' }}" data-test="month-state">{{ $closed ? __('ledger.monthend.state_closed') : __('ledger.monthend.state_open') }}</p>
    </div>

    <x-ui.scroll-table :paginate="false">
        <table class="fin-table" data-test="monthend-rows">
            <thead>
                <tr>
                    <th>{{ __('ledger.monthend.account') }}</th>
                    <th>{{ __('ledger.treasury.currency') }}</th>
                    <th class="fin-num">{{ __('ledger.monthend.ledger') }}</th>
                    <th class="fin-num">{{ __('ledger.monthend.counted') }}</th>
                    <th class="fin-num">{{ __('ledger.monthend.difference') }}</th>
                    <th>{{ __('ledger.monthend.explanation') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr wire:key="me-{{ $row['code'] }}" data-test="me-{{ $row['code'] }}">
                        <td><span dir="ltr">{{ $row['code'] }}</span> · {{ app()->getLocale() === 'ar' ? $row['name_ar'] : $row['name_en'] }}</td>
                        <td>{{ $row['currency'] }}</td>
                        <td class="fin-num" dir="ltr">{{ number_format((float) $row['ledger'], 2) }}</td>
                        <td class="fin-num"><input type="text" inputmode="decimal" dir="ltr" wire:model="counted.{{ $row['code'] }}" @disabled($closed || ! $canEdit) class="w-32 rounded border border-secondary-light bg-transparent px-2 py-1 text-end dark:border-ink-muted/30" data-test="counted-{{ $row['code'] }}"></td>
                        <td class="fin-num" dir="ltr">
                            @if ($row['difference'] === null)
                                —
                            @elseif (bccomp($row['difference'], '0', 2) === 0)
                                <span class="text-status-success">0.00</span>
                            @else
                                <span class="{{ $row['settled'] ? 'text-status-warning' : 'text-status-critical' }}">{{ number_format((float) $row['difference'], 2) }}</span>
                            @endif
                        </td>
                        <td><input type="text" wire:model="note.{{ $row['code'] }}" @disabled($closed || ! $canEdit) class="w-56 rounded border border-secondary-light bg-transparent px-2 py-1 dark:border-ink-muted/30" data-test="note-{{ $row['code'] }}"></td>
                        <td>@if (! $closed && $canEdit)<x-ui.button wire:click="saveRow('{{ $row['code'] }}')" data-test="save-{{ $row['code'] }}">{{ __('ledger.monthend.save') }}</x-ui.button>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="fin-empty">{{ __('ledger.monthend.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.scroll-table>

    @if (! $closed)
        <x-ui.panel class="flex flex-col gap-3 p-4" data-test="blockers">
            @if ($blockers === [])
                <p class="text-sm text-status-success">{{ __('ledger.monthend.ready') }}</p>
            @else
                <p class="text-sm font-semibold">{{ __('ledger.monthend.blocked') }}</p>
                <ul class="list-disc ps-5 text-sm text-status-critical">
                    @foreach ($blockers as $problem)
                        <li>{{ $problem }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($canClose)
                <div><x-ui.button tone="primary" wire:click="close" wire:confirm="{{ __('ledger.monthend.close_confirm') }}" :disabled="$blockers !== []" data-test="close-month">{{ __('ledger.monthend.close') }}</x-ui.button></div>
            @endif
        </x-ui.panel>
    @elseif ($canReopen)
        <x-ui.panel class="flex flex-col gap-3 p-4" data-test="reopen">
            <x-ui.form-textarea wire:model="reopenReason" :label="__('ledger.monthend.reopen_reason')" rows="2" />
            <div><x-ui.button wire:click="reopen" wire:confirm="{{ __('ledger.monthend.reopen_confirm') }}" data-test="reopen-month">{{ __('ledger.monthend.reopen') }}</x-ui.button></div>
        </x-ui.panel>
    @endif

    @if ($history->isNotEmpty())
        <x-ui.panel class="flex flex-col gap-1 p-4 text-sm" data-test="month-history">
            @foreach ($history as $entry)
                <p>{{ $entry->created_at->format('Y-m-d H:i') }} · {{ __('ledger.monthend.log_'.$entry->action) }} · #{{ $entry->user_id }}@if ($entry->reason) · {{ $entry->reason }}@endif</p>
            @endforeach
        </x-ui.panel>
    @endif
</div>
