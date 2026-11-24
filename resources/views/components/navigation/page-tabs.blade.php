@php
    // Each menu group's icon for its tabs; "other" for a page outside the menu.
    $tabIcons = [
        'home' => 'home',
        'operations' => 'arrows-right-left',
        'funding' => 'banknotes',
        'entries' => 'document-text',
        'micro' => 'briefcase',
        'treasury' => 'building-library',
        'reports' => 'chart-bar',
        'chart' => 'rectangle-stack',
        'settings' => 'cog-6-tooth',
        'other' => 'document',
    ];
@endphp

{{--
    Browser-like tabs (finance request of 2026-11-20); the behaviour is in resources/js/page-tabs.js. A middle click on
    a link opens it in a new tab behind; pinned tabs come first and cannot be closed; drag to reorder; right-click for
    the menu. Kept across pages by @persist in the layout.
--}}
<div
    x-data="pageTabs({{ \Illuminate\Support\Js::from([
        'user' => (int) auth()->id(),
        'appName' => __('common.app_name'),
        'screens' => \App\Support\Navigation::tabScreens(),
    ]) }})"
    data-page-tabs
    data-test="page-tabs"
    class="border-b border-secondary-soft bg-white/95 backdrop-blur dark:border-line dark:bg-surface-1/95"
    x-on:keydown.escape.window="closeMenu()"
    x-on:click.outside="closeMenu()"
>
    @foreach ($tabIcons as $group => $icon)
        <template data-tab-icon="{{ $group }}"><flux:icon :name="$icon" variant="micro" class="size-4 shrink-0" /></template>
    @endforeach

    <div role="tablist" aria-label="{{ __('common.tabs.label') }}" class="flex min-h-10 items-end gap-1 overflow-x-auto px-2 pt-1.5">
        <template x-for="tab in tabs" :key="tab.id">
            <div
                role="tab"
                tabindex="0"
                draggable="true"
                :aria-selected="(tab.id === activeId).toString()"
                :data-active="(tab.id === activeId).toString()"
                :title="tab.title"
                class="group flex h-8 shrink-0 cursor-pointer select-none items-center gap-1.5 rounded-t-lg border border-b-0 px-2.5 text-xs transition-colors"
                :class="[
                    tab.id === activeId
                        ? 'border-secondary-soft bg-ground font-semibold text-secondary-dark dark:border-line dark:bg-surface-0 dark:text-ink'
                        : 'border-transparent text-secondary-medium hover:bg-secondary-soft/60 dark:text-ink-muted dark:hover:bg-white/[0.06]',
                    tab.pinned ? 'w-9 justify-center px-0' : 'w-44 max-w-44',
                    dragging === tab.id ? 'opacity-50' : '',
                ]"
                x-on:click="activate(tab)"
                x-on:keydown.enter.prevent="activate(tab)"
                x-on:auxclick.prevent="$event.button === 1 && close(tab)"
                x-on:mousedown="$event.button === 1 && $event.preventDefault()"
                x-on:contextmenu.prevent="openMenu($event, tab)"
                x-on:dragstart="dragStart($event, tab)"
                x-on:dragover="dragOver($event, tab)"
                x-on:drop.prevent="dragEnd()"
                x-on:dragend="dragEnd()"
                data-test="page-tab"
            >
                <span class="flex shrink-0 items-center text-primary dark:text-brand-text" x-html="icon(tab.group)" aria-hidden="true"></span>
                <span x-show="! tab.pinned" class="min-w-0 flex-1 truncate" x-text="tab.title"></span>
                <button
                    type="button"
                    x-show="! tab.pinned && tabs.length > 1"
                    class="flex size-5 shrink-0 items-center justify-center rounded text-secondary-light hover:bg-secondary-soft hover:text-secondary-dark dark:text-ink-faint dark:hover:bg-white/[0.1] dark:hover:text-ink"
                    x-on:click.stop="close(tab)"
                    aria-label="{{ __('common.tabs.close') }}"
                    title="{{ __('common.tabs.close') }}"
                    data-test="page-tab-close"
                >
                    <flux:icon.x-mark variant="micro" class="size-3.5" />
                </button>
            </div>
        </template>

        <span class="ms-2 hidden shrink-0 self-center text-[11px] text-secondary-light xl:inline dark:text-ink-faint" x-show="tabs.length < 2">{{ __('common.tabs.hint') }}</span>
    </div>

    {{-- The right-click menu of a tab. --}}
    <div
        x-show="menu !== null"
        x-cloak
        x-transition.opacity.duration.100ms
        class="fixed z-[70] w-56 overflow-hidden rounded-lg border border-secondary-soft bg-white py-1 text-sm shadow-lg dark:border-line dark:bg-surface-2"
        :style="menu ? `left: ${menu.x}px; top: ${menu.y}px` : ''"
        role="menu"
        data-test="page-tab-menu"
    >
        <template x-if="menuTab()">
            <div class="flex flex-col">
                <button type="button" role="menuitem" class="px-3 py-1.5 text-start hover:bg-secondary-soft dark:hover:bg-white/[0.06]" x-on:click="reload(menuTab())">{{ __('common.tabs.reload') }}</button>
                <button type="button" role="menuitem" class="px-3 py-1.5 text-start hover:bg-secondary-soft dark:hover:bg-white/[0.06]" x-on:click="duplicate(menuTab())">{{ __('common.tabs.duplicate') }}</button>
                <button type="button" role="menuitem" class="px-3 py-1.5 text-start hover:bg-secondary-soft dark:hover:bg-white/[0.06]" x-on:click="togglePin(menuTab())" x-text="menuTab().pinned ? @js(__('common.tabs.unpin')) : @js(__('common.tabs.pin'))"></button>
                <div class="my-1 border-t border-secondary-soft dark:border-line"></div>
                <button type="button" role="menuitem" class="px-3 py-1.5 text-start hover:bg-secondary-soft disabled:opacity-40 disabled:hover:bg-transparent dark:hover:bg-white/[0.06]" :disabled="menuTab().pinned || tabs.length <= 1" x-on:click="close(menuTab())">{{ __('common.tabs.close') }}</button>
                <button type="button" role="menuitem" class="px-3 py-1.5 text-start hover:bg-secondary-soft disabled:opacity-40 disabled:hover:bg-transparent dark:hover:bg-white/[0.06]" :disabled="tabs.length <= 1" x-on:click="closeOthers(menuTab())">{{ __('common.tabs.close_others') }}</button>
                <button type="button" role="menuitem" class="px-3 py-1.5 text-start hover:bg-secondary-soft disabled:opacity-40 disabled:hover:bg-transparent dark:hover:bg-white/[0.06]" :disabled="index(menuTab()) >= tabs.length - 1" x-on:click="closeRight(menuTab())">{{ __('common.tabs.close_right') }}</button>
            </div>
        </template>
    </div>
</div>
