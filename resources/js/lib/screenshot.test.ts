import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { captureScreenshot, skipNode } from './screenshot';

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
