<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.statement')" :subtitle="__('ledger.statement.intro')">
        @if ($account)
            <x-slot:actions>
                <x-ui.button tone="primary" wire:click="export" data-test="export-csv">{{ __('ledger.statement.export') }}</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <div class="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-4" data-test="statement-filters">
        <x-ui.form-select wire:model.live="code" :label="__('ledger.statement.account')" :placeholder="__('ledger.statement.pick_account')">
            @foreach ($accounts as $option)
                <option value="{{ $option->code }}">{{ $option->code }} · {{ $option->localizedName() }}</option>
            @endforeach
        </x-ui.form-select>
        <x-ui.form-select wire:model.live="project" :label="__('ledger.statement.project')" :placeholder="__('ledger.statement.all_projects')">
            @foreach ($projects as $option)
                <option value="{{ $option->code }}">{{ $option->code }} · {{ $option->name }}</option>
            @endforeach
        </x-ui.form-select>
        <x-ui.month-filter />
        <x-ui.form-input type="date" wire:model.live="from" :label="__('ledger.statement.from')" />
        <x-ui.form-input type="date" wire:model.live="to" :label="__('ledger.statement.to')" />
    </div>

    @if ($account === null)
        <x-ui.panel class="p-4">
            <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('ledger.statement.pick_hint') }}</p>
        </x-ui.panel>
    @else
        @php
            $side = fn (string $amount) => bccomp($amount, '0', 2) < 0 ? __('ledger.statement.cr') : __('ledger.statement.dr');
        @endphp
        <p class="text-lg font-semibold text-secondary-dark dark:text-ink"><span dir="ltr">{{ $account->code }}</span> · {{ $account->localizedName() }}</p>

        <x-ui.scroll-table>
            <table class="fin-table" data-test="statement-table">
                <thead>
                    <tr>
                        <th>{{ __('ledger.statement.date') }}</th>
                        <th>{{ __('ledger.statement.voucher') }}</th>
                        <th>{{ __('ledger.statement.description') }}</th>
                        <th>{{ __('ledger.statement.project') }}</th>
                        <th class="fin-num">{{ __('ledger.statement.debit') }}</th>
                        <th class="fin-num">{{ __('ledger.statement.credit') }}</th>
                        <th class="fin-num">{{ __('ledger.statement.balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr data-test="opening-row">
                        <td colspan="6" class="font-semibold">{{ __('ledger.statement.opening') }}</td>
                        <td class="fin-num"><x-money :amount="ltrim($data['opening'], '-')" /> {{ $side($data['opening']) }}</td>
                    </tr>
                    @forelse ($data['rows'] as $row)
                        <tr>
                            <td><span dir="ltr">{{ $row['date'] }}</span></td>
                            <td><a href="{{ route('entries.vouchers', ['voucher' => $row['voucher']]) }}" class="underline" dir="ltr">{{ $row['voucher'] }}</a></td>
                            <td>{{ $row['description'] }}</td>
                            <td><span dir="ltr">{{ $row['project'] }}</span></td>
                            <td class="fin-num"><x-money :amount="$row['debit']" /></td>
                            <td class="fin-num"><x-money :amount="$row['credit']" /></td>
                            <td class="fin-num"><x-money :amount="ltrim($row['balance'], '-')" /> {{ $side($row['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-secondary-medium dark:text-ink-muted">{{ __('ledger.statement.empty') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr data-test="closing-row" class="font-semibold">
                        <td colspan="4">{{ __('ledger.statement.closing') }}</td>
                        <td class="fin-num"><x-money :amount="$data['debit']" /></td>
                        <td class="fin-num"><x-money :amount="$data['credit']" /></td>
                        <td class="fin-num"><x-money :amount="ltrim($data['closing'], '-')" /> {{ $side($data['closing']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </x-ui.scroll-table>
        <p class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('ledger.statement.note') }}</p>
    @endif
</div>
