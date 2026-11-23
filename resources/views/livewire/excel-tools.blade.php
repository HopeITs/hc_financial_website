{{-- The Excel buttons of a data screen and the import dialog (App\Livewire\ExcelTools). --}}
<div class="flex flex-wrap items-center gap-2" data-test="excel-tools-{{ $kind }}">
    <x-ui.button tone="action" size="sm" icon="arrow-down-tray" wire:click="export" data-test="excel-export-{{ $kind }}">{{ __('excel.export') }}</x-ui.button>

    @if ($canImport)
        <x-ui.button tone="action" size="sm" icon="document-arrow-down" wire:click="template" data-test="excel-template-{{ $kind }}">{{ __('excel.template') }}</x-ui.button>
        <x-ui.button tone="action" size="sm" icon="arrow-up-tray" wire:click="openImport" data-test="excel-import-{{ $kind }}">{{ __('excel.import') }}</x-ui.button>

        <x-ui.modal wire:model.self="showImport" size="xl" :title="__('excel.import_title.'.$kind)" :description="__('excel.import_intro')" close="closeImport" data-test="excel-modal-{{ $kind }}">
            <div class="flex flex-col gap-4">
                <ol class="list-decimal ps-5 text-sm text-secondary-medium dark:text-ink-muted">
                    <li>{{ __('excel.step_template') }}</li>
                    <li>{{ __('excel.step_fill') }}</li>
                    <li>{{ __('excel.step_upload') }}</li>
                    <li>{{ __('excel.step_save') }}</li>
                </ol>

                <div class="flex flex-col gap-1">
                    <input type="file" wire:model="upload" accept=".xlsx" class="text-sm" aria-label="{{ __('excel.upload') }}" data-test="excel-upload-{{ $kind }}">
                    @error('upload')<p class="text-sm text-status-critical" role="alert">{{ $message }}</p>@enderror
                    <div wire:loading wire:target="upload" class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('excel.reading') }}</div>
                </div>

                @if ($problems !== [])
                    <div class="rounded-lg border border-status-warn bg-status-warn-soft px-4 py-2 text-sm text-status-warn" role="alert" data-test="excel-problems">
                        <ul class="list-disc ps-5">
                            @foreach ($problems as $problem)
                                <li>{{ $problem }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($extraName !== null && $reviewed !== [])
                    <p class="text-sm text-secondary-medium dark:text-ink-muted" data-test="excel-extra">{{ trans_choice('excel.extra_read', count($extra), ['count' => count($extra), 'sheet' => $extraName]) }}</p>
                @endif

                @if ($extraProblems !== [])
                    <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="excel-extra-problems">
                        <ul class="list-disc ps-5">
                            @foreach ($extraProblems as $problem)
                                <li>{{ $problem }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($reviewed !== [])
                    <p @class(['text-sm font-medium', 'text-status-good' => $broken === 0, 'text-status-warn' => $broken > 0]) data-test="excel-summary">
                        {{ $broken === 0 ? trans_choice('excel.all_good', count($reviewed), ['count' => count($reviewed), 'file' => $fileName]) : trans_choice('excel.some_broken', $broken, ['count' => $broken, 'file' => $fileName]) }}
                    </p>

                    <x-ui.scroll-table>
                        <table class="fin-table" data-test="excel-preview-{{ $kind }}">
                            <thead>
                                <tr>
                                    <th>{{ __('excel.row') }}</th>
                                    <th>{{ __('excel.action') }}</th>
                                    @foreach ($headings as $heading)
                                        <th>{{ $heading }}</th>
                                    @endforeach
                                    <th>{{ __('excel.check') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reviewed as $row)
                                    <tr wire:key="excel-row-{{ $row['row'] }}" data-test="excel-row-{{ $row['row'] }}">
                                        <td class="tabular-nums">{{ $row['row'] }}</td>
                                        <td><flux:badge size="sm" :color="$row['action'] === 'new' ? 'sky' : 'zinc'">{{ __('excel.actions.'.$row['action']) }}</flux:badge></td>
                                        @foreach ($columns as $column)
                                            <td class="max-w-48 truncate" dir="auto">{{ $row['cells'][$column] }}</td>
                                        @endforeach
                                        <td>
                                            @if ($row['problems'] === [])
                                                <span class="text-status-good">{{ __('excel.ok') }}</span>
                                            @else
                                                <ul class="text-xs text-status-critical">
                                                    @foreach ($row['problems'] as $problem)
                                                        <li>{{ $problem }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-ui.scroll-table>
                @endif
            </div>

            <x-slot:actions>
                @if ($reviewed !== [])
                    <x-ui.button tone="primary" wire:click="save" :disabled="$broken > 0" data-test="excel-save-{{ $kind }}">{{ trans_choice('excel.save', count($reviewed), ['count' => count($reviewed)]) }}</x-ui.button>
                @endif
            </x-slot:actions>
        </x-ui.modal>
    @endif
</div>
