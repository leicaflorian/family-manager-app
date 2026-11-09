import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    cleanAddress,
    isInstalled,
    keepSecretInAddress,
    secretFromHash,
} from './phoneLink';

/** Pretends to be a browser. */
function browser(agent: string, standalone = false): void {
    vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(agent);
    Object.defineProperty(navigator, 'standalone', {
        value: standalone,
        configurable: true,
    });
}

afterEach(() => {
    vi.restoreAllMocks();
    history.replaceState(null, '', '/');
    Reflect.deleteProperty(navigator, 'standalone');
});

describe('secretFromHash', () => {
    it('reads the secret after d= and nothing else', () => {
        expect(secretFromHash('#d=abc%2F123')).toBe('abc/123');
        expect(secretFromHash('d=abc')).toBe('abc');
        expect(secretFromHash('#d=')).toBeNull();
        expect(secretFromHash('#other=1')).toBeNull();
        expect(secretFromHash('')).toBeNull();
    });
});

describe('the secret in the address', () => {
    it('is written after the # and read back', () => {
        history.replaceState(null, '', '/connect/linked?x=1');

        keepSecretInAddress('s e/c');

        expect(location.pathname + location.search).toBe('/connect/linked?x=1');
        expect(secretFromHash(location.hash)).toBe('s e/c');
    });

    it('is cleaned in a browser that shares the cookies', () => {
        browser('Mozilla/5.0 (Linux; Android 15)');
        keepSecretInAddress('abc');

        cleanAddress();

        expect(location.hash).toBe('');
    });

    it('stays on an iPhone in Safari, and goes when the page is opened from the icon', () => {
        browser('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)');
        keepSecretInAddress('abc');

        cleanAddress();
        expect(location.hash).toBe('#d=abc');

        browser('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)', true);
        cleanAddress();
        expect(location.hash).toBe('');
    });
});

describe('isInstalled', () => {
    it('is true for the display mode standalone', () => {
        browser('Mozilla/5.0 (Linux; Android 15)');
        vi.stubGlobal(
            'matchMedia',
            vi.fn(() => ({ matches: true })),
        );

        expect(isInstalled()).toBe(true);

        vi.unstubAllGlobals();
    });
});
