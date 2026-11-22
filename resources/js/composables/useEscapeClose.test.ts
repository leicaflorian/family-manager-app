import { render } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent, h } from 'vue';
import { useEscapeClose } from './useEscapeClose';

/**
 * Mounts a component that uses the composable.
 *
 * @param layers - the extra selectors
 * @returns the function that was given to close
 */
function mountIt(layers: string[] = []) {
    const close = vi.fn();

    render(
        defineComponent({
            setup() {
                useEscapeClose(close, layers);

                return () => h('div');
            },
        }),
    );

    return close;
}

/**
 * Presses Escape on an element.
 *
 * @param target - where the key comes from
 */
function escape(target: Element | Document): void {
    target.dispatchEvent(
        new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
    );
}

describe('useEscapeClose', () => {
    it('closes on a plain Escape', () => {
        const close = mountIt();

        escape(document.body);

        expect(close).toHaveBeenCalledTimes(1);
    });

    it('ignores other keys and an Escape already taken', () => {
        const close = mountIt();

        document.body.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'a', bubbles: true }),
        );
        document.body.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Escape',
                bubbles: true,
                cancelable: true,
            }),
        );
        const taken = new KeyboardEvent('keydown', {
            key: 'Escape',
            bubbles: true,
            cancelable: true,
        });
        taken.preventDefault();
        document.body.dispatchEvent(taken);

        expect(close).toHaveBeenCalledTimes(1);
    });

    it('leaves the Escape to an open menu, list or field with its list open', () => {
        const close = mountIt();

        for (const html of [
            '<div role="menu"><button></button></div>',
            '<div role="listbox"><div></div></div>',
            '<input aria-expanded="true" />',
        ]) {
            const holder = document.createElement('div');
            holder.innerHTML = html;
            document.body.append(holder);
            escape(holder.querySelector('button, div, input') ?? holder);
            holder.remove();
        }

        expect(close).not.toHaveBeenCalled();
    });

    it('leaves the Escape to the extra layers given', () => {
        const close = mountIt(['[role="dialog"]']);
        const holder = document.createElement('div');
        holder.innerHTML = '<div role="dialog"><button></button></div>';
        document.body.append(holder);

        escape(holder.querySelector('button') as Element);
        holder.remove();

        expect(close).not.toHaveBeenCalled();
    });
});
