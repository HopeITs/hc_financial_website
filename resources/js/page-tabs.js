/**
 * Browser-like tabs inside the program (finance request of 2026-11-20).
 *
 * - The tab that is active follows the page: opening a page by a normal click, the menu or back/forward changes it.
 * - A middle click on a link of the program opens it in a new tab behind the current one, as a browser does.
 * - A tab can be pinned (first, small, cannot be closed by accident), dragged to another place among the tabs of its
 *   kind, and has a right-click menu: reload, duplicate, pin or unpin, close, close the others, close those to the right.
 * - The tabs last as long as the browser tab (sessionStorage): a reload keeps them, closing the browser tab ends them.
 *   Another person signing in on the same browser tab starts with fresh tabs.
 *
 * The bar itself is in components/navigation/page-tabs.blade.php, kept across pages with @persist.
 */
const KEY = 'hc.page-tabs';
const MAX = 30;

const read = () => {
    try {
        return JSON.parse(sessionStorage.getItem(KEY) ?? 'null');
    } catch {
        return null;
    }
};

const write = (state) => {
    try {
        sessionStorage.setItem(KEY, JSON.stringify(state));
    } catch {
        // Storage can be blocked: the tabs then only last until the next full page load.
    }
};

const newId = () => `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 7)}`;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('pageTabs', (config) => ({
        tabs: [],
        activeId: null,
        menu: null,
        dragging: null,
        icons: {},

        init() {
            // Each menu group's icon, drawn by the server once in <template data-tab-icon="group">.
            this.icons = Object.fromEntries([...this.$el.querySelectorAll('template[data-tab-icon]')].map((template) => [template.dataset.tabIcon, template.innerHTML]));

            const saved = read();
            if (saved && saved.user === config.user && Array.isArray(saved.tabs) && saved.tabs.length > 0) {
                this.tabs = saved.tabs;
                this.activeId = this.tabs.some((tab) => tab.id === saved.activeId) ? saved.activeId : this.tabs[0].id;
            }

            this.sync();

            document.addEventListener('livewire:navigated', () => this.sync());
            document.addEventListener('auxclick', (event) => this.middleClick(event));
            // Firefox and Chrome start their own autoscroll on a middle press over a link: not on the program's links.
            document.addEventListener('mousedown', (event) => {
                if (event.button === 1 && this.linkOf(event)) event.preventDefault();
            });
            window.addEventListener('scroll', () => this.closeMenu(), { passive: true });
            window.addEventListener('blur', () => this.closeMenu());
        },

        /** The current page, as a tab sees it. */
        page() {
            const marker = document.querySelector('[data-page-tab]');
            const suffix = ` - ${config.appName}`;
            const title = document.title.endsWith(suffix) ? document.title.slice(0, -suffix.length).trim() : document.title.trim();

            return {
                url: window.location.pathname + window.location.search,
                title: title || config.appName,
                group: marker?.dataset.group || 'other',
            };
        },

        /** The active tab shows the page now open: a new tab for it when there is none yet. */
        sync() {
            const page = this.page();
            let active = this.tabs.find((tab) => tab.id === this.activeId);

            if (!active) {
                active = { id: newId(), pinned: false, ...page };
                this.tabs.push(active);
                this.activeId = active.id;
            } else {
                Object.assign(active, page);
            }

            this.save();
            this.$nextTick(() => this.$el.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest', inline: 'nearest' }));
        },

        save() {
            write({ user: config.user, activeId: this.activeId, tabs: this.tabs });
        },

        /** The program's own link under a mouse event, or null. */
        linkOf(event) {
            const link = event.target.closest?.('a[href]');
            if (!link || link.closest('[data-page-tabs]') || link.target === '_blank' || link.hasAttribute('download')) return null;

            const url = new URL(link.href, window.location.href);
            if (url.origin !== window.location.origin || url.pathname.startsWith('/logout')) return null;

            return { link, url };
        },

        middleClick(event) {
            if (event.button !== 1) return;
            const found = this.linkOf(event);
            if (!found) return;

            event.preventDefault();
            const { link, url } = found;
            const path = url.pathname.replace(/^\/+/, '');
            const known = config.screens[path];
            const text = (link.getAttribute('aria-label') || link.textContent || '').replace(/\s+/g, ' ').trim();

            this.open({ url: url.pathname + url.search, title: known?.label || text || path, group: known?.group || 'other' });
        },

        /** Adds a tab after the active one (or after the given one), without moving to it. */
        open(page, after = this.activeId) {
            if (this.tabs.length >= MAX) return;

            const tab = { id: newId(), pinned: false, ...page };
            const index = this.tabs.findIndex((item) => item.id === after);
            const pinnedEnd = this.tabs.filter((item) => item.pinned).length;
            this.tabs.splice(Math.max(index + 1, pinnedEnd), 0, tab);
            this.save();
        },

        activate(tab) {
            this.closeMenu();
            if (tab.id === this.activeId) return;

            this.activeId = tab.id;
            this.save();
            window.Livewire.navigate(tab.url);
        },

        close(tab) {
            this.closeMenu();
            if (tab.pinned || this.tabs.length <= 1) return;

            const index = this.tabs.findIndex((item) => item.id === tab.id);
            this.tabs.splice(index, 1);

            if (tab.id === this.activeId) {
                const next = this.tabs[index] ?? this.tabs[index - 1];
                this.activeId = next.id;
                this.save();
                window.Livewire.navigate(next.url);

                return;
            }

            this.save();
        },

        closeOthers(tab) {
            this.keep((item) => item.pinned || item.id === tab.id, tab);
        },

        closeRight(tab) {
            const index = this.tabs.findIndex((item) => item.id === tab.id);
            this.keep((item, position) => item.pinned || position <= index, tab);
        },

        /** Keeps the tabs the test lets through; when the active one goes, the given tab becomes active. */
        keep(test, fallback) {
            this.closeMenu();
            const activeKept = this.tabs.some((item, position) => item.id === this.activeId && test(item, position));
            this.tabs = this.tabs.filter((item, position) => test(item, position));

            if (!activeKept) {
                this.activeId = fallback.id;
                this.save();
                window.Livewire.navigate(fallback.url);

                return;
            }

            this.save();
        },

        reload(tab) {
            this.closeMenu();
            if (tab.id === this.activeId) {
                window.location.reload();

                return;
            }

            this.activate(tab);
        },

        duplicate(tab) {
            this.closeMenu();
            this.open({ url: tab.url, title: tab.title, group: tab.group }, tab.id);
        },

        togglePin(tab) {
            this.closeMenu();
            tab.pinned = !tab.pinned;
            // Pinned tabs come first, each kind keeping its order.
            this.tabs = [...this.tabs.filter((item) => item.pinned), ...this.tabs.filter((item) => !item.pinned)];
            this.save();
        },

        openMenu(event, tab) {
            const width = 224;
            const rtl = document.documentElement.dir === 'rtl';
            const x = rtl ? Math.max(8, event.clientX - width) : Math.min(event.clientX, window.innerWidth - width - 8);
            this.menu = { id: tab.id, x, y: Math.min(event.clientY, window.innerHeight - 260) };
        },

        closeMenu() {
            this.menu = null;
        },

        menuTab() {
            return this.tabs.find((tab) => tab.id === this.menu?.id) ?? null;
        },

        index(tab) {
            return this.tabs.findIndex((item) => item.id === tab.id);
        },

        dragStart(event, tab) {
            this.dragging = tab.id;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', tab.id);
        },

        /** Moves the dragged tab to where the target is, among the tabs of its kind (pinned or not). */
        dragOver(event, target) {
            const dragged = this.tabs.find((tab) => tab.id === this.dragging);
            if (!dragged || dragged.id === target.id || dragged.pinned !== target.pinned) return;

            event.preventDefault();
            const from = this.index(dragged);
            const to = this.index(target);
            this.tabs.splice(from, 1);
            this.tabs.splice(to, 0, dragged);
        },

        dragEnd() {
            this.dragging = null;
            this.save();
        },

        icon(group) {
            return this.icons[group] ?? this.icons.other ?? '';
        },
    }));
});
