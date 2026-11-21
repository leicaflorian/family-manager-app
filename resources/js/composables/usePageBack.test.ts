import { router } from '@inertiajs/vue3';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import * as visitHistory from '@/lib/visitHistory';
import type { TrailPiece } from '@/lib/pageTrail';
import { usePageBack, type PageBack } from './usePageBack';

/**
 * Runs the composable with fixed inputs.
 *
 * @param trail - the pieces of the trail
 * @param section - whether the page is a section
 * @returns what the composable gives
 */
function make(trail: TrailPiece[], section = false): PageBack {
    return usePageBack(
        () => trail,
        () => section,
    );
}

const inner = [
    { label: 'Areas', href: '/areas' },
    { label: 'Casa', href: '/areas/1' },
    { label: 'Luce' },
];

describe('usePageBack', () => {
    beforeEach(() => {
        vi.spyOn(visitHistory, 'canGoBack').mockReturnValue(false);
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('takes the nearest piece before the last that has a link as the parent', () => {
        expect(make(inner).parent.value?.label).toBe('Casa');
        expect(
            make([
                { label: 'Family' },
                { label: 'People', href: '/people' },
                { label: 'Marco' },
            ]).parent.value?.label,
        ).toBe('People');
        expect(
            make([{ label: 'Family' }, { label: 'Marco' }]).parent.value,
        ).toBeUndefined();
    });

    it('is not visible on a section, nor without history and parent', () => {
        expect(make(inner, true).visible()).toBe(false);
        expect(make([{ label: 'Search' }]).visible()).toBe(false);
    });

    it('is visible with a parent and no history, or with history alone', () => {
        expect(make(inner).visible()).toBe(true);
        vi.mocked(visitHistory.canGoBack).mockReturnValue(true);
        expect(make([{ label: 'Search' }]).visible()).toBe(true);
    });

    it('visits the parent without history', () => {
        const visit = vi
            .spyOn(router, 'visit')
            .mockImplementation(() => undefined);
        const back = vi
            .spyOn(window.history, 'back')
            .mockImplementation(() => undefined);

        make(inner).back(new MouseEvent('click', { cancelable: true }));

        expect(visit).toHaveBeenCalledWith('/areas/1');
        expect(back).not.toHaveBeenCalled();
    });

    it('goes back in the history when it can', () => {
        vi.mocked(visitHistory.canGoBack).mockReturnValue(true);
        const visit = vi
            .spyOn(router, 'visit')
            .mockImplementation(() => undefined);
        const back = vi
            .spyOn(window.history, 'back')
            .mockImplementation(() => undefined);

        make(inner).back(new MouseEvent('click', { cancelable: true }));

        expect(back).toHaveBeenCalledOnce();
        expect(visit).not.toHaveBeenCalled();
    });

    it('leaves a click with a modifier key and a parent to the browser', () => {
        vi.mocked(visitHistory.canGoBack).mockReturnValue(true);
        const visit = vi
            .spyOn(router, 'visit')
            .mockImplementation(() => undefined);
        const back = vi
            .spyOn(window.history, 'back')
            .mockImplementation(() => undefined);
        const event = new MouseEvent('click', {
            metaKey: true,
            cancelable: true,
        });

        make(inner).back(event);

        expect(event.defaultPrevented).toBe(false);
        expect(back).not.toHaveBeenCalled();
        expect(visit).not.toHaveBeenCalled();
    });
});
