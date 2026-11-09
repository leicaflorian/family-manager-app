import { render, screen } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import type { Corners } from '@/lib/scan/crop';
import { firePointer, measure } from '@/test/pointer';
import FmCropStage from './FmCropStage.vue';

const corners: Corners = [
    { x: 0.17, y: 0.12 },
    { x: 0.84, y: 0.09 },
    { x: 0.88, y: 0.88 },
    { x: 0.13, y: 0.91 },
];

/** Draws the stage as a 350 by 470 box. */
function stage(props: Record<string, unknown> = {}) {
    const result = render(FmCropStage, {
        props: {
            image: '/photo.jpg',
            corners,
            turns: 0,
            editing: true,
            ...props,
        },
    });
    const box = result.container.querySelector('[data-stage]')!;

    measure(box, { left: 100, top: 50, width: 350, height: 470 });

    return {
        ...result,
        box,
        emitted: (name: string) => result.emitted(name),
    };
}

beforeEach(() => vi.useFakeTimers());
afterEach(() => vi.useRealTimers());

describe('FmCropStage', () => {
    it('has the four handles, named, and the hint', () => {
        stage();

        for (const name of [
            'Top left corner',
            'Top right corner',
            'Bottom right corner',
            'Bottom left corner',
        ]) {
            expect(screen.getByRole('button', { name })).toBeInTheDocument();
        }

        expect(
            screen.getByText('Drag the corners to the edges of the sheet'),
        ).toBeInTheDocument();
    });

    it('puts the handles on the corners and keeps touch-action off the rest', () => {
        const { box } = stage();
        const handle = screen.getByRole('button', { name: 'Top left corner' });

        expect(handle.style.left).toBe('17%');
        expect(handle.style.top).toBe('12%');
        expect(handle).toHaveClass('touch-none');
        expect(box).not.toHaveClass('touch-none');
    });

    it('emits the corners with the dragged one moved, within the photo', async () => {
        const { emitted } = stage();
        const handle = screen.getByRole('button', { name: 'Top left corner' });

        await firePointer(handle, 'pointerdown', { x: 160, y: 110 });
        await firePointer(handle, 'pointermove', { x: 275, y: 285 });

        let [latest] = emitted('update:corners').at(-1) as [Corners];
        expect(latest[0].x).toBeCloseTo(0.5);
        expect(latest[0].y).toBeCloseTo(0.5);
        expect(latest.slice(1)).toEqual(corners.slice(1));

        await firePointer(handle, 'pointermove', { x: 0, y: 9999 });

        [latest] = emitted('update:corners').at(-1) as [Corners];
        expect(latest[0]).toEqual({ x: 0, y: 1 });
    });

    it('moves nothing without a touch on the handle', async () => {
        const { emitted } = stage();
        const handle = screen.getByRole('button', { name: 'Top left corner' });

        await firePointer(handle, 'pointermove', { x: 275, y: 285 });
        await firePointer(handle, 'pointerdown', { x: 160, y: 110 });
        await firePointer(handle, 'pointerup', { x: 160, y: 110 });
        await firePointer(handle, 'pointermove', { x: 275, y: 285 });

        expect(emitted('update:corners')).toBeUndefined();
    });

    it('moves a handle with the arrow keys', async () => {
        const { emitted } = stage();
        const handle = screen.getByRole('button', { name: 'Top left corner' });

        handle.focus();
        handle.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }),
        );

        const [latest] = emitted('update:corners')[0] as [Corners];

        expect(latest[0].x).toBeCloseTo(0.18);
    });

    it('shows the lens with the cross only while a handle is held', async () => {
        stage();
        const handle = screen.getByRole('button', { name: 'Top left corner' });

        expect(screen.queryByTestId('lens')).not.toBeInTheDocument();

        await firePointer(handle, 'pointerdown', { x: 160, y: 110 });

        expect(screen.getByTestId('lens')).toBeInTheDocument();

        await firePointer(handle, 'pointerup', { x: 160, y: 110 });

        expect(screen.queryByTestId('lens')).not.toBeInTheDocument();
    });

    it('moves the lens to the free corner when the handle comes under it', async () => {
        stage();
        const handle = screen.getByRole('button', { name: 'Top left corner' });

        // a handle at the left of the photo, far from the top right corner: the lens is there
        await firePointer(handle, 'pointerdown', { x: 160, y: 110 });
        expect(screen.getByTestId('lens')).toHaveAttribute(
            'data-spot',
            'top-right',
        );

        // the handle goes to the top right corner (within 134 px of the corner both ways): the lens leaves it
        await firePointer(handle, 'pointermove', { x: 440, y: 60 });
        expect(screen.getByTestId('lens')).toHaveAttribute(
            'data-spot',
            'top-left',
        );

        // then it stays there until the handle comes under it
        await firePointer(handle, 'pointermove', { x: 300, y: 200 });
        expect(screen.getByTestId('lens')).toHaveAttribute(
            'data-spot',
            'top-left',
        );
    });

    it('enlarges the photo three times in the lens', async () => {
        stage();
        const handle = screen.getByRole('button', { name: 'Top left corner' });

        await firePointer(handle, 'pointerdown', { x: 160, y: 110 });

        const photo = screen.getByTestId('lens').querySelector('[data-zoom]');

        expect(photo).toHaveStyle({ width: '1050px', height: '1410px' });
    });

    describe('the original', () => {
        it('is peeked after 300 ms on the cut sheet, and put back when the finger leaves', async () => {
            const { box, emitted } = stage({ editing: false });

            await firePointer(box, 'pointerdown', { x: 200, y: 100 });
            await vi.advanceTimersByTimeAsync(299);
            expect(emitted('peek')).toBeUndefined();

            await vi.advanceTimersByTimeAsync(1);
            expect(emitted('peek')).toEqual([[true]]);
            expect(screen.getByText('Original')).toBeInTheDocument();

            await firePointer(box, 'pointerup', { x: 200, y: 100 });
            expect(emitted('peek')).toEqual([[true], [false]]);
            expect(screen.queryByText('Original')).not.toBeInTheDocument();
        });

        it('is not peeked when the finger moves 10 px or more first', async () => {
            const { box, emitted } = stage({ editing: false });

            await firePointer(box, 'pointerdown', { x: 200, y: 100 });
            await firePointer(box, 'pointermove', { x: 215, y: 100 });
            await vi.advanceTimersByTimeAsync(400);

            expect(emitted('peek')).toBeUndefined();
        });

        it('is not peeked while the corners are edited', async () => {
            const { box, emitted } = stage();

            await firePointer(box, 'pointerdown', { x: 200, y: 100 });
            await vi.advanceTimersByTimeAsync(400);

            expect(emitted('peek')).toBeUndefined();
        });

        it('is not peeked when the corners start to be edited while the finger waits', async () => {
            const view = stage({ editing: false });

            await firePointer(view.box, 'pointerdown', { x: 200, y: 100 });
            await view.rerender({ editing: true });
            await vi.advanceTimersByTimeAsync(400);

            expect(view.emitted('peek')).toBeUndefined();
        });

        it('puts the sheet back when it goes away while the original is shown', async () => {
            const onPeek = vi.fn();
            const view = stage({ editing: false, onPeek });

            await firePointer(view.box, 'pointerdown', { x: 200, y: 100 });
            await vi.advanceTimersByTimeAsync(300);
            view.unmount();

            expect(onPeek.mock.calls).toEqual([[true], [false]]);
        });

        it('has no handles while the sheet is shown', () => {
            stage({ editing: false });

            expect(
                screen.queryByRole('button', { name: 'Top left corner' }),
            ).not.toBeInTheDocument();
        });
    });

    it('turns the photo the quarters given', () => {
        const { container } = stage({ turns: 1 });

        expect(container.querySelector('img')?.style.transform).toContain(
            'rotate(90deg)',
        );
    });

    it('keeps the photo inside the height of its box with fit', () => {
        const fitted = stage({ fit: true }).box as HTMLElement;

        expect(fitted.className).toContain('100cqh');
        expect(fitted.style.getPropertyValue('--stage-shape')).toBe(
            String(3 / 4),
        );
        expect((fitted.parentElement as HTMLElement).style.containerType).toBe(
            'size',
        );
    });

    it('only follows the width of its box without fit', () => {
        expect((stage().box as HTMLElement).className).not.toContain('cqh');
    });

    it('lays the default slot on the photo itself', () => {
        const view = render(FmCropStage, {
            props: { image: '/photo.jpg', corners, turns: 0, editing: false },
            slots: { default: '<span>Cropped</span>' },
        });

        expect(
            view.container.querySelector('[data-stage]')?.textContent,
        ).toContain('Cropped');
    });
});
