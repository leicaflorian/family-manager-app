import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    applyShift,
    captureScreenshot,
    markShift,
    shiftOffset,
    skipNode,
} from './screenshot';

const library = vi.hoisted(() => ({
    domToCanvas: vi.fn(),
}));

vi.mock('modern-screenshot', () => library);

/** Builds an element from HTML and returns the one with the id `target`. */
function find(html: string): HTMLElement {
    document.body.innerHTML = html;

    return document.getElementById('target') as HTMLElement;
}

describe('skipNode', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('skips a dialog', () => {
        expect(skipNode(find('<div id="target" role="dialog"></div>'))).toBe(
            true,
        );
    });

    it('skips a menu', () => {
        expect(skipNode(find('<div id="target" role="menu"></div>'))).toBe(
            true,
        );
    });

    it('skips an element marked data-no-screenshot', () => {
        expect(
            skipNode(find('<div id="target" data-no-screenshot></div>')),
        ).toBe(true);
    });

    it('skips what is inside a dialog', () => {
        expect(
            skipNode(find('<div role="dialog"><p id="target">Hi</p></div>')),
        ).toBe(true);
    });

    it('keeps a plain main', () => {
        expect(skipNode(find('<main id="target"></main>'))).toBe(false);
    });

    it('keeps text nodes outside any dialog', () => {
        find('<main id="target">Hello</main>');

        expect(skipNode(document.createTextNode('x'))).toBe(false);
    });
});

describe('captureScreenshot', () => {
    afterEach(() => {
        vi.useRealTimers();
        library.domToCanvas.mockReset();
    });

    /** A canvas that answers `toBlob` with the type asked for, or a given one. */
    function canvas(answer?: string): HTMLCanvasElement {
        return {
            width: 800,
            height: 600,
            toBlob: (cb: (blob: Blob | null) => void, type: string) =>
                cb(new Blob(['x'], { type: answer ?? type })),
        } as unknown as HTMLCanvasElement;
    }

    it('resolves a WebP file named page.webp', async () => {
        library.domToCanvas.mockResolvedValue(canvas());

        const file = await captureScreenshot();

        expect(file?.name).toBe('page.webp');
        expect(file?.type).toBe('image/webp');
    });

    it('falls back to JPEG when the browser gives no WebP', async () => {
        library.domToCanvas.mockResolvedValue(canvas('image/png'));
        const jpeg = vi.fn((cb: (blob: Blob | null) => void) =>
            cb(new Blob(['x'], { type: 'image/jpeg' })),
        );
        const first = canvas('image/png');
        const calls: string[] = [];
        (first as { toBlob: unknown }).toBlob = (
            cb: (blob: Blob | null) => void,
            type: string,
        ) => {
            calls.push(type);
            if (type === 'image/jpeg') {
                jpeg(cb);
            } else {
                cb(new Blob(['x'], { type: 'image/png' }));
            }
        };
        library.domToCanvas.mockResolvedValue(first);

        const file = await captureScreenshot();

        expect(calls).toEqual(['image/webp', 'image/jpeg']);
        expect(file?.name).toBe('page.jpg');
    });

    it('renders at the viewport size, the longer side at most 1600 px', async () => {
        library.domToCanvas.mockResolvedValue(canvas());
        window.innerWidth = 3200;
        window.innerHeight = 1000;

        await captureScreenshot();

        const options = library.domToCanvas.mock.calls[0][1];
        expect(options.width).toBe(3200);
        expect(options.height).toBe(1000);
        expect(options.scale).toBe(0.5);
        document.body.innerHTML =
            '<main id="a"></main><div id="b" data-no-screenshot></div>';
        expect(options.filter(document.getElementById('a'))).toBe(true);
        expect(options.filter(document.getElementById('b'))).toBe(false);
    });

    it('draws the page as scrolled even while an open panel locks the scroll of the body', async () => {
        library.domToCanvas.mockResolvedValue(canvas());
        // The scroll lock of a dialog: in the clone the body would clip the
        // translated page and the photo would come out empty
        document.body.style.overflow = 'hidden';
        window.scrollY = 900;

        await captureScreenshot();

        const options = library.domToCanvas.mock.calls[0][1];
        expect(options.style).toEqual({
            margin: '0',
            overflow: 'visible',
            transform: 'translate(0px, -900px)',
        });
        document.body.style.overflow = '';
        window.scrollY = 0;
    });

    it('resolves null when the library throws', async () => {
        library.domToCanvas.mockRejectedValue(new Error('boom'));

        expect(await captureScreenshot()).toBeNull();
    });

    it('resolves null after the time limit', async () => {
        vi.useFakeTimers();
        library.domToCanvas.mockReturnValue(new Promise(() => {}));

        const result = captureScreenshot({ timeoutMs: 4000 });
        await vi.advanceTimersByTimeAsync(4000);

        expect(await result).toBeNull();
    });
});

describe('shiftOffset', () => {
    it('is zero for an element that scrolls with the page', () => {
        expect(shiftOffset({ x: 0, y: 300 }, { x: 0, y: 300 })).toEqual({
            x: 0,
            y: 0,
        });
    });

    it('moves a stuck top bar back to the top of the screen', () => {
        // Seen at 0, but its natural place is 200 above after a scroll of 200
        expect(shiftOffset({ x: 0, y: 0 }, { x: 0, y: -200 })).toEqual({
            x: 0,
            y: 200,
        });
    });

    it('moves a stuck bottom bar up to the bottom of the screen', () => {
        expect(shiftOffset({ x: 0, y: 700 }, { x: 0, y: 1500 })).toEqual({
            x: 0,
            y: -800,
        });
    });
});

describe('markShift and applyShift', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.restoreAllMocks();
    });

    it('marks a sticky element and the clone gets the compensating transform', () => {
        document.body.innerHTML =
            '<header id="h" style="position: sticky"></header>';
        const header = document.getElementById('h') as HTMLElement;
        // Stuck at the top while the page is scrolled by 200; once made
        // relative it is 200 above the screen
        header.getBoundingClientRect = () =>
            ({
                left: 0,
                top: header.style.position === 'relative' ? -200 : 0,
            }) as DOMRect;
        window.scrollX = 0;
        window.scrollY = 200;

        markShift(header);
        const clone = header.cloneNode() as HTMLElement;
        applyShift(clone);

        expect(clone.style.transform).toBe('translate(0px, 200px)');
        expect(clone.hasAttribute('data-shot-shift')).toBe(false);
    });

    it('lays the clone of a sticky element out in the flow, where the shift was measured', () => {
        document.body.innerHTML =
            '<div id="b" style="position: sticky; bottom: 0"></div>';
        const bar = document.getElementById('b') as HTMLElement;
        // Stuck at the bottom of the screen; in the flow it is 600 lower
        bar.getBoundingClientRect = () =>
            ({
                left: 0,
                top: bar.style.position === 'relative' ? 1360 : 760,
            }) as DOMRect;

        markShift(bar);
        const clone = bar.cloneNode() as HTMLElement;
        applyShift(clone);

        expect(clone.style.position).toBe('relative');
        expect(clone.style.transform).toBe('translate(0px, -600px)');
        expect(bar.style.position).toBe('sticky');
    });

    it('keeps the clone of a fixed element fixed', () => {
        document.body.innerHTML = '<div id="f" style="position: fixed"></div>';
        const bar = document.getElementById('f') as HTMLElement;
        bar.getBoundingClientRect = () => ({ left: 0, top: 0 }) as DOMRect;

        markShift(bar);
        const clone = bar.cloneNode() as HTMLElement;
        applyShift(clone);

        expect(clone.style.position).toBe('fixed');
    });

    it('leaves an ordinary element alone', () => {
        document.body.innerHTML = '<main id="m"></main>';
        const main = document.getElementById('m') as HTMLElement;

        markShift(main);

        expect(main.hasAttribute('data-shot-shift')).toBe(false);
    });
});
