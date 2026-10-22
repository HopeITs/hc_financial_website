<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <div>
        <a href="{{ route('projects') }}" wire:navigate class="text-sm text-secondary-medium hover:underline dark:text-ink-muted">{{ __('projects.back') }}</a>
        <flux:heading size="xl" level="1">{{ $project->name }}</flux:heading>
        <flux:subheading>
            {{ collect([$project->cityName, $project->programName, $project->donors ?: null])->filter()->implode(' · ') }}
            <flux:badge size="sm" class="ms-2">{{ $project->closed ? __('projects.status.closed') : __('projects.status.active') }}</flux:badge>
        </flux:subheading>
    </div>

    <div class="flex gap-1 border-b border-secondary-soft dark:border-line" role="tablist" data-test="tabs">
        @foreach ($tabs as $name)
            <button
                type="button" role="tab" wire:click="show('{{ $name }}')" wire:key="tab-{{ $name }}" aria-selected="{{ $tab === $name ? 'true' : 'false' }}"
                data-test="tab-{{ $name }}"
                @class([
                    'px-4 py-2 text-sm font-medium -mb-px border-b-2',
                    'border-primary text-secondary-dark dark:text-ink' => $tab === $name,
                    'border-transparent text-secondary-medium hover:text-secondary-dark dark:text-ink-muted' => $tab !== $name,
                ])
            >{{ __('projects.tabs.'.$name) }}</button>
        @endforeach
    </div>

    @if ($tab === 'overview')
        <section class="flex flex-col gap-4" data-test="tab-overview-content">
            <dl class="grid gap-3 text-sm sm:grid-cols-4">
                <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('home.city') }}</dt><dd>{{ $project->cityName }}</dd></div>
                <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('projects.sub_project') }}</dt><dd>{{ $project->subProjectName }}</dd></div>
                <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('projects.begin') }}</dt><dd>{{ $project->beginDate }}</dd></div>
                <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('projects.end') }}</dt><dd>{{ $project->endDate }}</dd></div>
            </dl>

            @if ($showMoney)
                <div class="grid gap-3 sm:grid-cols-3" data-test="balances">
                    <div class="rounded-xl border border-secondary-soft p-4 dark:border-line"><div class="text-xs text-secondary-medium dark:text-ink-muted">{{ __('home.balance_syp') }}</div><div class="text-xl tabular-nums" dir="ltr">{{ number_format((float) ($balance?->syp ?? 0), 2) }}</div></div>
                    <div class="rounded-xl border border-secondary-soft p-4 dark:border-line"><div class="text-xs text-secondary-medium dark:text-ink-muted">{{ __('projects.balance_syp_new') }}</div><div class="text-xl tabular-nums" dir="ltr">{{ number_format((float) ($balance?->sypNew ?? 0), 2) }}</div></div>
                    <div class="rounded-xl border border-secondary-soft p-4 dark:border-line"><div class="text-xs text-secondary-medium dark:text-ink-muted">{{ __('home.balance_usd') }}</div><div class="text-xl tabular-nums" dir="ltr">{{ number_format((float) ($balance?->usd ?? 0), 2) }}</div></div>
                </div>

                <flux:heading level="2">{{ __('projects.where_money') }}</flux:heading>
                <div class="overflow-x-auto rounded-xl border border-secondary-soft bg-white shadow-card dark:border-line dark:bg-surface-1">
                    <table class="w-full text-sm" data-test="cash-accounts-table">
                        <thead class="bg-secondary-soft/40 text-xs uppercase text-secondary-medium dark:bg-white/[0.04] dark:text-ink-muted">
                            <tr>
                                <th class="px-4 py-2 text-start font-medium">{{ __('projects.cash_account') }}</th>
                                <th class="px-4 py-2 text-end font-medium">{{ __('home.balance_syp') }}</th>
                                <th class="px-4 py-2 text-end font-medium">{{ __('projects.balance_syp_new') }}</th>
                                <th class="px-4 py-2 text-end font-medium">{{ __('home.balance_usd') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-secondary-soft dark:divide-line">
                            @forelse ($cashAccounts as $cash)
                                <tr wire:key="cash-{{ $cash->cashAccountId }}">
                                    <td class="px-4 py-2">{{ $cash->name }}</td>
                                    <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ number_format((float) $cash->syp, 2) }}</td>
                                    <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ number_format((float) $cash->sypNew, 2) }}</td>
                                    <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ number_format((float) $cash->usd, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-secondary-medium dark:text-ink-muted">{{ __('projects.none_money') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @elseif ($tab === 'accounts')
        <div class="overflow-x-auto rounded-xl border border-secondary-soft bg-white shadow-card dark:border-line dark:bg-surface-1" data-test="tab-accounts-content">
            <table class="w-full text-sm">
                <thead class="bg-secondary-soft/40 text-xs uppercase text-secondary-medium dark:bg-white/[0.04] dark:text-ink-muted">
                    <tr>
                        <th class="px-4 py-2 text-start font-medium">{{ __('projects.account') }}</th>
                        <th class="px-4 py-2 text-start font-medium">{{ __('projects.donor') }}</th>
                        <th class="px-4 py-2 text-start font-medium">{{ __('programs.status_label') }}</th>
                        @if ($showMoney)
                            <th class="px-4 py-2 text-end font-medium">{{ __('home.balance_syp') }}</th>
                            <th class="px-4 py-2 text-end font-medium">{{ __('home.balance_usd') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary-soft dark:divide-line">
                    @foreach ($siblings as $account)
                        <tr wire:key="account-{{ $account->accountId }}">
                            <td class="px-4 py-2">{{ $account->name }}</td>
                            <td class="px-4 py-2">{{ $account->donors }}</td>
                            <td class="px-4 py-2">{{ $account->closed ? __('projects.status.closed') : __('projects.status.active') }}</td>
                            @if ($showMoney)
                                @php($row = $siblingBalances->get($account->accountId))
                                <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ number_format((float) ($row?->syp ?? 0), 2) }}</td>
                                <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ number_format((float) ($row?->usd ?? 0), 2) }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif ($tab === 'budget')
        <div class="overflow-x-auto rounded-xl border border-secondary-soft bg-white shadow-card dark:border-line dark:bg-surface-1" data-test="tab-budget-content">
            <table class="w-full text-sm">
                <thead class="bg-secondary-soft/40 text-xs uppercase text-secondary-medium dark:bg-white/[0.04] dark:text-ink-muted">
                    <tr>
                        <th class="px-4 py-2 text-start font-medium">{{ __('projects.budget') }}</th>
                        <th class="px-4 py-2 text-start font-medium">{{ __('projects.line') }}</th>
                        <th class="px-4 py-2 text-end font-medium">{{ __('projects.unit') }}</th>
                        <th class="px-4 py-2 text-end font-medium">{{ __('projects.timing') }}</th>
                        @if ($showMoney)
                            <th class="px-4 py-2 text-end font-medium">{{ __('projects.price') }}</th>
                            <th class="px-4 py-2 text-end font-medium">{{ __('projects.total') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary-soft dark:divide-line">
                    @forelse ($budgetLines as $line)
                        <tr wire:key="line-{{ $loop->index }}">
                            <td class="px-4 py-2">{{ $line['budget'] }}</td>
                            <td class="px-4 py-2">{{ $line['line'] }}</td>
                            <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ $line['unit'] }}</td>
                            <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ $line['timing'] }}</td>
                            @if ($showMoney)
                                <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ number_format((float) $line['price'], 2) }}</td>
                                <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ number_format((float) $line['total'], 2) }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $showMoney ? 6 : 4 }}" class="px-4 py-6 text-center text-secondary-medium dark:text-ink-muted">{{ __('projects.none_budget') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-secondary-soft bg-white shadow-card dark:border-line dark:bg-surface-1" data-test="tab-lifetime-content">
            @if ($showMoney)
                <table class="w-full text-sm">
                    <thead class="bg-secondary-soft/40 text-xs uppercase text-secondary-medium dark:bg-white/[0.04] dark:text-ink-muted">
                        <tr>
                            <th class="px-4 py-2 text-start font-medium">{{ __('projects.month') }}</th>
                            <th class="px-4 py-2 text-start font-medium">{{ __('projects.currency') }}</th>
                            <th class="px-4 py-2 text-end font-medium">{{ __('projects.income') }}</th>
                            <th class="px-4 py-2 text-end font-medium">{{ __('projects.expenses') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary-soft dark:divide-line">
                        @forelse ($lifetime as $row)
                            <tr wire:key="life-{{ $loop->index }}">
                                <td class="px-4 py-2" dir="ltr">{{ $row['month'] }}</td>
                                <td class="px-4 py-2">{{ $row['currency'] }}</td>
                                <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ number_format((float) $row['income'], 2) }}</td>
                                <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ number_format((float) $row['expense'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-secondary-medium dark:text-ink-muted">{{ __('projects.none_lifetime') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <p class="px-4 py-6 text-center text-secondary-medium dark:text-ink-muted">{{ __('projects.money_hidden') }}</p>
            @endif
        </div>
    @endif
</div>
