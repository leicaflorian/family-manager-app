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

/** A point or distance on the page, in pixels. */
type Offset = { x: number; y: number };

/** The attribute that carries the shift from an original element to its clone. */
const SHIFT_ATTRIBUTE = 'data-shot-shift';

/**
 * How far a clone must move so that, after the whole page is translated by the
 * scroll, it lands where the original is on screen. In the clone nothing
 * scrolls, so a sticky or fixed element sits at its natural place and the
 * translation would carry it away from the frame of the app.
 *
 * @param seen Where the original is on screen now (viewport coordinates).
 * @param natural Where the clone appears after the translation: its place with
 * no sticky or fixed position, as seen on screen.
 * @returns The translation to give the clone.
 */
export function shiftOffset(seen: Offset, natural: Offset): Offset {
    return { x: seen.x - natural.x, y: seen.y - natural.y };
}

/**
 * Writes on a sticky or fixed original the shift its clone will need, as an
 * attribute the clone inherits. The natural place is measured by briefly
 * making the element `relative`, which lays it out the same way in the flow and
 * is undone before the browser paints.
 *
 * @param element The original element, before it is cloned.
 */
export function markShift(element: Element): void {
    if (!(element instanceof HTMLElement)) {
        return;
    }

    const position = getComputedStyle(element).position;

    if (position !== 'sticky' && position !== 'fixed') {
        return;
    }

    const scroll = { x: window.scrollX, y: window.scrollY };
    const seenRect = element.getBoundingClientRect();
    const seen = { x: seenRect.left, y: seenRect.top };
    let natural = seen;

    // A fixed element has no natural place: its clone is placed from the page
    // origin and then carried by the scroll, so the scroll is what to add back
    if (position === 'fixed') {
        natural = { x: seen.x - scroll.x, y: seen.y - scroll.y };
    } else {
        const before = element.style.position;
        element.style.position = 'relative';
        const rect = element.getBoundingClientRect();
        element.style.position = before;
        natural = { x: rect.left, y: rect.top };
    }

    const { x, y } = shiftOffset(seen, natural);

    element.setAttribute(SHIFT_ATTRIBUTE, `${x},${y}`);
}

/**
 * Applies the shift written by {@link markShift} to a clone and removes the mark.
 *
 * @param cloned The clone the library just made.
 */
export function applyShift(cloned: Node): void {
    if (
        !(cloned instanceof HTMLElement) ||
        !cloned.hasAttribute(SHIFT_ATTRIBUTE)
    ) {
        return;
    }

    const [x, y] = (cloned.getAttribute(SHIFT_ATTRIBUTE) ?? '0,0').split(',');

    cloned.removeAttribute(SHIFT_ATTRIBUTE);
    cloned.style.transform =
        `translate(${x}px, ${y}px) ${cloned.style.transform}`.trim();
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
            // The filter sees each original just before it is cloned: the place
            // to measure sticky and fixed elements
            filter: (node: Node): boolean => {
                if (skipNode(node)) {
                    return false;
                }

                if (node instanceof Element) {
                    markShift(node);
                }

                return true;
            },
            onCloneEachNode: applyShift,
            style: {
                // Show the page as scrolled, not from its top
                transform: `translate(${-window.scrollX}px, ${-window.scrollY}px)`,
            },
        });

        return toFile(canvas);
    })()
        .catch((): null => null)
        .finally(() => {
            // Marks left on the page by a capture that failed half way
            document
                .querySelectorAll(`[${SHIFT_ATTRIBUTE}]`)
                .forEach((element) => element.removeAttribute(SHIFT_ATTRIBUTE));
        });

    try {
        return await Promise.race([capture, timeout]);
    } finally {
        clearTimeout(timer);
    }
}
