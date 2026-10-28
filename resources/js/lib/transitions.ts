/**
 * The light transitions of the app: the fade from one page to the next.
 */
import type { VisitOptions } from '@inertiajs/core';

/**
 * What Inertia adds to every visit: a page that opens fades in over the old
 * one, with the browser's view transitions (`app.css` sets how long). A visit
 * that keeps the page (a filter, a partial reload, a form sent) does not fade,
 * nor a page loaded ahead. A `<Link>` always passes `viewTransition: false`,
 * so that value cannot mean «no».
 *
 * @param options - the options of the visit, as the link or the router gives them
 * @returns the options to add: `viewTransition` for a new page, nothing otherwise
 */
export function pageVisitOptions(options: VisitOptions): VisitOptions {
    const method = options.method ?? 'get';
    const keepsPage =
        Boolean(options.preserveState) || (options.only?.length ?? 0) > 0;

    if (method !== 'get' || keepsPage || options.prefetch) {
        return {};
    }

    return { viewTransition: true };
}
