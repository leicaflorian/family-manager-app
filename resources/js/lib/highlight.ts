/** A piece of a text, and whether it is one the search matched. */
export type HighlightPart = { text: string; match: boolean };

/** The shortest word of a query that is looked for in the text. */
const MIN_WORD_LENGTH = 2;

/**
 * Lowers the case and takes the accents off a text.
 *
 * @param text - any text
 * @returns the text in the form used to compare
 */
function fold(text: string): string {
    return text
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLowerCase();
}

/**
 * Cuts a text into pieces, marking the ones that contain a word of the query.
 * The comparison ignores case and accents, but the pieces are slices of the
 * original text.
 *
 * @param text - the text to show
 * @param query - what the person wrote; words under two letters are ignored
 * @returns the pieces in order, which put together give back `text`
 */
export function highlightParts(text: string, query: string): HighlightPart[] {
    const words = query
        .split(/\s+/)
        .map(fold)
        .filter((word) => word.length >= MIN_WORD_LENGTH);

    // Folded character by character so each one can be traced back to the
    // character it came from: a folded text can be longer or shorter.
    const origins: number[] = [];
    let folded = '';
    let index = 0;

    for (const char of text) {
        const piece = fold(char);

        folded += piece;

        for (let i = 0; i < piece.length; i++) {
            origins.push(index);
        }

        index += char.length;
    }

    const marked = Array.from({ length: text.length }, () => false);

    for (const word of words) {
        let from = folded.indexOf(word);

        while (from !== -1) {
            const start = origins[from];
            const end = (origins[from + word.length - 1] ?? start) + 1;

            for (let i = start; i < end; i++) {
                marked[i] = true;
            }

            from = folded.indexOf(word, from + 1);
        }
    }

    const parts: HighlightPart[] = [];

    for (let i = 0; i < text.length; i++) {
        const last = parts[parts.length - 1];

        if (last !== undefined && last.match === marked[i]) {
            last.text += text[i];
        } else {
            parts.push({ text: text[i], match: marked[i] });
        }
    }

    return parts;
}
