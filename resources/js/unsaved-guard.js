/**
 * Warns before leaving an entry screen with changes that are not saved (U-04): closing or reloading the tab, and links
 * followed through wire:navigate. Typing in the form marks it changed. A save button calls `leaving()` so its own
 * redirect is not stopped; once the request is over without leaving (a refused save) the warning is armed again.
 * A saved or restored draft sends `form-clean`.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('unsavedGuard', () => ({
        dirty: false,
        saving: false,

        init() {
            const touched = () => {
                this.dirty = true;
                this.saving = false;
            };

            this.$el.addEventListener('input', touched);
            this.$el.addEventListener('change', touched);

            this.onBeforeUnload = (event) => {
                if (this.dirty && ! this.saving) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            };

            this.onNavigate = (event) => {
                if (this.dirty && ! this.saving && ! window.confirm(this.$el.dataset.unsavedMessage)) {
                    event.preventDefault();
                }
            };

            this.onRequestDone = () => {
                this.saving = false;
            };

            window.addEventListener('beforeunload', this.onBeforeUnload);
            document.addEventListener('livewire:navigate', this.onNavigate);
            window.addEventListener('hc-request-done', this.onRequestDone);
        },

        destroy() {
            window.removeEventListener('beforeunload', this.onBeforeUnload);
            document.removeEventListener('livewire:navigate', this.onNavigate);
            window.removeEventListener('hc-request-done', this.onRequestDone);
        },

        leaving() {
            this.saving = true;
        },

        clean() {
            this.dirty = false;
            this.saving = false;
        },
    }));
});

// After a Livewire request, once its effects (a redirect among them) have run.
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('request', ({ succeed, fail }) => {
        const done = () => setTimeout(() => window.dispatchEvent(new CustomEvent('hc-request-done')), 0);

        succeed(done);
        fail(done);
    });
});
