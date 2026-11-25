@php
    $actionLabel = fn (string $slug): string => $slug === 'home' || $slug === 'project-money' ? __('master.users.screens.'.$slug) : __('navigation.screens.'.$slug);
    // Two letters for a person's badge: the first letters of the first two words of their name.
    $initials = function (?string $name): string {
        $words = preg_split('/[\s._-]+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];

        return mb_strtoupper(mb_substr($words[0], 0, 1).(isset($words[1]) ? mb_substr($words[1], 0, 1) : ''));
    };
    $displayName = fn ($person): string => $profiles && filled($person->profile?->full_name) ? (string) $person->profile->full_name : $person->username;
@endphp

<div class="ud">
<style>
    .ud { display: flex; flex-direction: column; gap: 1.25rem; padding: 0 1rem 2.5rem; }
    @media (min-width: 1024px) { .ud { padding-inline: 2rem; } }
    .ud-tabs { display: inline-flex; gap: .25rem; padding: .25rem; border: 1px solid var(--color-line); border-radius: .625rem; background: var(--color-surface-2); }
    .ud-tab { min-height: 2.25rem; padding: 0 1rem; border-radius: .5rem; font-size: .875rem; font-weight: 600; color: var(--color-ink-muted); }
    .ud-tab:hover { color: var(--color-ink); }
    .ud-tab.is-on { background: var(--color-surface-1); color: var(--color-brand); box-shadow: 0 1px 2px rgb(0 0 0 / .2); }
    .ud-stats { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); }
    .ud-stat { display: flex; flex-direction: column; gap: .125rem; padding: .875rem 1rem; border: 1px solid var(--color-line); border-radius: .75rem; background: var(--color-surface-1); }
    .ud-stat b { font-size: 1.5rem; font-weight: 700; color: var(--color-ink); font-variant-numeric: tabular-nums; }
    .ud-stat span { font-size: .75rem; color: var(--color-ink-muted); }
    .ud-filters { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); }
    .ud-split { display: grid; gap: 1.25rem; grid-template-columns: minmax(0, 1fr); align-items: start; }
    @media (min-width: 1100px) { .ud-split { grid-template-columns: 22rem minmax(0, 1fr); } }
    .ud-card { min-width: 0; border: 1px solid var(--color-line); border-radius: .875rem; background: var(--color-surface-1); overflow: hidden; }
    .ud-list { max-height: min(66vh, 46rem); overflow-y: auto; overscroll-behavior: contain; }
    .ud-row { position: relative; display: flex; align-items: center; gap: .75rem; width: 100%; padding: .75rem .875rem; text-align: start; border-bottom: 1px solid var(--color-line); background: transparent; transition: background-color .15s; }
    .ud-row:hover { background: var(--color-surface-2); }
    .ud-row.is-on { background: color-mix(in srgb, var(--color-brand) 10%, transparent); }
    .ud-row.is-on::before { content: ''; position: absolute; inset-block: .5rem; inset-inline-start: 0; width: 3px; border-radius: 3px; background: var(--color-brand); }
    .ud-avatar { position: relative; flex: none; display: inline-flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; border-radius: 999px; background: color-mix(in srgb, var(--color-brand) 14%, var(--color-surface-2)); color: var(--color-brand); font-size: .8125rem; font-weight: 700; }
    .ud-avatar.is-big { width: 3.75rem; height: 3.75rem; font-size: 1.125rem; }
    .ud-dot { position: absolute; inset-block-end: 0; inset-inline-end: 0; width: .65rem; height: .65rem; border: 2px solid var(--color-surface-1); border-radius: 999px; background: var(--color-status-good); }
    .ud-dot.is-off { background: var(--color-ink-muted); }
    .ud-grow { flex: 1 1 0; min-width: 0; }
    .ud-name { display: block; font-size: .875rem; font-weight: 700; color: var(--color-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ud-sub { display: block; font-size: .75rem; color: var(--color-ink-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ud-row.is-on .ud-name { color: var(--color-brand); }
    .ud-chips { display: flex; flex-wrap: wrap; gap: .25rem; margin-top: .375rem; }
    .ud-chip { padding: .125rem .45rem; border: 1px solid var(--color-line); border-radius: 999px; font-size: .675rem; font-weight: 600; line-height: 1.3; color: var(--color-ink-muted); }
    .ud-chip.is-main { border-color: color-mix(in srgb, var(--color-brand) 40%, transparent); color: var(--color-brand); }
    .ud-foot { padding: .5rem .75rem; border-top: 1px solid var(--color-line); }
    .ud-head { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--color-line); background: linear-gradient(to bottom, color-mix(in srgb, var(--color-brand) 6%, transparent), transparent); }
    .ud-head h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--color-ink); }
    .ud-head p { margin: .125rem 0 0; font-size: .875rem; color: var(--color-ink-muted); }
    .ud-badge { display: inline-flex; align-items: center; gap: .35rem; padding: .125rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 600; background: color-mix(in srgb, var(--color-status-good) 14%, transparent); color: var(--color-status-good); }
    .ud-badge.is-off { background: var(--color-surface-2); color: var(--color-ink-muted); }
    .ud-panes { display: flex; gap: .25rem; padding: .5rem 1rem 0; border-bottom: 1px solid var(--color-line); overflow-x: auto; }
    .ud-pane { flex: none; min-height: 2.5rem; padding: 0 .875rem; border-bottom: 2px solid transparent; font-size: .8125rem; font-weight: 600; color: var(--color-ink-muted); }
    .ud-pane:hover { color: var(--color-ink); }
    .ud-pane.is-on { border-color: var(--color-brand); color: var(--color-brand); }
    .ud-body { display: flex; flex-direction: column; gap: 1.5rem; padding: 1.5rem; }
    fieldset.ud-body { min-width: 0; margin: 0; border: 0; }
    .ud-sec { display: flex; flex-direction: column; gap: .75rem; }
    .ud-sec + .ud-sec { padding-top: 1.25rem; border-top: 1px solid var(--color-line); }
    .ud-sec h3 { margin: 0; font-size: .875rem; font-weight: 700; color: var(--color-ink); }
    .ud-help { margin: 0; font-size: .75rem; line-height: 1.5; color: var(--color-ink-muted); }
    .ud-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); }
    .ud-checks { display: grid; gap: .5rem 1rem; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); }
    .ud-check { display: inline-flex; align-items: center; gap: .5rem; padding: .375rem .5rem; border-radius: .5rem; font-size: .875rem; color: var(--color-ink); cursor: pointer; }
    .ud-check:hover { background: var(--color-surface-2); }
    .ud-check input { width: 1rem; height: 1rem; accent-color: var(--color-primary); }
    .ud-tools { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .ud-perms { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr)); }
    .ud-group { min-width: 0; margin: 0; padding: .875rem; border: 1px solid var(--color-line); border-radius: .625rem; }
    .ud-group-head { display: flex; align-items: center; gap: .5rem; margin-bottom: .375rem; }
    .ud-group-head strong { flex: 1; font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--color-ink-muted); }
    .ud-link { font-size: .7rem; font-weight: 600; color: var(--color-brand); }
    .ud-link:hover { text-decoration: underline; }
    .ud-screen { display: flex; flex-wrap: wrap; align-items: center; gap: .25rem .875rem; padding-block: .3rem; }
    .ud-screen + .ud-screen { border-top: 1px solid color-mix(in srgb, var(--color-line) 60%, transparent); }
    .ud-screen > span { flex: 0 0 10rem; font-size: .8125rem; font-weight: 500; color: var(--color-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ud-act { display: inline-flex; align-items: center; gap: .25rem; font-size: .75rem; color: var(--color-ink-muted); cursor: pointer; }
    .ud-act input { width: .875rem; height: .875rem; accent-color: var(--color-primary); }
    .ud-alert { margin: 1rem 1.5rem 0; padding: .5rem 1rem; border: 1px solid var(--color-status-critical); border-radius: .5rem; font-size: .875rem; color: var(--color-status-critical); }
    .ud-alert p { margin: 0; }
    .ud-info { padding: .5rem 1rem; border: 1px solid var(--color-line); border-radius: .5rem; background: var(--color-surface-2); font-size: .8125rem; color: var(--color-ink-muted); }
    .ud-note { padding: .5rem 1rem; border: 1px solid var(--color-status-good); border-radius: .5rem; background: color-mix(in srgb, var(--color-status-good) 10%, var(--color-surface-1)); font-size: .875rem; }
    .ud-rolescreens { display: grid; gap: .5rem; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); }
    .ud-rs { display: flex; flex-wrap: wrap; align-items: center; gap: .375rem; padding: .5rem .75rem; border: 1px solid var(--color-line); border-radius: .5rem; }
    .ud-rs > span:first-child { margin-inline-end: auto; font-size: .875rem; font-weight: 500; }
    .ud-muted { font-size: .875rem; color: var(--color-ink-muted); }
    .ud-stack { display: grid; gap: 1rem; }
    .ud-empty { min-height: 22rem; }
</style>
    <x-ui.page-header :title="__('navigation.screens.users')" :count="$tab === 'users' ? $people->total() : count($roleRows)" :subtitle="__('master.decision.users')">
        <x-slot:actions>
            @if ($canCreate)
                <x-ui.button tone="primary" icon="user-plus" wire:click="openCreate" data-test="add-user">{{ __('master.users.add') }}</x-ui.button>
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
        <div class="ud-stats" data-test="users-stats">
            <div class="ud-stat"><b>{{ $counts['all'] }}</b><span>{{ __('master.users.stat_all') }}</span></div>
            <div class="ud-stat"><b>{{ $counts['active'] }}</b><span>{{ __('master.users.active') }}</span></div>
            <div class="ud-stat"><b>{{ $counts['all'] - $counts['active'] }}</b><span>{{ __('master.users.inactive') }}</span></div>
        </div>

        <div class="ud-filters">
            <x-ui.input type="search" icon="magnifying-glass" wire:model.live.debounce.350ms="search" :label="__('master.search')" :placeholder="__('master.users.search_hint')" clearable />

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
                            <span class="ud-avatar" aria-hidden="true">{{ $initials($displayName($person)) }}<span @class(['ud-dot', 'is-off' => ! $person->isActive()])></span></span>
                            <span class="ud-grow">
                                <span class="ud-name">{{ $displayName($person) }}</span>
                                <span class="ud-sub" dir="auto">{{ '@'.$person->username }}@if ($profiles && filled($person->profile?->job_title)) · {{ $person->profile->job_title }}@endif</span>
                                <span class="ud-chips">
                                    <span class="ud-chip is-main">{{ $person->roleRecord?->name ?? '—' }}</span>
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
                    <div x-data="{ pane: 'profile' }" wire:key="picked-{{ $picked->getKey() }}">
                        <div class="ud-head">
                            <span class="ud-avatar is-big" aria-hidden="true">{{ $initials($fullName !== '' ? $fullName : $picked->username) }}</span>
                            <div class="ud-grow">
                                <h2 data-test="picked-name">{{ $fullName !== '' ? $fullName : $picked->username }}</h2>
                                <p dir="auto">{{ '@'.$picked->username }}@if ($jobTitle !== '') · {{ $jobTitle }}@endif</p>
                                <div class="ud-chips">
                                    <span @class(['ud-badge', 'is-off' => ! $picked->isActive()])>{{ $picked->isActive() ? __('master.users.active') : __('master.users.inactive') }}</span>
                                    @foreach ($pickedRoles as $heldRole)
                                        <span class="ud-chip">{{ $heldRole->label() }}</span>
                                    @endforeach
                                </div>
                            </div>
                            @if ($canDelete && $picked->getKey() !== auth()->id())
                                <x-ui.button tone="ghost" icon="trash" wire:click="confirmDelete({{ $picked->getKey() }})" data-test="delete-user">{{ __('master.users.delete') }}</x-ui.button>
                            @endif
                        </div>

                        <nav class="ud-panes" aria-label="{{ __('master.users.sections') }}">
                            @foreach (['profile', 'access', 'projects', 'permissions', 'security'] as $pane)
                                <button type="button" x-on:click="pane = '{{ $pane }}'" :class="{ 'is-on': pane === '{{ $pane }}' }" class="ud-pane" data-test="pane-{{ $pane }}">{{ __('master.users.panes.'.$pane) }}</button>
                            @endforeach
                        </nav>

                        @if ($failures !== [])
                            <div class="ud-alert" role="alert" data-test="failures">
                                @foreach ($failures as $message)
                                    <p>{{ $message }}</p>
                                @endforeach
                            </div>
                        @endif

                        {{-- 1. Profile: sign-in name and details --}}
                        <form wire:submit="saveProfile" x-show="pane === 'profile'" data-test="profile-form">
                            <fieldset class="ud-body" @disabled(! $canEdit)>
                                <section class="ud-sec">
                                    <h3>{{ __('master.users.profile') }}</h3>
                                    <p class="ud-help">{{ __('master.users.profile_help') }}</p>
                                    <div class="ud-grid">
                                        <x-ui.form-input wire:model="username" :label="__('master.col.username')" dir="auto" required :help="__('master.users.username_help')" data-test="profile-username" />
                                        <x-ui.form-input wire:model="fullName" :label="__('master.users.full_name')" dir="auto" :disabled="! $profiles" data-test="profile-full-name" />
                                        <x-ui.form-input wire:model="jobTitle" :label="__('master.users.job_title')" dir="auto" :disabled="! $profiles" data-test="profile-job" />
                                        <x-ui.form-input type="email" wire:model="email" :label="__('master.users.email')" dir="ltr" :disabled="! $profiles" data-test="profile-email" />
                                        <x-ui.form-input wire:model="phone" :label="__('master.users.phone')" dir="ltr" :disabled="! $profiles" data-test="profile-phone" />
                                        <x-ui.form-select wire:model="city" :label="__('master.users.city')" :placeholder="__('master.users.no_city')" :disabled="! $profiles" data-test="profile-city">
                                            <option value="">{{ __('master.users.no_city') }}</option>
                                            @foreach ($cities as $cityName)
                                                <option value="{{ $cityName }}">{{ $cityName }}</option>
                                            @endforeach
                                        </x-ui.form-select>
                                    </div>
                                    <x-ui.form-textarea wire:model="profileNotes" :label="__('master.users.notes')" rows="2" :disabled="! $profiles" />
                                    @unless ($profiles)
                                        <p class="ud-info">{{ __('master.users.profiles_pending') }}</p>
                                    @endunless
                                </section>
                            </fieldset>
                            @if ($canEdit)
                                <x-ui.action-bar>
                                    <x-ui.button type="submit" tone="primary" icon="check" x-on:click="leaving()" data-test="save-profile">{{ __('master.users.save_profile') }}</x-ui.button>
                                </x-ui.action-bar>
                            @endif
                        </form>

                        {{-- 2–4. Roles and state, projects, permissions: saved together --}}
                        <form wire:submit="saveAccess" x-show="['access', 'projects', 'permissions'].includes(pane)" data-test="access-form">
                            <fieldset class="ud-body" @disabled(! $canEdit)>
                                <div x-show="pane === 'access'" class="ud-stack">
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
                                </div>

                                <section class="ud-sec" x-show="pane === 'projects'" x-data="{ find: '' }" data-test="user-projects">
                                    <h3>{{ __('master.users.projects') }} <span class="ud-chip">{{ count($projectCodes) }} / {{ count($projectChoices) }}</span></h3>
                                    @if ($neverLimited)
                                        <p class="ud-help">{{ __('master.users.projects_never') }}</p>
                                    @elseif (! $seesAllProjects)
                                        <p class="ud-help">{{ __('master.users.projects_help') }}</p>
                                    @elseif ($switchAvailable)
                                        <label class="ud-check" data-test="limit-projects">
                                            <input type="checkbox" wire:model.live="limitProjects" data-test="limit-projects-box">
                                            <span><strong>{{ __('master.users.limit_projects') }}</strong> — {{ $limitProjects ? __('master.users.projects_help') : __('master.users.projects_all') }}</span>
                                        </label>
                                    @else
                                        <p class="ud-help">{{ __('master.users.projects_all') }}</p>
                                    @endif
                                    <div class="ud-tools">
                                        <div class="ud-grow"><x-ui.input type="search" icon="magnifying-glass" x-model="find" :label="__('master.users.find_project')" :placeholder="__('master.users.find_project')" /></div>
                                        <button type="button" class="ud-link" wire:click="$set('projectCodes', @js(array_keys($projectChoices)))" data-test="projects-all">{{ __('master.users.select_all') }}</button>
                                        <button type="button" class="ud-link" wire:click="$set('projectCodes', [])" data-test="projects-none">{{ __('master.users.select_none') }}</button>
                                    </div>
                                    <div class="ud-checks">
                                        @foreach ($projectChoices as $code => $name)
                                            <label class="ud-check" wire:key="project-{{ $code }}" x-show="find === '' || @js(mb_strtolower($code.' '.$name)).includes(find.toLowerCase())">
                                                <input type="checkbox" wire:model="projectCodes" value="{{ $code }}" data-test="project-{{ $code }}">
                                                <span dir="auto">{{ $code }} · {{ $name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </section>

                                <section class="ud-sec" x-show="pane === 'permissions'">
                                    <h3>{{ __('master.users.direct') }}</h3>
                                    <p class="ud-help">{{ __('master.users.direct_help') }} {{ __('master.users.deny_help') }}</p>
                                    @if ($denied !== [])
                                        <p class="ud-info" data-test="denied-count">{{ trans_choice('master.users.denied_count', count($denied), ['count' => count($denied)]) }}</p>
                                    @endif

                                    <div class="ud-perms">
                                        @foreach ($groups as $group => $screens)
                                            <fieldset class="ud-group" wire:key="group-{{ $group }}">
                                                <div class="ud-group-head">
                                                    <strong>{{ $group === 'general' ? __('master.users.general') : __('navigation.groups.'.$group) }}</strong>
                                                    @if ($canEdit)
                                                        <button type="button" class="ud-link" wire:click="grantGroup(@js($group), true)" data-test="grant-{{ $group }}">{{ __('master.users.select_all') }}</button>
                                                        <button type="button" class="ud-link" wire:click="grantGroup(@js($group), false)" data-test="clear-{{ $group }}">{{ __('master.users.select_none') }}</button>
                                                    @endif
                                                </div>
                                                @foreach ($screens as $slug => $actions)
                                                    <div class="ud-screen" wire:key="screen-{{ $slug }}">
                                                        <span>{{ $actionLabel($slug) }}</span>
                                                        @foreach ($actions as $action)
                                                            @php($key = $slug.'.'.$action)
                                                            <label class="ud-act">
                                                                @if (isset($inherited[$key]))
                                                                    {{-- From a role: unticking takes it from this person only. --}}
                                                                    <input type="checkbox" @checked(! in_array($key, $denied, true)) wire:click="toggleDenied(@js($key))" title="{{ __('master.users.from_role_click') }}" @disabled(! $canEdit) data-test="role-perm-{{ $key }}">
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
                                <x-ui.action-bar>
                                    <x-ui.button type="button" wire:click="saveDraft" data-test="save-draft">{{ __('drafts.save') }}</x-ui.button>
                                    <x-ui.button type="submit" tone="primary" icon="check" x-on:click="leaving()" data-test="save-access">{{ __('master.users.save_access') }}</x-ui.button>
                                </x-ui.action-bar>
                            @endif
                        </form>

                        {{-- 5. Password --}}
                        <div x-show="pane === 'security'">
                            @if ($canEdit)
                                <form wire:submit="save" class="ud-body" data-test="password-form">
                                    <section class="ud-sec">
                                        <h3>{{ __('master.users.for', ['name' => $picked->username]) }}</h3>
                                        <p class="ud-help">{{ __('master.users.help') }}</p>
                                        <div class="ud-grid">
                                            <x-ui.form-input type="password" wire:model="password" :label="__('master.users.new_password')" autocomplete="new-password" />
                                            <x-ui.form-input type="password" wire:model="confirmation" :label="__('master.users.confirm')" autocomplete="new-password" />
                                        </div>
                                        <div><x-ui.button type="submit" tone="primary" icon="key" x-on:click="leaving()" data-test="save-password">{{ __('master.users.save') }}</x-ui.button></div>
                                    </section>
                                </form>
                            @else
                                <p class="ud-body ud-muted">{{ __('master.users.no_edit') }}</p>
                            @endif
                        </div>
                    </div>
                @else
                    <x-ui.empty-state icon="user" :heading="__('master.users.select')" :body="__('master.users.select_help')" class="ud-empty" />
                @endif
            </section>
        </div>
    @else
        <div class="ud-split">
            <section class="ud-card">
                <div data-test="roles-list">
                    @foreach ($roleRows as $row)
                        <button type="button" wire:click="selectRole({{ $row['role']->value }})" wire:key="role-{{ $row['role']->value }}" data-test="role-{{ $row['role']->value }}" @class(['ud-row', 'is-on' => $selectedRoleId === $row['role']->value])>
                            <span class="ud-avatar" aria-hidden="true">{{ $initials($row['role']->label()) }}</span>
                            <span class="ud-grow">
                                <span class="ud-name">{{ $row['role']->label() }}</span>
                                <span class="ud-chips">
                                    <span class="ud-chip">{{ __('master.users.people_count', ['count' => $row['people']]) }}</span>
                                    <span class="ud-chip">{{ __('master.users.screens_count', ['count' => $row['screens']]) }}</span>
                                    @if ($row['changed'])
                                        <span class="ud-chip is-main">{{ __('master.users.role_changed') }}</span>
                                    @endif
                                </span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </section>

            <section class="ud-card" data-test="role-detail">
                @if ($roleDetail)
                    <div class="ud-head">
                        <span class="ud-avatar is-big" aria-hidden="true">{{ $initials($roleDetail['role']->label()) }}</span>
                        <div class="ud-grow">
                            <h2>{{ $roleDetail['role']->label() }}</h2>
                            <p>{{ $roleDetail['role']->isWebOnly() ? __('master.users.web_only') : __('master.users.from_old_program') }}</p>
                            <div class="ud-chips">
                                <span @class(['ud-badge', 'is-off' => ! $roleChanged]) data-test="role-state">{{ $roleChanged ? __('master.users.role_changed') : __('master.users.role_default') }}</span>
                            </div>
                        </div>
                        @if ($canEditRoles && $roleChanged)
                            <x-ui.button tone="ghost" icon="arrow-uturn-left" wire:click="resetRole" wire:confirm="{{ __('master.users.role_reset_confirm') }}" data-test="reset-role">{{ __('master.users.role_reset_button') }}</x-ui.button>
                        @endif
                    </div>

                    @if ($failures !== [])
                        <div class="ud-alert" role="alert" data-test="role-failures">
                            @foreach ($failures as $message)
                                <p>{{ $message }}</p>
                            @endforeach
                        </div>
                    @endif

                    @if ($canEditRoles && $roleDetail['role'] !== \App\Enums\RoleId::SuperUser)
                        {{-- What the role allows, changed here for everyone who holds it. --}}
                        <form wire:submit="saveRole" data-test="role-form">
                            <div class="ud-body">
                                <section class="ud-sec">
                                    <h3>{{ __('master.users.role_edit') }}</h3>
                                    <p class="ud-help">{{ __('master.users.role_edit_help') }}</p>
                                    <div class="ud-tools">
                                        <button type="button" class="ud-link" wire:click="$set('rolePermissions', @js(collect($groups)->flatMap(fn ($screens, $group) => collect($screens)->flatMap(fn ($actions, $slug) => array_map(fn ($action) => $slug.'.'.$action, $actions)))->values()->all()))" data-test="role-all">{{ __('master.users.select_all') }}</button>
                                        <button type="button" class="ud-link" wire:click="$set('rolePermissions', [])" data-test="role-none">{{ __('master.users.select_none') }}</button>
                                    </div>
                                    <div class="ud-perms">
                                        @foreach ($groups as $group => $screens)
                                            <fieldset class="ud-group" wire:key="role-group-{{ $group }}">
                                                <div class="ud-group-head"><strong>{{ $group === 'general' ? __('master.users.general') : __('navigation.groups.'.$group) }}</strong></div>
                                                @foreach ($screens as $slug => $actions)
                                                    <div class="ud-screen" wire:key="role-screen-{{ $slug }}">
                                                        <span>{{ $actionLabel($slug) }}</span>
                                                        @foreach ($actions as $action)
                                                            <label class="ud-act">
                                                                <input type="checkbox" wire:model="rolePermissions" value="{{ $slug.'.'.$action }}" data-test="role-{{ $slug.'.'.$action }}">
                                                                {{ __('master.users.actions.'.$action) }}
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                @endforeach
                                            </fieldset>
                                        @endforeach
                                    </div>
                                </section>
                            </div>
                            <x-ui.action-bar>
                                <x-ui.button type="submit" tone="primary" icon="check" wire:confirm="{{ __('master.users.role_save_confirm', ['count' => count($roleDetail['people'])]) }}" data-test="save-role">{{ __('master.users.role_save') }}</x-ui.button>
                            </x-ui.action-bar>
                        </form>
                    @endif

                    <div class="ud-body">
                        <section class="ud-sec">
                            <h3>{{ __('master.users.role_people') }}</h3>
                            <div class="ud-chips">
                                @forelse ($roleDetail['people'] as $name)
                                    <span class="ud-chip">{{ $name }}</span>
                                @empty
                                    <span class="ud-muted">{{ __('master.empty') }}</span>
                                @endforelse
                            </div>
                        </section>

                        <section class="ud-sec" @if ($canEditRoles && $roleDetail['role'] !== \App\Enums\RoleId::SuperUser) hidden @endif>
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
            <div class="ud-stack">
                <x-ui.form-input wire:model="newUsername" :label="__('master.col.username')" required autofocus data-test="new-username" />
                <x-ui.form-input type="password" wire:model="newPassword" :label="__('master.users.new_password')" autocomplete="new-password" :help="__('master.users.help')" required data-test="new-password" />
                <x-ui.form-select wire:model="newRoleId" :label="__('master.col.role')" :placeholder="__('master.users.pick_role')" required data-test="new-role">
                    @foreach ($legacyRoles as $legacyRole)
                        <option value="{{ $legacyRole->value }}">{{ $legacyRole->label() }}</option>
                    @endforeach
                </x-ui.form-select>
                @if ($profiles)
                    <x-ui.form-input wire:model="newFullName" :label="__('master.users.full_name')" dir="auto" data-test="new-full-name" />
                    <x-ui.form-input wire:model="newJobTitle" :label="__('master.users.job_title')" dir="auto" />
                    <x-ui.form-input type="email" wire:model="newEmail" :label="__('master.users.email')" dir="ltr" />
                @endif
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
            <div class="ud-alert" role="alert" data-test="delete-error">{{ $deleteError }}</div>
        @endif
    </x-ui.confirm-modal>
</div>
