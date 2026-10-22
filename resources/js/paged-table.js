/**
 * Paginates the rows of a table in the browser, inside a scrolling card.
 *
 * The wrapper scrolls (see `.fin-table-wrap` in finance.css) and shows `perPage`
 * rows at a time with a Prev / Next footer. Rows are hidden, not removed, so the
 * server-rendered table still works with JavaScript off. A MutationObserver
 * re-applies the page after Livewire morphs the tbody (filters, sorting).
 * Pass `{ paginate: false }` for tables that Livewire already paginates.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('pagedTable', (options = {}) => ({
        perPage: options.perPage ?? 10,
        paginate: options.paginate ?? true,
        page: 1,
        total: 0,
        observer: null,

        init() {
            if (!this.paginate) return;
            const body = this.$el.querySelector('tbody');
            if (!body) return;

            this.observer = new MutationObserver(() => this.render());
            this.observer.observe(body, { childList: true });
            this.render();
        },

        destroy() {
            this.observer?.disconnect();
        },

        rows() {
            return [...this.$el.querySelectorAll('tbody > tr')].filter((row) => !row.querySelector('[colspan]'));
        },

        get pages() {
            return Math.max(1, Math.ceil(this.total / this.perPage));
        },

        get from() {
            return this.total === 0 ? 0 : (this.page - 1) * this.perPage + 1;
        },

        get to() {
            return Math.min(this.total, this.page * this.perPage);
        },

        go(page) {
            this.page = Math.min(this.pages, Math.max(1, page));
            this.render();
        },

        render() {
            const rows = this.rows();
            this.total = rows.length;
            if (this.page > this.pages) this.page = this.pages;
            const start = (this.page - 1) * this.perPage;
            rows.forEach((row, index) => {
                const hidden = index < start || index >= start + this.perPage;
                if (row.hidden !== hidden) row.hidden = hidden;
            });
        },
    }));
});
