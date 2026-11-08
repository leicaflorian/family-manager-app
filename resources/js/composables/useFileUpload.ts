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

/** How `useFileUpload` sends the files. */
export type FileUploadOptions = {
    /** The files stay in *To sort* (the page they are dropped on) instead of opening *Import*. */
    stay?: boolean;
};

/**
 * Sends the files of documents to *Upload*, where they wait for their
 * document; the server then opens *Import more files* with them, or with
 * `stay` goes back to *To sort*.
 *
 * @param link - the card the files come from, linked from the start of their form
 * @param options - whether the files stay in *To sort*
 * @returns `send` for the files of `FmFileDrop`, whether they are being sent and the messages of those refused
 */
export function useFileUpload(
    link: () => DocumentLink | undefined,
    { stay = false }: FileUploadOptions = {},
): UseFileUpload {
    const form = useForm<{ files: File[] }>({ files: [] });

    /**
     * Sends the chosen or dropped files.
     *
     * @param files - the files given by the zone
     */
    function send(files: File[]): void {
        form.files = files;
        form.submit(
            stay ? store({ query: { stay: 1 } }) : store(linkQuery(link())),
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => form.reset(),
            },
        );
    }

    return {
        send,
        processing: computed(() => form.processing),
        // The messages of the files refused (`files.0`, …), all in one notice.
        errors: computed(() => listErrors(form.errors, 'files')),
    };
}
