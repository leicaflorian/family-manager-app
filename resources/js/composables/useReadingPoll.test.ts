import { router } from '@inertiajs/vue3';
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
import { useReadingPoll } from './useReadingPoll';

beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
});

/** Mounts a component that polls while `running` is true. */
function mount(running: ReturnType<typeof ref<boolean>>) {
    return render(
        defineComponent({
            setup() {
                useReadingPoll(() => running.value === true, ['files']);

                return () => null;
            },
        }),
    );
}

describe('useReadingPoll', () => {
    it('asks for the named props every 2 seconds while a reading runs, and stops after', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        const running = ref(true);
        mount(running);

        await vi.advanceTimersByTimeAsync(2000);
        expect(visit).toHaveBeenCalledOnce();
        expect(visit).toHaveBeenCalledWith(
            expect.anything(),
            expect.objectContaining({ only: ['files'], preserveUrl: true }),
        );

        running.value = false;
        await vi.advanceTimersByTimeAsync(6000);
        expect(visit).toHaveBeenCalledOnce();
    });

    it('does not ask when nothing runs', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        mount(ref(false));

        await vi.advanceTimersByTimeAsync(6000);

        expect(visit).not.toHaveBeenCalled();
    });

    it('starts when a reading starts later', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        const running = ref(false);
        mount(running);

        running.value = true;
        await vi.advanceTimersByTimeAsync(2000);

        expect(visit).toHaveBeenCalledOnce();
    });

    it('stops when the page goes away', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        const view = mount(ref(true));

        view.unmount();
        await vi.advanceTimersByTimeAsync(6000);

        expect(visit).not.toHaveBeenCalled();
    });
});
