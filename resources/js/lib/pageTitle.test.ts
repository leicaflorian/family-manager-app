import { describe, expect, it } from 'vite-plus/test';
import { pageTitle } from './pageTitle';

describe('pageTitle', () => {
    it('puts the name of the app after the title of the page', () => {
        expect(pageTitle('Accedi', 'Fameja.app')).toBe('Accedi - Fameja.app');
    });

    it('is the name of the app alone when the page has no title', () => {
        expect(pageTitle('', 'Fameja.app')).toBe('Fameja.app');
    });
});
