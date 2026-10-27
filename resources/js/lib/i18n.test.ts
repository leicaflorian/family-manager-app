import { describe, expect, it } from 'vite-plus/test';
import { i18nOptions, loadTranslations } from '@/lib/i18n';

describe('loadTranslations', () => {
    it('reads the Italian translations of Laravel', () => {
        const italian = loadTranslations('it');

        expect(italian['Saved.']).toBe('Salvato.');
    });

    it('gives no translations for English, the language the texts are written in', () => {
        expect(loadTranslations('en')).toEqual({});
    });

    it('gives no translations for a language the app does not have', () => {
        expect(loadTranslations('fr')).toEqual({});
    });
});

describe('i18nOptions', () => {
    it('starts from the language of the page and falls back to English', () => {
        const options = i18nOptions('it');

        expect(options.lang).toBe('it');
        expect(options.fallbackLang).toBe('en');
        expect(options.resolve).toBe(loadTranslations);
    });
});
