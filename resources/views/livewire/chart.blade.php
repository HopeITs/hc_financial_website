<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.chart')" :subtitle="__('chart.intro')" :count="$total">
        @if ($canCreate)
            <x-slot:actions>
                <x-ui.button tone="primary" icon="plus" wire:click="openAdd" data-test="add-account">{{ __('chart.add') }}</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <x-ui.form-draft :saved-at="$draftSavedAt" :notice="$draftNotice" />

    @if ($notice)
        <div class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" role="status" data-test="notice">{{ $notice }}</div>
    @endif

    @if ($account === null)
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" data-test="chart-classes">
            @foreach ($roots as $root)
                @php
                    $edge = match ($root->class->value) {
                        'assets' => 'border-t-sky-600',
                        'liabilities' => 'border-t-amber-600',
                        'net_assets' => 'border-t-teal-700',
                        'income' => 'border-t-emerald-600',
                        default => 'border-t-primary',
                    };
                @endphp
                <a href="{{ route('chart', ['account' => $root->code]) }}" wire:navigate class="block min-w-0 rounded-xl border border-t-2 border-secondary-soft bg-white px-4 py-3 shadow-card hover:bg-board-head dark:border-line dark:bg-surface-1 dark:hover:bg-white/[0.04] {{ $edge }}">
                    <p class="type-label truncate text-secondary-medium dark:text-ink-muted">{{ $root->class->label() }}</p>
                    <p class="mt-1.5 truncate text-lg font-semibold text-secondary-dark dark:text-ink" title="{{ $root->localizedName() }}">{{ $root->localizedName() }}</p>
                    <p class="type-meta mt-1 text-secondary-medium dark:text-ink-muted"><span class="fin-code">{{ $root->code }}</span> · {{ __('chart.posting_accounts', ['count' => $postingCounts[$root->class->value] ?? 0]) }}</p>
                </a>
            @endforeach
        </div>

        <x-ui.panel class="p-4">
            <p class="type-label text-secondary-medium dark:text-ink-muted">{{ __('chart.tags_title') }}</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach (array_keys(__('chart.tags')) as $tag)
                    <flux:badge size="sm" color="sky">{{ __('chart.tags.'.$tag) }}</flux:badge>
                @endforeach
            </div>
            <p class="type-meta mt-2 text-secondary-medium dark:text-ink-muted">{{ __('chart.tags_note') }}</p>
        </x-ui.panel>

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('chart.all_accounts') }}</h2>
            <div class="grid items-end gap-4 sm:grid-cols-[1fr_14rem_auto]" data-test="chart-filters">
                <x-ui.form-input wire:model.live.debounce.300ms="search" :label="__('chart.search')" :placeholder="__('chart.search')" />
                <x-ui.form-select wire:model.live="classFilter" :label="__('chart.class')" :placeholder="__('chart.all_classes')">
                    @foreach ($classes as $class)
                        <option value="{{ $class->value }}">{{ $class->label() }}</option>
                    @endforeach
                </x-ui.form-select>
                <label class="flex items-center gap-2 pb-2 text-sm text-secondary-dark dark:text-ink">
                    <input type="checkbox" wire:model.live="postingOnly" data-test="posting-only" />
                    {{ __('chart.posting_only') }}
                </label>
            </div>

            <x-ui.scroll-table>
                <table class="fin-table" data-test="chart-list">
                    <thead>
                        <tr>
                            <th>{{ __('chart.code') }}</th>
                            <th>{{ __('chart.name') }}</th>
                            <th>{{ __('chart.class') }}</th>
                            <th>{{ __('chart.currency') }}</th>
                            <th>{{ __('chart.city') }}</th>
                            <th class="fin-num">{{ __('chart.balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($listed as $row)
                            <tr wire:key="row-{{ $row->code }}">
                                <td dir="ltr" class="text-start"><a href="{{ route('chart', ['account' => $row->code]) }}" wire:navigate class="font-medium text-primary hover:underline dark:text-brand-text">{{ $row->code }}</a></td>
                                <td>
                                    {{ $row->localizedName() }}
                                    @unless ($row->is_active)
                                        <flux:badge size="sm" class="ms-1">{{ __('chart.inactive') }}</flux:badge>
                                    @endunless
                                </td>
                                <td>{{ $row->class->label() }}</td>
                                <td>{{ $row->currency === 'Any' ? __('chart.any_currency') : ($row->currency ?: '—') }}</td>
                                <td>{{ $row->city ?: '—' }}</td>
                                <td class="fin-num"><x-money :amount="$balances[$row->code] ?? '0.00'" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="fin-empty">{{ __('chart.no_match') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.scroll-table>
        </section>
    @else
        <nav aria-label="breadcrumb" class="flex flex-wrap items-center gap-1 text-sm text-secondary-medium dark:text-ink-muted" data-test="chart-path">
            <a href="{{ route('chart') }}" wire:navigate class="hover:underline">{{ __('chart.home') }}</a>
            @foreach ($path as $step)
                <span aria-hidden="true" class="rtl:-scale-x-100">›</span>
                <a href="{{ route('chart', ['account' => $step->code]) }}" wire:navigate class="hover:underline"><span dir="ltr">{{ $step->code }}</span> {{ $step->localizedName() }}</a>
            @endforeach
        </nav>

        <x-ui.panel class="p-5" data-test="chart-account">
            <p class="type-meta text-secondary-medium dark:text-ink-muted"><span dir="ltr">{{ $account->code }}</span> · {{ $account->class->label() }} · {{ $account->level->label() }}</p>
            <h2 class="mt-1 text-2xl font-semibold text-secondary-dark dark:text-ink">{{ $account->name_en }}
                @unless ($account->is_active)
                    <flux:badge size="sm" class="ms-2" data-test="inactive-badge">{{ __('chart.inactive') }}</flux:badge>
                @endunless
            </h2>
            <p dir="rtl" class="mt-0.5 text-base text-secondary-medium dark:text-ink-muted">{{ $account->name_ar }}</p>

            @if ($account->usage_note)
                <div class="mt-4">
                    <p class="type-label text-secondary-medium dark:text-ink-muted">{{ __('chart.how_to_use') }}</p>
                    <p class="mt-1 text-sm text-secondary-dark dark:text-ink">{{ $account->usage_note }}</p>
                </div>
            @endif

            @if ($used)
                <p class="mt-4 text-sm text-secondary-medium dark:text-ink-muted" data-test="used-note">{{ __('chart.used_note') }}</p>
            @endif

            @if ($canCreate || $canEdit || $canDelete)
                <div class="mt-4 flex flex-wrap gap-2" data-test="account-actions">
                    @if ($canEdit)
                        <x-ui.button wire:click="openEdit('{{ $account->code }}')" data-test="edit-account">{{ __('chart.edit') }}</x-ui.button>
                        <x-ui.button wire:click="toggleActive('{{ $account->code }}')" data-test="toggle-active">{{ $account->is_active ? __('chart.deactivate') : __('chart.reactivate') }}</x-ui.button>
                    @endif
                    @if ($canCreate && $account->level === \App\Enums\AccountLevel::Header)
                        <x-ui.button wire:click="openAdd('{{ $account->code }}')" data-test="add-child">{{ __('chart.add_child') }}</x-ui.button>
                    @endif
                    @if ($canDelete && ! $used)
                        <x-ui.button wire:click="askDelete('{{ $account->code }}')" data-test="delete-account">{{ __('chart.delete') }}</x-ui.button>
                    @endif
                </div>
            @endif

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <span class="type-label text-secondary-medium dark:text-ink-muted">{{ __('chart.tags_asked') }}</span>
                @forelse (($account->required_tags ?? []) as $tag)
                    <flux:badge size="sm" color="sky">{{ __('chart.tags.'.$tag) }}</flux:badge>
                @empty
                    <span class="type-meta text-secondary-medium dark:text-ink-muted">{{ $account->level === \App\Enums\AccountLevel::Header ? __('chart.no_tags') : '—' }}</span>
                @endforelse
            </div>
        </x-ui.panel>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <x-ui.stat-tile :label="__('chart.balance')" :value="number_format((float) ($balances[$account->code] ?? 0), 2)" />
            <x-ui.stat-tile :label="__('chart.normal_side')" :value="$account->normal_side->label()" />
            <x-ui.stat-tile :label="__('chart.currency')" :value="$account->currency === 'Any' ? __('chart.any_currency') : ($account->currency ?: '—')" />
            <x-ui.stat-tile :label="__('chart.city')" :value="$account->city ?: '—'" />
            <x-ui.stat-tile :label="__('chart.cash_account')" :value="$account->is_cash ? __('chart.yes') : __('chart.no')" />
        </div>

        @if ($account->statement_line)
            <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('chart.statement_line') }}: <span class="text-secondary-dark dark:text-ink">{{ $account->statement_line }}</span></p>
        @endif

        @if ($children->isNotEmpty())
            <div>
                <h3 class="mb-2 text-base font-semibold text-secondary-dark dark:text-ink">{{ __('chart.below') }}</h3>
                <x-ui.scroll-table>
                    <table class="fin-table" data-test="chart-children">
                        <thead>
                            <tr>
                                <th>{{ __('chart.code') }}</th>
                                <th>{{ __('chart.name') }}</th>
                                <th>{{ __('chart.level') }}</th>
                                <th class="fin-num">{{ __('chart.balance') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($children as $child)
                                <tr wire:key="child-{{ $child->code }}">
                                    <td dir="ltr" class="text-start"><a href="{{ route('chart', ['account' => $child->code]) }}" wire:navigate class="font-medium text-primary hover:underline dark:text-brand-text">{{ $child->code }}</a></td>
                                    <td>{{ $child->localizedName() }}</td>
                                    <td>{{ $child->level->label() }}</td>
                                    <td class="fin-num"><x-money :amount="$balances[$child->code] ?? '0.00'" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-ui.scroll-table>
            </div>
        @endif
    @endif

    @if ($canCreate || $canEdit)
        <div x-data="unsavedGuard" data-unsaved-message="{{ __('drafts.leave_warning') }}" x-on:form-clean.window="clean()">
        <x-ui.modal wire:model.self="showEditor" size="lg" :title="$editing ? __('chart.edit_title', ['code' => $editing]) : __('chart.add_title')" submit="save" close="closeEditor" :required-note="true" data-test="account-editor">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.form-input wire:model="formCode" :label="__('chart.code')" dir="ltr" maxlength="4" :disabled="$editing !== null" :help="$editing ? __('chart.code_locked') : null" required />
                <x-ui.form-select wire:model="formLevel" :label="__('chart.level')" :disabled="$editingUsed">
                    <option value="posting">{{ \App\Enums\AccountLevel::Posting->label() }}</option>
                    <option value="header">{{ \App\Enums\AccountLevel::Header->label() }}</option>
                </x-ui.form-select>
                <x-ui.form-input wire:model="formNameEn" :label="__('chart.name_en')" dir="ltr" required />
                <x-ui.form-input wire:model="formNameAr" :label="__('chart.name_ar')" dir="rtl" required />
                <x-ui.form-select wire:model="formParent" :label="__('chart.parent')" :placeholder="__('chart.no_parent')">
                    <option value="">{{ __('chart.no_parent') }}</option>
                    @foreach ($headers as $header)
                        <option value="{{ $header->code }}">{{ $header->code }} · {{ app()->getLocale() === 'ar' ? $header->name_ar : $header->name_en }}</option>
                    @endforeach
                </x-ui.form-select>
                <x-ui.form-select wire:model="formClass" :label="__('chart.class')" :disabled="$editingUsed" :locked-reason="$editingUsed ? __('chart.used_note') : null">
                    @foreach ($classes as $class)
                        <option value="{{ $class->value }}">{{ $class->label() }}</option>
                    @endforeach
                </x-ui.form-select>
                <x-ui.form-select wire:model="formSide" :label="__('chart.normal_side')">
                    @foreach ($sides as $side)
                        <option value="{{ $side->value }}">{{ $side->label() }}</option>
                    @endforeach
                </x-ui.form-select>
                <x-ui.form-select wire:model="formCurrency" :label="__('chart.currency')" :disabled="$editingUsed" :locked-reason="$editingUsed ? __('chart.used_note') : null">
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency }}">{{ $currency === 'Any' ? __('chart.any_currency') : $currency }}</option>
                    @endforeach
                </x-ui.form-select>
                <x-ui.form-input wire:model="formCity" :label="__('chart.city')" />
                <x-ui.form-input wire:model="formStatementLine" :label="__('chart.statement_line')" />
                <label class="flex items-center gap-2 self-end pb-2 text-sm text-secondary-dark dark:text-ink">
                    <input type="checkbox" wire:model="formCash" data-test="form-cash" />
                    {{ __('chart.cash_account') }}
                </label>
            </div>

            <fieldset class="mt-4">
                <legend class="type-label text-secondary-medium dark:text-ink-muted">{{ __('chart.tags_field') }}</legend>
                <div class="mt-2 flex flex-wrap gap-4">
                    @foreach (array_keys(__('chart.tags')) as $tag)
                        <label class="flex items-center gap-2 text-sm text-secondary-dark dark:text-ink">
                            <input type="checkbox" wire:model="formTags" value="{{ $tag }}" />
                            {{ __('chart.tags.'.$tag) }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="mt-4">
                <x-ui.form-textarea wire:model="formNote" :label="__('chart.how_to_use')" />
            </div>

            <x-slot:actions>
                <x-ui.button wire:click="saveDraft" data-test="save-draft">{{ __('drafts.save') }}</x-ui.button>
                <x-ui.button type="submit" tone="primary" x-on:click="leaving()" data-test="save-account">{{ __('chart.save') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.modal>
        </div>
    @endif

    @if ($canDelete)
        <x-ui.confirm-modal wire:model.self="showDeleteConfirm" :title="__('chart.delete_title')" :message="__('chart.delete_message')" confirm="delete" :confirm-label="__('chart.delete')" confirm-test="confirm-delete" close="cancelDelete" />
    @endif
</div>
