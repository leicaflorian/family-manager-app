import { computed, ref, watch, type ComputedRef, type Ref } from 'vue';
import { useSearchSuggest } from '@/composables/useSearchSuggest';

/** The id of the menu of the first results, for `aria-controls` of the box. */
export const SEARCH_MENU_ID = 'search-results';

/** The id of the input of the *Search* page on the phone, which the search shortcut focuses. */
export const SEARCH_PAGE_FIELD = 'search-page-field';

/** What the box asks of the menu of the first results (`FmSearchMenu`). */
export type SearchMenuControls = {
    /** Moves the choice by one row. */
    move: (step: 1 | -1) => void;
    /** Opens the chosen row; false when none is chosen. */
    open: () => boolean;
    /** Forgets the chosen row. */
    reset: () => void;
};

/**
 * The search box of the whole app with its menu of the first results, as the
 * Home and the *Search* page use it: while the person types the menu opens
 * with what the server finds, the arrows move the choice, Enter opens the
 * chosen result (or searches the text when none is chosen) and Esc closes the
 * menu until the text changes again. Where the menu sits inside the element of
 * the box, `onFocusIn` and `onFocusOut` on that element tell whether the box
 * has the focus, and close the menu when the focus goes away.
 *
 * @param text - what is written in the box
 * @param menu - the menu, once it is on the page
 * @param submit - what searching the text does
 * @returns the results of the menu (`null` while it is closed) and what the
 *   box does on Enter, on a key, on Esc and when the focus leaves it
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
    focused: Ref<boolean>;
    onFocusIn: () => void;
    onFocusOut: (event: FocusEvent) => void;
} {
    const { results: suggested } = useSearchSuggest(text);

    /** The menu is wanted: the text changed and Esc was not pressed since. */
    const menuOpen = ref(false);

    watch(text, () => {
        menuOpen.value = true;
        // the row chosen belongs to the answer for the old text
        menu.value?.reset();
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

    /** The box or its menu has the focus. */
    const focused = ref(false);

    /** The focus enters the box or its menu. */
    function onFocusIn(): void {
        focused.value = true;
    }

    /**
     * The focus leaves an element of the box: when it goes outside the box and
     * its menu, the box has no focus any more and the menu closes.
     *
     * @param event - the focus leaving, heard on the element of the box
     */
    function onFocusOut(event: FocusEvent): void {
        const box = event.currentTarget;
        const to = event.relatedTarget;

        if (
            !(box instanceof Node) ||
            !(to instanceof Node) ||
            !box.contains(to)
        ) {
            focused.value = false;
            closeMenu();
        }
    }

    return {
        menuResults,
        enter,
        onKeydown,
        closeMenu,
        focused,
        onFocusIn,
        onFocusOut,
    };
}
