<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('navigation.screens.projects') }}</flux:heading>
        <flux:subheading>{{ __('projects.intro') }}</flux:subheading>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" data-test="filters">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('projects.search')" icon="magnifying-glass" />

        <flux:select wire:model.live="cityId" :placeholder="__('home.all_cities')">
            <flux:select.option value="">{{ __('home.all_cities') }}</flux:select.option>
            @foreach ($cities as $id => $name)
                <flux:select.option :value="$id">{{ $name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="program" :placeholder="__('projects.all_programs')">
            <flux:select.option value="">{{ __('projects.all_programs') }}</flux:select.option>
            @foreach ($programs as $key => $name)
                <flux:select.option :value="$key">{{ $name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="donorId" :placeholder="__('projects.all_donors')">
            <flux:select.option value="">{{ __('projects.all_donors') }}</flux:select.option>
            @foreach ($donors as $id => $name)
                <flux:select.option :value="$id">{{ $name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="status" :placeholder="__('projects.all_statuses')">
            <flux:select.option value="">{{ __('projects.all_statuses') }}</flux:select.option>
            <flux:select.option value="active">{{ __('projects.status.active') }}</flux:select.option>
            <flux:select.option value="closed">{{ __('projects.status.closed') }}</flux:select.option>
        </flux:select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-secondary-soft bg-white shadow-card dark:border-line dark:bg-surface-1">
        <table class="w-full text-sm" data-test="projects-table">
            <thead class="bg-secondary-soft/40 text-start text-xs uppercase text-secondary-medium dark:bg-white/[0.04] dark:text-ink-muted">
                <tr>
                    <th class="px-4 py-2 text-start font-medium">{{ __('home.project') }}</th>
                    <th class="px-4 py-2 text-start font-medium">{{ __('home.city') }}</th>
                    <th class="px-4 py-2 text-start font-medium">{{ __('programs.program') }}</th>
                    <th class="px-4 py-2 text-start font-medium">{{ __('projects.donor') }}</th>
                    <th class="px-4 py-2 text-start font-medium">{{ __('programs.status_label') }}</th>
                    @if ($showMoney)
                        <th class="px-4 py-2 text-end font-medium">{{ __('home.balance_syp') }}</th>
                        <th class="px-4 py-2 text-end font-medium">{{ __('home.balance_usd') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-secondary-soft dark:divide-line">
                @forelse ($projects as $project)
                    <tr wire:key="project-{{ $project->accountId }}">
                        <td class="px-4 py-2 font-medium text-secondary-dark dark:text-ink">
                            <a href="{{ route('projects.show', $project->accountId) }}" wire:navigate class="hover:underline">{{ $project->name }}</a>
                        </td>
                        <td class="px-4 py-2">{{ $project->cityName }}</td>
                        <td class="px-4 py-2">{{ $project->programName }}</td>
                        <td class="px-4 py-2">{{ $project->donors }}</td>
                        <td class="px-4 py-2">{{ $project->closed ? __('projects.status.closed') : __('projects.status.active') }}</td>
                        @if ($showMoney)
                            @php($balance = $balances->get($project->accountId))
                            <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ number_format((float) ($balance?->syp ?? 0), 2) }}</td>
                            <td class="px-4 py-2 text-end tabular-nums" dir="ltr">{{ number_format((float) ($balance?->usd ?? 0), 2) }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showMoney ? 7 : 5 }}" class="px-4 py-8 text-center text-secondary-medium dark:text-ink-muted">{{ __('projects.none') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $projects->links() }}
</div>
