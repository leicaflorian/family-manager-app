import { router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { pin, unpin } from '@/routes/documents';

/** What `useDocumentPin` gives to the components. */
export type UseDocumentPin = {
    /** Pins the document at the top of its person, or takes it off: one visit that keeps the scroll. */
    togglePin: (document: { id: number; pinned: boolean }) => void;
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
        togglePin: (document) => {
            if (document.pinned) {
                router.delete(unpin.url(document.id), {
                    preserveScroll: true,
                });
            } else {
                router.put(pin.url(document.id), {}, { preserveScroll: true });
            }
        },
        pinLabel: (pinned) =>
            pinned ? trans('Unpin') : trans('Pin at the top'),
    };
}
