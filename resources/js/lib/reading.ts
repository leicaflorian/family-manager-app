import { router } from '@inertiajs/vue3';
import { read } from '@/routes/files';

/** Where the AI reading of a file stands; `null` or missing when it was never asked. */
type Status = App.Enums.ReadingStatus | null | undefined;

/**
 * Whether the file is waiting in the queue or being read, which keeps a page asking.
 *
 * @param status - the reading status of the file
 * @returns true for `queued` and `reading`
 */
export function isReading(status: Status): boolean {
    return status === 'queued' || status === 'reading';
}

/**
 * Whether the reading ended without a proposal, so the page tells why: it failed, is off or was skipped.
 *
 * @param status - the reading status of the file
 * @returns true for `failed`, `off` and `skipped`
 */
export function isNotRead(status: Status): boolean {
    return status === 'failed' || status === 'off' || status === 'skipped';
}

/**
 * Whether the page offers *Read again*: the reading failed, or was off when the file came in and may be on now.
 * A file skipped is one the model does not read, so asking again would give the same answer.
 *
 * @param status - the reading status of the file
 * @returns true for `failed` and `off`
 */
export function canReadAgain(status: Status): boolean {
    return status === 'failed' || status === 'off';
}

/**
 * Puts the reading of a file back in the queue (*Read again*). The page keeps its state, so the forms keep what
 * the person wrote, and its scroll.
 *
 * @param fileId - the id of the file
 */
export function readAgain(fileId: number): void {
    router.post(
        read.url(fileId),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

/**
 * Whether any of the files is waiting in the queue or being read: a page showing them keeps asking.
 *
 * @param files - the files, with their reading status
 * @returns true when one of them is `queued` or `reading`
 */
export function anyFileReading(
    files: readonly { reading_status: Status }[],
): boolean {
    return files.some((file) => isReading(file.reading_status));
}
