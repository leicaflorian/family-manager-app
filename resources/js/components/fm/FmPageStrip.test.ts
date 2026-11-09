import { render, screen } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { firePointer, measure } from '@/test/pointer';
import FmPageStrip from './FmPageStrip.vue';

const pages = [
    { id: 'a', url: null },
    { id: 'b', url: null },
    { id: 'c', url: null },
];

/** Draws the strip with three pages 60 px apart, as the browser would measure them. */
function strip(orientation: 'row' | 'column' = 'row', selected = 'a') {
    const result = render(FmPageStrip, {
        props: { pages, selected, orientation },
    });
    const items = Array.from(result.container.querySelectorAll('[data-page]'));

    items.forEach((item, index) =>
        measure(
            item,
            orientation === 'row'
                ? { left: index * 60, width: 52, height: 70 }
                : { top: index * 60, width: 52, height: 70 },
        ),
    );

    return {
        ...result,
        items,
        emitted: (name: string) => result.emitted(name),
    };
}

beforeEach(() => vi.useFakeTimers());
afterEach(() => vi.useRealTimers());

describe('FmPageStrip', () => {
    it('has a button for each page, the chosen one marked, and one to add', async () => {
        const { emitted } = strip();

        expect(screen.getByRole('button', { name: 'Page 1' })).toHaveAttribute(
            'aria-current',
            'true',
        );
        expect(
            screen.getByRole('button', { name: 'Page 2' }),
        ).not.toHaveAttribute('aria-current');

        screen.getByRole('button', { name: 'Page 2' }).click();
        screen.getByRole('button', { name: 'Add a page' }).click();

        expect(emitted('select')).toEqual([['b']]);
        expect(emitted('add')).toHaveLength(1);
    });

    it('shows a page turned by the quarters it was given', () => {
        const { container } = render(FmPageStrip, {
            props: {
                pages: [
                    { id: 'a', url: 'blob:a', rotation: 90 },
                    { id: 'b', url: 'blob:b' },
                ],
                selected: 'a',
            },
        });
        const [turned, straight] = Array.from(
            container.querySelectorAll('img'),
        );

        expect(turned.style.transform).toContain('rotate(90deg)');
        expect(straight.style.transform).toBe('');
    });

    it('shows the page numbers and the hint only in a column', () => {
        const view = strip('column');

        expect(screen.getByText('Drag to reorder')).toBeInTheDocument();
        expect(screen.getByText('3')).toBeInTheDocument();

        view.unmount();
        strip('row');

        expect(screen.queryByText('Drag to reorder')).not.toBeInTheDocument();
    });

    it('lifts a page held for 500 ms, and not before', async () => {
        const { items } = strip();

        await firePointer(items[0], 'pointerdown', { x: 10 });
        await vi.advanceTimersByTimeAsync(499);
        expect(items[0]).not.toHaveAttribute('data-lifted');

        await vi.advanceTimersByTimeAsync(1);
        expect(items[0]).toHaveAttribute('data-lifted');
    });

    it('moves the others aside and emits the move when the page is put down', async () => {
        const { items, emitted } = strip();

        await firePointer(items[0], 'pointerdown', { x: 10 });
        await vi.advanceTimersByTimeAsync(500);
        await firePointer(items[0], 'pointermove', { x: 75 });

        // the page is now over the second place: the second goes to the first
        expect(items[1].getAttribute('style')).toContain('translateX(-60px)');
        expect(items[2].getAttribute('style') ?? '').not.toContain('translate');

        await firePointer(items[0], 'pointerup', { x: 75 });

        expect(emitted('move')).toEqual([[0, 1]]);
        expect(items[0]).not.toHaveAttribute('data-lifted');
    });

    it('goes down the column with the vertical axis', async () => {
        const { items, emitted } = strip('column');

        await firePointer(items[2], 'pointerdown', { y: 130 });
        await vi.advanceTimersByTimeAsync(500);
        await firePointer(items[2], 'pointermove', { y: 10 });
        await firePointer(items[2], 'pointerup', { y: 10 });

        expect(emitted('move')).toEqual([[2, 0]]);
    });

    it('does not lift a page when the finger moves first: it is scrolling', async () => {
        const { items, emitted } = strip();

        await firePointer(items[0], 'pointerdown', { x: 10 });
        await firePointer(items[0], 'pointermove', { x: 40 });
        await vi.advanceTimersByTimeAsync(600);
        await firePointer(items[0], 'pointerup', { x: 40 });

        expect(items[0]).not.toHaveAttribute('data-lifted');
        expect(emitted('move')).toBeUndefined();
    });

    it('leaves the order alone when the phone takes the touch away', async () => {
        const { items, emitted } = strip();

        await firePointer(items[0], 'pointerdown', { x: 10 });
        await vi.advanceTimersByTimeAsync(500);
        await firePointer(items[0], 'pointermove', { x: 130 });
        await firePointer(items[0], 'pointercancel', { x: 130 });

        expect(emitted('move')).toBeUndefined();
        expect(items[0]).not.toHaveAttribute('data-lifted');
        expect(items[1].getAttribute('style') ?? '').not.toContain('translate');
    });

    it('does not choose the page that was just carried', async () => {
        const { items, emitted } = strip();

        await firePointer(items[0], 'pointerdown', { x: 10 });
        await vi.advanceTimersByTimeAsync(500);
        await firePointer(items[0], 'pointerup', { x: 10 });
        screen.getByRole('button', { name: 'Page 1' }).click();

        expect(emitted('select')).toBeUndefined();
    });

    it('lifts a page by dragging it with the mouse', async () => {
        const { items, emitted } = strip();

        await firePointer(items[0], 'pointerdown', {
            x: 10,
            pointerType: 'mouse',
        });
        await firePointer(items[0], 'pointermove', {
            x: 130,
            pointerType: 'mouse',
        });
        await firePointer(items[0], 'pointerup', {
            x: 130,
            pointerType: 'mouse',
        });

        expect(emitted('move')).toEqual([[0, 2]]);
    });

    it('has nothing to reorder with a single page', async () => {
        const result = render(FmPageStrip, {
            props: { pages: [pages[0]], selected: 'a', orientation: 'row' },
        });
        const item = result.container.querySelector('[data-page]')!;

        await firePointer(item, 'pointerdown', { x: 10 });
        await vi.advanceTimersByTimeAsync(600);

        expect(item).not.toHaveAttribute('data-lifted');
    });
});
