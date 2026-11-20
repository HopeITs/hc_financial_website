/**
 * The chart sidebar on a desktop: drag its inner edge to resize it, or fold it away with the arrow at its top.
 *
 * The width lives in the `--sidebar-w` custom property on <html>, which the sidebar uses for its width; it is
 * clamped, remembered in localStorage, and can be moved with the arrow keys when the handle has focus. A folded
 * sidebar is a narrow rail (`data-tree-collapsed` on <html>) with the arrow to open it again.
 *
 * Moving to another page with wire:navigate replaces the attributes of <html>, so both are put back on every
 * navigation (and early in <head> on a full load, see partials/head), and the sidebar keeps its size and state.
 */
const KEY = 'sidebar-width';
const COLLAPSED_KEY = 'sidebar-collapsed';
const MIN = 240;
const MAX = 640;
const STEP = 16;
const RAIL = 56;

const clamp = (value) => Math.min(MAX, Math.max(MIN, Math.round(value)));

const read = (key) => {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
};

const write = (key, value) => {
    try {
        localStorage.setItem(key, value);
    } catch {
        // Storage can be blocked; the sidebar then simply resets on the next visit.
    }
};

const isCollapsed = () => read(COLLAPSED_KEY) === '1';

const apply = (width) => document.documentElement.style.setProperty('--sidebar-w', `${width}px`);

/** Puts the remembered state back on <html>: the rail when folded, else the remembered width (or the default). */
const restore = () => {
    const root = document.documentElement;

    if (isCollapsed()) {
        root.dataset.treeCollapsed = '1';
        apply(RAIL);

        return;
    }

    delete root.dataset.treeCollapsed;
    const saved = Number(read(KEY));
    if (saved) {
        apply(clamp(saved));
    } else {
        root.style.removeProperty('--sidebar-w');
    }
};

restore();

document.addEventListener('livewire:navigating', (event) => event.detail?.onSwap?.(restore));
document.addEventListener('livewire:navigated', restore);

window.hcSidebar = {
    isCollapsed,
    setCollapsed(collapsed) {
        write(COLLAPSED_KEY, collapsed ? '1' : '0');
        restore();
    },
};

document.addEventListener('alpine:init', () => {
    window.Alpine.data('sidebarResize', () => ({
        dragging: false,

        start(event) {
            const sidebar = this.$el.closest('[data-navigation-sidebar]');
            const rtl = document.documentElement.dir === 'rtl';
            const rect = sidebar.getBoundingClientRect();
            const edge = rtl ? rect.right : rect.left;
            this.dragging = true;
            document.body.style.userSelect = 'none';
            document.body.style.cursor = 'col-resize';

            const move = (e) => apply(clamp(rtl ? edge - e.clientX : e.clientX - edge));
            const stop = () => {
                this.dragging = false;
                document.body.style.userSelect = '';
                document.body.style.cursor = '';
                window.removeEventListener('pointermove', move);
                window.removeEventListener('pointerup', stop);
                write(KEY, String(clamp(sidebar.getBoundingClientRect().width)));
            };

            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', stop);
            event.preventDefault();
        },

        nudge(direction) {
            const sidebar = this.$el.closest('[data-navigation-sidebar]');
            const rtl = document.documentElement.dir === 'rtl';
            const width = clamp(sidebar.getBoundingClientRect().width + direction * STEP * (rtl ? -1 : 1));
            apply(width);
            write(KEY, String(width));
        },

        reset() {
            document.documentElement.style.removeProperty('--sidebar-w');
            write(KEY, '');
        },
    }));
});
