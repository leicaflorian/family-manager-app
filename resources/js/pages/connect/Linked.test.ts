import { router } from '@inertiajs/vue3';
import { screen } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { renderPage } from '@/test/page';
import Linked from './Linked.vue';

const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)';
const ANDROID = 'Mozilla/5.0 (Linux; Android 15; Pixel 9)';

let post: ReturnType<typeof vi.spyOn>;
let visit: ReturnType<typeof vi.spyOn>;

/** Pretends to be a browser, in Safari or opened from the icon. */
function browser(agent: string, installed = false): void {
    vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(agent);
    Object.defineProperty(navigator, 'standalone', {
        value: installed,
        configurable: true,
    });
}

beforeEach(() => {
    post = vi.spyOn(router, 'post').mockImplementation(() => undefined);
    visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
});

afterEach(() => {
    vi.restoreAllMocks();
    history.replaceState(null, '', '/');
    Reflect.deleteProperty(navigator, 'standalone');
});

describe('Phone linked', () => {
    const props = { name: 'Marco', family: 'Bianchi', secret: null };

    it('says who the phone is for, shows the steps and leads to the scan', () => {
        browser(ANDROID);
        renderPage(Linked, props);

        expect(
            screen.getByRole('heading', { name: 'Phone linked' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'Hi, Marco: from this phone you upload the documents to the Bianchi family without a password.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Put it on the Home screen'),
        ).toBeInTheDocument();
        expect(screen.getAllByRole('listitem')).toHaveLength(3);
        expect(
            screen.getByRole('link', { name: 'Take a photo' }),
        ).toHaveAttribute('href', '/scan');
    });

    it('keeps the secret after the # on an iPhone in Safari, and does not write it elsewhere', () => {
        browser(IPHONE);
        renderPage(Linked, { ...props, secret: 'abc123' });
        expect(location.hash).toBe('#d=abc123');

        history.replaceState(null, '', '/');
        browser(ANDROID);
        renderPage(Linked, { ...props, secret: 'abc123' });
        expect(location.hash).toBe('');
    });

    it('opened from the icon with the session there goes straight to the scan', () => {
        browser(IPHONE, true);
        renderPage(Linked, props);

        expect(visit).toHaveBeenCalledWith('/scan');
        expect(post).not.toHaveBeenCalled();
    });

    it('opened from the icon without a session sends the secret and takes it out of the address', () => {
        browser(IPHONE, true);
        history.replaceState(null, '', '/connect/linked#d=abc123');

        renderPage(Linked, { name: null, family: null, secret: null });

        expect(post).toHaveBeenCalledWith('/connect/resume', {
            secret: 'abc123',
        });
        expect(location.hash).toBe('');
    });

    it('opened in Safari on an iPhone leaves the secret in the address and does not enter', () => {
        browser(IPHONE);
        history.replaceState(null, '', '/connect/linked#d=abc123');

        renderPage(Linked, props);

        expect(location.hash).toBe('#d=abc123');
        expect(post).not.toHaveBeenCalled();
        expect(visit).not.toHaveBeenCalled();
    });

    it('with nobody signed in and no secret goes to the page to write the code', () => {
        browser(ANDROID);

        renderPage(Linked, { name: null, family: null, secret: null });

        expect(visit).toHaveBeenCalledWith('/connect');
    });
});
