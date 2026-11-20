/**
 * Whether the sidebar of the computer is collapsed to its icons, remembered in this browser only: a
 * convenience of who uses it, not something the server needs to know.
 */
import { ref, watch, type Ref } from 'vue';

/** The key of the browser storage. */
const STORAGE_KEY = 'fm.sidebar.collapsed';

/**
 * Reads what this browser remembers.
 *
 * @returns true when the sidebar was collapsed; false also when the browser keeps no storage
 */
function remembered(): boolean {
    try {
        return localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

/**
 * Writes the choice in the browser.
 *
 * @param collapsed - whether the sidebar is collapsed
 */
function remember(collapsed: boolean): void {
    try {
        if (collapsed) {
            localStorage.setItem(STORAGE_KEY, '1');
        } else {
            localStorage.removeItem(STORAGE_KEY);
        }
    } catch {
        // without storage the sidebar opens again at the next page load, and that is all
    }
}

/**
 * The collapsed state of the sidebar of the computer, starting from what the browser remembers.
 *
 * @returns a ref that saves each change in the browser
 */
export function useSidebarCollapsed(): Ref<boolean> {
    const collapsed = ref(remembered());

    watch(collapsed, remember);

    return collapsed;
}
