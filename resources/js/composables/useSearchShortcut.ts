import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';
import { search } from '@/routes';

/**
 * Opens the search with ⌘K on the Mac and Ctrl K elsewhere, instead of the
 * browser using the keys.
 *
 * @param focus - what the search field of the page does, to focus it; without
 *   it the keys open the *Search* page
 */
export function useSearchShortcut(focus?: () => void): void {
    /**
     * Opens the search when the keys are ⌘K or Ctrl K.
     *
     * @param event - a key pressed on the page
     */
    function onKeydown(event: KeyboardEvent): void {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();

            if (focus) {
                focus();
            } else {
                router.visit(search.url());
            }
        }
    }

    onMounted(() => document.addEventListener('keydown', onKeydown));
    onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
}
