@php
    $cell = 'w-full min-w-20 rounded-md border border-secondary-light bg-white px-2 py-1.5 text-sm text-secondary-dark disabled:opacity-60 dark:border-field-line dark:bg-surface-2 dark:text-ink';
    $badgeColors = ['draft' => 'zinc', 'submitted' => 'amber', 'approved' => 'green', 'rejected' => 'red', 'closed' => 'zinc'];
@endphp

<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="requests">
    @if ($notice)
        <div class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" role="status" data-test="notice">{{ $notice }}</div>
    @endif
    @if ($failures !== [])
        <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="failures">
            @foreach ($failures as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    @if ($mode === 'form')
        <div class="flex flex-col gap-6" x-data="unsavedGuard" data-unsaved-message="{{ __('drafts.leave_warning') }}" x-on:form-clean.window="clean()">
        <div>
            <button type="button" wire:click="cancelForm" class="text-sm text-secondary-medium hover:underline dark:text-ink-muted">{{ __('requests.back') }}</button>
        </div>

        <x-ui.page-header :title="$editingId ? __('requests.edit_title', ['number' => $editingId]) : __('requests.new_title')" :subtitle="__('requests.form_intro')" />

        <x-ui.panel class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4" data-test="request-header">
            <x-ui.form-select wire:model.live="fProject" :label="__('requests.project')" required>
                @foreach ($projects as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </x-ui.form-select>
            <x-ui.form-select wire:model.live="fType" :label="__('requests.type')" required>
                <option value="monthly">{{ __('requests.types.monthly') }}</option>
                <option value="extra">{{ __('requests.types.extra') }}</option>
            </x-ui.form-select>
            <x-ui.form-input type="month" wire:model.live="fMonth" :label="__('requests.month')" required />
            <x-ui.form-select wire:model.live="fCategory" :label="__('requests.category_label')" :placeholder="__('requests.no_category')" :help="__('requests.category_help')">
                @foreach ($categories as $category)
                    <option value="{{ $category->value }}">{{ $category->label() }}</option>
                @endforeach
            </x-ui.form-select>
            <x-ui.form-textarea wire:model="fDescription" :label="__('requests.description')" rows="2" class="w-full sm:col-span-2 lg:col-span-4" />
        </x-ui.panel>

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('requests.lines') }}</h2>
            <x-ui.scroll-table :paginate="false">
                <table class="fin-table" data-test="request-form-lines">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th class="min-w-56">{{ __('requests.budget_line') }}</th>
                            <th class="min-w-32">{{ __('requests.amount') }}</th>
                            <th>{{ __('requests.currency') }}</th>
                            <th class="min-w-28">{{ __('requests.rate') }}</th>
                            <th class="min-w-48">{{ __('requests.note') }}</th>
                            <th><span class="sr-only">{{ __('requests.remove_line') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($fLines as $i => $line)
                            <tr wire:key="form-line-{{ $i }}">
                                <td class="text-secondary-medium dark:text-ink-muted">{{ $i + 1 }}</td>
                                <td>
                                    <x-ui.form-select bare wire:model.live="fLines.{{ $i }}.budget_line" :label="(__('requests.budget_line'))">
                                        <option value="">—</option>
                                        @foreach ($budgetLines as $budgetLine)
                                            <option value="{{ $budgetLine->key }}">{{ $budgetLine->name }} · {{ $budgetLine->account_code }}</option>
                                        @endforeach
                                    </x-ui.form-select>
                                </td>
                                <td><input type="text" inputmode="decimal" dir="ltr" wire:model.live.debounce.300ms="fLines.{{ $i }}.amount" class="{{ $cell }}" aria-label="{{ __('requests.amount') }}"></td>
                                <td>
                                    <x-ui.form-select bare wire:model.live="fLines.{{ $i }}.currency" :label="(__('requests.currency'))">
                                        @foreach (app(\App\Services\Ledger\Currencies::class)->codes() as $code)
                                            <option value="{{ $code }}">{{ $code }}</option>
                                        @endforeach
                                    </x-ui.form-select>
                                </td>
                                <td><input type="text" inputmode="decimal" dir="ltr" wire:model.live.debounce.300ms="fLines.{{ $i }}.rate" class="{{ $cell }}" @disabled(in_array($line['currency'] ?? '', ['', 'USD'], true)) placeholder="{{ ! in_array($line['currency'] ?? '', ['', 'USD'], true) ? __('requests.rate_auto') : '' }}" aria-label="{{ __('requests.rate') }}"></td>
                                <td><input type="text" wire:model="fLines.{{ $i }}.note" class="{{ $cell }}" aria-label="{{ __('requests.note') }}"></td>
                                <td><x-ui.button tone="ghost" wire:click="removeLine({{ $i }})" :aria-label="__('requests.remove_line')">✕</x-ui.button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.scroll-table>
            <div><x-ui.button wire:click="addLine" icon="plus" data-test="add-line">{{ __('requests.add_line') }}</x-ui.button></div>
        </section>

        <div class="flex flex-wrap gap-3">
            <x-ui.button tone="primary" wire:click="save" x-on:click="leaving()" data-test="save-request">{{ __('requests.save') }}</x-ui.button>
            <x-ui.button wire:click="cancelForm">{{ __('requests.cancel') }}</x-ui.button>
        </div>
        </div>
    @elseif ($mode === 'list')
        <x-ui.page-header :title="__('navigation.screens.requests')" :subtitle="__('requests.intro')" :count="$list->total()">
            @if ($canCreate)
                <x-slot:actions>
                    <x-ui.button tone="primary" icon="plus" wire:click="newRequest" data-test="new-request">{{ __('requests.new') }}</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        <div class="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-4" data-test="request-filters">
            <x-ui.form-select wire:model.live="status" :label="__('requests.status_label')" :placeholder="__('requests.all_statuses')">
                @foreach ($statuses as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </x-ui.form-select>
            <x-ui.form-select wire:model.live="project" :label="__('requests.project')" :placeholder="__('requests.all_projects')">
                @foreach ($projects as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </x-ui.form-select>
        </div>

        <x-ui.scroll-table :paginate="false">
            <table class="fin-table" data-test="request-list">
                <thead>
                    <tr>
                        <th>{{ __('requests.number') }}</th>
                        <th>{{ __('requests.project') }}</th>
                        <th>{{ __('requests.month') }}</th>
                        <th>{{ __('requests.type') }}</th>
                        <th class="fin-num">{{ __('requests.total') }}</th>
                        <th>{{ __('requests.requested_by') }}</th>
                        <th>{{ __('requests.status_label') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($list as $row)
                        <tr wire:key="request-{{ $row->id }}">
                            <td dir="ltr" class="whitespace-nowrap text-start"><a href="{{ route('entries.requests', ['request' => $row->id]) }}" wire:navigate class="font-medium text-primary hover:underline dark:text-brand-text">#{{ $row->id }}</a></td>
                            <td dir="ltr" class="text-start">{{ $row->project_code }}</td>
                            <td dir="ltr" class="text-start">{{ $row->month?->format('Y-m') ?? '—' }}</td>
                            <td>{{ __('requests.types.'.$row->type) }}</td>
                            <td class="fin-num"><x-money :amount="$row->amount" currency="USD" /></td>
                            <td>{{ $names[$row->requested_by] ?? '—' }}</td>
                            <td><flux:badge size="sm" :color="$badgeColors[$row->status] ?? 'zinc'">{{ $row->state()?->label() ?? $row->status }}</flux:badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="fin-empty" data-test="no-requests">{{ __('requests.no_match') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.scroll-table>

        {{ $list->links() }}
    @else
        <div>
            <a href="{{ route('entries.requests') }}" wire:navigate class="text-sm text-secondary-medium hover:underline dark:text-ink-muted">{{ __('requests.back') }}</a>
        </div>

        <x-ui.page-header :title="__('requests.request_number', ['number' => $current->id])" :subtitle="$current->description ?: __('requests.no_description')">
            <x-slot:actions>
                <flux:badge size="lg" :color="$badgeColors[$current->status] ?? 'zinc'" data-test="request-status">{{ $current->state()?->label() ?? $current->status }}</flux:badge>
            </x-slot:actions>
        </x-ui.page-header>

        @if ($current->status === 'rejected')
            <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-3 text-sm text-status-critical" data-test="rejection">
                <p class="font-semibold">{{ __('requests.rejected_by', ['name' => $names[$current->decided_by] ?? '—', 'when' => $current->decided_at?->format('Y-m-d H:i')]) }}</p>
                <p class="mt-1">{{ $current->reject_reason }}</p>
            </div>
        @endif

        @if ($pendingRole)
            <div class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" data-test="waiting-for">
                {{ __('requests.waiting_for', ['role' => $pendingRole->label()]) }}
            </div>
        @endif

        @if ($lateWarning)
            <div class="rounded-lg border border-status-warning bg-status-warning-soft px-4 py-2 text-sm" role="note" data-test="late-warning">{{ $lateWarning }}</div>
        @endif

        @foreach ($warnings as $warning)
            <div class="rounded-lg border border-status-warning bg-status-warning-soft px-4 py-2 text-sm" role="note" data-test="budget-warning">
                {{ __('requests.budget_warning', ['line' => $warning['key'], 'budget' => number_format((float) $warning['budget'], 2), 'spent' => number_format((float) $warning['spent'], 2), 'requested' => number_format((float) $warning['requested'], 2), 'currency' => $warning['currency']]) }}
            </div>
        @endforeach

        <x-ui.panel class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-5" data-test="request-summary">
            <div><p class="type-label text-secondary-medium dark:text-ink-muted">{{ __('requests.project') }}</p><p dir="ltr" class="text-start">{{ $current->project_code }}</p></div>
            <div><p class="type-label text-secondary-medium dark:text-ink-muted">{{ __('requests.type') }}</p><p>{{ __('requests.types.'.$current->type) }}</p></div>
            <div><p class="type-label text-secondary-medium dark:text-ink-muted">{{ __('requests.month') }}</p><p dir="ltr" class="text-start">{{ $current->month?->format('Y-m') ?? '—' }}</p></div>
            <div><p class="type-label text-secondary-medium dark:text-ink-muted">{{ __('requests.category_label') }}</p><p>{{ $current->categoryType()?->label() ?? '—' }}</p></div>
            <div><p class="type-label text-secondary-medium dark:text-ink-muted">{{ __('requests.requested_by') }}</p><p>{{ $names[$current->requested_by] ?? '—' }}</p></div>
        </x-ui.panel>

        @if ($canEdit || $canDecide || $canClose)
            <x-ui.panel class="flex flex-wrap items-center gap-3 p-4" data-test="request-actions">
                @if ($canEdit)
                    <x-ui.button wire:click="editRequest" data-test="edit-request">{{ __('requests.edit') }}</x-ui.button>
                    @if ($current->status === 'draft')
                        <x-ui.button tone="primary" wire:click="submit" data-test="submit-request">{{ __('requests.submit') }}</x-ui.button>
                    @endif
                @endif
                @if ($canDecide)
                    <x-ui.button tone="primary" wire:click="approve" data-test="approve-request">{{ __('requests.approve') }}</x-ui.button>
                    <x-ui.button wire:click="openReject" data-test="reject-request">{{ __('requests.reject') }}</x-ui.button>
                @endif
                @if ($canClose)
                    <x-ui.button wire:click="close" wire:confirm="{{ __('requests.close_confirm') }}" data-test="close-request">{{ __('requests.close') }}</x-ui.button>
                @endif
            </x-ui.panel>
        @elseif ($ownRequest && $current->status === 'submitted')
            <p class="text-sm text-secondary-medium dark:text-ink-muted" data-test="own-note">{{ __('requests.own_note') }}</p>
        @endif

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('requests.lines') }}</h2>
            <x-ui.scroll-table :paginate="false">
                <table class="fin-table" data-test="request-lines">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('requests.budget_line') }}</th>
                            <th class="fin-num">{{ __('requests.amount') }}</th>
                            <th>{{ __('requests.currency') }}</th>
                            <th class="fin-num">{{ __('requests.rate') }}</th>
                            <th class="fin-num">{{ __('requests.amount') }} USD</th>
                            <th>{{ __('requests.note') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($current->lines as $line)
                            <tr wire:key="line-{{ $line->id }}">
                                <td class="text-secondary-medium dark:text-ink-muted">{{ $loop->iteration }}</td>
                                <td>{{ $line->budgetLine?->name ?? $line->budget_line_key }} · <span dir="ltr">{{ $line->budgetLine?->account_code }}</span></td>
                                <td class="fin-num"><x-money :amount="$line->amount" /></td>
                                <td dir="ltr" class="text-start">{{ $line->currency }}</td>
                                <td class="fin-num" dir="ltr">{{ $line->currency !== 'USD' ? rtrim(rtrim($line->rate, '0'), '.') : '—' }}</td>
                                <td class="fin-num"><x-money :amount="$line->amount_usd" /></td>
                                <td>{{ $line->note ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-semibold">
                            <td colspan="5">{{ __('requests.total') }}</td>
                            <td class="fin-num"><x-money :amount="$current->amount" currency="USD" /></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </x-ui.scroll-table>
        </section>

        @if ($quotesNeeded || $current->quotes->isNotEmpty())
            <section class="flex flex-col gap-3">
                <h2 class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('requests.quotes') }}</h2>
                <x-ui.panel class="flex flex-col gap-2 p-4" data-test="request-quotes">
                    @if ($quotesNeeded)
                        <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('requests.quotes_help', ['min' => \App\Enums\RequestCategory::MIN_QUOTES, 'max' => \App\Enums\RequestCategory::MAX_QUOTES]) }}</p>
                    @endif
                    @forelse ($current->quotes as $quote)
                        <p class="flex flex-wrap items-baseline gap-3 text-sm" wire:key="quote-{{ $quote->id }}">
                            <a href="{{ route('entries.requests.quote', $quote) }}" class="text-primary hover:underline dark:text-brand-text" dir="ltr">{{ $quote->original_name }}</a>
                            <span class="text-secondary-medium dark:text-ink-muted" dir="ltr">{{ number_format($quote->size / 1024, 1) }} KB</span>
                            @if ($canEdit)
                                <button type="button" wire:click="removeQuote({{ $quote->id }})" class="text-status-critical hover:underline">{{ __('requests.remove') }}</button>
                            @endif
                        </p>
                    @empty
                        <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('requests.no_quotes') }}</p>
                    @endforelse
                    @if ($canEdit)
                        <div>
                            <input type="file" wire:model="quoteFiles" multiple accept=".pdf,.jpg,.jpeg,.png" class="text-sm" aria-label="{{ __('requests.add_quote') }}" data-test="quote-upload">
                            @error('quoteFiles') <p class="text-sm text-status-critical">{{ $message }}</p> @enderror
                            @error('quoteFiles.*') <p class="text-sm text-status-critical">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </x-ui.panel>
            </section>
        @endif

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('requests.approvals') }}</h2>
            <x-ui.panel class="flex flex-col gap-2 p-4" data-test="request-approvals">
                @forelse ($current->approvals as $approval)
                    <p class="text-sm" wire:key="approval-{{ $approval->id }}">
                        {{ __('requests.approval_line', ['step' => $approval->step, 'role' => $approval->role_id->label(), 'name' => $names[$approval->decided_by] ?? '—', 'decision' => __('requests.decision.'.$approval->decision), 'when' => $approval->decided_at->format('Y-m-d H:i')]) }}
                    </p>
                @empty
                    <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('requests.no_approvals') }}</p>
                @endforelse
            </x-ui.panel>
        </section>

        @if ($vouchers->isNotEmpty())
            <section class="flex flex-col gap-3">
                <h2 class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('requests.vouchers') }}</h2>
                <x-ui.panel class="flex flex-col gap-1 p-4" data-test="request-vouchers">
                    @foreach ($vouchers as $voucher)
                        <p class="text-sm" wire:key="voucher-{{ $voucher->id }}"><a href="{{ route('entries.vouchers', ['voucher' => $voucher->id]) }}" wire:navigate class="text-primary hover:underline dark:text-brand-text" dir="ltr">{{ $voucher->id }}</a> · {{ $voucher->date->toDateString() }}</p>
                    @endforeach
                </x-ui.panel>
            </section>
        @endif

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('requests.audit') }}</h2>
            <x-ui.scroll-table :paginate="false">
                <table class="fin-table" data-test="request-audit">
                    <thead>
                        <tr>
                            <th>{{ __('requests.when') }}</th>
                            <th>{{ __('requests.who') }}</th>
                            <th>{{ __('requests.action') }}</th>
                            <th>{{ __('requests.change') }}</th>
                            <th>{{ __('requests.reason') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($current->audits as $audit)
                            <tr wire:key="audit-{{ $audit->id }}">
                                <td dir="ltr" class="whitespace-nowrap text-start">{{ $audit->created_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $names[$audit->changed_by] ?? '—' }}</td>
                                <td>{{ __('requests.audit_action.'.$audit->action) }}</td>
                                <td>{{ $audit->field ? $audit->field.': '.($audit->old_value ?? '—').' → '.($audit->new_value ?? '—') : '—' }}</td>
                                <td>{{ $audit->reason ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.scroll-table>
        </section>

        @if ($canDecide)
            <x-ui.modal wire:model.self="showReject" size="md" :title="__('requests.reject_title', ['number' => $current->id])" submit="reject" close="closeReject" :required-note="true" data-test="reject-modal">
                <x-ui.form-textarea wire:model="rejectReason" :label="__('requests.reject_reason_label')" :help="__('requests.reject_help')" required />

                <x-slot:actions>
                    <x-ui.button type="submit" tone="primary" data-test="confirm-reject">{{ __('requests.reject') }}</x-ui.button>
                </x-slot:actions>
            </x-ui.modal>
        @endif
    @endif
</div>
