/**
 * Splits a text in two, the last characters apart: shown in two pieces, the
 * first cut with an ellipsis where room is short and the second always
 * whole, a long title keeps both its beginning and its end
 * (`Polizza RC a…uto 2026`). Characters made of more than one code point,
 * as an emoji, are never broken.
 *
 * @param text - the text
 * @param tail - how many characters to keep apart at the end
 * @returns the beginning and the end; the end is empty when the text is short
 */
export function splitTail(text: string, tail: number): [string, string] {
    const characters = Array.from(
        new Intl.Segmenter().segment(text),
        ({ segment }) => segment,
    );

    if (characters.length <= tail) {
        return [text, ''];
    }

    return [
        characters.slice(0, -tail).join(''),
        characters.slice(-tail).join(''),
    ];
}
