/**
 * Keeps the page of the catalogue where it is: the examples are the real
 * components, and a *Delete* or a form in them would send a real visit.
 */
import { router } from '@inertiajs/vue3';

/** The pages of the catalogue: the only visits that may leave. */
const catalogue = '/dev/components';

/**
 * Stops every Inertia visit outside the catalogue, of the router, of a reload
 * and of `useForm`, and writes it in the log.
 *
 * @param log - Receives each stopped visit, as `DELETE /documents/3`.
 * @returns The function that stops listening.
 */
export function blockVisits(log: (entry: string) => void): () => void {
    return router.on('before', (event) => {
        const { method, url } = event.detail.visit;

        if (url.pathname.startsWith(catalogue)) {
            return;
        }

        // `before` is a cancelable DOM event: preventing it ends the visit before any request
        event.preventDefault();
        log(`${method.toUpperCase()} ${url.pathname}${url.search}`);
    });
}
