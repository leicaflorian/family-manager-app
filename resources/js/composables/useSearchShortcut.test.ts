import { router } from '@inertiajs/vue3';
import { render } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent, h } from 'vue';
import { press } from '@/test/page';
import { useSearchShortcut } from './useSearchShortcut';

/**
 * Renders a page that uses the shortcut.
 *
 * @param focus - what the field does, if the page has one
 * @returns a way to unmount the page
 */
function renderPage(focus?: () => void): { unmount: () => void } {
    const Page = defineComponent({
        setup() {
            useSearchShortcut(focus);

            return () => h('div');
        },
    });

    return render(Page);
}

describe('useSearchShortcut', () => {
    afterEach(() => vi.restoreAllMocks());

    it.each([
        { keys: '⌘K', init: { key: 'k', metaKey: true } },
        { keys: 'Ctrl K', init: { key: 'k', ctrlKey: true } },
    ])('focuses the field with $keys', ({ init }) => {
        const focus = vi.fn();
        renderPage(focus);

        const event = press(init);

        expect(focus).toHaveBeenCalledOnce();
        expect(event.defaultPrevented).toBe(true);
    });

    it('opens the search page when there is no field', () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage();

        press({ key: 'k', metaKey: true });

        expect(visit).toHaveBeenCalledWith('/search');
    });

    it('does nothing with another key', () => {
        const focus = vi.fn();
        renderPage(focus);

        const event = press({ key: 's', metaKey: true });

        expect(focus).not.toHaveBeenCalled();
        expect(event.defaultPrevented).toBe(false);
    });

    it('stops listening once the page has gone', () => {
        const focus = vi.fn();
        const { unmount } = renderPage(focus);

        unmount();
        press({ key: 'k', metaKey: true });

        expect(focus).not.toHaveBeenCalled();
    });
});
