/**
 * Lets the reader drag the chart sidebar's inner edge to resize it (desktop only).
 *
 * The width lives in the `--sidebar-w` custom property on <html>, which the sidebar
 * uses for its width; it is clamped, remembered in localStorage, and can be moved
 * with the arrow keys when the handle has focus.
 */
const KEY = 'sidebar-width';
const MIN = 240;
const MAX = 640;
const STEP = 16;

const clamp = (value) => Math.min(MAX, Math.max(MIN, Math.round(value)));

const apply = (width) => document.documentElement.style.setProperty('--sidebar-w', `${width}px`);

const remember = (width) => {
    try {
        localStorage.setItem(KEY, String(width));
    } catch {
        // Storage can be blocked; the width then simply resets on the next visit.
    }
};

try {
    const saved = Number(localStorage.getItem(KEY));
    if (saved) apply(clamp(saved));
} catch {
    // Ignore: the default width applies.
}

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
                remember(clamp(sidebar.getBoundingClientRect().width));
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
            remember(width);
        },

        reset() {
            document.documentElement.style.removeProperty('--sidebar-w');
            remember('');
        },
    }));
});
