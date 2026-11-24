import { describe, expect, it } from 'vite-plus/test';
import { initialsOf, initialsOfName, listNames } from './names';

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

describe('initialsOfName', () => {
    it('takes the first letter of the first and of the last word, upper case', () => {
        expect(initialsOfName('marco de luca bianchi')).toBe('MB');
        expect(initialsOfName('  Laura   Rossi ')).toBe('LR');
    });

    it('takes one letter of a single word', () => {
        expect(initialsOfName('èlia')).toBe('È');
    });

    it('gives nothing without a name', () => {
        expect(initialsOfName('')).toBe('');
        expect(initialsOfName('   ')).toBe('');
        expect(initialsOfName(undefined)).toBe('');
    });
});
