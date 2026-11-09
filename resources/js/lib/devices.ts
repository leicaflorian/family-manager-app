/**
 * What the pages of the scan need to know about the device: whether it is a phone, an iPhone or an iPad, and
 * whether the page was opened from the icon on the Home screen. The one place that reads `navigator` for it.
 */

/**
 * Whether the browser is on a phone (not a tablet, not a computer).
 *
 * @returns true on an iPhone, an iPod or an Android phone
 */
export function isPhone(): boolean {
    return /iPhone|iPod|Android.*Mobile/.test(navigator.userAgent);
}

/**
 * Whether the browser is on an iPhone or an iPad, also an iPad that presents itself as a Mac.
 *
 * @returns true on iOS
 */
export function isIos(): boolean {
    return (
        /iPhone|iPad|iPod/.test(navigator.userAgent) ||
        (/Macintosh/.test(navigator.userAgent) && navigator.maxTouchPoints > 1)
    );
}

/**
 * Whether the page was opened from the icon on the Home screen, not from the browser.
 *
 * @returns true when the page runs as an installed app
 */
export function isStandalone(): boolean {
    return (
        (navigator as Navigator & { standalone?: boolean }).standalone ===
            true ||
        (typeof window.matchMedia === 'function' &&
            window.matchMedia('(display-mode: standalone)').matches)
    );
}
