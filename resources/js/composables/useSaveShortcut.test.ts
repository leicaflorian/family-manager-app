import { render } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { defineComponent, h } from 'vue';
import { press } from '@/test/page';
import { useSaveShortcut } from './useSaveShortcut';

/**
 * A page with a form that counts how many times it is saved.
 *
 * @param when - when the keys save, if not always
 * @returns the page, and how many saves there were
 */
function renderForm(when?: () => boolean): {
    saves: () => number;
    unmount: () => void;
} {
    let saves = 0;
    const Form = defineComponent({
        setup() {
            useSaveShortcut(() => {
                saves++;
            }, when);

            return () => h('form');
        },
    });
    const result = render(Form);

    return { saves: () => saves, unmount: () => result.unmount() };
}

describe('useSaveShortcut', () => {
    it.each([
        { keys: '⌘S', init: { key: 's', metaKey: true } },
        { keys: 'Ctrl+S', init: { key: 's', ctrlKey: true } },
    ])(
        'saves with $keys, instead of the browser saving the page',
        ({ init }) => {
            const { saves } = renderForm();

            const event = press(init);

            expect(saves()).toBe(1);
            expect(event.defaultPrevented).toBe(true);
        },
    );

    it('does not save with S alone', () => {
        const { saves } = renderForm();

        const event = press({ key: 's' });

        expect(saves()).toBe(0);
        expect(event.defaultPrevented).toBe(false);
    });

    it('does not save when the form says it cannot, for example while it is saving', () => {
        const { saves } = renderForm(() => false);

        const event = press({ key: 's', metaKey: true });

        expect(saves()).toBe(0);
        expect(event.defaultPrevented).toBe(true);
    });

    it('no longer saves once the form has left the page', () => {
        const { saves, unmount } = renderForm();

        unmount();
        press({ key: 's', metaKey: true });

        expect(saves()).toBe(0);
    });
});
