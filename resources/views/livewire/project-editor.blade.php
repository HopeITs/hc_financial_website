<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" x-data="unsavedGuard" data-unsaved-message="{{ __('drafts.leave_warning') }}" x-on:form-clean.window="clean()">
    <x-ui.page-header :title="$isEditing ? __('projects.manage.edit_title') : __('projects.manage.new_title')" :subtitle="__('projects.manage.intro')" />

    <x-ui.form-draft :saved-at="$draftSavedAt" :notice="$draftNotice" />

    @if ($notice)
        <div class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" role="status" data-test="notice">{{ $notice }}</div>
    @endif

    @unless ($isEditing)
        <x-ui.panel :title="__('projects.manage.import_title')" class="p-0">
            <form wire:submit="importSheet" class="flex flex-col gap-3 p-4" data-test="import-form">
                <flux:text>{{ __('projects.manage.import_help') }}</flux:text>
                <div class="flex flex-wrap items-center gap-3">
                    <input type="file" wire:model="sheet" accept=".xlsx" class="text-sm" data-test="sheet" />
                    <x-ui.button type="submit" tone="primary" data-test="import">{{ __('projects.manage.import') }}</x-ui.button>
                </div>
                @error('sheet') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                @if ($importError)
                    <p class="text-sm text-red-600" role="alert" data-test="import-error">{{ $importError }}</p>
                @endif
            </form>
        </x-ui.panel>
    @endunless

    <form wire:submit="save" class="flex flex-col gap-6" data-test="project-form">
        @foreach ($projects as $p => $project)
            <x-ui.panel :title="$project['code'] !== '' ? $project['code'].' - '.$project['name'] : __('projects.manage.project')" class="p-0" wire:key="project-{{ $p }}">
                <div class="flex flex-col gap-4 p-4" data-test="project-{{ $p }}">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.form-input wire:model="projects.{{ $p }}.code" :label="__('projects.manage.code')" :disabled="$isEditing" maxlength="16" dir="ltr" required />
                        <x-ui.form-input wire:model="projects.{{ $p }}.name" :label="__('projects.manage.name')" class="lg:col-span-3" required />
                        <x-ui.form-select wire:model="projects.{{ $p }}.donor" :label="__('projects.donor')">
                            <option value=""></option>
                            @foreach ($donors as $donor)
                                <option value="{{ $donor }}">{{ $donor }}</option>
                            @endforeach
                        </x-ui.form-select>
                        <x-ui.form-select wire:model="projects.{{ $p }}.restriction" :label="__('projects.manage.restriction')">
                            @foreach ($restrictions as $restriction)
                                <option value="{{ $restriction }}">{{ $restriction }}</option>
                            @endforeach
                        </x-ui.form-select>
                        <x-ui.form-input wire:model="projects.{{ $p }}.city" :label="__('home.city')" />
                        <x-ui.form-input wire:model="projects.{{ $p }}.contract_number" :label="__('projects.manage.contract_number')" />
                        <x-ui.form-select wire:model="projects.{{ $p }}.donor_currency" :label="__('projects.manage.donor_currency')">
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency }}">{{ $currency }}</option>
                            @endforeach
                        </x-ui.form-select>
                        <x-ui.form-input wire:model="projects.{{ $p }}.donor_rate" type="number" step="0.000001" min="0" :label="__('projects.manage.donor_rate')" />
                        <x-ui.form-input wire:model="projects.{{ $p }}.start_date" type="date" :label="__('projects.manage.start_date')" />
                        <x-ui.form-input wire:model="projects.{{ $p }}.end_date" type="date" :label="__('projects.manage.end_date')" :help="__('projects.manage.end_help')" />
                    </div>

                    <x-ui.scroll-table :paginate="false">
                        <table class="fin-table" data-test="lines-{{ $p }}">
                            <thead>
                                <tr>
                                    <th>{{ __('projects.manage.line_name') }}</th>
                                    <th>{{ __('projects.manage.line_type') }}</th>
                                    <th>{{ __('projects.manage.line_account') }}</th>
                                    <th>{{ __('projects.donor') }}</th>
                                    <th class="fin-num">{{ __('projects.manage.line_budget') }}</th>
                                    <th>{{ __('projects.currency') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($project['lines'] as $l => $line)
                                    <tr wire:key="line-{{ $p }}-{{ $l }}">
                                        <td>
                                            <div class="form-box"><input type="text" wire:model="projects.{{ $p }}.lines.{{ $l }}.name" class="form-box-input" /></div>
                                            @error("projects.$p.lines.$l.name") <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                        </td>
                                        <td class="text-xs text-secondary-medium dark:text-ink-muted">{{ $line['type'] }}</td>
                                        <td>
                                            <div class="form-box">
                                                <x-ui.form-select bare wire:model="projects.{{ $p }}.lines.{{ $l }}.account_code" :label="__('projects.manage.choose_account')" data-test="account-{{ $p }}-{{ $l }}">
                                                    <option value="">{{ __('projects.manage.choose_account') }}</option>
                                                    @foreach ($accounts as $account)
                                                        <option value="{{ $account->code }}">{{ $account->code }} - {{ $account->name_en }}</option>
                                                    @endforeach
                                                </x-ui.form-select>
                                            </div>
                                            @error("projects.$p.lines.$l.account_code") <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                        </td>
                                        <td>
                                            <div class="form-box">
                                                <x-ui.form-select bare wire:model="projects.{{ $p }}.lines.{{ $l }}.donor" :label="__('projects.donor')">
                                                    <option value=""></option>
                                                    @foreach ($donors as $donor)
                                                        <option value="{{ $donor }}">{{ $donor }}</option>
                                                    @endforeach
                                                </x-ui.form-select>
                                            </div>
                                            @error("projects.$p.lines.$l.donor") <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                        </td>
                                        <td class="fin-num">
                                            <div class="form-box"><input type="number" step="0.01" min="0" wire:model="projects.{{ $p }}.lines.{{ $l }}.budget" class="form-box-input tabular-nums" /></div>
                                            @error("projects.$p.lines.$l.budget") <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                        </td>
                                        <td>
                                            <div class="form-box">
                                                <x-ui.form-select bare wire:model="projects.{{ $p }}.lines.{{ $l }}.currency" :label="__('projects.manage.donor_currency')">
                                                    @foreach ($currencies as $currency)
                                                        <option value="{{ $currency }}">{{ $currency }}</option>
                                                    @endforeach
                                                </x-ui.form-select>
                                            </div>
                                        </td>
                                        <td>
                                            @if (blank($line['key']))
                                                <x-ui.button type="button" size="sm" wire:click="removeLine({{ $p }}, {{ $l }})">{{ __('projects.manage.remove') }}</x-ui.button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="fin-empty">{{ __('projects.none_budget') }}</td></tr>
                                @endforelse
                            </tbody>
                            @if (count($project['lines']) > 0)
                                <tfoot>
                                    <tr>
                                        <th colspan="4">{{ __('projects.total') }}</th>
                                        <th class="fin-num" data-test="total-{{ $p }}"><x-money :amount="collect($project['lines'])->sum(fn ($line) => (float) $line['budget'])" /></th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </x-ui.scroll-table>

                    <div class="flex flex-wrap gap-2">
                        <x-ui.button type="button" icon="plus" wire:click="addLine({{ $p }})" data-test="add-line-{{ $p }}">{{ __('projects.manage.add_line') }}</x-ui.button>
                        @if (! $isEditing && count($projects) > 1)
                            <x-ui.button type="button" wire:click="removeProject({{ $p }})">{{ __('projects.manage.remove_project') }}</x-ui.button>
                        @endif
                    </div>

                    @error("projects.$p.code") <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                    @error("projects.$p.name") <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                    @error("projects.$p.end_date") <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
            </x-ui.panel>
        @endforeach

        <div class="flex flex-wrap gap-2">
            <x-ui.button type="submit" tone="primary" x-on:click="leaving()" data-test="save">{{ __('projects.manage.save') }}</x-ui.button>
            <x-ui.button type="button" wire:click="saveDraft" data-test="save-draft">{{ __('drafts.save') }}</x-ui.button>
            @unless ($isEditing)
                <x-ui.button type="button" wire:click="addProject">{{ __('projects.manage.add_project') }}</x-ui.button>
            @endunless
            <x-ui.button type="button" href="{{ route(\App\Support\Navigation::routeName('projects')) }}" wire:navigate>{{ __('projects.manage.cancel') }}</x-ui.button>
        </div>
    </form>
</div>
