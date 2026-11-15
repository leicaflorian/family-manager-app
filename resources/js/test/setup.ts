/**
 * Common setup of the Vitest tests: the jest-dom matchers
 * (`toBeInTheDocument`, `toHaveClass`, …), the translations and the cleanup of
 * the DOM after each test.
 */
import '@testing-library/jest-dom/vitest';
import { cleanup, configure } from '@testing-library/vue';
import { config } from '@vue/test-utils';
import { i18nVue } from 'laravel-vue-i18n';
import { afterEach, vi } from 'vite-plus/test';
import { i18nOptions } from '@/lib/i18n';

// findBy… and waitFor wait 3 s, not 1: on the GitHub runner a page that loads another with a dynamic import
// (EditReminder over the Agenda) took just over a second
configure({ asyncUtilTimeout: 3000 });

// The Inertia <Head> writes the page title with the manager that
// createInertiaApp creates: in the tests there is no app, and no title is needed.
// Without an app usePage() has no props: the name of the app, which the
// wordmark and the texts with :app read, comes from the shared props of the tests.
vi.mock('@inertiajs/vue3', async (original) => {
    const inertia = await original<typeof import('@inertiajs/vue3')>();
    const { sharedPageProps } = await import('@/test/fixtures');

    return {
        ...inertia,
        Head: { render: () => null },
        usePage: () =>
            new Proxy(inertia.usePage(), {
                get: (page, key) =>
                    key === 'props' && page.props === undefined
                        ? { name: sharedPageProps.name }
                        : Reflect.get(page, key),
            }),
    };
});

// jsdom does not measure the page: the FormKit Pro dropdown watches its size
// with a ResizeObserver, which here has nothing to report.
globalThis.ResizeObserver ??= class {
    /** Nothing to watch in jsdom. */
    observe(): void {}
    /** Nothing to stop watching in jsdom. */
    unobserve(): void {}
    /** Nothing to disconnect in jsdom. */
    disconnect(): void {}
};

// jsdom has no matchMedia, nor element.animate: auto-animate asks whether the
// person wants less motion, and here the answer is yes, so it does not animate.
// The Node-only tests (`@vitest-environment node`) have no window at all.
if (typeof window !== 'undefined') {
    window.matchMedia ??= (query: string): MediaQueryList =>
        ({
            matches: query.includes('prefers-reduced-motion'),
            media: query,
            onchange: null,
            addEventListener: () => {},
            removeEventListener: () => {},
            addListener: () => {},
            removeListener: () => {},
            dispatchEvent: () => false,
        }) satisfies MediaQueryList;
}

// jsdom has no scrollIntoView, which the FormKit Pro menus call when they open.
if (typeof window !== 'undefined' && !('scrollIntoView' in Element.prototype)) {
    Object.defineProperty(Element.prototype, 'scrollIntoView', {
        value: (): void => {},
        writable: true,
        configurable: true,
    });
}

// jsdom has no elementFromPoint, which the drag-and-drop of `FmSortableList` reads on every pointer press in a row.
if (typeof document !== 'undefined' && !('elementFromPoint' in document)) {
    Object.defineProperty(document, 'elementFromPoint', {
        value: (): Element | null => null,
        writable: true,
        configurable: true,
    });
}

// jsdom has no pointer capture, which the toasts of `vue-sonner` ask for when a button of a toast is pressed.
if (
    typeof Element !== 'undefined' &&
    !('setPointerCapture' in Element.prototype)
) {
    Object.defineProperty(Element.prototype, 'setPointerCapture', {
        value: (): void => {},
        writable: true,
        configurable: true,
    });
    Object.defineProperty(Element.prototype, 'releasePointerCapture', {
        value: (): void => {},
        writable: true,
        configurable: true,
    });
}

// The tests read the texts in English, the language they are written in.
config.global.plugins.push([i18nVue, i18nOptions('en')]);

afterEach(() => {
    cleanup();
});
