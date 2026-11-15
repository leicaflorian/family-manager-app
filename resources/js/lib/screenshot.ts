/**
 * The photo of the page that goes with a report: what the person sees at the
 * moment the panel opens, without the panels and menus on top of it.
 */

/** The longest side of the picture, in pixels. */
const MAX_SIDE = 1600;

/** How long the picture may take before the report goes without it. */
const DEFAULT_TIMEOUT_MS = 4000;

/**
 * Tells whether a node stays out of the photo: an element marked
 * `data-no-screenshot`, a dialog or a menu, and everything inside them.
 *
 * @param node The node the library is about to draw.
 * @returns `true` when the node must not appear (the library's filter keeps
 * the nodes it says yes to, so {@link captureScreenshot} inverts this).
 */
export function skipNode(node: Node): boolean {
    if (!(node instanceof Element)) {
        return false;
    }

    return (
        node.closest('[data-no-screenshot], [role="dialog"], [role="menu"]') !==
        null
    );
}

/**
 * Turns a canvas into a file, WebP when the browser can write it, JPEG if not.
 *
 * @param canvas The drawn page.
 * @returns The file, or `null` when the browser gave nothing.
 */
async function toFile(canvas: HTMLCanvasElement): Promise<File | null> {
    const blob = (type: string): Promise<Blob | null> =>
        new Promise((resolve) => canvas.toBlob(resolve, type, 0.8));

    const webp = await blob('image/webp');

    if (webp?.type === 'image/webp') {
        return new File([webp], 'page.webp', { type: 'image/webp' });
    }

    const jpeg = await blob('image/jpeg');

    return jpeg === null
        ? null
        : new File([jpeg], 'page.jpg', { type: 'image/jpeg' });
}

/**
 * Takes the photo of what the person sees now (viewport size, current scroll).
 * The library is loaded only here, so it stays out of the main bundle.
 *
 * @param options `timeoutMs`: how long to wait before giving up (4000 by default).
 * @returns The picture, or `null` on any error or when time runs out.
 */
export async function captureScreenshot(options?: {
    timeoutMs?: number;
}): Promise<File | null> {
    const timeoutMs = options?.timeoutMs ?? DEFAULT_TIMEOUT_MS;
    let timer: ReturnType<typeof setTimeout> | undefined;

    const timeout = new Promise<null>((resolve) => {
        timer = setTimeout(() => resolve(null), timeoutMs);
    });

    const capture = (async (): Promise<File | null> => {
        const { domToCanvas } = await import('modern-screenshot');
        const width = window.innerWidth;
        const height = window.innerHeight;
        const canvas = await domToCanvas(document.body, {
            width,
            height,
            scale: Math.min(1, MAX_SIDE / Math.max(width, height)),
            // The library keeps what the filter says yes to
            filter: (node: Node): boolean => !skipNode(node),
            style: {
                // Show the page as scrolled, not from its top
                transform: `translate(${-window.scrollX}px, ${-window.scrollY}px)`,
            },
        });

        return toFile(canvas);
    })().catch((): null => null);

    try {
        return await Promise.race([capture, timeout]);
    } finally {
        clearTimeout(timer);
    }
}
