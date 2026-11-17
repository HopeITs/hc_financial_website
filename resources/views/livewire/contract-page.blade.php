<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="$contract->title" :subtitle="__('contracts.page_subtitle', ['number' => $contract->number, 'donor' => $contract->donor->localizedName()])">
        <x-slot:actions>
            <x-contract-status :contract="$contract" />
            <x-ui.button :href="route('funding.agreements')" wire:navigate icon="arrow-uturn-left">{{ __('contracts.back') }}</x-ui.button>
            @if ($canApprove)
                <x-ui.button tone="primary" icon="check" wire:click="approve" wire:confirm="{{ __('contracts.approve_confirm') }}" data-test="approve-contract">{{ __('contracts.approve') }}</x-ui.button>
            @endif
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

    @if (! $contract->isApproved() && $contract->isOpen() && $approvalProblems !== [])
        <div class="rounded-lg border border-status-warn bg-status-warn-soft px-4 py-2 text-sm text-status-warn" data-test="approval-checklist">
            <p class="font-semibold">{{ __('contracts.before_approval') }}</p>
            <ul class="list-disc ps-5">
                @foreach ($approvalProblems as $problem)
                    <li>{{ $problem }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-tile :label="__('contracts.kpi_value')" :value="$kpi['value']" :unit="$contract->currency" icon="document-text" />
        <x-ui.stat-tile :label="__('contracts.kpi_value_usd')" :value="$kpi['value_usd']" unit="USD" icon="currency-dollar" />
        <x-ui.stat-tile :label="__('contracts.kpi_income')" :value="$kpi['income']" :unit="$contract->currency" icon="banknotes" />
        <x-ui.stat-tile :label="__('contracts.kpi_late')" :value="(string) $lateCount" :tone="$lateCount > 0 ? 'critical' : 'good'" icon="clock" />
    </div>

    <x-ui.view-toggle wire-model="tab" :active="$tab" :options="$tabs" :label="__('contracts.tabs')" class="max-w-full overflow-x-auto" />

    @if ($tab === 'details')
        @if ($editingDetails)
            <form wire:submit="saveDetails" data-test="details-form">
                <x-ui.panel :title="__('contracts.edit_details')" class="p-0">
                    <div class="flex flex-col gap-4 p-4">
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <x-ui.form-input wire:model="title" :label="__('contracts.contract_title')" required />
                            <x-ui.form-input wire:model="donorReference" :label="__('contracts.donor_reference')" dir="ltr" />
                            <x-ui.form-select wire:model="status" :label="__('contracts.status')" required data-test="details-status">
                                @foreach ($statuses as $option)
                                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                @endforeach
                            </x-ui.form-select>
                            <x-ui.form-input wire:model="signedOn" type="date" :label="__('contracts.signed_on')" />
                            <x-ui.form-input wire:model="startsOn" type="date" :label="__('contracts.starts_on')" required />
                            <x-ui.form-input wire:model="endsOn" type="date" :label="__('contracts.ends_on')" required />
                            @if ($canChangeMoney)
                                <x-ui.form-input wire:model="amount" type="number" step="0.01" min="0" :label="__('contracts.amount').' ('.$contract->currency.')'" dir="ltr" required data-test="details-amount" />
                                @if ($contract->currency !== 'USD')
                                    <x-ui.form-input wire:model="fixedRate" type="number" step="0.000001" min="0" :label="__('contracts.fixed_rate')" :help="__('contracts.fixed_rate_help')" dir="ltr" required />
                                @endif
                            @endif
                            <x-ui.form-select wire:model="restriction" :label="__('contracts.restriction')" required>
                                @foreach ($restrictions as $option)
                                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                @endforeach
                            </x-ui.form-select>
                        </div>
                        @if (! $canChangeMoney)
                            <p class="text-xs text-secondary-medium dark:text-ink-muted">{{ __('contracts.money_locked') }}</p>
                        @endif
                        <x-ui.form-textarea wire:model="notes" :label="__('contracts.notes')" rows="2" />
                        <div class="flex flex-col gap-1">
                            <span class="text-sm font-medium text-secondary-dark dark:text-ink">{{ __('contracts.signed_file') }}</span>
                            <input type="file" wire:model="signedFile" accept=".pdf,.jpg,.jpeg,.png" class="text-sm" data-test="signed-file">
                            @error('signedFile') <span class="text-xs text-status-critical">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex gap-2">
                            <x-ui.button type="submit" tone="primary" data-test="save-details">{{ __('contracts.save') }}</x-ui.button>
                            <x-ui.button type="button" wire:click="cancelDetails">{{ __('contracts.cancel') }}</x-ui.button>
                        </div>
                    </div>
                </x-ui.panel>
            </form>
        @else
            <x-ui.panel :title="__('contracts.tab_details')">
                <x-slot:toolbar>
                    @if ($canChange)
                        <x-ui.button size="sm" icon="pencil" wire:click="editDetails" data-test="edit-details">{{ __('contracts.edit') }}</x-ui.button>
                    @endif
                </x-slot:toolbar>
                <dl class="grid gap-x-6 gap-y-3 p-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('contracts.number') }}</dt><dd dir="ltr" class="text-start">{{ $contract->number }}</dd></div>
                    <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('contracts.donor_reference') }}</dt><dd dir="ltr" class="text-start">{{ $contract->donor_reference ?? '—' }}</dd></div>
                    <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('contracts.donor') }}</dt><dd>{{ $contract->donor->localizedName() }} <span dir="ltr">({{ $contract->donor->code }})</span></dd></div>
                    <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('contracts.signed_on') }}</dt><dd dir="ltr" class="text-start">{{ $contract->signed_on?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('contracts.period') }}</dt><dd dir="ltr" class="text-start">{{ $contract->starts_on->format('d/m/Y') }} – {{ $contract->ends_on->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('contracts.amount') }}</dt><dd><x-money :amount="$contract->amount" :currency="$contract->currency" /></dd></div>
                    <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('contracts.fixed_rate') }}</dt><dd dir="ltr" class="text-start">{{ $contract->currency === 'USD' ? '—' : rtrim(rtrim((string) $contract->fixed_rate, '0'), '.') }}</dd></div>
                    <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('contracts.restriction') }}</dt><dd>{{ $contract->restriction->label() }}@if ($contract->is_yearly_general) · {{ __('contracts.yearly_general_short') }}@endif</dd></div>
                    <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('contracts.signed_file') }}</dt><dd>
                        @if ($contract->signed_file_path)
                            <a href="{{ route('funding.agreements.file', ['contract' => $contract->id, 'kind' => 'signed']) }}" class="underline" data-test="signed-file-link">{{ $contract->signed_file_name }}</a>
                        @else
                            —
                        @endif
                    </dd></div>
                    <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('contracts.prepared_by') }}</dt><dd>{{ $people[$contract->created_by] ?? '—' }}</dd></div>
                    <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('contracts.approved_by') }}</dt><dd>
                        @if ($contract->isApproved())
                            {{ $people[$contract->approved_by] ?? '—' }} · <span dir="ltr">{{ $contract->approved_at->format('d/m/Y') }}</span>
                        @else
                            <flux:badge size="sm" color="amber">{{ __('contracts.waiting_approval') }}</flux:badge>
                        @endif
                    </dd></div>
                    @if ($contract->notes)
                        <div class="sm:col-span-2 lg:col-span-3"><dt class="text-secondary-medium dark:text-ink-muted">{{ __('contracts.notes') }}</dt><dd class="whitespace-pre-line">{{ $contract->notes }}</dd></div>
                    @endif
                </dl>
            </x-ui.panel>
        @endif
    @endif

    @if ($tab === 'projects')
        <x-ui.panel :title="__('contracts.tab_projects')" :subtitle="__('contracts.projects_hint')">
            <x-slot:toolbar>
                @if ($sharesBalanced)
                    <flux:badge size="sm" color="green" data-test="shares-balanced">{{ __('contracts.shares_balanced') }}</flux:badge>
                @else
                    <flux:badge size="sm" color="amber" data-test="shares-unbalanced">{{ __('contracts.shares_unbalanced') }}</flux:badge>
                @endif
            </x-slot:toolbar>
            <div class="fin-table-wrap">
                <table class="fin-table" data-test="shares-table">
                    <thead>
                        <tr>
                            <th>{{ __('contracts.project') }}</th>
                            <th class="fin-num">{{ __('contracts.share') }} ({{ $contract->currency }})</th>
                            <th class="fin-num">{{ __('contracts.share_percent') }}</th>
                            <th class="fin-num">{{ __('contracts.share_usd') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($contract->projects as $share)
                            <tr wire:key="share-{{ $share->id }}">
                                <td><span dir="ltr">{{ $share->project_code }}</span> — {{ $share->project?->name }}</td>
                                <td class="fin-num"><x-money :amount="$share->amount" /></td>
                                <td class="fin-num" dir="ltr">{{ $sharePercent[$share->id] }}%</td>
                                <td class="fin-num"><x-money :amount="$shareUsd[$share->id]" /></td>
                                <td>
                                    @if ($canChangeMoney)
                                        <x-ui.button size="sm" tone="ghost" icon="trash" wire:click="removeShare({{ $share->id }})" wire:confirm="{{ __('contracts.remove_confirm') }}" :aria-label="__('contracts.remove')" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="fin-empty">{{ __('contracts.no_projects') }}</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="font-semibold">
                            <td>{{ __('contracts.total') }}</td>
                            <td class="fin-num"><x-money :amount="$sharesTotal" /></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @if ($canChangeMoney)
                <form wire:submit="addShare" class="flex flex-wrap items-end gap-3 border-t border-secondary-soft p-4 dark:border-line" data-test="share-form">
                    <x-ui.form-select wire:model="shareProject" :label="__('contracts.project')" :placeholder="__('contracts.choose')" required class="min-w-64" data-test="share-project">
                        <option value="">{{ __('contracts.choose') }}</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->code }}">{{ $project->code }} — {{ $project->name }}</option>
                        @endforeach
                    </x-ui.form-select>
                    <x-ui.form-input wire:model="shareAmount" type="number" step="0.01" min="0" :label="__('contracts.share').' ('.$contract->currency.')'" dir="ltr" required data-test="share-amount" />
                    <x-ui.button type="submit" icon="plus" data-test="add-share">{{ __('contracts.add_share') }}</x-ui.button>
                </form>
            @endif
        </x-ui.panel>
    @endif

    @if ($tab === 'instalments')
        @if ($lateCount > 0)
            <div class="rounded-lg border border-status-warn bg-status-warn-soft px-4 py-2 text-sm text-status-warn" role="status" data-test="late-warning">{{ trans_choice('contracts.late_warning', $lateCount, ['count' => $lateCount]) }}</div>
        @endif
        <x-ui.panel :title="__('contracts.tab_instalments')" :subtitle="__('contracts.instalments_hint')">
            <x-slot:toolbar>
                @if ($instalmentsBalanced)
                    <flux:badge size="sm" color="green">{{ __('contracts.instalments_balanced') }}</flux:badge>
                @else
                    <flux:badge size="sm" color="amber">{{ __('contracts.instalments_unbalanced') }}</flux:badge>
                @endif
            </x-slot:toolbar>
            <div class="fin-table-wrap">
                <table class="fin-table" data-test="instalments-table">
                    <thead>
                        <tr>
                            <th class="fin-num">#</th>
                            <th>{{ __('contracts.due_on') }}</th>
                            <th class="fin-num">{{ __('contracts.amount') }} ({{ $contract->currency }})</th>
                            <th>{{ __('contracts.condition') }}</th>
                            <th>{{ __('contracts.income') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($contract->instalments as $instalment)
                            <tr wire:key="instalment-{{ $instalment->id }}">
                                <td class="fin-num">{{ $instalment->number }}</td>
                                <td dir="ltr" class="text-start">
                                    {{ $instalment->due_on->format('d/m/Y') }}
                                    @if (! $instalment->isRecognised() && $instalment->daysLate($today) > 0)
                                        <flux:badge size="sm" color="red" class="ms-1">{{ trans_choice('contracts.days_late', $instalment->daysLate($today), ['days' => $instalment->daysLate($today)]) }}</flux:badge>
                                    @endif
                                </td>
                                <td class="fin-num"><x-money :amount="$instalment->amount" /></td>
                                <td>{{ $instalment->is_conditional ? $instalment->condition : __('contracts.unconditional') }}</td>
                                <td>
                                    @if (! $instalment->is_conditional)
                                        <flux:badge size="sm" color="green">{{ __('contracts.income_at_signing') }}</flux:badge>
                                    @elseif ($instalment->condition_met_on)
                                        <flux:badge size="sm" color="green">{{ __('contracts.income_condition_met', ['date' => $instalment->condition_met_on->format('d/m/Y')]) }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc">{{ __('contracts.income_waiting') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">
                                    @if ($canChange && $contract->isApproved() && $instalment->is_conditional && ! $instalment->condition_met_on)
                                        <x-ui.button size="sm" wire:click="markConditionMet({{ $instalment->id }})" wire:confirm="{{ __('contracts.condition_met_confirm') }}" data-test="condition-met-{{ $instalment->number }}">{{ __('contracts.mark_condition_met') }}</x-ui.button>
                                    @endif
                                    @if ($canChangeMoney)
                                        <x-ui.button size="sm" tone="ghost" icon="trash" wire:click="removeInstalment({{ $instalment->id }})" wire:confirm="{{ __('contracts.remove_confirm') }}" :aria-label="__('contracts.remove')" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="fin-empty">{{ __('contracts.no_instalments') }}</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="font-semibold">
                            <td></td>
                            <td>{{ __('contracts.total') }}</td>
                            <td class="fin-num"><x-money :amount="$instalmentsTotal" /></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @if ($canChangeMoney)
                <form wire:submit="addInstalment" class="flex flex-col gap-3 border-t border-secondary-soft p-4 dark:border-line" data-test="instalment-form">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <x-ui.form-input wire:model="instalmentDue" type="date" :label="__('contracts.due_on')" required data-test="instalment-due" />
                        <x-ui.form-input wire:model="instalmentAmount" type="number" step="0.01" min="0" :label="__('contracts.amount').' ('.$contract->currency.')'" dir="ltr" required data-test="instalment-amount" />
                        <x-ui.form-input wire:model="instalmentCondition" :label="__('contracts.condition')" :help="__('contracts.condition_help')" data-test="instalment-condition" />
                    </div>
                    <flux:checkbox wire:model="instalmentConditional" :label="__('contracts.conditional')" :description="__('contracts.conditional_help')" data-test="instalment-conditional" />
                    <div><x-ui.button type="submit" icon="plus" data-test="add-instalment">{{ __('contracts.add_instalment') }}</x-ui.button></div>
                </form>
            @endif
        </x-ui.panel>
        <p class="text-xs text-secondary-medium dark:text-ink-muted">{{ __('contracts.instalments_note') }}</p>
    @endif

    @if ($tab === 'reports')
        <x-ui.panel :title="__('contracts.tab_reports')" :subtitle="__('contracts.reports_hint', ['days' => $reminderDays])">
            <div class="fin-table-wrap">
                <table class="fin-table" data-test="reports-table">
                    <thead>
                        <tr>
                            <th>{{ __('contracts.report_kind') }}</th>
                            <th>{{ __('contracts.report_period') }}</th>
                            <th>{{ __('contracts.due_on') }}</th>
                            <th>{{ __('contracts.report_status') }}</th>
                            <th>{{ __('contracts.report_file') }}</th>
                            <th>{{ __('contracts.report_instalment') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($contract->reports as $report)
                            <tr wire:key="report-{{ $report->id }}">
                                <td>{{ $report->kind->label() }}</td>
                                <td dir="ltr" class="text-start">{{ $report->period_from->format('d/m/Y') }} – {{ $report->period_to->format('d/m/Y') }}</td>
                                <td dir="ltr" class="text-start">
                                    {{ $report->due_on->format('d/m/Y') }}
                                    @if ($report->status === \App\Enums\ContractReportStatus::NotSent && $report->due_on->lt($today))
                                        <flux:badge size="sm" color="red" class="ms-1">{{ __('contracts.overdue') }}</flux:badge>
                                    @elseif ($report->status === \App\Enums\ContractReportStatus::NotSent && $report->due_on->lte($today->addDays($reminderDays)))
                                        <flux:badge size="sm" color="amber" class="ms-1">{{ __('contracts.due_soon') }}</flux:badge>
                                    @endif
                                </td>
                                <td>
                                    @if ($canChange)
                                        <flux:dropdown>
                                            <x-ui.button size="sm" icon-trailing="chevron-down" data-test="report-status-{{ $report->id }}">{{ $report->status->label() }}</x-ui.button>
                                            <flux:menu>
                                                @foreach ($reportStatuses as $option)
                                                    <flux:menu.item wire:click="setReportStatus({{ $report->id }}, '{{ $option->value }}')" data-test="report-{{ $report->id }}-{{ $option->value }}">{{ $option->label() }}</flux:menu.item>
                                                @endforeach
                                            </flux:menu>
                                        </flux:dropdown>
                                    @else
                                        {{ $report->status->label() }}
                                    @endif
                                </td>
                                <td>
                                    @if ($report->file_path)
                                        <a href="{{ route('funding.agreements.file', ['contract' => $contract->id, 'kind' => 'report', 'report' => $report->id]) }}" class="underline">{{ $report->file_name }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $report->instalment ? __('contracts.instalment_number', ['number' => $report->instalment->number]) : '—' }}</td>
                                <td>
                                    @if ($canChange)
                                        <x-ui.button size="sm" tone="ghost" icon="trash" wire:click="removeReport({{ $report->id }})" wire:confirm="{{ __('contracts.remove_confirm') }}" :aria-label="__('contracts.remove')" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="fin-empty">{{ __('contracts.no_reports') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($canChange)
                <form wire:submit="addReport" class="flex flex-col gap-3 border-t border-secondary-soft p-4 dark:border-line" data-test="report-form">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <x-ui.form-select wire:model="reportKind" :label="__('contracts.report_kind')" required>
                            @foreach ($reportKinds as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </x-ui.form-select>
                        <x-ui.form-input wire:model="reportFrom" type="date" :label="__('contracts.report_from')" required data-test="report-from" />
                        <x-ui.form-input wire:model="reportTo" type="date" :label="__('contracts.report_to')" required data-test="report-to" />
                        <x-ui.form-input wire:model="reportDue" type="date" :label="__('contracts.due_on')" required data-test="report-due" />
                        <x-ui.form-select wire:model="reportInstalment" :label="__('contracts.report_instalment')">
                            <option value="">—</option>
                            @foreach ($contract->instalments as $instalment)
                                <option value="{{ $instalment->id }}">{{ __('contracts.instalment_number', ['number' => $instalment->number]) }}</option>
                            @endforeach
                        </x-ui.form-select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="text-sm font-medium text-secondary-dark dark:text-ink">{{ __('contracts.report_file') }}</span>
                        <input type="file" wire:model="reportFile" accept=".pdf,.jpg,.jpeg,.png" class="text-sm">
                        @error('reportFile') <span class="text-xs text-status-critical">{{ $message }}</span> @enderror
                    </div>
                    <div><x-ui.button type="submit" icon="plus" data-test="add-report">{{ __('contracts.add_report') }}</x-ui.button></div>
                </form>
            @endif
        </x-ui.panel>
    @endif

    @if ($tab === 'closing')
        <x-ui.panel :title="__('contracts.tab_closing')" :subtitle="__('contracts.closing_hint')">
            <div class="flex flex-col gap-4 p-4 text-sm">
                @if ($contract->status === \App\Enums\ContractStatus::Ended)
                    <p data-test="closed">{{ __('contracts.closed_on', ['date' => $contract->closing_gm_at?->format('d/m/Y')]) }}</p>
                @endif

                @if ($contract->closing_requested_at)
                    <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                        <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('contracts.closing_outcome') }}</dt><dd>{{ $contract->closing_outcome?->label() }}</dd></div>
                        <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('contracts.closing_file') }}</dt><dd>
                            @if ($contract->closing_file_path)
                                <a href="{{ route('funding.agreements.file', ['contract' => $contract->id, 'kind' => 'closing']) }}" class="underline">{{ $contract->closing_file_name }}</a>
                            @endif
                        </dd></div>
                        <div class="sm:col-span-2"><dt class="text-secondary-medium dark:text-ink-muted">{{ __('contracts.closing_note') }}</dt><dd class="whitespace-pre-line">{{ $contract->closing_note }}</dd></div>
                        <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('contracts.closing_requested_by') }}</dt><dd>{{ $people[$contract->closing_requested_by] ?? '—' }} · <span dir="ltr">{{ $contract->closing_requested_at->format('d/m/Y') }}</span></dd></div>
                        <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('contracts.closing_fm') }}</dt><dd>
                            @if ($contract->closing_fm_at)
                                {{ $people[$contract->closing_fm_by] ?? '—' }} · <span dir="ltr">{{ $contract->closing_fm_at->format('d/m/Y') }}</span>
                            @else
                                <flux:badge size="sm" color="amber">{{ __('contracts.waiting') }}</flux:badge>
                            @endif
                        </dd></div>
                        <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('contracts.closing_gm') }}</dt><dd>
                            @if ($contract->closing_gm_at)
                                {{ $people[$contract->closing_gm_by] ?? '—' }} · <span dir="ltr">{{ $contract->closing_gm_at->format('d/m/Y') }}</span>
                            @else
                                <flux:badge size="sm" color="amber">{{ __('contracts.waiting') }}</flux:badge>
                            @endif
                        </dd></div>
                    </dl>
                    @if ($canApproveClosing)
                        <div><x-ui.button tone="primary" icon="check" wire:click="approveClosing" wire:confirm="{{ __('contracts.closing_approve_confirm') }}" data-test="approve-closing">{{ $nextClosingStep === \App\Enums\RoleId::FinancialManager ? __('contracts.closing_approve_fm') : __('contracts.closing_approve_gm') }}</x-ui.button></div>
                    @endif
                @elseif ($canRequestClosing)
                    <form wire:submit="requestClosing" class="flex flex-col gap-4" data-test="closing-form">
                        <x-ui.form-select wire:model="closingOutcome" :label="__('contracts.closing_outcome')" :placeholder="__('contracts.choose')" required data-test="closing-outcome">
                            <option value="">{{ __('contracts.choose') }}</option>
                            @foreach ($outcomes as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </x-ui.form-select>
                        <x-ui.form-textarea wire:model="closingNote" :label="__('contracts.closing_note')" rows="2" required data-test="closing-note" />
                        <div class="flex flex-col gap-1">
                            <span class="text-sm font-medium text-secondary-dark dark:text-ink">{{ __('contracts.closing_file') }} <span class="text-status-critical">*</span></span>
                            <input type="file" wire:model="closingFile" accept=".pdf,.jpg,.jpeg,.png" class="text-sm" data-test="closing-file">
                            @error('closingFile') <span class="text-xs text-status-critical">{{ $message }}</span> @enderror
                        </div>
                        <div><x-ui.button type="submit" tone="primary" data-test="request-closing">{{ __('contracts.request_closing') }}</x-ui.button></div>
                    </form>
                @elseif (! $contract->isApproved())
                    <p>{{ __('contracts.closing_after_approval') }}</p>
                @endif
                <p class="text-xs text-secondary-medium dark:text-ink-muted">{{ __('contracts.closing_note_balance') }}</p>
            </div>
        </x-ui.panel>
    @endif

    <p class="text-xs text-secondary-medium dark:text-ink-muted">{{ __('contracts.next_step_note') }}</p>
</div>
