import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed, watch } from 'vue';

const DARK_QUERY = '(prefers-color-scheme: dark)';

/**
 * Keeps the `dark` class of `<html>` right for the theme of the account.
 *
 * The server and the inline script of `app.blade.php` draw the first paint; this
 * keeps it right while the app is open: a theme saved after a visit applies at
 * once, and with `system` the page follows the device when its scheme changes.
 * It reads the shared `usePage()` state, which is global, so it also works outside
 * a component (it is called once in `app.ts` and lives as long as the app).
 *
 * Nothing changes until the first page is loaded.
 *
 * @returns the theme of the account (`system` when the page shares none) and `apply()`, which sets the class now
 */
export function useTheme(): {
    theme: ComputedRef<App.Enums.Theme>;
    apply: () => void;
} {
    const page = usePage();
    const theme = computed(
        (): App.Enums.Theme => page.props?.theme ?? 'system',
    );

    /** Adds or removes the `dark` class from the theme and, for `system`, from the device. */
    function apply(): void {
        const dark =
            theme.value === 'dark' ||
            (theme.value === 'system' && window.matchMedia(DARK_QUERY).matches);

        document.documentElement.classList.toggle('dark', dark);
    }

    // Until the first page is loaded its props do not exist: the class the server and
    // the inline script set is right, and a fallback to `system` would undo a dark theme.
    const loaded = computed((): boolean => page.props !== undefined);

    watch(
        [loaded, theme],
        ([isLoaded, value], _previous, onCleanup): void => {
            if (!isLoaded) {
                return;
            }

            apply();

            if (value !== 'system') {
                return;
            }

            const media = window.matchMedia(DARK_QUERY);

            media.addEventListener('change', apply);
            onCleanup((): void => media.removeEventListener('change', apply));
        },
        { immediate: true },
    );

    return { theme, apply };
}
