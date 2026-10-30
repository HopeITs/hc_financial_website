/**
 * Keyboard shortcuts (U-07). The page marks what a shortcut presses with data-shortcut: "save" (Ctrl+S),
 * Ctrl+Enter (save, then a fresh form), "add-line" (Alt+N). Ctrl+K opens the search and ? lists the shortcuts.
 * Letters are read from the physical key, so they work with the Arabic keyboard layout too.
 */

const typing = (element) =>
    element instanceof HTMLElement && (element.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(element.tagName));

const shown = (element) => element instanceof HTMLElement && element.offsetParent !== null;

/** The first visible element with that shortcut, enabled or not (a disabled save still keeps the browser's own Ctrl+S away). */
const target = (name) => [...document.querySelectorAll(`[data-shortcut="${name}"]`)].find(shown) ?? null;

const enabled = (element) => !element.disabled && element.getAttribute('aria-disabled') !== 'true';

/** Ctrl+Enter: tell the component to open a fresh form after this save. */
const saveThenNew = (button) => {
    const root = button.closest('[wire\\:id]');
    const component = root && window.Livewire?.find(root.getAttribute('wire:id'));

    if (component) {
        component.thenNew = true;
    }

    button.click();
};

document.addEventListener('keydown', (event) => {
    if (event.defaultPrevented || event.isComposing) {
        return;
    }

    const mod = event.ctrlKey || event.metaKey;

    if (mod && !event.altKey && event.code === 'KeyK') {
        event.preventDefault();
        window.dispatchEvent(new CustomEvent('hc-search-open'));

        return;
    }

    if (mod && !event.altKey && (event.code === 'KeyS' || event.key === 'Enter')) {
        const button = target('save');

        if (button === null) {
            return;
        }

        event.preventDefault();

        if (enabled(button)) {
            event.key === 'Enter' ? saveThenNew(button) : button.click();
        }

        return;
    }

    if (event.altKey && !mod && event.code === 'KeyN') {
        const button = target('add-line');

        if (button !== null && enabled(button)) {
            event.preventDefault();
            button.click();
        }

        return;
    }

    if (!mod && !event.altKey && (event.key === '?' || event.key === '؟') && !typing(event.target)) {
        event.preventDefault();
        window.dispatchEvent(new CustomEvent('hc-shortcuts-open'));
    }
});
