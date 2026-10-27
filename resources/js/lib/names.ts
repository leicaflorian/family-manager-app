import { getActiveLanguage } from 'laravel-vue-i18n';

/**
 * Joins names as they are written in a sentence: «Marco, Laura and Giulia» in
 * English, «Marco, Laura e Giulia» in Italian.
 *
 * @param names - the names, in the order they are read
 * @param lang - the language of the sentence, the one of the page by default
 * @returns the sentence, empty if there are no names
 */
export function listNames(
    names: string[],
    lang: string = getActiveLanguage(),
): string {
    return new Intl.ListFormat(lang, {
        style: 'long',
        type: 'conjunction',
    }).format(names);
}
