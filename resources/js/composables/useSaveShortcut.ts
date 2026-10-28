import { onMounted, onUnmounted } from 'vue';

/**
 * Saves a form with ⌘S on the Mac and Ctrl+S elsewhere, instead of the
 * browser saving the page. Both keys work everywhere: Ctrl+S does nothing else
 * on the Mac, and the system keeps ⊞S for itself on Windows.
 *
 * @param save - what the *Save* button of the form does
 * @param when - when the keys save, for example not while the form is
 *   already saving; the browser does not save the page either way
 */
export function useSaveShortcut(
    save: () => void,
    when: () => boolean = (): boolean => true,
): void {
    /**
     * Saves when the keys are ⌘S or Ctrl+S.
     *
     * @param event - a key pressed on the page
     */
    function onKeydown(event: KeyboardEvent): void {
        if (
            (event.metaKey || event.ctrlKey) &&
            !event.shiftKey &&
            !event.altKey &&
            event.key.toLowerCase() === 's'
        ) {
            event.preventDefault();

            if (when()) {
                save();
            }
        }
    }

    onMounted(() => window.addEventListener('keydown', onKeydown));
    onUnmounted(() => window.removeEventListener('keydown', onKeydown));
}
