<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <header class="flex flex-wrap items-center justify-between gap-4 border-b border-secondary-soft pb-6 dark:border-line">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-3xl font-bold leading-none">{{ __('navigation.screens.users') }}</h1>
                <span class="inline-flex min-w-7 items-center justify-center rounded-md bg-primary px-2 py-0.5 text-xs font-bold tabular-nums text-white dark:bg-white/10 dark:text-ink" data-test="users-count">{{ $people->total() }}</span>
            </div>
            <p class="mt-2 text-sm text-secondary-medium dark:text-ink-muted">{{ __('master.decision.users') }}</p>
        </div>
    </header>

    @if ($notice)
        <div class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" role="status" data-test="notice">{{ $notice }}</div>
    @endif

    <div class="grid min-w-0 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <x-ui.input type="search" icon="magnifying-glass" wire:model.live.debounce.350ms="search" :label="__('master.search')" :placeholder="__('master.search')" clearable />

        <x-ui.header-filter-select id="users-role-filter" :label="__('master.col.role')" wire:model.live="role" :value="$role">
            <option value="">{{ __('master.users.all_roles') }}</option>
            @foreach ($roles as $roleOption)
                <option value="{{ $roleOption->getKey() }}">{{ $roleOption->name }}</option>
            @endforeach
        </x-ui.header-filter-select>

        <x-ui.header-filter-select id="users-status-filter" :label="__('master.col.state')" wire:model.live="status" :value="$status">
            <option value="">{{ __('master.users.all_states') }}</option>
            <option value="active">{{ __('master.users.active') }}</option>
            <option value="inactive">{{ __('master.users.inactive') }}</option>
        </x-ui.header-filter-select>
    </div>

    <div class="grid min-w-0 gap-6 xl:grid-cols-[21rem_minmax(0,1fr)]">
        <section class="flex min-w-0 flex-col overflow-hidden rounded-xl border border-secondary-soft bg-white shadow-card dark:border-transparent dark:bg-surface-1 dark:shadow-raised">
            <div class="max-h-[min(62vh,44rem)] divide-y divide-secondary-soft overflow-y-auto overscroll-contain dark:divide-line" data-test="users-table">
                @forelse ($people as $person)
                    @php($selected = $editing === $person->getKey())
                    <{{ $canEdit ? 'button type=button' : 'div' }}
                        @if ($canEdit) wire:click="edit({{ $person->getKey() }})" data-test="change-{{ $person->getKey() }}" @endif
                        wire:key="user-{{ $person->getKey() }}"
                        data-test="user-{{ $person->getKey() }}"
                        @class([
                            'group relative flex min-h-[4.5rem] w-full items-center gap-3 px-3 py-2.5 text-start transition',
                            'bg-secondary-soft/60 dark:bg-brand/10' => $selected,
                            'bg-white dark:bg-surface-1' => ! $selected,
                            'hover:bg-secondary-soft/40 dark:hover:bg-surface-2' => $canEdit && ! $selected,
                        ])
                    >
                        @if ($selected)
                            <span class="absolute inset-y-2 start-0 w-0.5 rounded-full bg-primary dark:bg-brand" aria-hidden="true"></span>
                        @endif

                        <span class="min-w-0 flex-1">
                            <span @class([
                                'block truncate text-sm font-bold',
                                'text-primary dark:text-brand-text' => $selected,
                                'text-secondary-dark dark:text-ink' => ! $selected,
                            ])>{{ $person->username }}</span>
                            <span class="mt-1 flex flex-wrap gap-1">
                                <span class="rounded-md border border-secondary-soft bg-white px-1.5 py-0.5 text-[0.65rem] font-semibold leading-none text-secondary-medium dark:border-line dark:bg-surface-1 dark:text-ink-muted">{{ $person->roleRecord?->name ?? '—' }}</span>
                                <span class="rounded-md border border-secondary-soft bg-white px-1.5 py-0.5 text-[0.65rem] font-semibold leading-none text-secondary-medium dark:border-line dark:bg-surface-1 dark:text-ink-muted">{{ $person->isActive() ? __('master.users.active') : __('master.users.inactive') }}</span>
                            </span>
                        </span>
                    </{{ $canEdit ? 'button' : 'div' }}>
                @empty
                    <x-ui.empty-state icon="users" :heading="__('master.empty')" />
                @endforelse
            </div>

            <div class="border-t border-secondary-soft px-3 pb-3 dark:border-line">
                {{ $people->links() }}
            </div>
        </section>

        <section class="flex min-w-0 flex-col rounded-xl border border-secondary-soft bg-white shadow-card dark:border-transparent dark:bg-surface-1 dark:shadow-raised">
            @if ($editing !== null)
                <form wire:submit="save" data-test="password-form">
                    <div class="border-b border-secondary-soft px-6 py-4 dark:border-line">
                        <h2 class="truncate text-lg font-bold text-secondary-dark dark:text-ink">{{ __('master.users.for', ['name' => $editingName]) }}</h2>
                    </div>

                    <div class="flex flex-col gap-4 p-6">
                        @if ($failures !== [])
                            <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="failures">
                                @foreach ($failures as $message)
                                    <p>{{ $message }}</p>
                                @endforeach
                            </div>
                        @endif
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.form-input type="password" wire:model="password" :label="__('master.users.new_password')" autocomplete="new-password" :help="__('master.users.help')" />
                            <x-ui.form-input type="password" wire:model="confirmation" :label="__('master.users.confirm')" autocomplete="new-password" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-secondary-soft px-6 py-3 dark:border-line">
                        <x-ui.button type="button" wire:click="cancel" data-test="cancel-password">{{ __('master.users.cancel') }}</x-ui.button>
                        <x-ui.button type="submit" tone="primary" data-test="save-password">{{ __('master.users.save') }}</x-ui.button>
                    </div>
                </form>
            @else
                <x-ui.empty-state icon="user" :heading="__('master.users.select')" :body="$canEdit ? __('master.users.select_help') : null" class="min-h-[clamp(22rem,55vh,38rem)] flex-1" />
            @endif
        </section>
    </div>
</div>
