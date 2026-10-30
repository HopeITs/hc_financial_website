<div class="flex flex-col gap-4" data-test="settlement-form">
    @if ($failures !== [])
        <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="failures">
            @foreach ($failures as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    <x-ui.panel class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-ui.form-input type="date" wire:model.live="date" :label="__('ledger.builder.date')" required />
        <x-ui.form-select wire:model.live="person" :label="__('ledger.settlement.person')" required data-test="settle-person">
            <option value="">—</option>
            @foreach ($people as $row)
                <option value="{{ $row['person'] }}">{{ $row['person'] }} · {{ number_format((float) $row['open'], 2) }}</option>
            @endforeach
        </x-ui.form-select>
        <x-ui.form-select wire:model.live="cash" :label="__('ledger.settlement.cash')" :help="__('ledger.settlement.cash_help')" data-test="settle-cash">
            <option value="">—</option>
            @foreach ($cashAccounts as $option)
                <option value="{{ $option->code }}">{{ $option->code }} · {{ app()->getLocale() === 'ar' ? $option->name_ar : $option->name_en }}</option>
            @endforeach
        </x-ui.form-select>
    </x-ui.panel>

    @if ($people === [])
        <p class="text-sm text-secondary-medium dark:text-ink-muted" data-test="no-advances">{{ __('ledger.settlement.none_open') }}</p>
    @endif

    <x-ui.panel class="flex flex-col gap-3 p-4" data-test="settle-bills">
        <h3 class="text-sm font-semibold">{{ __('ledger.settlement.bills') }}</h3>
        @foreach ($bills as $index => $bill)
            <div class="grid items-end gap-3 sm:grid-cols-[2fr_1fr_2fr_auto]" wire:key="bill-{{ $index }}">
                <x-ui.form-select wire:model.live="bills.{{ $index }}.budget_line" :label="__('ledger.builder.budget_line')">
                    <option value="">—</option>
                    @foreach ($budgetLines as $line)
                        <option value="{{ $line->key }}">{{ $line->key }}</option>
                    @endforeach
                </x-ui.form-select>
                <x-ui.form-input wire:model.live.debounce.400ms="bills.{{ $index }}.amount" :label="__('ledger.entry.amount', ['currency' => 'USD'])" dir="ltr" inputmode="decimal" />
                <x-ui.form-input wire:model.live.debounce.400ms="bills.{{ $index }}.note" :label="__('ledger.settlement.note')" />
                <button type="button" wire:click="removeBill({{ $index }})" class="pb-2 text-status-critical hover:underline">{{ __('ledger.builder.remove_file') }}</button>
            </div>
        @endforeach
        <div><x-ui.button wire:click="addBill" data-test="add-bill">{{ __('ledger.settlement.add_bill') }}</x-ui.button></div>
        <x-ui.form-textarea wire:model.live.debounce.400ms="description" :label="__('ledger.builder.description')" rows="2" />
    </x-ui.panel>

    @if ($result !== null)
        <x-ui.panel class="flex flex-col gap-2 p-4" data-test="settle-summary">
            <p class="text-sm">{{ __('ledger.settlement.open') }}: <span dir="ltr" class="font-semibold" data-test="settle-open">{{ number_format((float) $result['open'], 2) }}</span>
                · {{ __('ledger.settlement.billed') }}: <span dir="ltr" class="font-semibold" data-test="settle-billed">{{ number_format((float) $result['billed'], 2) }}</span></p>
            <p class="text-sm" data-test="settle-difference">
                @if (bccomp($result['difference'], '0', 2) > 0)
                    {{ __('ledger.settlement.repay', ['amount' => number_format((float) $result['difference'], 2)]) }}
                @elseif (bccomp($result['difference'], '0', 2) < 0)
                    {{ __('ledger.settlement.pay_out', ['amount' => number_format((float) abs((float) $result['difference']), 2)]) }}
                @else
                    {{ __('ledger.settlement.exact') }}
                @endif
            </p>
            @foreach ([...$result['problems'], ...$violations] as $message)
                <p class="text-sm text-status-critical">{{ $message }}</p>
            @endforeach
            @if ($result['problems'] === [] && $violations === [])
                <p class="text-sm text-status-success">{{ __('ledger.settlement.vouchers', ['count' => count($result['drafts'])]) }}</p>
            @endif
        </x-ui.panel>
    @endif

    <div>
        <x-ui.button tone="primary" wire:click="save" :disabled="$result === null || $result['problems'] !== [] || $violations !== []" data-test="save-settlement">{{ __('ledger.settlement.save') }}</x-ui.button>
    </div>
</div>
