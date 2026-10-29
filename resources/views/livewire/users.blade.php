@php
    $actionLabel = fn (string $slug): string => $slug === 'home' || $slug === 'project-money' ? __('master.users.screens.'.$slug) : __('navigation.screens.'.$slug);
@endphp


<div class="ud">
<style>
    .ud { display: flex; flex-direction: column; gap: 1.5rem; padding: 0 1rem 2.5rem; }
    @media (min-width: 1024px) { .ud { padding-inline: 2rem; } }
    .ud-tabs { display: inline-flex; gap: .25rem; padding: .25rem; border: 1px solid var(--color-line); border-radius: .5rem; background: var(--color-surface-2); }
    .ud-tab { min-height: 2.25rem; padding: 0 1rem; border-radius: .375rem; font-size: .875rem; font-weight: 600; color: var(--color-ink-muted); }
    .ud-tab:hover { color: var(--color-ink); }
    .ud-tab.is-on { background: var(--color-surface-1); color: var(--color-brand); box-shadow: 0 1px 2px rgb(0 0 0 / .25); }
    .ud-filters { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); }
    .ud-split { display: grid; gap: 1.5rem; grid-template-columns: minmax(0, 1fr); align-items: start; }
    @media (min-width: 1100px) { .ud-split { grid-template-columns: 21rem minmax(0, 1fr); } }
    .ud-card { min-width: 0; border: 1px solid var(--color-line); border-radius: .75rem; background: var(--color-surface-1); overflow: hidden; }
    .ud-list { max-height: min(62vh, 44rem); overflow-y: auto; overscroll-behavior: contain; }
    .ud-row { position: relative; display: flex; align-items: center; width: 100%; min-height: 4.5rem; padding: .625rem .875rem; text-align: start; border-bottom: 1px solid var(--color-line); background: transparent; transition: background-color .15s; }
    .ud-row:hover { background: var(--color-surface-2); }
    .ud-row.is-on { background: color-mix(in srgb, var(--color-brand) 12%, transparent); }
    .ud-row.is-on::before { content: ''; position: absolute; inset-block: .5rem; inset-inline-start: 0; width: 2px; border-radius: 2px; background: var(--color-brand); }
    .ud-name { display: block; font-size: .875rem; font-weight: 700; color: var(--color-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ud-row.is-on .ud-name { color: var(--color-brand); }
    .ud-chips { display: flex; flex-wrap: wrap; gap: .25rem; margin-top: .375rem; }
    .ud-chip { padding: .125rem .375rem; border: 1px solid var(--color-line); border-radius: .375rem; font-size: .65rem; font-weight: 600; line-height: 1.25; color: var(--color-ink-muted); }
    .ud-foot { padding: .5rem .75rem; border-top: 1px solid var(--color-line); }
    .ud-head { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; padding: 1rem 1.5rem; border-bottom: 1px solid var(--color-line); }
    .ud-head h2 { margin: 0; font-size: 1.125rem; font-weight: 700; color: var(--color-ink); }
    .ud-head p { margin: .125rem 0 0; font-size: .875rem; color: var(--color-ink-muted); }
    .ud-grow { flex: 1 1 0; min-width: 0; }
    .ud-body { display: flex; flex-direction: column; gap: 1.5rem; padding: 1.5rem; }
    .ud-sec { display: flex; flex-direction: column; gap: .75rem; }
    .ud-sec + .ud-sec { padding-top: 1.25rem; border-top: 1px solid var(--color-line); }
    .ud-sec h3 { margin: 0; font-size: .875rem; font-weight: 700; color: var(--color-ink); }
    .ud-help { margin: 0; font-size: .75rem; line-height: 1.5; color: var(--color-ink-muted); }
    .ud-checks { display: grid; gap: .625rem 1rem; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); }
    .ud-check { display: inline-flex; align-items: center; gap: .5rem; font-size: .875rem; color: var(--color-ink); cursor: pointer; }
    .ud-check input { width: 1rem; height: 1rem; accent-color: var(--color-primary); }
    .ud-perms { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr)); }
    .ud-group { min-width: 0; margin: 0; padding: .875rem; border: 1px solid var(--color-line); border-radius: .5rem; }
    .ud-group legend { padding: 0 .25rem; font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--color-ink-muted); }
    .ud-screen { display: flex; flex-wrap: wrap; align-items: center; gap: .25rem .875rem; padding-block: .3rem; }
    .ud-screen + .ud-screen { border-top: 1px solid color-mix(in srgb, var(--color-line) 60%, transparent); }
    .ud-screen > span { flex: 0 0 10rem; font-size: .8125rem; font-weight: 500; color: var(--color-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ud-act { display: inline-flex; align-items: center; gap: .25rem; font-size: .75rem; color: var(--color-ink-muted); cursor: pointer; }
    .ud-act input { width: .875rem; height: .875rem; accent-color: var(--color-primary); }
    .ud-bar { display: flex; justify-content: flex-end; gap: .75rem; padding: .75rem 1.5rem; border-top: 1px solid var(--color-line); }
    .ud-pass { display: flex; flex-direction: column; gap: 1rem; padding: 1.5rem; border-top: 1px solid var(--color-line); }
    .ud-pass h3 { margin: 0; font-size: .875rem; font-weight: 700; }
    .ud-two { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); }
    .ud-alert { margin: 1.5rem 1.5rem 0; padding: .5rem 1rem; border: 1px solid var(--color-status-critical); border-radius: .5rem; font-size: .875rem; color: var(--color-status-critical); }
    .ud-note { padding: .5rem 1rem; border: 1px solid var(--color-line); border-radius: .5rem; background: var(--color-surface-1); font-size: .875rem; }
    .ud-rolescreens { display: grid; gap: .5rem; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); }
    .ud-rs { display: flex; flex-wrap: wrap; align-items: center; gap: .375rem; padding: .5rem .75rem; border: 1px solid var(--color-line); border-radius: .5rem; }
    .ud-rs > span:first-child { margin-inline-end: auto; font-size: .875rem; font-weight: 500; }
    .ud-muted { font-size: .875rem; color: var(--color-ink-muted); }
</style>
    <x-ui.page-header :title="__('navigation.screens.users')" :count="$tab === 'users' ? $people->total() : count($roleRows)" :subtitle="__('master.decision.users')">
        <x-slot:actions>
            @if ($canCreate)
                <x-ui.button tone="primary" wire:click="openCreate" data-test="add-user">{{ __('master.users.add') }}</x-ui.button>
            @endif

            <nav class="ud-tabs" aria-label="{{ __('navigation.screens.users') }}">
                <button type="button" wire:click="showTab('users')" data-test="tab-users" @class(['ud-tab', 'is-on' => $tab === 'users'])>{{ __('master.users.tab_users') }}</button>
                <button type="button" wire:click="showTab('roles')" data-test="tab-roles" @class(['ud-tab', 'is-on' => $tab === 'roles'])>{{ __('master.users.tab_roles') }}</button>
            </nav>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.form-draft :saved-at="$draftSavedAt" :notice="$draftNotice" />

    @if ($notice)
        <div class="ud-note" role="status" data-test="notice">{{ $notice }}</div>
    @endif

    @if ($tab === 'users')
        <div class="ud-filters">
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

        <div class="ud-split">
            <section class="ud-card">
                <div class="ud-list" data-test="users-table">
                    @forelse ($people as $person)
                        <button type="button" wire:click="selectUser({{ $person->getKey() }})" wire:key="user-{{ $person->getKey() }}" data-test="user-{{ $person->getKey() }}" @class(['ud-row', 'is-on' => $editing === $person->getKey()])>
                            <span class="ud-grow">
                                <span class="ud-name">{{ $person->username }}</span>
                                <span class="ud-chips">
                                    <span class="ud-chip">{{ $person->roleRecord?->name ?? '—' }}</span>
                                    @foreach ($person->webRoles->take(1) as $webRole)
                                        <span class="ud-chip">{{ $webRole->role_id->label() }}</span>
                                    @endforeach
                                    @if ($person->webRoles->count() > 1)
                                        <span class="ud-chip">+{{ $person->webRoles->count() - 1 }}</span>
                                    @endif
                                    @unless ($person->isActive())
                                        <span class="ud-chip">{{ __('master.users.inactive') }}</span>
                                    @endunless
                                </span>
                            </span>
                        </button>
                    @empty
                        <x-ui.empty-state icon="users" :heading="__('master.empty')" />
                    @endforelse
                </div>

                <div class="ud-foot">{{ $people->links() }}</div>
            </section>

            <section class="ud-card" x-data="unsavedGuard" data-unsaved-message="{{ __('drafts.leave_warning') }}" x-on:form-clean.window="clean()">
                @if ($picked)
                    <div class="ud-head">
                        <div class="ud-grow">
                            <h2 data-test="picked-name">{{ $picked->username }}</h2>
                            <p>{{ $picked->isActive() ? __('master.users.active') : __('master.users.inactive') }}</p>
                        </div>
                        @if ($canDelete && $picked->getKey() !== auth()->id())
                            <x-ui.button wire:click="confirmDelete({{ $picked->getKey() }})" data-test="delete-user">{{ __('master.users.delete') }}</x-ui.button>
                        @endif
                    </div>

                    @if ($failures !== [])
                        <div class="ud-alert" role="alert" data-test="failures">
                            @foreach ($failures as $message)
                                <p style="margin:0">{{ $message }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form wire:submit="saveAccess" data-test="access-form">
                        <fieldset class="ud-body" style="border:0;margin:0;min-width:0" @disabled(! $canEdit)>
                            <section class="ud-sec">
                                <h3>{{ __('master.users.primary_role') }}</h3>
                                <x-ui.form-select wire:model.live="primaryRoleId" :label="__('master.users.primary_role')" :disabled="! $canEdit" :help="__('master.users.primary_help')" data-test="primary-role">
                                    @foreach ($legacyRoles as $legacyRole)
                                        <option value="{{ $legacyRole->value }}">{{ $legacyRole->label() }}</option>
                                    @endforeach
                                </x-ui.form-select>
                            </section>

                            <section class="ud-sec">
                                <h3>{{ __('master.users.extra_roles') }}</h3>
                                <p class="ud-help">{{ __('master.users.extra_help') }}</p>
                                <div class="ud-checks">
                                    @foreach (\App\Enums\RoleId::cases() as $roleCase)
                                        @continue($roleCase->value === $primaryRoleId)
                                        <label class="ud-check" wire:key="extra-role-{{ $roleCase->value }}">
                                            <input type="checkbox" wire:model.live="extraRoleIds" value="{{ $roleCase->value }}" data-test="extra-role-{{ $roleCase->value }}">
                                            {{ $roleCase->label() }}
                                        </label>
                                    @endforeach
                                </div>
                            </section>

                            <section class="ud-sec">
                                <h3>{{ __('master.col.state') }}</h3>
                                <label class="ud-check">
                                    <input type="checkbox" wire:model="active" data-test="active">
                                    {{ __('master.users.can_sign_in') }}
                                </label>
                            </section>

                            <section class="ud-sec">
                                <h3>{{ __('master.users.direct') }}</h3>
                                <p class="ud-help">{{ __('master.users.direct_help') }}</p>

                                <div class="ud-perms">
                                    @foreach ($groups as $group => $screens)
                                        <fieldset class="ud-group" wire:key="group-{{ $group }}">
                                            <legend>{{ $group === 'general' ? __('master.users.general') : __('navigation.groups.'.$group) }}</legend>
                                            @foreach ($screens as $slug => $actions)
                                                <div class="ud-screen" wire:key="screen-{{ $slug }}">
                                                    <span>{{ $actionLabel($slug) }}</span>
                                                    @foreach ($actions as $action)
                                                        @php($key = $slug.'.'.$action)
                                                        <label class="ud-act">
                                                            @if (isset($inherited[$key]))
                                                                <input type="checkbox" checked disabled title="{{ __('master.users.from_role') }}">
                                                            @else
                                                                <input type="checkbox" wire:model="permissions" value="{{ $key }}" data-test="perm-{{ $key }}">
                                                            @endif
                                                            {{ __('master.users.actions.'.$action) }}
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        </fieldset>
                                    @endforeach
                                </div>
                            </section>
                        </fieldset>

                        @if ($canEdit)
                            <div class="ud-bar">
                                <x-ui.button type="submit" tone="primary" x-on:click="leaving()" data-test="save-access">{{ __('master.users.save_access') }}</x-ui.button>
                                <x-ui.button type="button" wire:click="saveDraft" data-test="save-draft">{{ __('drafts.save') }}</x-ui.button>
                            </div>
                        @endif
                    </form>

                    @if ($canEdit)
                        <form wire:submit="save" class="ud-pass" data-test="password-form">
                            <h3>{{ __('master.users.for', ['name' => $picked->username]) }}</h3>
                            <div class="ud-two">
                                <x-ui.form-input type="password" wire:model="password" :label="__('master.users.new_password')" autocomplete="new-password" :help="__('master.users.help')" />
                                <x-ui.form-input type="password" wire:model="confirmation" :label="__('master.users.confirm')" autocomplete="new-password" />
                            </div>
                            <div>
                                <x-ui.button type="submit" x-on:click="leaving()" data-test="save-password">{{ __('master.users.save') }}</x-ui.button>
                            </div>
                        </form>
                    @endif
                @else
                    <x-ui.empty-state icon="user" :heading="__('master.users.select')" :body="__('master.users.select_help')" style="min-height: 22rem" />
                @endif
            </section>
        </div>
    @else
        <div class="ud-split">
            <section class="ud-card">
                <div data-test="roles-list">
                    @foreach ($roleRows as $row)
                        <button type="button" wire:click="selectRole({{ $row['role']->value }})" wire:key="role-{{ $row['role']->value }}" data-test="role-{{ $row['role']->value }}" @class(['ud-row', 'is-on' => $selectedRoleId === $row['role']->value])>
                            <span class="ud-grow">
                                <span class="ud-name">{{ $row['role']->label() }}</span>
                                <span class="ud-chips">
                                    <span class="ud-chip">{{ __('master.users.people_count', ['count' => $row['people']]) }}</span>
                                    <span class="ud-chip">{{ __('master.users.screens_count', ['count' => $row['screens']]) }}</span>
                                </span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </section>

            <section class="ud-card" data-test="role-detail">
                @if ($roleDetail)
                    <div class="ud-head">
                        <div class="ud-grow">
                            <h2>{{ $roleDetail['role']->label() }}</h2>
                            <p>{{ $roleDetail['role']->isWebOnly() ? __('master.users.web_only') : __('master.users.from_old_program') }}</p>
                        </div>
                    </div>

                    <div class="ud-body">
                        <section class="ud-sec">
                            <h3>{{ __('master.users.role_people') }}</h3>
                            <div class="ud-chips" style="margin:0">
                                @forelse ($roleDetail['people'] as $name)
                                    <span class="ud-chip">{{ $name }}</span>
                                @empty
                                    <span class="ud-muted">{{ __('master.empty') }}</span>
                                @endforelse
                            </div>
                        </section>

                        <section class="ud-sec">
                            <h3>{{ __('master.users.role_screens') }}</h3>
                            <div class="ud-rolescreens">
                                @foreach ($roleDetail['screens'] as $slug => $actions)
                                    <div class="ud-rs">
                                        <span>{{ $actionLabel($slug) }}</span>
                                        @foreach ($actions as $action)
                                            <span class="ud-chip">{{ __('master.users.actions.'.$action) }}</span>
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

    <div x-data="unsavedGuard" data-unsaved-message="{{ __('drafts.leave_warning') }}" x-on:form-clean.window="clean()">
    <x-ui.modal wire:model="showCreate" size="md" :title="__('master.users.add')" :description="__('master.users.add_help')" submit="createUser" close="closeCreate" required-note>
        @if ($showCreate)
            <div style="display:grid;gap:1rem">
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
            <x-ui.button type="button" wire:click="saveDraft" data-test="save-draft-create">{{ __('drafts.save') }}</x-ui.button>
            <x-ui.button type="submit" tone="primary" x-on:click="leaving()" data-test="create-user">{{ __('master.users.add') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>
    </div>

    <x-ui.confirm-modal wire:model="showDelete" close="closeDelete" :title="__('master.users.delete')" :message="__('master.users.delete_confirm', ['name' => $deletingName])" confirm="deleteUser" :confirm-label="__('master.users.delete')" confirm-test="confirm-delete-user">
        @if ($deleteError)
            <div class="ud-alert" style="margin:0" role="alert" data-test="delete-error">{{ $deleteError }}</div>
        @endif
    </x-ui.confirm-modal>
</div>
