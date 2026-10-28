import { useForm } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import type { DocumentLink } from '@/lib/documents';
import { linkQuery } from '@/lib/documents';
import { listErrors } from '@/lib/errors';
import { store } from '@/routes/upload';

/** What `useFileUpload` gives to the pages. */
export type UseFileUpload = {
    /** Sends the files chosen or dropped in `FmFileDrop`. */
    send: (files: File[]) => void;
    /** The files are being sent. */
    processing: ComputedRef<boolean>;
    /** The messages of the files refused, in their order. */
    errors: ComputedRef<string[]>;
};

/**
 * Sends the files of documents to *Upload*, where they wait for their
 * document; the server then opens *Upload* with the form of the first one.
 *
 * @param link - the card the files come from, linked from the start of their form
 * @returns `send` for the files of `FmFileDrop`, whether they are being sent and the messages of those refused
 */
export function useFileUpload(
    link: () => DocumentLink | undefined,
): UseFileUpload {
    const form = useForm<{ files: File[] }>({ files: [] });

    /**
     * Sends the chosen or dropped files.
     *
     * @param files - the files given by the zone
     */
    function send(files: File[]): void {
        form.files = files;
        form.submit(store(linkQuery(link())), {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => form.reset(),
        });
    }

    return {
        send,
        processing: computed(() => form.processing),
        // The messages of the files refused (`files.0`, …), all in one notice.
        errors: computed(() => listErrors(form.errors, 'files')),
    };
}
