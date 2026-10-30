@php
    // [keys, what they do]; a key list with "+" between keys pressed together.
    $groups = [
        'everywhere' => [
            [['Ctrl', 'K'], 'search'],
            [['?'], 'help'],
            [['Esc'], 'close'],
        ],
        'entry' => [
            [['Ctrl', 'S'], 'save'],
            [['Ctrl', 'Enter'], 'save_new'],
            [['Alt', 'N'], 'add_line'],
        ],
    ];
@endphp

{{-- The list of keyboard shortcuts (U-07), opened with ? from any screen. A native dialog: Esc and the backdrop close it. --}}
<div x-data x-on:hc-shortcuts-open.window="$refs.dialog.showModal()">
    <dialog
        x-ref="dialog"
        x-on:click.self="$el.close()"
        class="m-auto w-[min(28rem,calc(100vw-2rem))] rounded-xl border border-secondary-soft bg-white p-0 text-secondary-dark shadow-card-hover backdrop:bg-black/40 dark:border-line dark:bg-surface-1 dark:text-ink"
        aria-labelledby="shortcuts-title"
        data-test="shortcuts-help"
    >
        <div class="flex items-center justify-between gap-3 border-b border-secondary-soft px-5 py-4 dark:border-line">
            <h2 id="shortcuts-title" class="text-base font-bold">{{ __('shortcuts.title') }}</h2>
            <flux:button variant="ghost" size="sm" square icon="x-mark" x-on:click="$refs.dialog.close()" :aria-label="__('common.close')" />
        </div>

        <div class="flex flex-col gap-5 px-5 py-4">
            @foreach ($groups as $group => $rows)
                <section class="flex flex-col gap-2">
                    <h3 class="text-[13px] font-semibold text-secondary-medium dark:text-ink-muted">{{ __('shortcuts.groups.'.$group) }}</h3>
                    <dl class="flex flex-col">
                        @foreach ($rows as [$keys, $action])
                            <div class="flex min-h-10 items-center justify-between gap-4 border-b border-secondary-soft/60 last:border-b-0 dark:border-line/60">
                                <dt class="text-sm">{{ __('shortcuts.actions.'.$action) }}</dt>
                                <dd class="flex shrink-0 items-center gap-1" dir="ltr">
                                    @foreach ($keys as $key)
                                        @if (! $loop->first)<span class="text-xs text-secondary-medium dark:text-ink-muted" aria-hidden="true">+</span>@endif
                                        <x-ui.kbd>{{ $key }}</x-ui.kbd>
                                    @endforeach
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endforeach

            <p class="text-xs text-secondary-medium dark:text-ink-muted">{{ __('shortcuts.note') }}</p>
        </div>
    </dialog>
</div>
