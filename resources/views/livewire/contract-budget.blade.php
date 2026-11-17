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

    @if ($needsMatching)
        <div class="rounded-lg border border-status-warn bg-status-warn-soft px-4 py-2 text-sm text-status-warn" role="status" data-test="budget-needs-matching">{{ __('contracts.budget_match_after_approval') }}</div>
    @endif

    <x-ui.panel :title="__('contracts.budget_title')" :subtitle="__('contracts.budget_hint', ['currency' => $contract->currency])">
        @forelse ($sections as $section)
            <div class="border-b border-secondary-soft last:border-b-0 dark:border-line" wire:key="budget-{{ $section['share']->project_code }}" data-test="budget-{{ $section['share']->project_code }}">
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 pt-4">
                    <h3 class="text-sm font-semibold text-secondary-dark dark:text-ink"><span dir="ltr">{{ $section['share']->project_code }}</span> — {{ $section['share']->project?->name }}</h3>
                    <span class="inline-flex flex-wrap items-center gap-3 text-sm text-secondary-medium dark:text-ink-muted">
                        <span data-test="received-{{ $section['share']->project_code }}">{{ __('contracts.actual_received') }}: <x-money :amount="$section['received']" :currency="$contract->currency" /></span>
                        @if ($section['balanced'])
                            <flux:badge size="sm" color="green">{{ __('contracts.budget_balanced') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="amber">{{ __('contracts.budget_unbalanced') }}</flux:badge>
                        @endif
                    </span>
                </div>
                <div class="fin-table-wrap">
                    <table class="fin-table">
                        <thead>
                            <tr>
                                <th>{{ __('contracts.budget_line') }}</th>
                                <th>{{ __('contracts.budget_account') }}</th>
                                <th class="fin-num">{{ __('contracts.budget_amount') }} ({{ $contract->currency }})</th>
                                <th class="fin-num">{{ __('contracts.actual_spent') }}</th>
                                <th class="fin-num">{{ __('contracts.actual_remaining') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($section['lines'] as $line)
                                @php($lineSpent = $spent[$line->key] ?? '0.00')
                                <tr wire:key="line-{{ md5($line->key) }}">
                                    <td>{{ $line->name }}</td>
                                    <td><span dir="ltr">{{ $line->account_code }}</span> {{ app()->getLocale() === 'ar' ? $line->account?->name_ar : $line->account?->name_en }}</td>
                                    <td class="fin-num"><x-money :amount="$line->budget" /></td>
                                    <td class="fin-num" data-test="spent-{{ $line->key }}"><x-money :amount="$lineSpent" /></td>
                                    <td class="fin-num"><x-money :amount="bcsub((string) $line->budget, $lineSpent, 2)" /></td>
                                    <td>
                                        @if ($canChange)
                                            <x-ui.button size="sm" tone="ghost" icon="trash" wire:click="removeLine(@js($line->key))" wire:confirm="{{ __('contracts.remove_confirm') }}" :aria-label="__('contracts.remove')" />
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="fin-empty">{{ __('contracts.budget_no_lines') }}</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold">
                                <td colspan="2">{{ __('contracts.budget_total_of_share') }}</td>
                                <td class="fin-num"><x-money :amount="$section['total']" /> / <x-money :amount="$section['share']->amount" /></td>
                                <td class="fin-num"><x-money :amount="$section['spent']" /></td>
                                <td class="fin-num"><x-money :amount="$section['remaining']" /></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @empty
            <p class="p-4 text-sm text-secondary-medium dark:text-ink-muted">{{ __('contracts.budget_needs_project') }}</p>
        @endforelse

        @if ($sections->isNotEmpty())
            <p class="px-4 pb-4 text-xs text-secondary-medium dark:text-ink-muted" data-test="actuals-note">{{ __('contracts.actuals_note') }}</p>
        @endif

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

            @if ($importRows === [])
                <form wire:submit="readBudgetFile" class="flex flex-col gap-3 border-t border-secondary-soft p-4 dark:border-line" data-test="budget-import-form">
                    <div>
                        <h3 class="text-sm font-semibold text-secondary-dark dark:text-ink">{{ __('contracts.import_title') }}</h3>
                        <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('contracts.import_hint') }}</p>
                    </div>
                    <div class="flex flex-wrap items-end gap-3">
                        <x-ui.form-select wire:model="importProject" :label="__('contracts.project')" :placeholder="__('contracts.choose')" required class="min-w-48" data-test="import-project">
                            <option value="">{{ __('contracts.choose') }}</option>
                            @foreach ($sections as $section)
                                <option value="{{ $section['share']->project_code }}">{{ $section['share']->project_code }} — {{ $section['share']->project?->name }}</option>
                            @endforeach
                        </x-ui.form-select>
                        <div class="flex flex-col gap-1">
                            <input type="file" wire:model="budgetFile" accept=".xlsx" class="text-sm" data-test="import-file">
                            @error('budgetFile') <span class="text-xs text-status-critical">{{ $message }}</span> @enderror
                        </div>
                        <x-ui.button type="submit" icon="document-arrow-up" wire:loading.attr="disabled" wire:target="budgetFile,readBudgetFile" data-test="read-budget-file">{{ __('contracts.import_read') }}</x-ui.button>
                    </div>
                </form>
            @else
                <div class="flex flex-col gap-3 border-t border-secondary-soft p-4 dark:border-line" data-test="budget-import-preview">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-secondary-dark dark:text-ink">{{ __('contracts.import_preview', ['project' => $importProject]) }}</h3>
                        <span class="inline-flex flex-wrap items-center gap-2 text-sm">
                            <span>{{ __('contracts.import_total') }}: <x-money :amount="$importTotal" :currency="$contract->currency" /> / <x-money :amount="$importShare" /></span>
                            @if ($importMatches)
                                <flux:badge size="sm" color="green">{{ __('contracts.budget_balanced') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber">{{ __('contracts.budget_unbalanced') }}</flux:badge>
                            @endif
                        </span>
                    </div>
                    <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('contracts.import_check_hint') }}</p>
                    @if ($importOtherCurrency !== [])
                        <div class="rounded-lg border border-status-warn bg-status-warn-soft px-4 py-2 text-sm text-status-warn" role="status" data-test="import-currency-warning">{{ __('contracts.import_other_currency', ['found' => implode(', ', $importOtherCurrency), 'currency' => $contract->currency]) }}</div>
                    @endif
                    @if ($importUnassigned > 0)
                        <div class="rounded-lg border border-status-warn bg-status-warn-soft px-4 py-2 text-sm text-status-warn" role="status" data-test="import-unassigned">{{ trans_choice('contracts.import_unassigned', $importUnassigned, ['count' => $importUnassigned]) }}</div>
                    @endif
                    <div class="fin-table-wrap">
                        <table class="fin-table" data-test="import-table">
                            <thead>
                                <tr>
                                    <th>{{ __('contracts.import_include') }}</th>
                                    <th>{{ __('contracts.budget_line') }}</th>
                                    <th>{{ __('contracts.budget_account') }}</th>
                                    <th>{{ __('contracts.import_why') }}</th>
                                    <th class="fin-num">{{ __('contracts.budget_amount') }} ({{ $contract->currency }})</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($importRows as $i => $row)
                                    <tr wire:key="import-{{ $i }}">
                                        <td><flux:checkbox wire:model.live="importRows.{{ $i }}.include" :aria-label="__('contracts.import_include')" /></td>
                                        <td>
                                            <x-ui.input wire:model="importRows.{{ $i }}.name" maxlength="200" size="sm" :aria-label="__('contracts.budget_line')" class="min-w-48" />
                                            @if ($row['category'] !== '')
                                                <span class="text-xs text-secondary-medium dark:text-ink-muted">{{ $row['category'] }}</span>
                                            @endif
                                            @if ($row['note'] === 'duplicate')
                                                <span class="block text-xs text-status-warn">{{ __('contracts.import_duplicate_note') }}</span>
                                            @endif
                                            @error("importRows.$i.name") <span class="block text-xs text-status-critical">{{ $message }}</span> @enderror
                                        </td>
                                        <td>
                                            <div class="form-box">
                                                <x-ui.form-select bare wire:model.live="importRows.{{ $i }}.account" :label="__('contracts.budget_account')" data-test="import-account-{{ $i }}">
                                                    <option value="">{{ __('contracts.choose') }}</option>
                                                    @foreach ($accounts as $code => $label)
                                                        <option value="{{ $code }}">{{ $label }}</option>
                                                    @endforeach
                                                </x-ui.form-select>
                                            </div>
                                            @error("importRows.$i.account") <span class="text-xs text-status-critical">{{ $message }}</span> @enderror
                                        </td>
                                        <td>
                                            @if ($row['source'] === 'rule')
                                                <flux:badge size="sm" color="green">{{ __('contracts.import_source_rule') }}</flux:badge>
                                            @elseif ($row['source'] === 'learned')
                                                <flux:badge size="sm" color="green">{{ __('contracts.import_source_learned') }}</flux:badge>
                                            @elseif ($row['source'] === 'chart')
                                                <flux:badge size="sm" color="amber">{{ __('contracts.import_source_chart') }}</flux:badge>
                                            @else
                                                <flux:badge size="sm" color="zinc">{{ __('contracts.import_source_none') }}</flux:badge>
                                            @endif
                                        </td>
                                        <td class="fin-num">
                                            <x-ui.input type="number" step="0.01" min="0" wire:model.blur="importRows.{{ $i }}.amount" size="sm" dir="ltr" :aria-label="__('contracts.budget_amount')" class="w-32 tabular-nums" />
                                            @error("importRows.$i.amount") <span class="text-xs text-status-critical">{{ $message }}</span> @enderror
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button tone="primary" icon="check" wire:click="importBudget" wire:confirm="{{ __('contracts.import_confirm') }}" data-test="import-budget">{{ __('contracts.import_add_all') }}</x-ui.button>
                        <x-ui.button tone="ghost" wire:click="cancelImport" data-test="cancel-import">{{ __('contracts.cancel') }}</x-ui.button>
                    </div>
                </div>
            @endif
        @endif
    </x-ui.panel>

    <x-ui.panel :title="__('contracts.history_title')" :subtitle="__('contracts.history_hint')" :count="$history->count()" data-test="budget-history">
        <div class="fin-table-wrap">
            <table class="fin-table">
                <thead>
                    <tr>
                        <th>{{ __('contracts.history_when') }}</th>
                        <th>{{ __('contracts.history_who') }}</th>
                        <th>{{ __('contracts.history_what') }}</th>
                        <th>{{ __('contracts.project') }}</th>
                        <th>{{ __('contracts.budget_line') }}</th>
                        <th class="fin-num">{{ __('contracts.budget_amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $entry)
                        <tr wire:key="history-{{ $entry->id }}">
                            <td dir="ltr">{{ $entry->created_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ $historyPeople[$entry->changed_by] ?? '#'.$entry->changed_by }}</td>
                            <td>
                                {{ __('contracts.audit_actions.'.$entry->action) }}
                                @if (($entry->details['source'] ?? null) === 'excel')
                                    <span class="text-xs text-secondary-medium dark:text-ink-muted">({{ __('contracts.history_from_excel') }})</span>
                                @endif
                                @if ($entry->after_approval)
                                    <flux:badge size="sm" color="amber">{{ __('contracts.history_after_approval') }}</flux:badge>
                                @endif
                            </td>
                            <td dir="ltr">{{ $entry->project_code }}</td>
                            <td>
                                @if ($entry->action === \App\Models\ContractAudit::BUDGET_IMPORTED)
                                    {{ trans_choice('contracts.history_imported_lines', (int) ($entry->details['lines'] ?? 0), ['count' => (int) ($entry->details['lines'] ?? 0)]) }}
                                @else
                                    {{ $entry->details['name'] ?? '' }}
                                    @if (($entry->details['account'] ?? '') !== '')
                                        <span class="text-xs text-secondary-medium dark:text-ink-muted" dir="ltr">({{ $entry->details['account'] }})</span>
                                    @endif
                                @endif
                            </td>
                            <td class="fin-num">
                                @if (isset($entry->details['amount']) || isset($entry->details['total']))
                                    <x-money :amount="$entry->details['amount'] ?? $entry->details['total']" :currency="$entry->details['currency'] ?? null" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="fin-empty">{{ __('contracts.history_empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.panel>
</div>
