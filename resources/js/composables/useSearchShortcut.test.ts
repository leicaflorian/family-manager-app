import { router } from '@inertiajs/vue3';
import { render, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent, h } from 'vue';
import { press } from '@/test/page';
import { useSearchShortcut } from './useSearchShortcut';

/**
 * Renders a page that uses the shortcut.
 *
 * @param focus - what the field does, if the page has one
 * @param active - whether the shortcut works now
 * @returns a way to unmount the page
 */
function renderPage(
    focus?: () => void,
    active?: () => boolean,
): { unmount: () => void } {
    const Page = defineComponent({
        setup() {
            useSearchShortcut(focus, active);

            return () =>
                h('div', [
                    h('input', { 'aria-label': 'Name' }),
                    h('textarea', { 'aria-label': 'Notes' }),
                    h('div', {
                        'aria-label': 'Text',
                        contenteditable: 'true',
                    }),
                    h('div', {
                        'aria-label': 'Choice',
                        role: 'combobox',
                        tabindex: 0,
                    }),
                ]);
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

    it('focuses the field with «/» pressed outside a text field', () => {
        const focus = vi.fn();
        renderPage(focus);

        const event = press({ key: '/' });

        expect(focus).toHaveBeenCalledOnce();
        expect(event.defaultPrevented).toBe(true);
    });

    it.each(['Name', 'Notes', 'Text', 'Choice'])(
        'leaves «/» to the person writing in %s',
        (label) => {
            const focus = vi.fn();
            renderPage(focus);
            const target = screen.getByLabelText(label);

            const event = new KeyboardEvent('keydown', {
                key: '/',
                bubbles: true,
                cancelable: true,
            });
            target.dispatchEvent(event);

            expect(focus).not.toHaveBeenCalled();
            expect(event.defaultPrevented).toBe(false);
        },
    );

    it('leaves «/» alone while a word is being composed or the key was already used', () => {
        const focus = vi.fn();
        renderPage(focus);

        press({ key: '/', isComposing: true });
        const used = new KeyboardEvent('keydown', {
            key: '/',
            bubbles: true,
            cancelable: true,
        });
        used.preventDefault();
        document.body.dispatchEvent(used);

        expect(focus).not.toHaveBeenCalled();
    });

    it('opens the search page with «/» when there is no field', () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage();

        press({ key: '/' });

        expect(visit).toHaveBeenCalledWith('/search');
    });

    it('does nothing while it is not active, and works again once it is', () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        let active = false;
        renderPage(undefined, () => active);

        const event = press({ key: 'k', metaKey: true });
        expect(visit).not.toHaveBeenCalled();
        expect(event.defaultPrevented).toBe(false);

        active = true;
        press({ key: 'k', metaKey: true });
        expect(visit).toHaveBeenCalledOnce();
    });

    it('stops listening once the page has gone', () => {
        const focus = vi.fn();
        const { unmount } = renderPage(focus);

        unmount();
        press({ key: 'k', metaKey: true });

        expect(focus).not.toHaveBeenCalled();
    });
});
