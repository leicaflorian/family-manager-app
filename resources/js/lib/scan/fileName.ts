// The names of the PDFs made from the sides of the scanner: one for the whole scan, or one per side.

/** The longest file name the server takes (`max:200` of `ScanRequest`). */
export const MOST_NAME = 200;

/**
 * The names of `count` PDFs: the name written, cleaned of a second `.pdf` and of the signs a file name cannot hold,
 * or, without one, "Scanner" with the day and the time of `now`. With more than one PDF each name takes its number,
 * with as many digits as the last one, so the files stay in order. `before` PDFs of the same scan are already
 * uploaded (an upload stopped halfway): the numbers go on from there, and no name comes twice.
 */
export function scanFileNames(
    name: string,
    count: number,
    now: Date,
    before = 0,
): string[] {
    const two = (value: number) => String(value).padStart(2, '0');
    const cleaned =
        name
            .replace(/\.pdf$/i, '')
            .replace(/[\\/:*?"<>|]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim() ||
        `Scanner ${now.getFullYear()}-${two(now.getMonth() + 1)}-${two(now.getDate())} ${two(now.getHours())}.${two(now.getMinutes())}`;
    const digits = String(before + count).length;
    const single = count === 1 && !before;
    // the server takes names up to MOST_NAME: the base is cut so the number and `.pdf` still fit
    const room = MOST_NAME - (single ? 4 : 1 + digits + 4);
    const base = cleaned.slice(0, room).trimEnd();
    if (single) return [`${base}.pdf`];
    return Array.from(
        { length: count },
        (_, index) =>
            `${base} ${String(before + index + 1).padStart(digits, '0')}.pdf`,
    );
}
