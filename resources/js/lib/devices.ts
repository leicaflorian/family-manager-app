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

/**
 * Whether the browser is Firefox, which replaces the page with an error when a link to a program is unknown.
 *
 * @returns true on Firefox
 */
export function isFirefox(): boolean {
    return /Firefox/i.test(navigator.userAgent);
}

/**
 * The system of a computer the scanner agent is built for. A phone, a tablet, an iPad that presents itself as a
 * Mac, and Linux are `other`.
 *
 * @returns `mac`, `windows` or `other`
 */
export function desktopSystem(): 'mac' | 'windows' | 'other' {
    const agent = navigator.userAgent;

    if (/Android|iPhone|iPad|iPod/i.test(agent)) {
        return 'other';
    }

    // an iPad says it is a Mac: a Mac has no touch screen
    if (/Macintosh/i.test(agent)) {
        return navigator.maxTouchPoints > 1 ? 'other' : 'mac';
    }

    return /Windows/i.test(agent) ? 'windows' : 'other';
}
