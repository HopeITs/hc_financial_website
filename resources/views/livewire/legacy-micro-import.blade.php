{{-- The import of the old micro projects for follow-up (App\Livewire\LegacyMicroImport). --}}
<div class="flex flex-wrap items-center gap-2" data-test="legacy-micro-import">
    @if ($canImport)
        <x-ui.button tone="action" size="sm" icon="archive-box-arrow-down" wire:click="open" data-test="legacy-micro-open">{{ __('legacy_micro.button') }}</x-ui.button>

        <x-ui.modal wire:model.self="show" size="xl" :title="__('legacy_micro.title')" :description="__('legacy_micro.intro')" close="close" data-test="legacy-micro-modal">
            <div class="flex flex-col gap-4">
                <ol class="list-decimal ps-5 text-sm text-secondary-medium dark:text-ink-muted">
                    <li>{{ __('legacy_micro.step_template') }}</li>
                    <li>{{ __('legacy_micro.step_fill') }}</li>
                    <li>{{ __('legacy_micro.step_upload') }}</li>
                    <li>{{ __('legacy_micro.step_save') }}</li>
                </ol>

                <div>
                    <x-ui.button tone="ghost" size="sm" icon="document-arrow-down" wire:click="template" data-test="legacy-micro-template">{{ __('legacy_micro.template') }}</x-ui.button>
                </div>

                <p class="rounded-lg border border-status-warn bg-status-warn-soft px-4 py-2 text-sm text-status-warn" data-test="legacy-micro-opening">{{ __('legacy_micro.opening_note') }}</p>

                <div class="flex flex-col gap-1">
                    <input type="file" wire:model="upload" accept=".xlsx" class="text-sm" aria-label="{{ __('excel.upload') }}" data-test="legacy-micro-upload">
                    @error('upload')<p class="text-sm text-status-critical" role="alert">{{ $message }}</p>@enderror
                    <div wire:loading wire:target="upload" class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('excel.reading') }}</div>
                </div>

                @if ($summary !== null)
                    <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4" data-test="legacy-micro-summary">
                        <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('legacy_micro.summary.file') }}</dt><dd class="font-medium" dir="auto">{{ $fileName }}</dd></div>
                        <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('legacy_micro.summary.new') }}</dt><dd class="font-medium tabular-nums">{{ number_format($summary['new']) }} / {{ number_format($summary['projects']) }}</dd></div>
                        <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('legacy_micro.summary.skipped') }}</dt><dd class="font-medium tabular-nums">{{ number_format($summary['skipped']) }}</dd></div>
                        <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('legacy_micro.summary.instalments') }}</dt><dd class="font-medium tabular-nums">{{ number_format($summary['instalments']) }}</dd></div>
                        @foreach ($summary['owed'] as $currency => $amount)
                            <div><dt class="text-secondary-medium dark:text-ink-muted">{{ __('legacy_micro.summary.owed', ['currency' => $currency]) }}</dt><dd class="font-medium" data-test="legacy-micro-owed-{{ $currency }}"><x-money :amount="$amount" /></dd></div>
                        @endforeach
                    </dl>

                    @if ($summary['problem_count'] > 0)
                        <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="legacy-micro-problems">
                            <p class="font-medium">{{ trans_choice('legacy_micro.problem_count', $summary['problem_count'], ['count' => $summary['problem_count'], 'shown' => count($summary['problems'])]) }}</p>
                            <ul class="list-disc ps-5">
                                @foreach ($summary['problems'] as $problem)
                                    <li>{{ $problem }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @elseif ($summary['new'] === 0)
                        <p class="text-sm font-medium text-status-warn" data-test="legacy-micro-nothing">{{ __('legacy_micro.nothing_new') }}</p>
                    @else
                        <p class="text-sm font-medium text-status-good" data-test="legacy-micro-ready">{{ trans_choice('legacy_micro.ready', $summary['new'], ['count' => number_format($summary['new'])]) }}</p>
                    @endif
                @endif

                @if ($problems !== [])
                    <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="legacy-micro-errors">
                        <ul class="list-disc ps-5">
                            @foreach ($problems as $problem)
                                <li>{{ $problem }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex flex-wrap justify-end gap-2">
                    <x-ui.button tone="ghost" wire:click="close">{{ __('common.cancel') }}</x-ui.button>
                    @if ($summary !== null && $summary['problem_count'] === 0 && $summary['new'] > 0)
                        <x-ui.button tone="primary" icon="check" wire:click="save" wire:loading.attr="disabled" wire:target="save" data-test="legacy-micro-save">{{ __('legacy_micro.save') }}</x-ui.button>
                    @endif
                </div>
            </div>
        </x-ui.modal>
    @endif
</div>
