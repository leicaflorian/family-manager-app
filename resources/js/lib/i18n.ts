/**
 * The translations of the pages. The texts are written in English in the code;
 * the other languages are the JSON files of Laravel (`lang/it.json`), the same
 * ones `__()` reads in PHP.
 */

/**
 * The language files of Laravel, in the bundle: they are small, and loading them
 * with the page avoids showing the English texts for a moment before the others.
 */
const files = import.meta.glob<Record<string, string>>('../../../lang/*.json', {
    import: 'default',
    eager: true,
});

/**
 * The translations of a language, English text => translated text.
 *
 * @param lang - the language, for example `it`
 * @returns the translations, empty for English or for a language the app does not have
 */
export function loadTranslations(lang: string): Record<string, string> {
    return files[`../../../lang/${lang}.json`] ?? {};
}

/** The options of laravel-vue-i18n this app uses. */
export type I18nOptions = {
    /** The language of the page. */
    lang: string;
    /** The language of a text without translation: English, the one of the code. */
    fallbackLang: string;
    /** Gives the translations of a language. */
    resolve: (lang: string) => Record<string, string>;
};

/**
 * The options of laravel-vue-i18n for the language of the page.
 *
 * @param lang - the language chosen by the server (`<html lang>`)
 * @returns the options of the plugin
 */
export function i18nOptions(lang: string): I18nOptions {
    return { lang, fallbackLang: 'en', resolve: loadTranslations };
}
