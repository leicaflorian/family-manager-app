/**
 * Keeps the page of a story where it is: the stories show the real
 * components, and a *Delete* or a form in them would send a real visit.
 */
import { router } from '@inertiajs/vue3';

/**
 * Stops every Inertia visit, of the router, of a reload and of `useForm`, and
 * writes it in the log. Only the visits to an allowed path (or below it) leave.
 *
 * @param log - Receives each stopped visit, as `DELETE /documents/3`.
 * @param allowed - Paths whose visits may leave, as `/allowed/page`: none for a story.
 * @returns The function that stops listening.
 */
export function blockVisits(
    log: (entry: string) => void,
    allowed: readonly string[] = [],
): () => void {
    return router.on('before', (event) => {
        const { method, url } = event.detail.visit;

        if (
            allowed.some(
                (path) =>
                    url.pathname === path ||
                    url.pathname.startsWith(`${path}/`),
            )
        ) {
            return;
        }

        // `before` is a cancelable DOM event: preventing it ends the visit before any request
        event.preventDefault();
        log(`${method.toUpperCase()} ${url.pathname}${url.search}`);
    });
}
