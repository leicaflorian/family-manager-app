import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { isIos, isPhone, isStandalone } from './devices';

const IPHONE =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';
const IPAD =
    'Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) AppleWebKit/605.1.15';
const ANDROID =
    'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 Chrome/130 Mobile Safari/537.36';
const ANDROID_TABLET =
    'Mozilla/5.0 (Linux; Android 15; Tab) AppleWebKit/537.36 Chrome/130 Safari/537.36';
const MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15';

/** Pretends to be a browser. */
function browser(agent: string, standalone?: boolean, touchPoints = 0): void {
    vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(agent);
    Object.defineProperty(navigator, 'maxTouchPoints', {
        value: touchPoints,
        configurable: true,
    });
    if (standalone !== undefined) {
        Object.defineProperty(navigator, 'standalone', {
            value: standalone,
            configurable: true,
        });
    }
}

afterEach(() => {
    vi.restoreAllMocks();
    Reflect.deleteProperty(navigator, 'standalone');
    Reflect.deleteProperty(navigator, 'maxTouchPoints');
});

describe('isPhone', () => {
    it('is true on an iPhone and on an Android phone', () => {
        browser(IPHONE);
        expect(isPhone()).toBe(true);
        browser(ANDROID);
        expect(isPhone()).toBe(true);
    });

    it('is false on a tablet and on a computer', () => {
        browser(IPAD);
        expect(isPhone()).toBe(false);
        browser(ANDROID_TABLET);
        expect(isPhone()).toBe(false);
        browser(MAC);
        expect(isPhone()).toBe(false);
    });
});

describe('isIos', () => {
    it('is true on an iPhone and an iPad, also the one that says it is a Mac', () => {
        browser(IPHONE);
        expect(isIos()).toBe(true);
        browser(IPAD);
        expect(isIos()).toBe(true);
        browser(MAC, undefined, 5);
        expect(isIos()).toBe(true);
    });

    it('is false on a Mac without a touch screen and on Android', () => {
        browser(MAC);
        expect(isIos()).toBe(false);
        browser(ANDROID);
        expect(isIos()).toBe(false);
    });
});

describe('isStandalone', () => {
    it('is true when Safari says the page runs from the Home screen', () => {
        browser(IPHONE, true);
        expect(isStandalone()).toBe(true);
    });

    it('is true when the display mode is standalone', () => {
        browser(ANDROID);
        window.matchMedia = vi
            .fn()
            .mockReturnValue({ matches: true }) as typeof window.matchMedia;
        expect(isStandalone()).toBe(true);
    });

    it('is false in a normal tab', () => {
        browser(IPHONE, false);
        window.matchMedia = vi
            .fn()
            .mockReturnValue({ matches: false }) as typeof window.matchMedia;
        expect(isStandalone()).toBe(false);
    });
});
