<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ __('navigation.screens.programs') }}</flux:heading>
            <flux:subheading>{{ __('programs.intro') }}</flux:subheading>
        </div>

        @if ($canCreate)
            <flux:button variant="primary" icon="plus" wire:click="openForm" data-test="add-program">{{ __('programs.add') }}</flux:button>
        @endif
    </div>

    @if ($notice)
        <div class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" role="status" data-test="notice">{{ $notice }}</div>
    @endif

    @if ($duplicateWarning)
        <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200" role="alert" data-test="duplicate-warning">{{ __('programs.duplicate_warning') }}</div>
    @endif

    @if ($showForm && $canCreate)
        <form wire:submit="save" class="flex flex-col gap-4 rounded-xl border border-secondary-soft bg-white p-4 shadow-card dark:border-line dark:bg-surface-1" data-test="program-form">
            <flux:heading level="2">{{ __('programs.add') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="nameEn" :label="__('programs.name_en')" dir="ltr" required />
                <flux:input wire:model="nameAr" :label="__('programs.name_ar')" dir="rtl" required />
            </div>

            <flux:textarea wire:model="description" :label="__('programs.description')" rows="3" />

            <div class="w-full sm:w-64">
                <flux:select wire:model="status" :label="__('programs.status_label')">
                    @foreach ($statuses as $status)
                        <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">{{ __('programs.save') }}</flux:button>
                <flux:button type="button" wire:click="cancel">{{ __('programs.cancel') }}</flux:button>
            </div>
        </form>
    @endif

    <div class="overflow-x-auto rounded-xl border border-secondary-soft bg-white shadow-card dark:border-line dark:bg-surface-1">
        <table class="w-full text-sm" data-test="programs-table">
            <thead class="bg-secondary-soft/40 text-start text-xs uppercase text-secondary-medium dark:bg-white/[0.04] dark:text-ink-muted">
                <tr>
                    <th class="px-4 py-2 text-start font-medium">{{ __('programs.program') }}</th>
                    <th class="px-4 py-2 text-end font-medium">{{ __('programs.active_projects') }}</th>
                    <th class="px-4 py-2 text-end font-medium">{{ __('home.balance_syp') }}</th>
                    <th class="px-4 py-2 text-end font-medium">{{ __('home.balance_usd') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-secondary-soft dark:divide-line">
                @forelse ($rows as $row)
                    <tr wire:key="program-{{ $row->key() }}">
                        <td class="px-4 py-2 font-medium text-secondary-dark dark:text-ink">
                            {{ $row->name }}
                            @if ($row->isWeb())
                                <flux:badge size="sm" color="blue" class="ms-2" data-test="web-badge">{{ __('programs.added_on_web') }}</flux:badge>
                                @if ($row->status === 'inactive')
                                    <flux:badge size="sm" class="ms-1">{{ __('programs.status.inactive') }}</flux:badge>
                                @endif
                                @if ($row->description)
                                    <span class="block text-xs font-normal text-secondary-medium dark:text-ink-muted">{{ $row->description }}</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ $row->activeProjects }}</td>
                        <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ number_format((float) $row->syp, 2) }}</td>
                        <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ number_format((float) $row->usd, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-secondary-medium dark:text-ink-muted">{{ __('programs.none') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <section class="flex flex-col gap-3">
        <flux:heading level="2">{{ __('programs.link_title') }}</flux:heading>
        <flux:text>{{ __('programs.link_intro') }}</flux:text>

        @if ($canCreate)
            <form wire:submit="link" class="grid items-end gap-4 sm:grid-cols-[1fr_1fr_auto]" data-test="link-form">
                <flux:select wire:model="linkProject" :label="__('programs.project')" placeholder="{{ __('programs.choose') }}">
                    @foreach ($projects as $project)
                        <flux:select.option :value="$project['id']">{{ $project['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="linkProgram" :label="__('programs.program')" placeholder="{{ __('programs.choose') }}">
                    @foreach ($options as $key => $name)
                        <flux:select.option :value="$key">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:button type="submit" variant="primary">{{ __('programs.link') }}</flux:button>
            </form>
        @endif

        <div class="overflow-x-auto rounded-xl border border-secondary-soft bg-white shadow-card dark:border-line dark:bg-surface-1">
            <table class="w-full text-sm" data-test="projects-table">
                <thead class="bg-secondary-soft/40 text-start text-xs uppercase text-secondary-medium dark:bg-white/[0.04] dark:text-ink-muted">
                    <tr>
                        <th class="px-4 py-2 text-start font-medium">{{ __('programs.project') }}</th>
                        <th class="px-4 py-2 text-start font-medium">{{ __('programs.program') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary-soft dark:divide-line">
                    @foreach ($projects as $project)
                        <tr wire:key="project-{{ $project['id'] }}">
                            <td class="px-4 py-2 text-secondary-dark dark:text-ink">{{ $project['name'] }}</td>
                            <td class="px-4 py-2 text-secondary-medium dark:text-ink-muted">{{ $project['program'] }}</td>
                            <td class="px-4 py-2 text-end">
                                @if ($canCreate && $project['linked'])
                                    <button type="button" wire:click="unlink({{ $project['id'] }})" class="text-xs text-primary hover:underline dark:text-ink-muted">{{ __('programs.unlink') }}</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
