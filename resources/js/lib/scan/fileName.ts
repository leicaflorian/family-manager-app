// The names of the PDFs made from the sides of the scanner: one for the whole scan, or one per side.

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
    const base =
        name
            .replace(/\.pdf$/i, '')
            .replace(/[\\/:*?"<>|]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim() ||
        `Scanner ${now.getFullYear()}-${two(now.getMonth() + 1)}-${two(now.getDate())} ${two(now.getHours())}.${two(now.getMinutes())}`;
    if (count === 1 && !before) return [`${base}.pdf`];
    const digits = String(before + count).length;
    return Array.from(
        { length: count },
        (_, index) =>
            `${base} ${String(before + index + 1).padStart(digits, '0')}.pdf`,
    );
}
