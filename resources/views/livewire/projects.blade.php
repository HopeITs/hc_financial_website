<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.projects')" :subtitle="__('projects.intro')" :count="$projects->total()" />

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

    <div class="fin-table-wrap">
        <table class="fin-table" data-test="projects-table">
            <thead>
                <tr>
                    <th>{{ __('home.project') }}</th>
                    <th>{{ __('home.city') }}</th>
                    <th>{{ __('programs.program') }}</th>
                    <th>{{ __('projects.donor') }}</th>
                    <th>{{ __('programs.status_label') }}</th>
                    @if ($showMoney)
                        <th class="fin-num">{{ __('home.balance_syp') }}</th>
                        <th class="fin-num">{{ __('home.balance_usd') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($projects as $project)
                    <tr wire:key="project-{{ $project->accountId }}">
                        <td class="font-medium text-secondary-dark dark:text-ink">
                            <a href="{{ route('projects.show', $project->accountId) }}" wire:navigate class="hover:underline">{{ $project->name }}</a>
                        </td>
                        <td>{{ $project->cityName }}</td>
                        <td>{{ $project->programName }}</td>
                        <td>{{ $project->donors }}</td>
                        <td><flux:badge size="sm" :color="$project->closed ? 'zinc' : 'green'">{{ $project->closed ? __('projects.status.closed') : __('projects.status.active') }}</flux:badge></td>
                        @if ($showMoney)
                            @php($balance = $balances->get($project->accountId))
                            <td class="fin-num"><x-money :amount="($balance?->syp ?? 0)" /></td>
                            <td class="fin-num"><x-money :amount="($balance?->usd ?? 0)" /></td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showMoney ? 7 : 5 }}" class="fin-empty">{{ __('projects.none') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $projects->links() }}
</div>
