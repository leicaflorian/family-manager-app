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

/**
 * The initials of a person, as the server makes them when the form leaves
 * them empty (`App\Support\Initials`).
 *
 * @param firstName - the first name
 * @param lastName - the last name
 * @returns the first letters, upper case, for example `MB`
 */
export function initialsOf(firstName: string, lastName: string): string {
    return (
        Array.from(firstName).slice(0, 1).join('') +
        Array.from(lastName).slice(0, 1).join('')
    ).toUpperCase();
}

/**
 * The initials of a name written in one piece, as the account menu shows them: the first letter of the first and
 * of the last word (one letter for a single word).
 *
 * @param fullName - the name, for example «Marco Bianchi»
 * @returns the initials, upper case, empty without a name
 */
export function initialsOfName(fullName?: string): string {
    const words = (fullName ?? '').trim().split(/\s+/u).filter(Boolean);

    if (words.length === 0) {
        return '';
    }

    return initialsOf(
        words[0],
        words.length > 1 ? words[words.length - 1] : '',
    );
}
