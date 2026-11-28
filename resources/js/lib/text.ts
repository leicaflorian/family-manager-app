import { trans } from 'laravel-vue-i18n';

/** Marks where the piece drawn apart goes in a translated sentence. */
const MARK = '\u0000';

/**
 * Cuts a translated sentence around one of its placeholders, so the page can draw that piece apart (a name in bold,
 * the name of a button in italics) without composing the sentence by hand.
 *
 * @param key - the English sentence, with the placeholder (`:name`)
 * @param placeholder - the name of the placeholder, without the colon
 * @param replacements - the other placeholders of the sentence
 * @returns the text before the placeholder and the text after it
 */
export function splitAround(
    key: string,
    placeholder: string,
    replacements: Record<string, string> = {},
): [string, string] {
    const [before = '', after = ''] = trans(key, {
        ...replacements,
        [placeholder]: MARK,
    }).split(MARK);

    return [before, after];
}

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

/**
 * A text without capitals and accents, to compare what is typed in a search
 * with names and titles.
 *
 * @param text - the text
 * @returns the text in lower case, without the accents
 */
export function plainText(text: string): string {
    return text.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
}
