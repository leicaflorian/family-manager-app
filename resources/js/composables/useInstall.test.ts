import { render } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { defineComponent, h } from 'vue';
import { installPrompt } from '@/lib/pwa/install';
import type { UseInstall } from './useInstall';
import { INSTALLED_KEY, useInstall } from './useInstall';

const IPHONE =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';
const ANDROID =
    'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 Chrome/130 Mobile Safari/537.36';
const DESKTOP =
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130 Safari/537.36';

/** Pretends to be a browser at an address. */
function browser(
    agent: string,
    { protocol = 'https:', standalone = false } = {},
): void {
    vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(agent);
    Object.defineProperty(navigator, 'maxTouchPoints', {
        value: 0,
        configurable: true,
    });
    Object.defineProperty(navigator, 'standalone', {
        value: standalone,
        configurable: true,
    });
    vi.stubGlobal('location', { protocol });
}

/** Mounts a component that uses the sheet and gives it back. */
function mountInstall(): UseInstall {
    let install: UseInstall | undefined;
    render(
        defineComponent({
            setup() {
                install = useInstall();

                return () => h('div');
            },
        }),
    );

    return install!;
}

/** An invitation of the phone, as `beforeinstallprompt` gives it. */
function invitation(outcome: 'accepted' | 'dismissed' = 'accepted') {
    return Object.assign(new Event('beforeinstallprompt'), {
        prompt: vi.fn().mockResolvedValue(undefined),
        userChoice: Promise.resolve({ outcome, platform: 'web' }),
    });
}

beforeEach(() => {
    localStorage.clear();
    installPrompt.value = null;
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    Reflect.deleteProperty(navigator, 'standalone');
    Reflect.deleteProperty(navigator, 'maxTouchPoints');
});

describe('useInstall', () => {
    it('offers the sheet on an iPhone in the browser, with the steps and no button', () => {
        browser(IPHONE);

        const install = mountInstall();

        expect(install.open.value).toBe(true);
        expect(install.ios).toBe(true);
        expect(install.showSteps.value).toBe(true);
    });

    it('offers the sheet on an Android phone, with the Install button', () => {
        browser(ANDROID);

        const install = mountInstall();

        expect(install.open.value).toBe(true);
        expect(install.ios).toBe(false);
        expect(install.showSteps.value).toBe(false);
    });

    it('does not offer it from the icon on the Home screen', () => {
        browser(ANDROID, { standalone: true });

        expect(mountInstall().open.value).toBe(false);
    });

    it('does not offer it over http, where the phone cannot install', () => {
        browser(ANDROID, { protocol: 'http:' });
        expect(mountInstall().open.value).toBe(false);

        browser(IPHONE, { protocol: 'http:' });
        expect(mountInstall().open.value).toBe(false);
    });

    it('does not offer it on a computer or when the app was already installed', () => {
        browser(DESKTOP);
        expect(mountInstall().open.value).toBe(false);

        browser(ANDROID);
        localStorage.setItem(INSTALLED_KEY, '1');
        expect(mountInstall().open.value).toBe(false);
    });

    it('Install calls the invitation of the phone and closes the sheet once accepted', async () => {
        browser(ANDROID);
        const prompt = invitation('accepted');
        installPrompt.value = prompt as never;
        const install = mountInstall();

        await install.install();

        expect(prompt.prompt).toHaveBeenCalledOnce();
        expect(install.open.value).toBe(false);
        expect(localStorage.getItem(INSTALLED_KEY)).toBe('1');
        expect(installPrompt.value).toBeNull();
    });

    it('stays open, and offered again, when the person turns the invitation down', async () => {
        browser(ANDROID);
        installPrompt.value = invitation('dismissed') as never;
        const install = mountInstall();

        await install.install();

        expect(install.open.value).toBe(true);
        expect(localStorage.getItem(INSTALLED_KEY)).toBeNull();
        expect(install.showSteps.value).toBe(true);
    });

    it('shows the steps by hand when the phone made no invitation', async () => {
        browser(ANDROID);
        const install = mountInstall();

        await install.install();

        expect(install.showSteps.value).toBe(true);
        expect(install.open.value).toBe(true);
    });

    it('shows the steps by hand when the invitation fails', async () => {
        browser(ANDROID);
        installPrompt.value = {
            prompt: vi.fn().mockRejectedValue(new Error('used')),
            userChoice: new Promise(() => {}),
        } as never;
        const install = mountInstall();

        await install.install();

        expect(install.showSteps.value).toBe(true);
    });

    it('Not now closes it until the next opening: nothing is remembered', () => {
        browser(ANDROID);
        const install = mountInstall();

        install.later();

        expect(install.open.value).toBe(false);
        expect(localStorage.length).toBe(0);
        expect(mountInstall().open.value).toBe(true);
    });

    it('closes the sheet when the app gets installed from the menu of the browser', () => {
        browser(ANDROID);
        const install = mountInstall();

        window.dispatchEvent(new Event('appinstalled'));

        expect(install.open.value).toBe(false);
        expect(localStorage.getItem(INSTALLED_KEY)).toBe('1');
    });

    it('works when the browser keeps no storage', () => {
        browser(ANDROID);
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('blocked');
        });

        expect(mountInstall().open.value).toBe(true);
    });
});
