// U-02: when the session has expired (419), show the app's own window in the person's language instead of
// Livewire's English "This page has expired" prompt (resources/views/components/session-expired.blade.php).
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            if (status !== 419) {
                return;
            }

            preventDefault();
            window.dispatchEvent(new CustomEvent('hc-session-expired'));
        });
    });
});
