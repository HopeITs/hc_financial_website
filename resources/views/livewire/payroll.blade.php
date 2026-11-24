@php
    $cell = 'w-full min-w-16 rounded-md border border-secondary-light bg-white px-2 py-1.5 text-sm text-secondary-dark disabled:opacity-60 dark:border-field-line dark:bg-surface-2 dark:text-ink';
    $draft = $current?->status === \App\Models\Payroll::DRAFT;
@endphp

<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="payroll">
    <x-ui.page-header :title="__('navigation.screens.payroll')" :subtitle="__('payroll.intro')" />

    <div class="flex gap-2" role="tablist">
        @foreach (['payrolls', 'staff'] as $name)
            <x-ui.button size="sm" :tone="$tab === $name ? 'primary' : 'secondary'" wire:click="$set('tab', '{{ $name }}')" role="tab" :aria-selected="$tab === $name ? 'true' : 'false'" data-test="tab-{{ $name }}">{{ __('payroll.tabs.'.$name) }}</x-ui.button>
        @endforeach
        {{-- The salary split is the payroll's third tab, on its own screen (finance decision of 2026-11-25). --}}
        @can('screen', ['salaries', 'view'])
            <x-ui.button size="sm" tone="secondary" :href="route(\App\Support\Navigation::routeName('salaries'))" wire:navigate data-test="tab-split">{{ __('navigation.screens.salaries') }}</x-ui.button>
        @endcan
    </div>

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

    @if ($tab === 'staff')
        {{-- The staff register --}}
        @if ($staffEditing !== '')
            <x-ui.panel :title="$staffEditing === 'new' ? __('payroll.staff_new') : __('payroll.staff_edit')" data-test="staff-form">
                <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.form-input type="number" min="1" wire:model="staff.employee_id" :label="__('payroll.employee_id')" :help="__('payroll.employee_id_help')" required data-test="s-employee-id" />
                    <x-ui.form-input wire:model="staff.name" :label="__('payroll.name')" required data-test="s-name" />
                    <x-ui.form-input wire:model="staff.position" :label="__('payroll.position')" data-test="s-position" />
                    <x-ui.form-select wire:model="staff.project" :label="__('payroll.project')" required data-test="s-project">
                        <option value="">{{ __('payroll.choose') }}</option>
                        @foreach ($projects as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </x-ui.form-select>
                    <x-ui.form-input wire:model="staff.basic_pay" inputmode="decimal" dir="ltr" :label="__('payroll.basic_pay')" required data-test="s-basic-pay" />
                    <x-ui.form-select wire:model="staff.currency" :label="__('payroll.contract_currency')" data-test="s-currency">
                        @foreach (\App\Services\Payroll\Payrolls::CURRENCIES as $code)
                            <option value="{{ $code }}">{{ $code }}</option>
                        @endforeach
                    </x-ui.form-select>
                    <x-ui.form-select wire:model="staff.active" :label="__('payroll.active')">
                        <option value="1">{{ __('payroll.yes') }}</option>
                        <option value="0">{{ __('payroll.no') }}</option>
                    </x-ui.form-select>
                    <div class="flex items-end gap-3">
                        <x-ui.button tone="primary" icon="check" wire:click="saveStaff" data-test="save-staff">{{ __('payroll.save') }}</x-ui.button>
                        <x-ui.button tone="ghost" wire:click="cancelStaff">{{ __('payroll.cancel') }}</x-ui.button>
                    </div>
                </div>
            </x-ui.panel>
        @endif

        <x-ui.panel :title="__('payroll.staff_title')" :subtitle="__('payroll.staff_hint')" :count="$members->count()">
            <x-slot:toolbar>
                <livewire:excel-tools kind="staff" :key="'excel-staff'" />
                @if ($canCreate)
                    <x-ui.button size="sm" icon="plus" wire:click="newStaff" data-test="new-staff">{{ __('payroll.staff_new') }}</x-ui.button>
                @endif
            </x-slot:toolbar>
            <x-ui.scroll-table :paginate="false">
                <table class="fin-table" data-test="staff-table">
                    <thead>
                        <tr>
                            <th>{{ __('payroll.employee_id') }}</th>
                            <th>{{ __('payroll.name') }}</th>
                            <th>{{ __('payroll.position') }}</th>
                            <th>{{ __('payroll.project') }}</th>
                            <th class="fin-num">{{ __('payroll.basic_pay') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($members as $member)
                            <tr wire:key="staff-{{ $member->id }}" @class(['opacity-60' => ! $member->active])>
                                <td dir="ltr" class="text-start">{{ $member->employee_id }}</td>
                                <td class="font-medium">{{ $member->name }}</td>
                                <td>{{ $member->position }}</td>
                                <td>{{ $member->project_code }}</td>
                                <td class="fin-num"><x-money :amount="$member->basic_pay" :currency="$member->currency" /></td>
                                <td>
                                    @if ($canEdit)
                                        <x-ui.button size="sm" tone="ghost" icon="pencil-square" wire:click="editStaff({{ $member->id }})" :aria-label="__('payroll.staff_edit')" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-ui.empty-state icon="user-group" :heading="__('payroll.staff_none')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.scroll-table>
        </x-ui.panel>
    @else
        {{-- The payrolls --}}
        @if ($canCreate)
            <x-ui.panel :title="__('payroll.new_title')" :subtitle="__('payroll.new_hint')">
                <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4" data-test="new-payroll">
                    <div class="sm:col-span-2">
                        <x-ui.form-select wire:model="start.project" :label="__('payroll.project')" required data-test="n-project">
                            <option value="">{{ __('payroll.choose') }}</option>
                            @foreach ($projects as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.form-select>
                    </div>
                    <x-ui.form-input type="month" wire:model="start.month" :label="__('payroll.month')" required data-test="n-month" />
                    <x-ui.form-select wire:model="start.currency" :label="__('payroll.pay_currency')" :help="__('payroll.pay_currency_help')" data-test="n-currency">
                        @foreach (\App\Services\Payroll\Payrolls::CURRENCIES as $code)
                            <option value="{{ $code }}">{{ $code }}</option>
                        @endforeach
                    </x-ui.form-select>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <x-ui.button tone="primary" icon="plus" wire:click="openPayroll" data-test="open-payroll">{{ __('payroll.open') }}</x-ui.button>
                    </div>
                </div>
            </x-ui.panel>
        @endif

        <x-ui.panel :title="__('payroll.list_title')" :count="$payrolls->count()">
            <x-ui.scroll-table :paginate="false">
                <table class="fin-table" data-test="payrolls-table">
                    <thead>
                        <tr>
                            <th>{{ __('payroll.month') }}</th>
                            <th>{{ __('payroll.project') }}</th>
                            <th>{{ __('payroll.pay_currency') }}</th>
                            <th>{{ __('payroll.status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payrolls as $row)
                            <tr wire:key="payroll-{{ $row->id }}" @class(['bg-primary-soft/40' => $current?->id === $row->id])>
                                <td dir="ltr" class="text-start">{{ $row->month->format('m/Y') }}</td>
                                <td>{{ $row->project_code }} · {{ $row->project?->name }}</td>
                                <td>{{ $row->pay_currency }}</td>
                                <td><x-ui.status-badge :status="$row->status" :label="__('payroll.statuses.'.$row->status)" /></td>
                                <td><x-ui.button size="sm" tone="ghost" icon="folder-open" wire:click="select({{ $row->id }})" data-test="open-{{ $row->id }}">{{ __('payroll.show') }}</x-ui.button></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-ui.empty-state icon="banknotes" :heading="__('payroll.none')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.scroll-table>
        </x-ui.panel>

        @if ($current !== null)
            <x-ui.panel :title="__('payroll.payroll_title', ['project' => $current->project_code, 'month' => $current->month->format('m/Y')])" :subtitle="__('payroll.statuses.'.$current->status)" data-test="payroll-panel">
                <div class="flex flex-col gap-4 p-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('operations.payroll.print', ['payroll' => $current->id, 'form' => 'summary']) }}" target="_blank" rel="noopener" class="text-sm underline" data-test="print-summary">{{ __('payroll.print_summary') }}</a>
                        <a href="{{ route('operations.payroll.print', ['payroll' => $current->id, 'form' => 'payslips']) }}" target="_blank" rel="noopener" class="text-sm underline" data-test="print-payslips">{{ __('payroll.print_payslips') }}</a>
                        {{-- The month's absences, bonuses and deductions from Excel (finance request of 2026-11-25). --}}
                        <livewire:excel-tools kind="payroll_lines" :context="['payroll' => $current->id]" :key="'excel-payroll-'.$current->id.'-'.$current->status" />
                        <div class="ms-auto"><x-ui.button size="sm" tone="ghost" icon="x-mark" wire:click="closePayroll" :aria-label="__('payroll.close')" /></div>
                    </div>

                    @if ($current->return_reason && $draft)
                        <p class="text-sm text-status-critical" data-test="return-reason">{{ __('payroll.returned_because', ['reason' => $current->return_reason]) }}</p>
                    @endif

                    <div class="flex flex-wrap items-end gap-3">
                        <div class="w-56">
                            <x-ui.form-input wire:model="rate" inputmode="decimal" dir="ltr" :label="__('payroll.rate')" :help="__('payroll.rate_help')" :disabled="! $canEdit || ! in_array($current->status, ['draft', 'submitted'], true)" data-test="rate" />
                        </div>
                        @if ($canEdit && in_array($current->status, ['draft', 'submitted'], true))
                            <x-ui.button icon="arrow-path" wire:click="applyRate" data-test="apply-rate">{{ __('payroll.apply_rate') }}</x-ui.button>
                        @endif
                    </div>

                    <x-ui.scroll-table :paginate="false">
                        <table class="fin-table" data-test="lines-table">
                            <thead>
                                <tr>
                                    <th>{{ __('payroll.employee') }}</th>
                                    <th class="fin-num">{{ __('payroll.basic_pay') }}</th>
                                    <th class="fin-num">{{ __('payroll.adjustment') }}</th>
                                    <th class="fin-num">{{ __('payroll.unpaid_days') }}</th>
                                    <th class="fin-num">{{ __('payroll.unpaid_leave') }}</th>
                                    <th class="fin-num">{{ __('payroll.other_deductions') }}</th>
                                    <th>{{ __('payroll.notes') }}</th>
                                    <th class="fin-num">{{ __('payroll.net') }}</th>
                                    <th class="fin-num">{{ __('payroll.net_paid', ['currency' => $current->pay_currency]) }}</th>
                                    <th>{{ __('payroll.allocations') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($current->lines as $line)
                                    <tr wire:key="line-{{ $line->id }}" data-test="line-{{ $line->employee_id }}">
                                        <td class="font-medium">{{ $line->name }} <span class="block text-xs text-secondary-medium dark:text-ink-muted">#{{ $line->employee_id }} · {{ $line->position }}</span></td>
                                        <td class="fin-num"><x-money :amount="$line->basic_pay" :currency="$line->currency" /></td>
                                        <td><input type="text" inputmode="decimal" dir="ltr" wire:model="edits.{{ $line->id }}.adjustment" class="{{ $cell }} text-end" @disabled(! $draft || ! $canEdit) aria-label="{{ __('payroll.adjustment') }} {{ $line->name }}" data-test="adjustment-{{ $line->employee_id }}"></td>
                                        <td><input type="text" inputmode="decimal" dir="ltr" wire:model="edits.{{ $line->id }}.unpaid_days" class="{{ $cell }} text-end" @disabled(! $draft || ! $canEdit) aria-label="{{ __('payroll.unpaid_days') }} {{ $line->name }}" data-test="days-{{ $line->employee_id }}"></td>
                                        <td class="fin-num"><x-money :amount="$line->unpaid_leave" :currency="$line->currency" /></td>
                                        <td><input type="text" inputmode="decimal" dir="ltr" wire:model="edits.{{ $line->id }}.other_deductions" class="{{ $cell }} text-end" @disabled(! $draft || ! $canEdit) aria-label="{{ __('payroll.other_deductions') }} {{ $line->name }}" data-test="other-{{ $line->employee_id }}"></td>
                                        <td><input type="text" wire:model="edits.{{ $line->id }}.notes" class="{{ $cell }}" @disabled(! $draft || ! $canEdit) aria-label="{{ __('payroll.notes') }} {{ $line->name }}"></td>
                                        <td class="fin-num font-semibold"><x-money :amount="$line->net" :currency="$line->currency" /></td>
                                        <td class="fin-num font-semibold" data-test="net-paid-{{ $line->employee_id }}"><x-money :amount="$line->net_paid" :currency="$current->pay_currency" /></td>
                                        <td class="text-xs">
                                            @forelse ($line->allocations ?? [] as $share)
                                                <span class="block">{{ $share['name'] }} · {{ rtrim(rtrim($share['percent'], '0'), '.') }}%</span>
                                            @empty
                                                <span class="text-status-critical">{{ __('payroll.no_split') }}</span>
                                            @endforelse
                                        </td>
                                        <td>
                                            @if ($draft && $canEdit)
                                                <x-ui.button size="sm" tone="ghost" icon="trash" wire:click="removeLine({{ $line->id }})" wire:confirm="{{ __('payroll.remove_line_confirm') }}" :aria-label="__('payroll.remove_line')" />
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="8" class="font-semibold">{{ __('payroll.total') }}</td>
                                    <td class="fin-num font-semibold" data-test="total-paid"><x-money :amount="$totals['paid']" :currency="$current->pay_currency" /></td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </x-ui.scroll-table>

                    <x-ui.action-bar data-test="payroll-actions">
                        @if ($draft && $canEdit)
                            <x-ui.button icon="check" wire:click="saveLines" data-test="save-lines">{{ __('payroll.save_lines') }}</x-ui.button>
                            <x-ui.button icon="user-plus" wire:click="addStaff" data-test="add-staff">{{ __('payroll.add_staff') }}</x-ui.button>
                            <x-ui.button tone="primary" icon="paper-airplane" wire:click="submit" data-test="submit-payroll">{{ __('payroll.submit') }}</x-ui.button>
                            @if ($canDelete)
                                <x-ui.button tone="ghost" icon="trash" wire:click="deletePayroll" wire:confirm="{{ __('payroll.delete_confirm') }}" data-test="delete-payroll">{{ __('payroll.delete') }}</x-ui.button>
                            @endif
                        @endif

                        @if ($mayApprove)
                            <x-ui.button tone="primary" icon="check" wire:click="approve" data-test="approve-payroll">{{ __('payroll.approve') }}</x-ui.button>
                            <div class="w-72"><x-ui.form-input wire:model="returnReason" :label="__('payroll.return_reason')" /></div>
                            <x-ui.button icon="arrow-uturn-left" wire:click="returnPayroll" data-test="return-payroll">{{ __('payroll.return') }}</x-ui.button>
                        @endif

                        @if ($current->status === \App\Models\Payroll::APPROVED && $canCreate)
                            <div class="w-72">
                                <x-ui.form-select wire:model="payAccount" :label="__('payroll.paid_from')" required data-test="pay-account">
                                    <option value="">{{ __('payroll.choose') }}</option>
                                    @foreach ($cashAccounts as $code => $label)
                                        <option value="{{ $code }}">{{ $label }}</option>
                                    @endforeach
                                </x-ui.form-select>
                            </div>
                            <div class="w-44"><x-ui.form-input type="date" wire:model="payDate" :label="__('payroll.pay_date')" data-test="pay-date" /></div>
                            <x-ui.button tone="primary" icon="banknotes" wire:click="pay" data-test="pay-payroll">{{ __('payroll.pay') }}</x-ui.button>
                        @endif

                        @if ($current->voucher_id)
                            <p class="text-sm" data-test="payroll-voucher">{{ __('payroll.paid_by') }} <a href="{{ route(\App\Support\Navigation::routeName('vouchers'), ['voucher' => $current->voucher_id]) }}" wire:navigate class="font-semibold underline" dir="ltr">{{ $current->voucher_id }}</a></p>
                        @endif
                    </x-ui.action-bar>
                </div>
            </x-ui.panel>
        @endif
    @endif
</div>
