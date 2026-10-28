@php
    $tabClasses = fn (bool $on): string => 'min-h-9 rounded-md px-4 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary '
        .($on
            ? 'bg-white text-primary shadow-sm dark:bg-surface-3 dark:text-brand-text dark:shadow-raised'
            : 'text-secondary-medium hover:text-secondary-dark dark:text-ink-muted dark:hover:text-ink');
    $chip = 'rounded-md border border-secondary-soft bg-white px-1.5 py-0.5 text-[0.65rem] font-semibold leading-none text-secondary-medium dark:border-line dark:bg-surface-1 dark:text-ink-muted';
    $panel = 'flex min-w-0 flex-col rounded-xl border border-secondary-soft bg-white shadow-card dark:border-transparent dark:bg-surface-1 dark:shadow-raised';
@endphp

<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <header class="flex flex-wrap items-center justify-between gap-4 border-b border-secondary-soft pb-6 dark:border-line">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-3xl font-bold leading-none">{{ __('navigation.screens.users') }}</h1>
                <span class="inline-flex min-w-7 items-center justify-center rounded-md bg-primary px-2 py-0.5 text-xs font-bold tabular-nums text-white dark:bg-white/10 dark:text-ink" data-test="users-count">{{ $tab === 'users' ? $people->total() : count($roleRows) }}</span>
            </div>
            <p class="mt-2 text-sm text-secondary-medium dark:text-ink-muted">{{ __('master.decision.users') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if ($canCreate)
                <x-ui.button tone="primary" wire:click="openCreate" data-test="add-user">{{ __('master.users.add') }}</x-ui.button>
            @endif

            <nav class="flex min-h-11 items-center gap-1 rounded-lg border border-secondary-soft bg-secondary-soft/40 p-1 dark:border-line dark:bg-surface-2" aria-label="{{ __('navigation.screens.users') }}">
                <button type="button" wire:click="showTab('users')" data-test="tab-users" class="{{ $tabClasses($tab === 'users') }}">{{ __('master.users.tab_users') }}</button>
                <button type="button" wire:click="showTab('roles')" data-test="tab-roles" class="{{ $tabClasses($tab === 'roles') }}">{{ __('master.users.tab_roles') }}</button>
            </nav>
        </div>
    </header>

    @if ($notice)
        <div class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" role="status" data-test="notice">{{ $notice }}</div>
    @endif

    @if ($tab === 'users')
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
            <section class="{{ $panel }} overflow-hidden">
                <div class="max-h-[min(62vh,44rem)] divide-y divide-secondary-soft overflow-y-auto overscroll-contain dark:divide-line" data-test="users-table">
                    @forelse ($people as $person)
                        @php($selected = $editing === $person->getKey())
                        <button type="button" wire:click="selectUser({{ $person->getKey() }})" wire:key="user-{{ $person->getKey() }}" data-test="user-{{ $person->getKey() }}" @class([
                            'group relative flex min-h-[4.5rem] w-full items-center gap-3 px-3 py-2.5 text-start transition focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-primary',
                            'bg-secondary-soft/60 dark:bg-brand/10' => $selected,
                            'bg-white hover:bg-secondary-soft/40 dark:bg-surface-1 dark:hover:bg-surface-2' => ! $selected,
                        ])>
                            @if ($selected)
                                <span class="absolute inset-y-2 start-0 w-0.5 rounded-full bg-primary dark:bg-brand" aria-hidden="true"></span>
                            @endif

                            <span class="min-w-0 flex-1">
                                <span @class(['block truncate text-sm font-bold', 'text-primary dark:text-brand-text' => $selected, 'text-secondary-dark dark:text-ink' => ! $selected])>{{ $person->username }}</span>
                                <span class="mt-1 flex flex-wrap gap-1">
                                    <span class="{{ $chip }}">{{ $person->roleRecord?->name ?? '—' }}</span>
                                    @foreach ($person->webRoles->take(1) as $webRole)
                                        <span class="{{ $chip }}">{{ $webRole->role_id->label() }}</span>
                                    @endforeach
                                    @if ($person->webRoles->count() > 1)
                                        <span class="{{ $chip }}">+{{ $person->webRoles->count() - 1 }}</span>
                                    @endif
                                    @unless ($person->isActive())
                                        <span class="{{ $chip }}">{{ __('master.users.inactive') }}</span>
                                    @endunless
                                </span>
                            </span>
                        </button>
                    @empty
                        <x-ui.empty-state icon="users" :heading="__('master.empty')" />
                    @endforelse
                </div>

                <div class="border-t border-secondary-soft px-3 pb-3 dark:border-line">
                    {{ $people->links() }}
                </div>
            </section>

            <section class="{{ $panel }}">
                @if ($picked)
                    <div class="flex flex-wrap items-center gap-3 border-b border-secondary-soft px-6 py-4 dark:border-line">
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate text-lg font-bold text-secondary-dark dark:text-ink" data-test="picked-name">{{ $picked->username }}</h2>
                            <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ $picked->isActive() ? __('master.users.active') : __('master.users.inactive') }}</p>
                        </div>
                        @if ($canDelete && $picked->getKey() !== auth()->id())
                            <x-ui.button wire:click="confirmDelete({{ $picked->getKey() }})" data-test="delete-user">{{ __('master.users.delete') }}</x-ui.button>
                        @endif
                    </div>

                    @if ($failures !== [])
                        <div class="mx-6 mt-6 rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="failures">
                            @foreach ($failures as $message)
                                <p>{{ $message }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form wire:submit="saveAccess" class="flex flex-col" data-test="access-form">
                        <fieldset class="space-y-8 p-6" @disabled(! $canEdit)>
                            <section aria-labelledby="primary-role-title">
                                <h3 id="primary-role-title" class="mb-3 text-sm font-bold">{{ __('master.users.primary_role') }}</h3>
                                <x-ui.form-select wire:model.live="primaryRoleId" :label="__('master.users.primary_role')" :disabled="! $canEdit" :help="__('master.users.primary_help')" data-test="primary-role">
                                    @foreach ($legacyRoles as $legacyRole)
                                        <option value="{{ $legacyRole->value }}">{{ $legacyRole->label() }}</option>
                                    @endforeach
                                </x-ui.form-select>
                            </section>

                            <section class="border-t border-secondary-soft pt-5 dark:border-line" aria-labelledby="extra-roles-title">
                                <h3 id="extra-roles-title" class="text-sm font-bold">{{ __('master.users.extra_roles') }}</h3>
                                <p class="mt-1 text-xs text-secondary-medium dark:text-ink-muted">{{ __('master.users.extra_help') }}</p>
                                <div class="mt-3 grid grid-cols-[repeat(auto-fit,minmax(min(100%,11rem),1fr))] gap-x-4 gap-y-2.5">
                                    @foreach (\App\Enums\RoleId::cases() as $roleCase)
                                        @continue($roleCase->value === $primaryRoleId)
                                        <label class="flex items-center gap-2 text-sm" wire:key="extra-role-{{ $roleCase->value }}">
                                            <input type="checkbox" class="size-4 accent-primary" wire:model.live="extraRoleIds" value="{{ $roleCase->value }}" data-test="extra-role-{{ $roleCase->value }}">
                                            {{ $roleCase->label() }}
                                        </label>
                                    @endforeach
                                </div>
                            </section>

                            <section class="border-t border-secondary-soft pt-5 dark:border-line" aria-labelledby="state-title">
                                <h3 id="state-title" class="mb-2 text-sm font-bold">{{ __('master.col.state') }}</h3>
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" class="size-4 accent-primary" wire:model="active" data-test="active">
                                    {{ __('master.users.can_sign_in') }}
                                </label>
                            </section>

                            <section class="border-t border-secondary-soft pt-5 dark:border-line" aria-labelledby="direct-title">
                                <h3 id="direct-title" class="text-sm font-bold">{{ __('master.users.direct') }}</h3>
                                <p class="mt-1 text-xs leading-5 text-secondary-medium dark:text-ink-muted">{{ __('master.users.direct_help') }}</p>

                                <div class="mt-4 grid grid-cols-[repeat(auto-fit,minmax(min(100%,20rem),1fr))] gap-3">
                                    @foreach ($groups as $group => $screens)
                                        <fieldset class="min-w-0 rounded-lg border border-secondary-soft p-3.5 dark:border-line" wire:key="group-{{ $group }}">
                                            <legend class="px-1 text-xs font-bold uppercase tracking-wide text-secondary-medium dark:text-ink-muted">{{ $group === 'general' ? __('master.users.general') : __('navigation.groups.'.$group) }}</legend>
                                            <div class="flex flex-col gap-2">
                                                @foreach ($screens as $slug => $actions)
                                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1" wire:key="screen-{{ $slug }}">
                                                        <span class="w-40 shrink-0 truncate text-sm font-medium">{{ $slug === 'home' || $slug === 'project-money' ? __('master.users.screens.'.$slug) : __('navigation.screens.'.$slug) }}</span>
                                                        @foreach ($actions as $action)
                                                            @php($key = $slug.'.'.$action)
                                                            <label class="flex items-center gap-1 text-xs text-secondary-medium dark:text-ink-muted">
                                                                @if (isset($inherited[$key]))
                                                                    <input type="checkbox" class="size-3.5" checked disabled title="{{ __('master.users.from_role') }}">
                                                                @else
                                                                    <input type="checkbox" class="size-3.5 accent-primary" wire:model="permissions" value="{{ $key }}" data-test="perm-{{ $key }}">
                                                                @endif
                                                                {{ __('master.users.actions.'.$action) }}
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                @endforeach
                                            </div>
                                        </fieldset>
                                    @endforeach
                                </div>
                            </section>
                        </fieldset>

                        @if ($canEdit)
                            <div class="sticky bottom-0 flex justify-end border-t border-secondary-soft bg-white/95 px-6 py-3 backdrop-blur-sm dark:border-line dark:bg-surface-1/95">
                                <x-ui.button type="submit" tone="primary" data-test="save-access">{{ __('master.users.save_access') }}</x-ui.button>
                            </div>
                        @endif
                    </form>

                    @if ($canEdit)
                        <form wire:submit="save" class="flex flex-col gap-4 border-t border-secondary-soft p-6 dark:border-line" data-test="password-form">
                            <h3 class="text-sm font-bold">{{ __('master.users.for', ['name' => $picked->username]) }}</h3>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-ui.form-input type="password" wire:model="password" :label="__('master.users.new_password')" autocomplete="new-password" :help="__('master.users.help')" />
                                <x-ui.form-input type="password" wire:model="confirmation" :label="__('master.users.confirm')" autocomplete="new-password" />
                            </div>
                            <div>
                                <x-ui.button type="submit" data-test="save-password">{{ __('master.users.save') }}</x-ui.button>
                            </div>
                        </form>
                    @endif
                @else
                    <x-ui.empty-state icon="user" :heading="__('master.users.select')" :body="__('master.users.select_help')" class="min-h-[clamp(22rem,55vh,38rem)] flex-1" />
                @endif
            </section>
        </div>
    @else
        <div class="grid min-w-0 gap-6 xl:grid-cols-[21rem_minmax(0,1fr)]">
            <section class="{{ $panel }} overflow-hidden">
                <div class="divide-y divide-secondary-soft dark:divide-line" data-test="roles-list">
                    @foreach ($roleRows as $row)
                        @php($on = $selectedRoleId === $row['role']->value)
                        <button type="button" wire:click="selectRole({{ $row['role']->value }})" wire:key="role-{{ $row['role']->value }}" data-test="role-{{ $row['role']->value }}" @class([
                            'relative flex min-h-[4.5rem] w-full items-center gap-3 px-3 py-2.5 text-start transition',
                            'bg-secondary-soft/60 dark:bg-brand/10' => $on,
                            'bg-white hover:bg-secondary-soft/40 dark:bg-surface-1 dark:hover:bg-surface-2' => ! $on,
                        ])>
                            @if ($on)
                                <span class="absolute inset-y-2 start-0 w-0.5 rounded-full bg-primary dark:bg-brand" aria-hidden="true"></span>
                            @endif
                            <span class="min-w-0 flex-1">
                                <span @class(['block truncate text-sm font-bold', 'text-primary dark:text-brand-text' => $on])>{{ $row['role']->label() }}</span>
                                <span class="mt-1 flex gap-1">
                                    <span class="{{ $chip }}">{{ __('master.users.people_count', ['count' => $row['people']]) }}</span>
                                    <span class="{{ $chip }}">{{ __('master.users.screens_count', ['count' => $row['screens']]) }}</span>
                                </span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </section>

            <section class="{{ $panel }}" data-test="role-detail">
                @if ($roleDetail)
                    <div class="border-b border-secondary-soft px-6 py-4 dark:border-line">
                        <h2 class="text-lg font-bold">{{ $roleDetail['role']->label() }}</h2>
                        <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ $roleDetail['role']->isWebOnly() ? __('master.users.web_only') : __('master.users.from_old_program') }}</p>
                    </div>

                    <div class="space-y-8 p-6">
                        <section>
                            <h3 class="mb-3 text-sm font-bold">{{ __('master.users.role_people') }}</h3>
                            <div class="flex flex-wrap gap-1.5">
                                @forelse ($roleDetail['people'] as $name)
                                    <span class="{{ $chip }}">{{ $name }}</span>
                                @empty
                                    <span class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('master.empty') }}</span>
                                @endforelse
                            </div>
                        </section>

                        <section class="border-t border-secondary-soft pt-5 dark:border-line">
                            <h3 class="mb-3 text-sm font-bold">{{ __('master.users.role_screens') }}</h3>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($roleDetail['screens'] as $slug => $actions)
                                    <div class="flex flex-wrap items-center gap-1.5 rounded-lg border border-secondary-soft px-3 py-2 dark:border-line">
                                        <span class="me-auto text-sm font-medium">{{ $slug === 'home' || $slug === 'project-money' ? __('master.users.screens.'.$slug) : __('navigation.screens.'.$slug) }}</span>
                                        @foreach ($actions as $action)
                                            <span class="{{ $chip }}">{{ __('master.users.actions.'.$action) }}</span>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    </div>
                @endif
            </section>
        </div>
    @endif

    <x-ui.modal wire:model="showCreate" size="md" :title="__('master.users.add')" :description="__('master.users.add_help')" submit="createUser" close="closeCreate" required-note>
        @if ($showCreate)
            <div class="grid gap-y-4">
                <x-ui.form-input wire:model="newUsername" :label="__('master.col.username')" required autofocus data-test="new-username" />
                <x-ui.form-input type="password" wire:model="newPassword" :label="__('master.users.new_password')" autocomplete="new-password" :help="__('master.users.help')" required data-test="new-password" />
                <x-ui.form-select wire:model="newRoleId" :label="__('master.col.role')" :placeholder="__('master.users.pick_role')" required data-test="new-role">
                    @foreach ($legacyRoles as $legacyRole)
                        <option value="{{ $legacyRole->value }}">{{ $legacyRole->label() }}</option>
                    @endforeach
                </x-ui.form-select>
            </div>
        @endif

        <x-slot:actions>
            <x-ui.button type="submit" tone="primary" data-test="create-user">{{ __('master.users.add') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.confirm-modal wire:model="showDelete" close="closeDelete" :title="__('master.users.delete')" :message="__('master.users.delete_confirm', ['name' => $deletingName])" confirm="deleteUser" :confirm-label="__('master.users.delete')" confirm-test="confirm-delete-user">
        @if ($deleteError)
            <div class="rounded-lg border border-primary bg-primary/5 p-3 text-sm text-status-critical" role="alert" data-test="delete-error">{{ $deleteError }}</div>
        @endif
    </x-ui.confirm-modal>
</div>
