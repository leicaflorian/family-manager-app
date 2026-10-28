import { describe, expect, it } from 'vite-plus/test';
import { initialsOf, listNames } from './names';

describe('listNames', () => {
    it('joins the names with commas and «and» before the last one', () => {
        expect(listNames(['Marco', 'Laura', 'Giulia'], 'en')).toBe(
            'Marco, Laura, and Giulia',
        );
        expect(listNames(['Marco', 'Laura'], 'en')).toBe('Marco and Laura');
        expect(listNames(['Marco'], 'en')).toBe('Marco');
        expect(listNames([], 'en')).toBe('');
    });

    it('writes the sentence in Italian for an Italian page', () => {
        expect(listNames(['Marco', 'Laura', 'Giulia'], 'it')).toBe(
            'Marco, Laura e Giulia',
        );
    });
});

describe('initialsOf', () => {
    it('takes the first letter of first and last name, upper case, as the server does', () => {
        expect(initialsOf('rosa', 'Bianchi')).toBe('RB');
        expect(initialsOf('Èlia', '')).toBe('È');
        expect(initialsOf('', '')).toBe('');
    });
});
