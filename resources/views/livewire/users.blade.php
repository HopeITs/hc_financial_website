<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.users')" :subtitle="__('master.decision.users')" />

    @if ($notice)
        <div class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" role="status" data-test="notice">{{ $notice }}</div>
    @endif

    <div class="grid items-end gap-4 sm:grid-cols-3">
        <x-ui.form-input wire:model.live.debounce.400ms="search" :label="__('master.search')" />
    </div>

    @if ($editing !== null)
        <x-ui.panel class="flex flex-col gap-4 p-4" data-test="password-form">
            <h2 class="text-base font-semibold">{{ __('master.users.for', ['name' => $editingName]) }}</h2>
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
            <div class="flex gap-3">
                <x-ui.button tone="primary" wire:click="save" data-test="save-password">{{ __('master.users.save') }}</x-ui.button>
                <x-ui.button wire:click="cancel" data-test="cancel-password">{{ __('master.users.cancel') }}</x-ui.button>
            </div>
        </x-ui.panel>
    @endif

    <x-ui.scroll-table :paginate="false">
        <table class="fin-table" data-test="users-table">
            <thead>
                <tr>
                    <th>{{ __('master.col.username') }}</th>
                    <th>{{ __('master.col.role') }}</th>
                    <th>{{ __('master.col.state') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($people as $person)
                    <tr wire:key="user-{{ $person->getKey() }}" data-test="user-{{ $person->getKey() }}">
                        <td>{{ $person->username }}</td>
                        <td>{{ $person->roleRecord?->name ?? '—' }}</td>
                        <td>{{ $person->isActive() ? __('master.users.active') : __('master.users.inactive') }}</td>
                        <td>
                            @if ($canEdit)
                                <x-ui.button wire:click="edit({{ $person->getKey() }})" data-test="change-{{ $person->getKey() }}">{{ __('master.users.change') }}</x-ui.button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-secondary-medium dark:text-ink-muted">{{ __('master.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.scroll-table>

    {{ $people->links() }}
</div>
