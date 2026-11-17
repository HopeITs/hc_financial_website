<div class="flex flex-col gap-4" data-test="contract-budget">
    @if ($notice)
        <div class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" role="status" data-test="budget-notice">{{ $notice }}</div>
    @endif

    @if ($problems !== [])
        <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="budget-problems">
            @foreach ($problems as $problem)
                <p>{{ $problem }}</p>
            @endforeach
        </div>
    @endif

    <x-ui.panel :title="__('contracts.budget_title')" :subtitle="__('contracts.budget_hint', ['currency' => $contract->currency])">
        @forelse ($sections as $section)
            <div class="border-b border-secondary-soft last:border-b-0 dark:border-line" wire:key="budget-{{ $section['share']->project_code }}" data-test="budget-{{ $section['share']->project_code }}">
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 pt-4">
                    <h3 class="text-sm font-semibold text-secondary-dark dark:text-ink"><span dir="ltr">{{ $section['share']->project_code }}</span> — {{ $section['share']->project?->name }}</h3>
                    @if ($section['balanced'])
                        <flux:badge size="sm" color="green">{{ __('contracts.budget_balanced') }}</flux:badge>
                    @else
                        <flux:badge size="sm" color="amber">{{ __('contracts.budget_unbalanced') }}</flux:badge>
                    @endif
                </div>
                <div class="fin-table-wrap">
                    <table class="fin-table">
                        <thead>
                            <tr>
                                <th>{{ __('contracts.budget_line') }}</th>
                                <th>{{ __('contracts.budget_account') }}</th>
                                <th class="fin-num">{{ __('contracts.budget_amount') }} ({{ $contract->currency }})</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($section['lines'] as $line)
                                <tr wire:key="line-{{ $line->key }}">
                                    <td>{{ $line->name }}</td>
                                    <td><span dir="ltr">{{ $line->account_code }}</span> {{ app()->getLocale() === 'ar' ? $line->account?->name_ar : $line->account?->name_en }}</td>
                                    <td class="fin-num"><x-money :amount="$line->budget" /></td>
                                    <td>
                                        @if ($canChange)
                                            <x-ui.button size="sm" tone="ghost" icon="trash" wire:click="removeLine(@js($line->key))" wire:confirm="{{ __('contracts.remove_confirm') }}" :aria-label="__('contracts.remove')" />
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="fin-empty">{{ __('contracts.budget_no_lines') }}</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold">
                                <td colspan="2">{{ __('contracts.budget_total_of_share') }}</td>
                                <td class="fin-num"><x-money :amount="$section['total']" /> / <x-money :amount="$section['share']->amount" /></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @empty
            <p class="p-4 text-sm text-secondary-medium dark:text-ink-muted">{{ __('contracts.budget_needs_project') }}</p>
        @endforelse

        @if ($canChange && $sections->isNotEmpty())
            <form wire:submit="addLine" class="flex flex-wrap items-end gap-3 border-t border-secondary-soft p-4 dark:border-line" data-test="budget-form">
                <x-ui.form-select wire:model="lineProject" :label="__('contracts.project')" :placeholder="__('contracts.choose')" required class="min-w-48" data-test="budget-project">
                    <option value="">{{ __('contracts.choose') }}</option>
                    @foreach ($sections as $section)
                        <option value="{{ $section['share']->project_code }}">{{ $section['share']->project_code }} — {{ $section['share']->project?->name }}</option>
                    @endforeach
                </x-ui.form-select>
                <x-ui.form-input wire:model="lineName" :label="__('contracts.budget_line')" maxlength="200" required class="min-w-56 flex-1" data-test="budget-name" />
                <x-ui.form-select wire:model="lineAccount" :label="__('contracts.budget_account')" :placeholder="__('contracts.choose')" required searchable class="min-w-64" data-test="budget-account">
                    <option value="">{{ __('contracts.choose') }}</option>
                    @foreach ($accounts as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </x-ui.form-select>
                <x-ui.form-input wire:model="lineAmount" type="number" step="0.01" min="0" :label="__('contracts.budget_amount').' ('.$contract->currency.')'" dir="ltr" required data-test="budget-amount" />
                <x-ui.button type="submit" icon="plus" data-test="add-budget-line">{{ __('contracts.budget_add_line') }}</x-ui.button>
            </form>
        @endif
    </x-ui.panel>
</div>
