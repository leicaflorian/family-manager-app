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
