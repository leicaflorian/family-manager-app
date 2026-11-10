import { inject, onBeforeUnmount, toRaw, watchEffect } from 'vue';
import type { TrailPiece } from '@/lib/pageTrail';
import { TRAIL_KEY } from '@/lib/pageTrail';

/**
 * Writes the trail of the page into the top bar: right away and at every change of the pieces, and
 * removes it when the page unmounts, unless a newer page has already written its own. Without a layout
 * (a page mounted alone in a test) it does nothing.
 *
 * @param pieces - the pieces of the trail; the last one has no `href`
 */
export function usePageTrail(pieces: () => TrailPiece[]): void {
    const state = inject(TRAIL_KEY, null);

    if (state === null) {
        return;
    }

    let written: TrailPiece[] | null = null;

    watchEffect(() => {
        written = pieces();
        state.set(written);
    });

    onBeforeUnmount(() => {
        // The ref stores a reactive proxy of the array, so compare the raw arrays.
        if (toRaw(state.pieces.value) === written) {
            state.clear();
        }
    });
}
