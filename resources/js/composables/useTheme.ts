import { router, usePage } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { computed, ref, watch } from 'vue';

const DARK_QUERY = '(prefers-color-scheme: dark)';

/**
 * The theme the server last sent, or `undefined` until the first page is loaded.
 * Shared by the app and the pages that show the choice (*My account*).
 */
const serverTheme: Ref<App.Enums.Theme | undefined> = ref(undefined);

/** Stops the listeners of the previous `useTheme()` call: only one lives at a time. */
let dispose: (() => void) | undefined;

/**
 * Reads the theme out of the props of a page.
 *
 * @param props - the props of a page, or `undefined` before the first one
 * @returns the theme, `system` when the page shares none, `undefined` without props
 */
function themeOf(
    props: { theme?: App.Enums.Theme } | undefined,
): App.Enums.Theme | undefined {
    return props === undefined ? undefined : (props.theme ?? 'system');
}

/**
 * Keeps the `dark` class of `<html>` right for the theme of the account.
 *
 * The server and the inline script of `app.blade.php` draw the first paint; this
 * keeps it right while the app is open: a theme saved after a visit applies at
 * once, and with `system` the page follows the device when its scheme changes.
 *
 * The theme comes only from what the server sent: the first page and every answer
 * of the server (`success`), including a redirect to the page already open. A page restored from the history (Back, Forward) or from
 * the prefetch cache carries the props it had then, and its old theme is ignored.
 * Nothing changes until the first page is loaded.
 *
 * Call it once, in `app.ts`: it listens to the navigations as long as the app lives
 * (a second call replaces the first).
 *
 * @returns the theme of the account (`system` when the page shares none) and `apply()`, which sets the class now
 */
export function useTheme(): {
    theme: ComputedRef<App.Enums.Theme>;
    apply: () => void;
} {
    dispose?.();
    serverTheme.value = themeOf(usePage().props);

    // `success` fires after every answer of the server, also for a visit to the page
    // already open (the redirect back after saving the theme), which fires no
    // `navigate`. A history restore (Back) and a prefetched page not yet shown fire
    // none of them as a fresh answer, so their old props never get here.
    const offSuccess = router.on('success', (event): void => {
        serverTheme.value = themeOf(event.detail.page.props);
    });

    // The first page is shown with `navigate` (`initial`); a reload the browser answers
    // from its history (`history`) is only believed when nothing else is known yet.
    const offNavigate = router.on('navigate', (event): void => {
        if (
            event.detail.type === 'initial' ||
            serverTheme.value === undefined
        ) {
            serverTheme.value = themeOf(event.detail.page.props);
        }
    });

    const theme = computed(
        (): App.Enums.Theme => serverTheme.value ?? 'system',
    );

    /** Adds or removes the `dark` class from the theme and, for `system`, from the device. */
    function apply(): void {
        const dark =
            theme.value === 'dark' ||
            (theme.value === 'system' && window.matchMedia(DARK_QUERY).matches);

        document.documentElement.classList.toggle('dark', dark);
    }

    const stop = watch(
        serverTheme,
        (value, _previous, onCleanup): void => {
            // Until the first page is loaded the class the server and the inline script
            // set is right, and a fallback to `system` would undo a dark theme.
            if (value === undefined) {
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

    dispose = (): void => {
        offSuccess();
        offNavigate();
        stop();
    };

    return { theme, apply };
}

/**
 * The theme of the account as the server last sent it, for the pages that show the choice.
 *
 * @returns a read-only view of the theme (`system` until the first page is loaded)
 */
export function useServerTheme(): ComputedRef<App.Enums.Theme> {
    return computed((): App.Enums.Theme => serverTheme.value ?? 'system');
}
