import { describe, expect, it } from 'vite-plus/test';
import { navigationItems, sectionOf } from './navigation';

describe('sectionOf', () => {
    const items = navigationItems();

    it('marks Areas on the pages of the areas and of the cards', () => {
        for (const path of [
            '/areas',
            '/areas/home',
            '/cards/create',
            '/cards/vehicle/1',
            '/cards/vehicle/1/edit',
        ]) {
            expect(sectionOf(path, items)).toBe('areas');
        }
    });

    it('marks the other sections by their address', () => {
        expect(sectionOf('/documents/5', items)).toBe('documents');
        expect(sectionOf('/family', items)).toBe('family');
        expect(sectionOf('/settings', items)).toBe('settings');
        expect(sectionOf('/dashboard', items)).toBe('home');
    });
});
