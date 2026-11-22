import { router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { pin, unpin } from '@/routes/documents';

/** What a page may add to the pin visit: which props to reload, and which lists to start again. */
export type PinVisitOptions = {
    /** The props the answer refreshes. */
    only?: string[];
    /** The merged lists that start again from their first page; always with `only`. */
    reset?: string[];
};

/** What `useDocumentPin` gives to the components. */
export type UseDocumentPin = {
    /** Pins the document at the top of its person, or takes it off: one visit that keeps the scroll, with the options the page adds (what to reload). */
    togglePin: (
        document: { id: number; pinned: boolean },
        options?: PinVisitOptions,
    ) => void;
    /** The label of the pin item, which follows the state. */
    pinLabel: (pinned: boolean) => string;
};

/**
 * The pin visit of a document, shared by the page menu and the ⋯ menu of a row.
 *
 * @returns the visit and the label of the item
 */
export function useDocumentPin(): UseDocumentPin {
    return {
        togglePin: (document, options = {}) => {
            const visit = { preserveScroll: true, ...options };

            if (document.pinned) {
                router.delete(unpin.url(document.id), visit);
            } else {
                router.put(pin.url(document.id), {}, visit);
            }
        },
        pinLabel: (pinned) =>
            pinned
                ? trans('Remove from favourites')
                : trans('Mark as favourite'),
    };
}
