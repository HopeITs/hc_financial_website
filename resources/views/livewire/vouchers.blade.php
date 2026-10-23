<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
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

    @if ($current === null)
        <x-ui.page-header :title="__('navigation.screens.vouchers')" :subtitle="__('vouchers.intro')" :count="$list->total()" />

        <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('vouchers.type') }}" data-test="type-chips">
            <button type="button" wire:click="setType('')" aria-pressed="{{ $type === '' ? 'true' : 'false' }}"
                class="rounded-full border px-3 py-1 text-sm {{ $type === '' ? 'border-primary bg-primary text-white' : 'border-secondary-light bg-white text-secondary-dark dark:border-line-strong dark:bg-surface-2 dark:text-ink' }}">{{ __('vouchers.all_types') }}</button>
            @foreach ($types as $option)
                <button type="button" wire:click="setType('{{ $option->value }}')" aria-pressed="{{ $type === $option->value ? 'true' : 'false' }}" title="{{ $option->label() }}" data-test="chip-{{ $option->value }}"
                    class="rounded-full border px-3 py-1 text-sm {{ $type === $option->value ? 'border-primary bg-primary text-white' : 'border-secondary-light bg-white text-secondary-dark dark:border-line-strong dark:bg-surface-2 dark:text-ink' }}"><span dir="ltr">{{ $option->value }}</span></button>
            @endforeach
        </div>

        <div class="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-4" data-test="voucher-filters">
            <x-ui.form-input wire:model.live.debounce.300ms="search" :label="__('vouchers.search')" :placeholder="__('vouchers.search')" />
            <x-ui.form-select wire:model.live="status" :label="__('vouchers.status_label')" :placeholder="__('vouchers.all_statuses')">
                @foreach ($statuses as $option)
                    <option value="{{ $option }}">{{ __('vouchers.status.'.$option) }}</option>
                @endforeach
            </x-ui.form-select>
            <x-ui.form-input type="date" wire:model.live="from" :label="__('vouchers.from')" />
            <x-ui.form-input type="date" wire:model.live="to" :label="__('vouchers.to')" />
        </div>

        <x-ui.scroll-table :paginate="false">
            <table class="fin-table" data-test="voucher-list">
                <thead>
                    <tr>
                        <th>{{ __('vouchers.number') }}</th>
                        <th>{{ __('vouchers.date') }}</th>
                        <th>{{ __('vouchers.type') }}</th>
                        <th>{{ __('vouchers.description') }}</th>
                        <th class="fin-num">{{ __('vouchers.amount') }}</th>
                        <th>{{ __('vouchers.prepared_by') }}</th>
                        <th>{{ __('vouchers.status_label') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($list as $row)
                        @php
                            $badge = $row->trashed() ? 'rejected' : $row->status->value;
                            $color = ['pending_approval' => 'amber', 'approved' => 'green', 'replaced' => 'zinc', 'rejected' => 'red'][$badge];
                        @endphp
                        <tr wire:key="voucher-{{ $row->id }}">
                            <td dir="ltr" class="whitespace-nowrap text-start"><a href="{{ route('vouchers', ['voucher' => $row->id]) }}" wire:navigate class="font-medium text-primary hover:underline dark:text-brand-text">{{ $row->id }}</a></td>
                            <td dir="ltr" class="whitespace-nowrap text-start">{{ $row->date->toDateString() }}</td>
                            <td title="{{ $row->type->label() }}"><span dir="ltr">{{ $row->type->value }}</span></td>
                            <td>{{ $row->description ?: '—' }}</td>
                            <td class="fin-num"><x-money :amount="$amounts[$row->id] ?? '0.00'" currency="USD" /></td>
                            <td>{{ $names[$row->prepared_by] ?? '—' }}</td>
                            <td><flux:badge size="sm" :color="$color">{{ __('vouchers.status.'.$badge) }}</flux:badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="fin-empty" data-test="no-vouchers">{{ __('vouchers.no_match') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.scroll-table>

        {{ $list->links() }}
    @else
        @php
            $badge = $voucher->trashed() ? 'rejected' : $voucher->status->value;
            $color = ['pending_approval' => 'amber', 'approved' => 'green', 'replaced' => 'zinc', 'rejected' => 'red'][$badge];
        @endphp

        <div>
            <a href="{{ route('vouchers') }}" wire:navigate class="text-sm text-secondary-medium hover:underline dark:text-ink-muted">{{ __('vouchers.back') }}</a>
        </div>

        <x-ui.page-header :title="$voucher->id" :subtitle="$voucher->description ?: __('vouchers.no_description')">
            <x-slot:actions>
                <flux:badge size="lg" :color="$color" data-test="voucher-status">{{ __('vouchers.status.'.$badge) }}</flux:badge>
            </x-slot:actions>
        </x-ui.page-header>

        @if ($rejection)
            <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-3 text-sm text-status-critical" data-test="rejection">
                <p class="font-semibold">{{ __('vouchers.rejected_by', ['name' => $names[$rejection->changed_by] ?? '—', 'when' => $rejection->created_at?->format('Y-m-d H:i')]) }}</p>
                <p class="mt-1">{{ $rejection->reason }}</p>
            </div>
        @endif

        <x-ui.panel class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4" data-test="voucher-summary">
            <div><p class="type-label text-secondary-medium dark:text-ink-muted">{{ __('vouchers.type') }}</p><p>{{ $voucher->type->label() }} <span dir="ltr" class="text-secondary-medium dark:text-ink-muted">({{ $voucher->type->value }})</span></p></div>
            <div><p class="type-label text-secondary-medium dark:text-ink-muted">{{ __('vouchers.date') }}</p><p dir="ltr" class="text-start">{{ $voucher->date->toDateString() }}</p></div>
            <div><p class="type-label text-secondary-medium dark:text-ink-muted">{{ __('vouchers.prepared_by') }}</p><p>{{ $names[$voucher->prepared_by] ?? '—' }}</p></div>
            <div>
                <p class="type-label text-secondary-medium dark:text-ink-muted">{{ __('vouchers.approved_by') }}</p>
                <p>{{ $voucher->approved_by ? ($names[$voucher->approved_by] ?? '—').' · '.$voucher->approved_at?->format('Y-m-d H:i') : '—' }}</p>
            </div>
        </x-ui.panel>

        @if ($showActions)
            <x-ui.panel class="flex flex-wrap items-center gap-3 p-4" data-test="approval-actions">
                <x-ui.button tone="primary" wire:click="approve" :disabled="! $canApprove" data-test="approve">{{ __('vouchers.approve') }}</x-ui.button>
                <x-ui.button wire:click="openReject" :disabled="$isOwn" data-test="reject">{{ __('vouchers.reject') }}</x-ui.button>
                @if ($isOwn)
                    <p class="text-sm text-secondary-medium dark:text-ink-muted" data-test="own-note">{{ __('vouchers.own_note') }}</p>
                @elseif (! $canApprove)
                    <p class="text-sm text-secondary-medium dark:text-ink-muted" data-test="limit-note">{{ __('vouchers.limit_note') }}</p>
                @endif
            </x-ui.panel>
        @endif

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('vouchers.lines') }}</h2>
            <x-ui.scroll-table :paginate="false">
                <table class="fin-table" data-test="voucher-lines">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('vouchers.account') }}</th>
                            <th>{{ __('vouchers.project') }}</th>
                            <th>{{ __('vouchers.tags') }}</th>
                            <th>{{ __('vouchers.currency') }}</th>
                            <th class="fin-num">{{ __('vouchers.debit') }}</th>
                            <th class="fin-num">{{ __('vouchers.credit') }}</th>
                            <th class="fin-num">{{ __('vouchers.rate') }}</th>
                            <th class="fin-num">{{ __('vouchers.debit') }} USD</th>
                            <th class="fin-num">{{ __('vouchers.credit') }} USD</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($voucher->lines as $line)
                            <tr wire:key="line-{{ $line->id }}">
                                <td class="text-secondary-medium dark:text-ink-muted">{{ $line->line_no }}</td>
                                <td><span dir="ltr">{{ $line->account_code }}</span> · {{ $line->account?->localizedName() }}</td>
                                <td dir="ltr" class="text-start">{{ $line->project_code ?? '—' }}</td>
                                <td class="text-sm">{{ collect([$line->budget_line_key, $line->fund_source?->label(), $line->contact, $line->center])->filter()->implode(' · ') ?: '—' }}</td>
                                <td dir="ltr" class="text-start">{{ $line->currency }}</td>
                                <td class="fin-num"><x-money :amount="$line->debit_orig" /></td>
                                <td class="fin-num"><x-money :amount="$line->credit_orig" /></td>
                                <td class="fin-num" dir="ltr">{{ rtrim(rtrim($line->rate, '0'), '.') }}</td>
                                <td class="fin-num"><x-money :amount="$line->debit_usd" /></td>
                                <td class="fin-num"><x-money :amount="$line->credit_usd" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-semibold">
                            <td colspan="8">{{ __('vouchers.total') }}</td>
                            <td class="fin-num"><x-money :amount="$totalDebit" /></td>
                            <td class="fin-num"><x-money :amount="$totalCredit" /></td>
                        </tr>
                    </tfoot>
                </table>
            </x-ui.scroll-table>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('vouchers.attachments') }}</h2>
            <x-ui.panel class="p-4" data-test="voucher-attachments">
                @forelse ($voucher->attachments as $file)
                    <p class="flex flex-wrap items-baseline gap-2 text-sm"><span dir="ltr">{{ $file->original_name }}</span><span class="text-secondary-medium dark:text-ink-muted" dir="ltr">{{ number_format($file->size / 1024, 1) }} KB</span></p>
                @empty
                    <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('vouchers.no_attachments') }}</p>
                @endforelse
            </x-ui.panel>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('vouchers.audit') }}</h2>
            <x-ui.scroll-table :paginate="false">
                <table class="fin-table" data-test="voucher-audit">
                    <thead>
                        <tr>
                            <th>{{ __('vouchers.when') }}</th>
                            <th>{{ __('vouchers.who') }}</th>
                            <th>{{ __('vouchers.action') }}</th>
                            <th>{{ __('vouchers.change') }}</th>
                            <th>{{ __('vouchers.reason') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($audits as $audit)
                            <tr wire:key="audit-{{ $audit->id }}">
                                <td dir="ltr" class="whitespace-nowrap text-start">{{ $audit->created_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $names[$audit->changed_by] ?? '—' }}</td>
                                <td>{{ __('vouchers.audit_action.'.$audit->action) }}</td>
                                <td>{{ $audit->field ? $audit->field.': '.($audit->old_value ?? '—').' → '.($audit->new_value ?? '—') : '—' }}</td>
                                <td>{{ $audit->reason ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.scroll-table>
        </section>

        @if ($showActions)
            <x-ui.modal wire:model.self="showReject" size="md" :title="__('vouchers.reject_title', ['number' => $voucher->id])" submit="reject" close="closeReject" :required-note="true" data-test="reject-modal">
                <x-ui.form-textarea wire:model="rejectReason" :label="__('vouchers.reject_reason_label')" :help="__('vouchers.reject_help')" required />

                <x-slot:actions>
                    <x-ui.button type="submit" tone="primary" data-test="confirm-reject">{{ __('vouchers.reject') }}</x-ui.button>
                </x-slot:actions>
            </x-ui.modal>
        @endif
    @endif
</div>
