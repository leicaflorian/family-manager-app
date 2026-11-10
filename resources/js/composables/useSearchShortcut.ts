import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';
import { search } from '@/routes';

/**
 * Whether a key was pressed while the person writes or chooses: in a field, a
 * text area, a menu, a text that can be edited or a widget that reads the keys
 * itself (combobox, textbox, listbox), where «/» belongs to it.
 *
 * @param target - where the key was pressed
 * @returns true inside something that takes text
 */
function writing(target: EventTarget | null): boolean {
    return (
        target instanceof Element &&
        target.closest(
            'input, textarea, select, [contenteditable]:not([contenteditable="false"]), [role="combobox"], [role="textbox"], [role="listbox"]',
        ) !== null
    );
}

/**
 * Whether the keys are the shortcut of the search: ⌘K or Ctrl K anywhere,
 * «/» alone outside a field.
 *
 * @param event - a key pressed on the page
 * @returns true for the shortcut
 */
function isShortcut(event: KeyboardEvent): boolean {
    // a key that composes a character, or that something already used, is not ours
    if (event.isComposing || event.defaultPrevented) {
        return false;
    }

    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        return true;
    }

    return (
        event.key === '/' &&
        !event.metaKey &&
        !event.ctrlKey &&
        !event.altKey &&
        !writing(event.target)
    );
}

/**
 * Opens the search with ⌘K on the Mac and Ctrl K elsewhere, instead of the
 * browser using the keys, and with «/» pressed outside a field.
 *
 * @param focus - what the search field of the page does, to focus it; without
 *   it the keys open the *Search* page
 * @param active - whether the shortcut works now (a layout leaves it to a page
 *   that has its own); always when left out
 */
export function useSearchShortcut(
    focus?: () => void,
    active: () => boolean = () => true,
): void {
    /**
     * Opens the search when the keys are the shortcut.
     *
     * @param event - a key pressed on the page
     */
    function onKeydown(event: KeyboardEvent): void {
        if (!active() || !isShortcut(event)) {
            return;
        }

        event.preventDefault();

        if (focus) {
            focus();
        } else {
            router.visit(search.url());
        }
    }

    onMounted(() => document.addEventListener('keydown', onKeydown));
    onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
}
