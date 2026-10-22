/**
 * Flips between Light and Dark appearance instantly (no animation).
 *
 * The theme buttons (desktop top header and mobile drawer) call
 * window.hcToggleAppearance(), which switches to the opposite of what the
 * page currently shows. Someone who has never pressed the button stays on
 * Flux's default, 'system', and follows their device until the first press.
 *
 * Flux still owns the choice: $flux.appearance is its store, it keeps the
 * choice in localStorage ('flux.appearance') and it toggles .dark on <html>.
 * Flux does that from an Alpine effect, which runs in a later microtask rather
 * than when the store is written, so the class is also set here,
 * synchronously; Flux's effect then finds the class in place and only stores
 * the choice.
 */
const applyAppearance = (appearance) => {
    window.Flux.appearance = appearance;
    document.documentElement.classList.toggle('dark', appearance === 'dark');
};

const toggleAppearance = () => {
    if (!window.Flux) {
        return;
    }

    const appearance = document.documentElement.classList.contains('dark') ? 'light' : 'dark';

    applyAppearance(appearance);
};

window.hcToggleAppearance = toggleAppearance;
