import userEvent from '@testing-library/user-event';
import { render, screen } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { defineComponent, h, nextTick } from 'vue';
import { toast } from 'vue-sonner';
import FmToaster from '@/components/fm/FmToaster.vue';
import { useNewVersion } from './useNewVersion';

/** Ten minutes, the time between two checks. */
const EVERY = 600_000;

let visibility: DocumentVisibilityState = 'visible';

/** Shows or hides the page, as the phone does when the app goes to the background and back. */
function setVisibility(state: DocumentVisibilityState): void {
    visibility = state;
    document.dispatchEvent(new Event('visibilitychange'));
}

/** A page of the app that runs the check, with the toaster of the layout. */
function renderPage(
    options: Parameters<typeof useNewVersion>[0],
): ReturnType<typeof render> {
    const Page = defineComponent({
        setup() {
            useNewVersion(options);

            return () => h(FmToaster);
        },
    });

    return render(Page);
}

/** Lets the timers, the answers and the toaster move on. */
async function advance(ms: number): Promise<void> {
    await vi.advanceTimersByTimeAsync(ms);
    await nextTick();
}

describe('useNewVersion', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        visibility = 'visible';
        Object.defineProperty(document, 'visibilityState', {
            configurable: true,
            get: () => visibility,
        });
    });

    afterEach(() => {
        toast.dismiss();
        vi.useRealTimers();
    });

    it('shows the notice when the server is newer, and reloads from it', async () => {
        const reload = vi.fn();
        vi.stubGlobal('location', { reload });
        const check = vi.fn(async () => '2.3.0');
        renderPage({ current: '2.2.2', check, every: EVERY });

        await advance(EVERY);

        expect(screen.getByText("There's a new version")).toBeInTheDocument();
        vi.useRealTimers();
        await userEvent.click(screen.getByRole('button', { name: 'Reload' }));
        expect(reload).toHaveBeenCalledOnce();
        vi.unstubAllGlobals();
    });

    it('stays quiet while the server has the same version', async () => {
        const check = vi.fn(async () => '2.2.2');
        renderPage({ current: '2.2.2', check, every: EVERY });

        await advance(EVERY * 2);

        expect(check).toHaveBeenCalledTimes(2);
        expect(
            screen.queryByText("There's a new version"),
        ).not.toBeInTheDocument();
    });

    it('checks when the page comes back, and not while it is hidden', async () => {
        const check = vi.fn(async () => '2.2.2');
        renderPage({ current: '2.2.2', check, every: EVERY });

        setVisibility('hidden');
        await advance(EVERY * 2);
        expect(check).not.toHaveBeenCalled();

        setVisibility('visible');
        await advance(0);
        expect(check).toHaveBeenCalledOnce();
    });

    it('shows the notice once, and stops asking after it', async () => {
        const answers = ['2.3.0', '2.2.2', '2.3.0'];
        const check = vi.fn(async () => answers.shift() ?? '2.3.0');
        renderPage({ current: '2.2.2', check, every: EVERY });

        await advance(EVERY);
        setVisibility('visible');
        await advance(EVERY * 3);

        expect(screen.getAllByText("There's a new version")).toHaveLength(1);
        expect(check).toHaveBeenCalledOnce();
    });

    it('ignores a failed check', async () => {
        const check = vi
            .fn<() => Promise<string | null>>()
            .mockRejectedValueOnce(new TypeError('Failed to fetch'))
            .mockResolvedValue(null);
        renderPage({ current: '2.2.2', check, every: EVERY });

        await advance(EVERY * 2);

        expect(check).toHaveBeenCalledTimes(2);
        expect(
            screen.queryByText("There's a new version"),
        ).not.toBeInTheDocument();
    });

    it('stays quiet in development', async () => {
        const check = vi.fn(async () => '2.3.0');
        renderPage({ current: 'dev', check, every: EVERY });

        setVisibility('visible');
        await advance(EVERY * 2);

        expect(check).not.toHaveBeenCalled();
    });

    it('stops when the page is gone', async () => {
        const check = vi.fn(async () => '2.2.2');
        const page = renderPage({
            current: '2.2.2',
            check,
            every: EVERY,
        });

        page.unmount();
        setVisibility('visible');
        await advance(EVERY * 2);

        expect(check).not.toHaveBeenCalled();
    });
});
