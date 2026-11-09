import { trans, transChoice } from 'laravel-vue-i18n';
import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import { formatSize } from '@/lib/format';
import type { Backend, ScanPage } from '@/lib/scan/pages';
import {
    ScanTooHeavyError,
    fileGroups,
    filesToSend,
    oneSheetState,
    pageCount,
    sendSummary,
} from '@/lib/scan/pages';
import { ScanRequestError, enterAgain, sendScanFile } from '@/lib/scan/server';

/** What the upload reads from the page and tells it. */
export type ScanUploadOptions = {
    /** The pages of the document, in order. */
    pages: () => ScanPage[];
    /** *On a single page* is switched on. */
    oneSheet: () => boolean;
    /** *What it is*. */
    note: () => string;
    /** The pages of a file that reached the server: they leave the document. */
    onArrived: (ids: string[]) => void;
    /** Everything arrived: the page is empty for the next document. */
    onFinished: () => void;
    /** What needs a canvas to make the files; the browser's by default. */
    backend?: Backend;
};

/** What `useScanUpload` gives to the scan. */
export type UseScanUpload = {
    /** The question «Upload 3 pages?» is open. */
    asking: Ref<boolean>;
    /** The question: «Upload 3 pages?», or «Upload 2 photos on a single page?». */
    question: ComputedRef<string>;
    /** Under the question: what it is and what leaves. */
    details: ComputedRef<string>;
    /** Opens the question. */
    ask: () => void;
    /** Sends the pages (after the question, and again with *Retry*). */
    send: () => Promise<void>;
    /** The files are leaving. */
    sending: Ref<boolean>;
    /** How much has left, from 0 to 100. */
    progress: Ref<number>;
    /** «Uploading…», or «Uploading file 2 of 3…». */
    progressText: Ref<string>;
    /** Why the upload stopped; null without a problem. */
    error: Ref<string | null>;
    /** *Retry* can send the rest. */
    canRetry: Ref<boolean>;
    /** «Uploaded: 3 pages, 1,5 MB» after the last file. */
    done: Ref<string | null>;
    /** Forgets the messages and what was sent: the document was thrown away. */
    reset: () => void;
};

/** What reached the server of a document sent in more than one go. */
type Sent = { files: number; photos: number; bytes: number };

/**
 * The upload of the scan to *To sort*: it asks first, then sends the files one by one with the progress. A file
 * that arrived takes its pages out of the document, so *Retry* sends only the rest. A session that has ended (401
 * or 419) is entered again with the linked phone and the file sent again, without losing the pages.
 *
 * @param options - the pages, *What it is* and what to do when files arrive
 * @returns the question, the state of the upload and `send`
 */
export function useScanUpload(options: ScanUploadOptions): UseScanUpload {
    const asking = ref(false);
    const sending = ref(false);
    const progress = ref(0);
    const progressText = ref('');
    const error = ref<string | null>(null);
    const canRetry = ref(false);
    const done = ref<string | null>(null);
    let sent: Sent = { files: 0, photos: 0, bytes: 0 };

    /** "On a single page" counts only when it can be used. */
    const single = (): boolean =>
        options.oneSheet() && oneSheetState(options.pages()) === 'ready';

    const question = computed((): string => {
        const photos = options
            .pages()
            .filter((page) => page.kind === 'photo').length;

        return single()
            ? trans('Upload :count photos on a single page?', {
                  count: String(photos),
              })
            : trans('Upload :what?', { what: pageCount(options.pages()) });
    });

    const details = computed((): string => {
        const note = options.note().trim();

        return [
            note && `«${note}»`,
            sendSummary(options.pages(), options.oneSheet()),
        ]
            .filter(Boolean)
            .join(' · ');
    });

    /** Opens the question. */
    function ask(): void {
        asking.value = true;
    }

    /**
     * Stops with a reason.
     *
     * @param message - what to tell the person
     * @param retry - whether *Retry* can send the rest
     */
    function fail(message: string, retry: boolean): void {
        error.value = message;
        canRetry.value = retry;
    }

    /** What arrived, as the person reads it. */
    function arrived(): string {
        const what =
            sent.files === 1 && sent.photos > 0
                ? transChoice(
                      '{1} :count page|[2,*] :count pages',
                      sent.photos,
                      {
                          count: String(sent.photos),
                      },
                  )
                : transChoice(
                      '{1} :count file|[2,*] :count files',
                      sent.files,
                      {
                          count: String(sent.files),
                      },
                  );

        return trans('Uploaded: :what, :size', {
            what,
            size: formatSize(sent.bytes),
        });
    }

    /**
     * Sends one file, entering again once if the session has ended.
     *
     * @param file - the file
     * @param note - *What it is*
     * @param onProgress - told how much of it has left
     * @throws ScanRequestError when it did not arrive
     */
    async function sendOne(
        file: File,
        note: string,
        onProgress: (fraction: number) => void,
    ): Promise<void> {
        try {
            await sendScanFile(file, note, onProgress);
        } catch (caught) {
            if (
                caught instanceof ScanRequestError &&
                caught.sessionEnded &&
                (await enterAgain())
            ) {
                await sendScanFile(file, note, onProgress);

                return;
            }

            throw caught;
        }
    }

    /** Sends the pages left in the document. */
    async function send(): Promise<void> {
        asking.value = false;

        if (sending.value || options.pages().length === 0) {
            return;
        }

        error.value = null;
        canRetry.value = false;
        done.value = null;

        const pages = [...options.pages()];
        const groups = fileGroups(pages);
        const many = single();
        let files: File[];

        try {
            files = await filesToSend(
                pages,
                options.oneSheet(),
                options.backend,
            );
        } catch (caught) {
            if (caught instanceof ScanTooHeavyError) {
                fail(
                    `${caught.message} ${trans('Take away some pages and upload in two goes.')}`,
                    false,
                );
            } else {
                fail(
                    trans('The PDF could not be made: try with fewer pages.'),
                    true,
                );
            }

            return;
        }

        const note = options.note().trim();
        sending.value = true;

        for (const [index, file] of files.entries()) {
            const group = groups[index] ?? [];
            const onProgress = (fraction: number): void => {
                progress.value = Math.round(
                    ((index + fraction) / files.length) * 100,
                );
            };
            progressText.value =
                files.length > 1
                    ? trans('Uploading file :number of :count…', {
                          number: String(index + 1),
                          count: String(files.length),
                      })
                    : trans('Uploading…');
            onProgress(0);

            try {
                await sendOne(file, note, onProgress);
            } catch (caught) {
                sending.value = false;

                if (caught instanceof ScanRequestError && caught.sessionEnded) {
                    fail(
                        trans(
                            'Your session has ended: sign in again. The pages are still here.',
                        ),
                        true,
                    );
                } else {
                    fail(
                        trans(':reason What was not uploaded is still here.', {
                            reason:
                                caught instanceof Error
                                    ? caught.message
                                    : trans('The upload did not go through.'),
                        }),
                        true,
                    );
                }

                return;
            }

            // what is on the server leaves the document: a retry sends only the rest
            sent.files++;
            sent.bytes += file.size;

            if (group[0]?.kind === 'photo') {
                sent.photos += many ? 1 : group.length;
            }

            options.onArrived(group.map((page) => page.id));
        }

        sending.value = false;
        progress.value = 100;
        done.value = arrived();
        sent = { files: 0, photos: 0, bytes: 0 };
        options.onFinished();
    }

    /** Forgets the messages and what was sent. */
    function reset(): void {
        error.value = null;
        canRetry.value = false;
        done.value = null;
        sent = { files: 0, photos: 0, bytes: 0 };
    }

    return {
        asking,
        question,
        details,
        ask,
        send,
        sending,
        progress,
        progressText,
        error,
        canRetry,
        done,
        reset,
    };
}
