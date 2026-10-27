<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <div>
        <a href="{{ route('projects.balances') }}" wire:navigate class="text-sm text-secondary-medium hover:underline dark:text-ink-muted">{{ __('projects.back') }}</a>
        <flux:heading size="xl" level="1" class="mt-1 text-3xl! font-bold! leading-none!">{{ $project->name }}</flux:heading>
        <flux:subheading class="mt-2">
            {{ collect([$project->cityName, $project->programName, $project->donors ?: null])->filter()->implode(' · ') }}
            <flux:badge size="sm" :color="$project->closed ? 'zinc' : 'green'" class="ms-2">{{ $project->closed ? __('projects.status.closed') : __('projects.status.active') }}</flux:badge>
        </flux:subheading>
    </div>

    <div class="flex gap-1 overflow-x-auto border-b border-secondary-soft dark:border-line" role="tablist" data-test="tabs">
        @foreach ($tabs as $name)
            <button
                type="button" role="tab" wire:click="show('{{ $name }}')" wire:key="tab-{{ $name }}" aria-selected="{{ $tab === $name ? 'true' : 'false' }}"
                data-test="tab-{{ $name }}"
                @class([
                    'px-4 py-3 text-sm font-medium -mb-px border-b-2',
                    'border-primary text-secondary-dark dark:text-ink' => $tab === $name,
                    'border-transparent text-secondary-medium hover:text-secondary-dark dark:text-ink-muted' => $tab !== $name,
                ])
            >{{ __('projects.tabs.'.$name) }}</button>
        @endforeach
    </div>

    @if ($tab === 'overview')
        <section class="flex flex-col gap-4" data-test="tab-overview-content">
            <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('home.city') }}</dt><dd>{{ $project->cityName }}</dd></div>
                <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('projects.sub_project') }}</dt><dd>{{ $project->subProjectName }}</dd></div>
                <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('projects.begin') }}</dt><dd>{{ $project->beginDate }}</dd></div>
                <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('projects.end') }}</dt><dd>{{ $project->endDate }}</dd></div>
            </dl>

            @if ($showMoney)
                <div class="grid gap-3 sm:grid-cols-3" data-test="balances">
                    <x-ui.stat-tile :label="__('home.balance_syp')" :value="number_format((float) ($balance?->syp ?? 0), 2)" unit="SYP" icon="banknotes" />
                    <x-ui.stat-tile :label="__('projects.balance_syp_new')" :value="number_format((float) ($balance?->sypNew ?? 0), 2)" unit="SYP" icon="banknotes" />
                    <x-ui.stat-tile :label="__('home.balance_usd')" :value="number_format((float) ($balance?->usd ?? 0), 2)" unit="USD" icon="banknotes" />
                </div>

                <flux:heading level="2">{{ __('projects.where_money') }}</flux:heading>
                <x-ui.scroll-table>
                    <table class="fin-table" data-test="cash-accounts-table">
                        <thead>
                            <tr>
                                <th>{{ __('projects.cash_account') }}</th>
                                <th class="fin-num">{{ __('home.balance_syp') }}</th>
                                <th class="fin-num">{{ __('projects.balance_syp_new') }}</th>
                                <th class="fin-num">{{ __('home.balance_usd') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cashAccounts as $cash)
                                <tr wire:key="cash-{{ $cash->cashAccountId }}">
                                    <td>{{ $cash->name }}</td>
                                    <td class="fin-num"><x-money :amount="$cash->syp" /></td>
                                    <td class="fin-num"><x-money :amount="$cash->sypNew" /></td>
                                    <td class="fin-num"><x-money :amount="$cash->usd" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="fin-empty">{{ __('projects.none_money') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-ui.scroll-table>
            @endif
        </section>
    @elseif ($tab === 'accounts')
        <x-ui.scroll-table data-test="tab-accounts-content">
            <table class="fin-table">
                <thead>
                    <tr>
                        <th>{{ __('projects.account') }}</th>
                        <th>{{ __('projects.donor') }}</th>
                        <th>{{ __('programs.status_label') }}</th>
                        @if ($showMoney)
                            <th class="fin-num">{{ __('home.balance_syp') }}</th>
                            <th class="fin-num">{{ __('home.balance_usd') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($siblings as $account)
                        <tr wire:key="account-{{ $account->accountId }}">
                            <td>{{ $account->name }}</td>
                            <td>{{ $account->donors }}</td>
                            <td><flux:badge size="sm" :color="$account->closed ? 'zinc' : 'green'">{{ $account->closed ? __('projects.status.closed') : __('projects.status.active') }}</flux:badge></td>
                            @if ($showMoney)
                                @php($row = $siblingBalances->get($account->accountId))
                                <td class="fin-num"><x-money :amount="($row?->syp ?? 0)" /></td>
                                <td class="fin-num"><x-money :amount="($row?->usd ?? 0)" /></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.scroll-table>
    @elseif ($tab === 'budget')
        <x-ui.scroll-table data-test="tab-budget-content">
            <table class="fin-table">
                <thead>
                    <tr>
                        <th>{{ __('projects.budget') }}</th>
                        <th>{{ __('projects.line') }}</th>
                        <th class="fin-num">{{ __('projects.unit') }}</th>
                        <th class="fin-num">{{ __('projects.timing') }}</th>
                        @if ($showMoney)
                            <th class="fin-num">{{ __('projects.price') }}</th>
                            <th class="fin-num">{{ __('projects.total') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($budgetLines as $line)
                        <tr wire:key="line-{{ $loop->index }}">
                            <td>{{ $line['budget'] }}</td>
                            <td>{{ $line['line'] }}</td>
                            <td class="fin-num" dir="ltr">{{ $line['unit'] }}</td>
                            <td class="fin-num" dir="ltr">{{ $line['timing'] }}</td>
                            @if ($showMoney)
                                <td class="fin-num"><x-money :amount="$line['price']" /></td>
                                <td class="fin-num"><x-money :amount="$line['total']" /></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $showMoney ? 6 : 4 }}" class="fin-empty">{{ __('projects.none_budget') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.scroll-table>
    @else
        <x-ui.scroll-table data-test="tab-lifetime-content">
            @if ($showMoney)
                <table class="fin-table">
                    <thead>
                        <tr>
                            <th>{{ __('projects.month') }}</th>
                            <th>{{ __('projects.currency') }}</th>
                            <th class="fin-num">{{ __('projects.income') }}</th>
                            <th class="fin-num">{{ __('projects.expenses') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lifetime as $row)
                            <tr wire:key="life-{{ $loop->index }}">
                                <td dir="ltr">{{ $row['month'] }}</td>
                                <td>{{ $row['currency'] }}</td>
                                <td class="fin-num"><x-money :amount="$row['income']" /></td>
                                <td class="fin-num"><x-money :amount="$row['expense']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="fin-empty">{{ __('projects.none_lifetime') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <p class="px-4 py-6 text-center text-secondary-medium dark:text-ink-muted">{{ __('projects.money_hidden') }}</p>
            @endif
        </x-ui.scroll-table>
    @endif
</div>
