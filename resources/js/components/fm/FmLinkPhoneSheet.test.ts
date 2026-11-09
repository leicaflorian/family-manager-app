import { router } from '@inertiajs/vue3';
import { fireEvent, render, screen, within } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { PHONE_POLL_INTERVAL } from '@/composables/usePhoneLink';
import { formPlugins } from '@/lib/formPlugins';
import FmLinkPhoneSheet from './FmLinkPhoneSheet.vue';

const qr =
    '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><path d="M0 0h10v10H0z"/></svg>';

const iphone: App.Data.LinkedDeviceData = {
    id: 4,
    name: 'iPhone of Marco',
    linked_on: '2026-10-12',
    last_upload_on: '2026-10-24',
    linked_now: false,
};

/** What the server answers when the panel opens. */
function link(
    devices: App.Data.LinkedDeviceData[] = [iphone],
): App.Data.PhoneLinkData {
    return {
        code: 'K7QM-XPA9',
        qr,
        address: 'familymanager.it/connect',
        expires_at: new Date(Date.now() + 10 * 60_000).toISOString(),
        person: 'Marco',
        family: 'Bianchi',
        devices,
    };
}

/** Answers `fetch` in order, one answer for each call. */
function answers(...bodies: unknown[]): ReturnType<typeof vi.fn> {
    const fetchMock = vi.fn();

    bodies.forEach((body) =>
        fetchMock.mockResolvedValueOnce({
            ok: true,
            json: () => Promise.resolve(body),
        }),
    );
    vi.stubGlobal('fetch', fetchMock);

    return fetchMock;
}

beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

/** Mounts the panel open. */
function mount(open = true) {
    return render(FmLinkPhoneSheet, {
        props: { open },
        global: { plugins: formPlugins },
    });
}

describe('FmLinkPhoneSheet', () => {
    it('shows the QR, the steps, the code to write and the phones linked', async () => {
        answers(link());
        mount();

        const dialog = await screen.findByRole('dialog', {
            name: 'Link the phone',
        });

        expect(await within(dialog).findByText('K7QM-XPA9')).toBeVisible();
        expect(within(dialog).getByRole('img')).toBeInTheDocument();
        expect(
            within(dialog).getByText(/Open the phone camera/),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByText(/as Marco, in the Bianchi family/),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByText('familymanager.it/connect'),
        ).toBeInTheDocument();
        expect(within(dialog).getByText('iPhone of Marco')).toBeInTheDocument();
        expect(
            within(dialog).getByText(
                'Linked on 12/10/2026 · last upload on 24/10/2026',
            ),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByRole('button', { name: 'Done' }),
        ).toBeInTheDocument();
    });

    it('shows only the phones when no code is made, during a visit', async () => {
        const fetchMock = answers(
            { ...link(), code: null, qr: null, expires_at: null },
            { devices: [iphone] },
        );
        mount();

        const dialog = await screen.findByRole('dialog', {
            name: 'Link the phone',
        });

        expect(
            await within(dialog).findByText(
                'During a visit no code is made to link a phone.',
            ),
        ).toBeInTheDocument();
        expect(within(dialog).getByText('iPhone of Marco')).toBeInTheDocument();
        expect(within(dialog).queryByRole('img')).not.toBeInTheDocument();
        expect(
            within(dialog).queryByText(/Open the phone camera/),
        ).not.toBeInTheDocument();
        expect(
            within(dialog).queryByText(/The code is valid/),
        ).not.toBeInTheDocument();

        await vi.advanceTimersByTimeAsync(PHONE_POLL_INTERVAL);

        // It keeps asking for the phones only: no new code
        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(fetchMock.mock.calls[1]![0]).toBe(
            '/settings/devices?only=devices',
        );
    });

    it('asks for the phones only every 2 seconds, and shows the one that just linked', async () => {
        const fetchMock = answers(link([]), {
            devices: [{ ...iphone, linked_now: true }],
        });
        mount();

        await screen.findByText('K7QM-XPA9');
        expect(screen.getByText('No phone is linked yet')).toBeInTheDocument();

        await vi.advanceTimersByTimeAsync(PHONE_POLL_INTERVAL);

        expect(await screen.findByText('Linked now')).toBeInTheDocument();
        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(fetchMock.mock.calls[0]![0]).toBe('/settings/devices');
        expect(fetchMock.mock.calls[1]![0]).toBe(
            '/settings/devices?only=devices',
        );
    });

    it('stops asking when it closes', async () => {
        const fetchMock = answers(link());
        const view = mount();

        await screen.findByText('K7QM-XPA9');
        await view.rerender({ open: false });
        await vi.advanceTimersByTimeAsync(PHONE_POLL_INTERVAL * 3);

        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('stops asking when it goes away', async () => {
        const fetchMock = answers(link());
        const view = mount();

        await screen.findByText('K7QM-XPA9');
        view.unmount();
        await vi.advanceTimersByTimeAsync(PHONE_POLL_INTERVAL * 3);

        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('makes a new code when the one shown has expired', async () => {
        const expired = {
            ...link(),
            code: 'AAAA-BBBB',
            expires_at: new Date(Date.now() + 1000).toISOString(),
        };
        const fetchMock = answers(expired, link());
        mount();

        await screen.findByText('AAAA-BBBB');
        await vi.advanceTimersByTimeAsync(PHONE_POLL_INTERVAL);

        expect(await screen.findByText('K7QM-XPA9')).toBeInTheDocument();
        expect(fetchMock.mock.calls[1]![0]).toBe('/settings/devices');
    });

    it('asks again before unlinking a phone, and unlinks it', async () => {
        answers(link());
        const deleted = vi
            .spyOn(router, 'delete')
            .mockImplementation(() => undefined);
        mount();

        await fireEvent.click(
            await screen.findByRole('button', {
                name: 'Unlink iPhone of Marco',
            }),
        );

        expect(deleted).not.toHaveBeenCalled();
        expect(
            await screen.findByText('Unlink this phone?'),
        ).toBeInTheDocument();

        await fireEvent.click(screen.getByRole('button', { name: 'Unlink' }));

        expect(deleted).toHaveBeenCalledWith(
            '/settings/devices/4',
            expect.anything(),
        );
    });

    it('lets the person try again when the code could not be made', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce({
                ok: false,
                json: () => Promise.resolve({}),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(link()),
            });
        vi.stubGlobal('fetch', fetchMock);
        mount();

        await fireEvent.click(
            await screen.findByRole('button', { name: 'Try again' }),
        );

        expect(await screen.findByText('K7QM-XPA9')).toBeInTheDocument();
    });

    it('asks nothing while closed', () => {
        const fetchMock = answers();
        mount(false);

        expect(fetchMock).not.toHaveBeenCalled();
    });
});
