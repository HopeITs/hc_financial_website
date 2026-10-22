<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.projects')" :subtitle="__('projects.intro')" :count="$projects->total()" />

    <x-ui.panel class="p-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5" data-test="filters">
            <x-ui.form-input wire:model.live.debounce.300ms="search" :label="__('projects.search')" :placeholder="__('projects.search')" />

            <x-ui.form-select wire:model.live="cityId" :label="__('home.city')" :placeholder="__('home.all_cities')">
                <option value="">{{ __('home.all_cities') }}</option>
                @foreach ($cities as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </x-ui.form-select>

            <x-ui.form-select wire:model.live="program" :label="__('programs.program')" :placeholder="__('projects.all_programs')">
                <option value="">{{ __('projects.all_programs') }}</option>
                @foreach ($programs as $key => $name)
                    <option value="{{ $key }}">{{ $name }}</option>
                @endforeach
            </x-ui.form-select>

            <x-ui.form-select wire:model.live="donorId" :label="__('projects.donor')" :placeholder="__('projects.all_donors')">
                <option value="">{{ __('projects.all_donors') }}</option>
                @foreach ($donors as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </x-ui.form-select>

            <x-ui.form-select wire:model.live="status" :label="__('programs.status_label')" :placeholder="__('projects.all_statuses')">
                <option value="">{{ __('projects.all_statuses') }}</option>
                <option value="active">{{ __('projects.status.active') }}</option>
                <option value="closed">{{ __('projects.status.closed') }}</option>
            </x-ui.form-select>
        </div>
    </x-ui.panel>

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
