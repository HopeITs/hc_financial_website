<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.projects')" :subtitle="__('projects.intro')" :count="$projects->total()">
        @if ($canCreate)
            <x-slot:actions>
                <x-ui.button tone="primary" icon="plus" href="{{ route('projects.new') }}" wire:navigate data-test="new-project">{{ __('projects.manage.new') }}</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if (session('status'))
        <div class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" role="status" data-test="notice">{{ session('status') }}</div>
    @endif

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

    <x-ui.scroll-table :paginate="false">
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
    </x-ui.scroll-table>

    {{ $projects->links() }}

    @if ($canCreate)
        <section class="flex flex-col gap-3" data-test="ledger-projects">
            <flux:heading level="2">{{ __('projects.manage.ledger_projects') }}</flux:heading>
            <x-ui.scroll-table :paginate="false">
                <table class="fin-table">
                    <thead>
                        <tr>
                            <th>{{ __('projects.manage.code') }}</th>
                            <th>{{ __('projects.manage.name') }}</th>
                            <th>{{ __('projects.donor') }}</th>
                            <th>{{ __('projects.manage.start_date') }}</th>
                            <th>{{ __('projects.manage.end_date') }}</th>
                            <th class="fin-num">{{ __('projects.manage.line_name') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ledgerProjects as $ledgerProject)
                            <tr wire:key="ledger-{{ $ledgerProject->code }}">
                                <td class="font-medium text-secondary-dark dark:text-ink">{{ $ledgerProject->code }}</td>
                                <td>{{ $ledgerProject->name }}</td>
                                <td>{{ $ledgerProject->donor }}</td>
                                <td>{{ $ledgerProject->start_date?->toDateString() }}</td>
                                <td>{{ $ledgerProject->end_date?->toDateString() }}</td>
                                <td class="fin-num">{{ $ledgerProject->budget_lines_count }}</td>
                                <td>
                                    @if ($canEdit)
                                        <a href="{{ route('projects.edit', $ledgerProject->code) }}" wire:navigate class="hover:underline" data-test="edit-{{ $ledgerProject->code }}">{{ __('projects.manage.edit') }}</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.scroll-table>
        </section>
    @endif
</div>
