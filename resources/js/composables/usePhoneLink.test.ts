import { render } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { defineComponent, ref } from 'vue';
import { PHONE_POLL_INTERVAL, usePhoneLink } from './usePhoneLink';

const link: App.Data.PhoneLinkData = {
    code: 'K7QM-XPA9',
    qr: '<svg/>',
    address: 'familymanager.it/connect',
    expires_at: '2999-01-01T00:00:00+00:00',
    person: 'Marco',
    family: 'Bianchi',
    devices: [],
};

beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

/** Mounts a component that uses the composable and gives back what it returns. */
function mount(open: ReturnType<typeof ref<boolean>>) {
    let result!: ReturnType<typeof usePhoneLink>;

    const view = render(
        defineComponent({
            setup() {
                result = usePhoneLink(() => open.value === true);

                return () => null;
            },
        }),
    );

    return { ...view, result };
}

describe('usePhoneLink', () => {
    it('asks for a code only when it opens, and again every time', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(link),
        });
        vi.stubGlobal('fetch', fetchMock);
        const open = ref(false);
        const { result } = mount(open);

        expect(fetchMock).not.toHaveBeenCalled();

        open.value = true;
        await vi.waitFor(() => expect(result.link.value).toEqual(link));

        open.value = false;
        await vi.advanceTimersByTimeAsync(PHONE_POLL_INTERVAL * 2);
        open.value = true;
        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));

        expect(fetchMock.mock.calls.map((call) => call[0])).toEqual([
            '/settings/devices',
            '/settings/devices',
        ]);
    });

    it('marks a failed answer', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: false,
                json: () => Promise.resolve({}),
            }),
        );
        const { result } = mount(ref(true));

        await vi.waitFor(() => expect(result.failed.value).toBe(true));
        expect(result.link.value).toBeNull();
    });

    it('does not start a second question while the first is on its way', async () => {
        let finish!: (value: unknown) => void;
        const fetchMock = vi.fn().mockReturnValue(
            new Promise((resolve) => {
                finish = resolve;
            }),
        );
        vi.stubGlobal('fetch', fetchMock);
        mount(ref(true));

        await vi.advanceTimersByTimeAsync(PHONE_POLL_INTERVAL * 3);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        finish({ ok: true, json: () => Promise.resolve(link) });
    });
});
