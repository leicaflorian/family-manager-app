import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import {
    addManifestLink,
    installPrompt,
    registerServiceWorker,
    startInstallWatch,
} from './install';

const IPHONE =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';
const ANDROID =
    'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 Chrome/130 Mobile Safari/537.36';

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    document.head.innerHTML = '';
    installPrompt.value = null;
});

describe('addManifestLink', () => {
    beforeEach(() => {
        document.head.innerHTML = '';
    });

    it('adds the manifest outside iPhone and iPad', () => {
        vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(ANDROID);

        addManifestLink();

        const link = document.head.querySelector('link[rel="manifest"]');
        expect(link?.getAttribute('href')).toBe('/manifest.webmanifest');
    });

    it('adds nothing on an iPhone: Safari gets the headers of Apple only', () => {
        vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(IPHONE);

        addManifestLink();

        expect(document.head.querySelector('link[rel="manifest"]')).toBeNull();
    });

    it('adds it once', () => {
        vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(ANDROID);

        addManifestLink();
        addManifestLink();

        expect(
            document.head.querySelectorAll('link[rel="manifest"]'),
        ).toHaveLength(1);
    });
});

describe('registerServiceWorker', () => {
    it('registers /sw.js with the scope of the whole site', async () => {
        const register = vi.fn().mockResolvedValue({});
        vi.stubGlobal('isSecureContext', true);
        Object.defineProperty(navigator, 'serviceWorker', {
            value: { register },
            configurable: true,
        });

        await registerServiceWorker();

        expect(register).toHaveBeenCalledWith('/sw.js', { scope: '/' });
        Reflect.deleteProperty(navigator, 'serviceWorker');
    });

    it('does nothing where the browser has no service worker or the address is not secure', async () => {
        const register = vi.fn().mockResolvedValue({});
        Object.defineProperty(navigator, 'serviceWorker', {
            value: { register },
            configurable: true,
        });
        vi.stubGlobal('isSecureContext', false);

        await registerServiceWorker();

        expect(register).not.toHaveBeenCalled();
        Reflect.deleteProperty(navigator, 'serviceWorker');
        vi.stubGlobal('isSecureContext', true);
        await expect(registerServiceWorker()).resolves.toBeUndefined();
    });

    it('does not fail the page when the registration fails', async () => {
        vi.stubGlobal('isSecureContext', true);
        Object.defineProperty(navigator, 'serviceWorker', {
            value: { register: vi.fn().mockRejectedValue(new Error('no')) },
            configurable: true,
        });

        await expect(registerServiceWorker()).resolves.toBeUndefined();
        Reflect.deleteProperty(navigator, 'serviceWorker');
    });
});

describe('startInstallWatch', () => {
    it('keeps the invitation of the phone for the sheet, without cancelling it', () => {
        startInstallWatch();
        const event = Object.assign(
            new Event('beforeinstallprompt', { cancelable: true }),
            {
                prompt: vi.fn(),
                userChoice: Promise.resolve({ outcome: 'dismissed' }),
            },
        );

        window.dispatchEvent(event);

        expect(installPrompt.value).toBe(event);
        expect(event.defaultPrevented).toBe(false);
    });

    it('forgets the invitation once the app is installed', () => {
        startInstallWatch();
        window.dispatchEvent(
            Object.assign(new Event('beforeinstallprompt'), {
                prompt: vi.fn(),
                userChoice: Promise.resolve({ outcome: 'accepted' }),
            }),
        );

        window.dispatchEvent(new Event('appinstalled'));

        expect(installPrompt.value).toBeNull();
    });
});
