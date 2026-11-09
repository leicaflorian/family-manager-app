/**
 * *Raddrizza* for the pages of the scan: the flat sheet of each page is kept in memory, so switching *Straighten* off
 * and on again, or cutting the page again with other improvements, does not make the model work twice. It is kept
 * until the corners or the turns of the page change (a different sheet), or until the page is forgotten.
 */
import type { Corners, Pixels, Turns } from './crop';
import { dewarp } from './dewarp';

/** What lays a sheet flat: `dewarp()`, or a fake one in a test. */
export type Dewarp = (sheet: Pixels, signal?: AbortSignal) => Promise<Pixels>;

/**
 * Makes the keeper of the flat sheets.
 *
 * @param run - lays a sheet flat
 * @returns `flatten()` to get the flat sheet of a page and `forget()` to let go of it
 */
export function createStraightener(run: Dewarp): {
    flatten: (
        id: string,
        corners: Corners,
        turns: Turns,
        sheet: Pixels,
        signal?: AbortSignal,
    ) => Promise<Pixels>;
    forget: (id: string) => void;
} {
    const kept = new Map<string, { key: string; flat: Pixels }>();

    return {
        /**
         * The flat sheet of a page: the one in memory when the corners and the turns are the same, else the model
         * works and the result replaces the old one. A failure or a stop keeps nothing.
         *
         * @param id - the page
         * @param corners - the corners the sheet was cut with
         * @param turns - the quarters of a turn it was cut with
         * @param sheet - the sheet cut out, before the improvements
         * @param signal - stops the wait
         * @returns the flat sheet
         * @throws if the work fails or the signal is aborted
         */
        async flatten(id, corners, turns, sheet, signal) {
            const key = JSON.stringify([corners, turns]);
            const have = kept.get(id);

            if (have?.key === key) {
                return have.flat;
            }

            kept.delete(id);
            const flat = await run(sheet, signal);
            kept.set(id, { key, flat });

            return flat;
        },
        /**
         * Lets go of the flat sheet of a page.
         *
         * @param id - the page
         */
        forget(id) {
            kept.delete(id);
        },
    };
}

/** The keeper the pages use, with the model in the worker. */
export const straightener = createStraightener(dewarp);
