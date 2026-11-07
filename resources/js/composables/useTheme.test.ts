import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { nextTick, reactive } from 'vue';
import { useTheme } from './useTheme';

const shared = vi.hoisted(() => ({
    page: null as { props?: { theme?: string } } | null,
}));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => shared.page,
}));

/** A device whose colour scheme can change while the test runs. */
function device(dark: boolean): {
    set(next: boolean): void;
    listeners(): number;
} {
    let matches = dark;
    const listeners = new Set<() => void>();

    window.matchMedia = ((query: string): MediaQueryList =>
        ({
            get matches(): boolean {
                return query.includes('prefers-color-scheme: dark') && matches;
            },
            media: query,
            addEventListener: (_type: string, listener: () => void): void => {
                listeners.add(listener);
            },
            removeEventListener: (
                _type: string,
                listener: () => void,
            ): void => {
                listeners.delete(listener);
            },
        }) as unknown as MediaQueryList) as typeof window.matchMedia;

    return {
        set(next: boolean): void {
            matches = next;
            listeners.forEach((listener) => listener());
        },
        listeners: (): number => listeners.size,
    };
}

/**
 * What Inertia fires after the server answered a visit, also one to the page already
 * open (@inertiajs/core `Response.handle`, `fireSuccessEvent`): `inertia:success`.
 * For that same-URL case it fires no `navigate` (`replace` is forced, `visit()` only
 * fires it when `!replace`).
 */
function serverAnswers(theme: string | undefined): void {
    document.dispatchEvent(
        new CustomEvent('inertia:success', {
            detail: { page: { props: { theme } } },
        }),
    );
}

/**
 * What Inertia fires after a page is shown from the history (`popstate`,
 * `handlePopstateEvent`): `navigate` of type `history`, and no `success`.
 */
function historyRestore(theme: string | undefined): void {
    document.dispatchEvent(
        new CustomEvent('inertia:navigate', {
            detail: { page: { props: { theme } }, type: 'history' },
        }),
    );
}

/** What Inertia fires for the first page. */
function firstPage(theme: string | undefined): void {
    document.dispatchEvent(
        new CustomEvent('inertia:navigate', {
            detail: { page: { props: { theme } }, type: 'initial' },
        }),
    );
}

const isDark = (): boolean =>
    document.documentElement.classList.contains('dark');

describe('useTheme', () => {
    const original = window.matchMedia;

    beforeEach(() => {
        document.documentElement.classList.remove('dark');
        shared.page = reactive({ props: { theme: 'system' } });
    });

    afterEach(() => {
        window.matchMedia = original;
    });

    it('draws the page dark when the theme is dark, even on a light device', () => {
        device(false);
        shared.page = reactive({ props: { theme: 'dark' } });

        useTheme();

        expect(isDark()).toBe(true);
    });

    it('draws the page light when the theme is light, even on a dark device', () => {
        device(true);
        document.documentElement.classList.add('dark');
        shared.page = reactive({ props: { theme: 'light' } });

        useTheme();

        expect(isDark()).toBe(false);
    });

    it('follows the device when the theme is system, and its changes without a reload', () => {
        const media = device(true);

        useTheme();
        expect(isDark()).toBe(true);

        media.set(false);
        expect(isDark()).toBe(false);

        media.set(true);
        expect(isDark()).toBe(true);
    });

    it('follows the device when the page has no theme, as the login page', () => {
        device(true);
        shared.page = reactive({ props: {} });

        expect(() => useTheme()).not.toThrow();
        expect(isDark()).toBe(true);
    });

    it('changes the class when the theme changes after a visit, and stops following the device', async () => {
        const media = device(false);

        useTheme();
        expect(isDark()).toBe(false);

        serverAnswers('dark');
        await nextTick();
        expect(isDark()).toBe(true);
        expect(media.listeners()).toBe(0);

        media.set(false);
        expect(isDark()).toBe(true);

        serverAnswers('light');
        await nextTick();
        expect(isDark()).toBe(false);
    });

    it('leaves the class the server set until the first page is loaded', async () => {
        const media = device(false);
        document.documentElement.classList.add('dark');
        const page = reactive<{ props?: { theme?: string } }>({
            props: undefined,
        });
        shared.page = page;

        useTheme();
        expect(isDark()).toBe(true);
        expect(media.listeners()).toBe(0);

        firstPage('dark');
        await nextTick();
        expect(isDark()).toBe(true);

        serverAnswers('system');
        await nextTick();
        expect(isDark()).toBe(false);
    });

    it('keeps the theme when a page restored from the history carries an older one', async () => {
        device(false);
        shared.page = reactive({ props: { theme: 'system' } });
        const { theme } = useTheme();

        serverAnswers('dark');
        await nextTick();
        expect(isDark()).toBe(true);

        historyRestore('system');
        await nextTick();
        expect(isDark()).toBe(true);
        expect(theme.value).toBe('dark');
    });

    it('gives the theme back', () => {
        device(false);
        shared.page = reactive({ props: { theme: 'dark' } });

        expect(useTheme().theme.value).toBe('dark');
    });
});
