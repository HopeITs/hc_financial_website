<div>
    @if ($available)
        {{-- Positioning is plain CSS here, not utility classes, so the corner and the round button work without a front-end rebuild. --}}
        <style>
            .hc-assistant { position: fixed; right: 1rem; bottom: 1rem; z-index: 45; display: flex; flex-direction: column; align-items: flex-end; gap: .75rem; }
            .hc-assistant__panel { position: relative; width: min(28rem, calc(100vw - 2rem)); height: min(36rem, calc(100dvh - 6rem)); min-width: 18rem; min-height: 16rem; max-width: calc(100vw - 1rem); max-height: calc(100dvh - 5rem); }
            .hc-assistant__grip { cursor: move; touch-action: none; user-select: none; }
            .hc-assistant__fab { touch-action: none; }
            .hc-assistant.is-moving, .hc-assistant.is-moving * { user-select: none; }
            /* The corner to pull: top-left, as the window grows towards it from its bottom-right corner. */
            .hc-assistant__resize { position: absolute; top: 0; left: 0; z-index: 2; width: 1.1rem; height: 1.1rem; cursor: nwse-resize; touch-action: none; background: linear-gradient(135deg, var(--color-primary) 0 2px, transparent 2px 5px, var(--color-primary) 5px 7px, transparent 7px); opacity: .55; border-top-left-radius: .75rem; }
            .hc-assistant__resize:hover { opacity: 1; }
            .hc-assistant__fab { display: grid; place-items: center; width: 3.5rem; height: 3.5rem; padding: 0; border: 0; border-radius: 9999px; cursor: pointer; color: #fff; background: var(--color-primary); box-shadow: 0 6px 18px rgb(0 0 0 / .28); transition: transform .15s ease, background-color .15s ease; }
            .hc-assistant__fab:hover { transform: scale(1.06); }
            .hc-assistant__fab:focus-visible { outline: 3px solid var(--color-primary); outline-offset: 3px; }
            .hc-assistant__fab svg { width: 2rem; height: 2rem; }
            .dark .hc-assistant__fab { background: var(--color-brand); }
            @media (prefers-reduced-motion: reduce) { .hc-assistant__fab { transition: none; } .hc-assistant__fab:hover { transform: none; } }
        </style>

        {{-- Moved by its title bar or its round button, and sized from its top-left corner (finance request of 2026-11-29).
             Where it sits and its size are kept in this browser only. --}}
        <div
            x-data="{
                pending: '',
                right: null, bottom: null, width: null, height: null,
                moving: false, moved: false,
                key: 'hc.assistant.box',
                init() {
                    try { Object.assign(this, JSON.parse(localStorage.getItem(this.key) || '{}')); } catch (e) {}
                    this.clamp();
                    window.addEventListener('resize', () => this.clamp());
                },
                keep() {
                    try { localStorage.setItem(this.key, JSON.stringify({ right: this.right, bottom: this.bottom, width: this.width, height: this.height })); } catch (e) {}
                },
                clamp() {
                    if (this.right === null || this.bottom === null) { return; }
                    const box = this.$root.getBoundingClientRect();
                    this.right = Math.min(Math.max(this.right, 0), Math.max(window.innerWidth - Math.min(box.width, window.innerWidth) , 0));
                    this.bottom = Math.min(Math.max(this.bottom, 0), Math.max(window.innerHeight - Math.min(box.height, window.innerHeight), 0));
                },
                place() {
                    return this.right === null ? '' : `right: ${this.right}px; bottom: ${this.bottom}px;`;
                },
                size() {
                    return this.width === null ? '' : `width: ${this.width}px; height: ${this.height}px;`;
                },
                move(event) {
                    if (event.button !== undefined && event.button !== 0) { return; }
                    if (event.target.closest('button:not(.hc-assistant__fab), a, textarea, input')) { return; }
                    const box = this.$root.getBoundingClientRect();
                    const start = { x: event.clientX, y: event.clientY, right: window.innerWidth - box.right, bottom: window.innerHeight - box.bottom };
                    this.moved = false;
                    const follow = (e) => {
                        const dx = e.clientX - start.x, dy = e.clientY - start.y;
                        if (! this.moved && Math.abs(dx) + Math.abs(dy) < 5) { return; }
                        this.moved = true; this.moving = true;
                        this.right = start.right - dx; this.bottom = start.bottom - dy;
                        this.clamp();
                    };
                    const stop = () => {
                        window.removeEventListener('pointermove', follow);
                        window.removeEventListener('pointerup', stop);
                        this.moving = false;
                        if (this.moved) { this.keep(); }
                    };
                    window.addEventListener('pointermove', follow);
                    window.addEventListener('pointerup', stop);
                },
                resize(event) {
                    event.preventDefault();
                    const panel = event.target.closest('.hc-assistant__panel').getBoundingClientRect();
                    const start = { x: event.clientX, y: event.clientY, width: panel.width, height: panel.height };
                    this.moving = true;
                    const follow = (e) => {
                        this.width = Math.round(Math.min(Math.max(start.width - (e.clientX - start.x), 288), window.innerWidth - 16));
                        this.height = Math.round(Math.min(Math.max(start.height - (e.clientY - start.y), 256), window.innerHeight - 80));
                    };
                    const stop = () => {
                        window.removeEventListener('pointermove', follow);
                        window.removeEventListener('pointerup', stop);
                        this.moving = false;
                        this.clamp();
                        this.keep();
                    };
                    window.addEventListener('pointermove', follow);
                    window.addEventListener('pointerup', stop);
                },
                reset() {
                    this.right = this.bottom = this.width = this.height = null;
                    try { localStorage.removeItem(this.key); } catch (e) {}
                },
            }"
            class="hc-assistant"
            :class="{ 'is-moving': moving }"
            :style="place()"
            data-test="assistant"
        >
            @if ($open)
                <section
                    class="hc-assistant__panel flex flex-col overflow-hidden rounded-xl border border-secondary-soft bg-white shadow-xl dark:border-line dark:bg-surface-1"
                    :style="size()"
                    role="dialog"
                    aria-labelledby="assistant-title"
                    data-test="assistant-panel"
                >
                    <span class="hc-assistant__resize" x-on:pointerdown="resize($event)" title="{{ __('assistant.resize') }}" aria-hidden="true" data-test="assistant-resize"></span>
                    <header class="hc-assistant__grip flex items-center gap-2 border-b border-secondary-soft px-4 py-3 dark:border-line" x-on:pointerdown="move($event)" x-on:dblclick="reset()" title="{{ __('assistant.move') }}" data-test="assistant-grip">
                        <flux:icon.sparkles variant="mini" class="size-4 text-primary dark:text-brand" />
                        <h2 id="assistant-title" class="flex-1 text-sm font-semibold text-secondary-dark dark:text-ink">{{ __('assistant.title') }}</h2>
                        @if ($hasHistory)
                            <x-ui.button tone="ghost" size="sm" wire:click="startOver" data-test="assistant-new">{{ __('assistant.new') }}</x-ui.button>
                        @endif
                        <x-ui.button tone="ghost" size="sm" icon="arrows-pointing-in" x-show="right !== null || width !== null" x-on:click="reset()" :aria-label="__('assistant.reset_place')" :title="__('assistant.reset_place')" data-test="assistant-reset" />
                        <x-ui.button tone="ghost" size="sm" icon="x-mark" wire:click="toggle" :aria-label="__('assistant.close')" />
                    </header>

                    <div
                        wire:key="assistant-log-{{ count($items) }}"
                        x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                        class="flex flex-1 flex-col gap-3 overflow-y-auto px-4 py-3 text-sm"
                        aria-live="polite"
                        data-test="assistant-log"
                    >
                        @if ($items === [] && ! $configured)
                            <div class="flex flex-col gap-3 text-secondary-medium dark:text-ink-muted" data-test="assistant-guide-empty">
                                <p>{{ __('assistant_guide.intro') }}</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($suggestions as $topic)
                                        <button
                                            type="button"
                                            class="rounded-full border border-secondary-light px-3 py-1 text-start text-secondary-dark hover:bg-secondary-soft dark:border-line-strong dark:text-ink dark:hover:bg-surface-3"
                                            wire:click="askTopic('{{ $topic['key'] }}')"
                                            data-test="guide-topic-{{ $topic['key'] }}"
                                        >{{ $topic['title'] }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @elseif ($items === [])
                            <div class="flex flex-col gap-3 text-secondary-medium dark:text-ink-muted" data-test="assistant-empty">
                                <p>{{ __('assistant.intro') }}</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach (__('assistant.examples') as $example)
                                        <button
                                            type="button"
                                            class="rounded-full border border-secondary-light px-3 py-1 text-start text-secondary-dark hover:bg-secondary-soft dark:border-line-strong dark:text-ink dark:hover:bg-surface-3"
                                            x-on:click="$wire.set('question', @js($example))"
                                        >{{ $example }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @foreach ($items as $index => $item)
                            @switch($item['type'])
                                @case('user')
                                    <p class="max-w-[85%] self-end whitespace-pre-line rounded-lg bg-secondary-soft px-3 py-2 text-secondary-dark dark:bg-surface-3 dark:text-ink" wire:key="item-{{ $index }}">{{ $item['text'] }}</p>
                                    @break

                                @case('assistant')
                                    <div
                                        class="max-w-full text-secondary-dark dark:text-ink [&_ol]:list-decimal [&_ol]:ps-5 [&_p]:my-1 [&_table]:my-2 [&_table]:w-full [&_table]:text-xs [&_td]:border-b [&_td]:border-secondary-soft [&_td]:py-1 [&_td]:pe-2 [&_th]:border-b [&_th]:py-1 [&_th]:pe-2 [&_th]:text-start [&_ul]:list-disc [&_ul]:ps-5 dark:[&_td]:border-line"
                                        wire:key="item-{{ $index }}"
                                        data-test="assistant-reply"
                                    >{!! Str::markdown($item['text'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                                    @break

                                @case('guide')
                                    <div class="flex max-w-full flex-col gap-2" wire:key="item-{{ $index }}" data-test="guide-reply">
                                        <div class="text-secondary-dark dark:text-ink [&_ol]:list-decimal [&_ol]:ps-5 [&_p]:my-1 [&_ul]:list-disc [&_ul]:ps-5">{!! Str::markdown($item['text'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                                        @if (($item['links'] ?? []) !== [])
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ($item['links'] as $link)
                                                    <x-ui.button size="sm" tone="primary" icon="arrow-top-right-on-square" :href="$link['url']" wire:navigate>{{ $link['label'] }}</x-ui.button>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if (($item['related'] ?? []) !== [])
                                            <p class="text-xs text-secondary-medium dark:text-ink-muted">{{ __('assistant_guide.related') }}</p>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ($item['related'] as $topic)
                                                    <button
                                                        type="button"
                                                        class="rounded-full border border-secondary-light px-3 py-1 text-start text-xs text-secondary-dark hover:bg-secondary-soft dark:border-line-strong dark:text-ink dark:hover:bg-surface-3"
                                                        wire:click="askTopic('{{ $topic['key'] }}')"
                                                    >{{ $topic['title'] }}</button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    @break

                                @case('note')
                                    <p class="self-center text-xs text-secondary-medium dark:text-ink-muted" wire:key="item-{{ $index }}">{{ $item['text'] }}</p>
                                    @break

                                @case('proposal')
                                    @php($card = $cards[$item['proposal']] ?? null)
                                    @if ($card !== null)
                                        @php($proposal = $card['proposal'])
                                        @php($preview = $card['preview'])
                                        <div class="rounded-lg border border-secondary-light p-3 dark:border-line-strong" wire:key="proposal-{{ $proposal->id }}" data-test="assistant-proposal-{{ $proposal->id }}">
                                            <div class="mb-2 flex items-center gap-2">
                                                <span class="flex-1 font-semibold">{{ __('assistant.proposal.title') }}</span>
                                                @if ($proposal->status === \App\Models\AssistantProposal::SAVED)
                                                    <flux:badge size="sm" color="green">{{ __('assistant.proposal.saved') }}</flux:badge>
                                                @elseif ($proposal->status === \App\Models\AssistantProposal::CANCELLED)
                                                    <flux:badge size="sm" color="zinc">{{ __('assistant.proposal.cancelled') }}</flux:badge>
                                                @else
                                                    <flux:badge size="sm" color="amber">{{ __('assistant.proposal.waiting') }}</flux:badge>
                                                @endif
                                            </div>

                                            @if ($proposal->status === \App\Models\AssistantProposal::SAVED)
                                                <a href="{{ route('entries.vouchers', ['voucher' => $proposal->voucher_id]) }}" wire:navigate class="text-primary underline dark:text-brand">{{ __('assistant.proposal.open_voucher', ['number' => $proposal->voucher_id]) }}</a>
                                            @elseif ($proposal->status === \App\Models\AssistantProposal::PENDING)
                                                <p class="mb-2 text-secondary-medium dark:text-ink-muted">
                                                    {{ $preview['type'] ?? '' }} · {{ $preview['date'] ?? '' }} · {{ $preview['description'] ?? '' }}
                                                </p>
                                                <div class="fin-table-wrap">
                                                    <table class="fin-table">
                                                        <thead>
                                                            <tr>
                                                                <th>{{ __('assistant.proposal.account') }}</th>
                                                                <th class="fin-num">{{ __('assistant.proposal.debit') }}</th>
                                                                <th class="fin-num">{{ __('assistant.proposal.credit') }}</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($preview['lines'] ?? [] as $line)
                                                                <tr>
                                                                    <td>
                                                                        {{ $line['account'] }}
                                                                        @if ($line['project'])
                                                                            <span class="text-secondary-medium dark:text-ink-muted">· {{ $line['project'] }}</span>
                                                                        @endif
                                                                    </td>
                                                                    <td class="fin-num"><x-money :amount="$line['debit']" :currency="$line['currency']" /></td>
                                                                    <td class="fin-num"><x-money :amount="$line['credit']" :currency="$line['currency']" /></td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>

                                                @if (($preview['problems'] ?? []) !== [])
                                                    <div class="mt-2 text-status-critical" role="alert">
                                                        @foreach ($preview['problems'] as $problem)
                                                            <p>{{ $problem }}</p>
                                                        @endforeach
                                                    </div>
                                                @endif

                                                <p class="mt-2 text-xs text-secondary-medium dark:text-ink-muted">{{ __('assistant.proposal.explain') }}</p>
                                                <div class="mt-2 flex gap-2">
                                                    <x-ui.button tone="primary" size="sm" wire:click="save({{ $proposal->id }})" wire:loading.attr="disabled" data-test="assistant-save">{{ __('assistant.proposal.save') }}</x-ui.button>
                                                    <x-ui.button tone="secondary" size="sm" wire:click="cancel({{ $proposal->id }})" wire:loading.attr="disabled">{{ __('assistant.proposal.cancel') }}</x-ui.button>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                    @break
                            @endswitch
                        @endforeach

                        <div wire:loading.flex wire:target="send" class="flex-col gap-3">
                            <p class="max-w-[85%] self-end whitespace-pre-line rounded-lg bg-secondary-soft px-3 py-2 text-secondary-dark dark:bg-surface-3 dark:text-ink" x-text="pending"></p>
                            <p class="text-secondary-medium dark:text-ink-muted" data-test="assistant-thinking">{{ __('assistant.thinking') }}</p>
                        </div>
                    </div>

                    @if ($error)
                        <p class="border-t border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="assistant-error">{{ $error }}</p>
                    @endif
                    @if ($notice)
                        <p class="border-t border-status-good bg-status-good-soft px-4 py-2 text-sm text-status-good" role="status" data-test="assistant-notice">{{ $notice }}</p>
                    @endif

                    <form
                        wire:submit="send"
                        x-on:submit="pending = $wire.question"
                        class="flex items-end gap-2 border-t border-secondary-soft p-3 dark:border-line"
                    >
                        <textarea
                            wire:model="question"
                            rows="2"
                            maxlength="4000"
                            dir="auto"
                            placeholder="{{ $configured ? __('assistant.placeholder') : __('assistant.guide_placeholder') }}"
                            aria-label="{{ $configured ? __('assistant.placeholder') : __('assistant.guide_placeholder') }}"
                            x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $el.form.requestSubmit() }"
                            wire:loading.attr="disabled"
                            wire:target="send"
                            class="min-h-10 flex-1 resize-none rounded-lg border border-secondary-light bg-white px-3 py-2 text-sm text-secondary-dark focus:border-primary focus:outline-none dark:border-line-strong dark:bg-surface-2 dark:text-ink"
                            data-test="assistant-input"
                        ></textarea>
                        <x-ui.button tone="primary" type="submit" icon="paper-airplane" wire:loading.attr="disabled" wire:target="send" :aria-label="__('assistant.send')" data-test="assistant-send" />
                    </form>
                </section>
            @endif

            <button
                type="button"
                class="hc-assistant__fab"
                x-on:pointerdown="move($event)"
                x-on:click="if (moved) { moved = false; return; } $wire.toggle()"
                aria-label="{{ __('assistant.title') }}"
                title="{{ __('assistant.title') }}"
                aria-expanded="{{ $open ? 'true' : 'false' }}"
                data-test="assistant-toggle"
            >
                {{-- Robot face: antenna, head, two eyes, mouth. --}}
                <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M16 4v4" />
                    <circle cx="16" cy="3.5" r="1.2" fill="currentColor" />
                    <rect x="5" y="8" width="22" height="18" rx="6" />
                    <circle cx="11.5" cy="16" r="1.8" fill="currentColor" stroke="none" />
                    <circle cx="20.5" cy="16" r="1.8" fill="currentColor" stroke="none" />
                    <path d="M11.5 21.5h9" />
                    <path d="M2.5 15v4M29.5 15v4" />
                </svg>
            </button>
        </div>
    @endif
</div>
