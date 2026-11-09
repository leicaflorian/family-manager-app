import { computed, ref, watch, type ComputedRef, type Ref } from 'vue';
import { useSearchSuggest } from '@/composables/useSearchSuggest';

/** What the box asks of the menu of the first results (`FmSearchMenu`). */
export type SearchMenuControls = {
    /** Moves the choice by one row. */
    move: (step: 1 | -1) => void;
    /** Opens the chosen row; false when none is chosen. */
    open: () => boolean;
};

/**
 * The search box of the whole app with its menu of the first results, as the
 * Home and the *Search* page use it: while the person types the menu opens
 * with what the server finds, the arrows move the choice, Enter opens the
 * chosen result (or searches the text when none is chosen) and Esc closes the
 * menu until the text changes again.
 *
 * @param text - what is written in the box
 * @param menu - the menu, once it is on the page
 * @param submit - what searching the text does
 * @returns the results of the menu (`null` while it is closed) and what the
 *   box does on Enter, on a key and on Esc
 */
export function useSearchBox(
    text: Ref<string>,
    menu: Readonly<Ref<SearchMenuControls | null>>,
    submit: () => void,
): {
    menuResults: ComputedRef<App.Data.Search.SearchResultsData | null>;
    enter: () => void;
    onKeydown: (event: KeyboardEvent) => void;
    closeMenu: () => void;
} {
    const { results: suggested } = useSearchSuggest(text);

    /** The menu is wanted: the text changed and Esc was not pressed since. */
    const menuOpen = ref(false);

    watch(text, () => {
        menuOpen.value = true;
    });

    const menuResults = computed(
        (): App.Data.Search.SearchResultsData | null =>
            menuOpen.value &&
            suggested.value !== null &&
            suggested.value.total > 0
                ? suggested.value
                : null,
    );

    /** Enter: opens the result chosen with the arrows, otherwise searches. */
    function enter(): void {
        if (menuResults.value !== null && menu.value?.open() === true) {
            return;
        }

        submit();
    }

    /**
     * The arrows of the box move the choice in the menu.
     *
     * @param event - the key pressed in the box
     */
    function onKeydown(event: KeyboardEvent): void {
        if (menuResults.value === null) {
            return;
        }

        const step =
            event.key === 'ArrowDown' ? 1 : event.key === 'ArrowUp' ? -1 : null;

        if (step !== null) {
            event.preventDefault();
            menu.value?.move(step);
        }
    }

    /** Esc closes the menu (the box keeps its text). */
    function closeMenu(): void {
        menuOpen.value = false;
    }

    return { menuResults, enter, onKeydown, closeMenu };
}
