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

    it('marks Agenda on the reminders, their tab', () => {
        for (const path of [
            '/reminders',
            '/reminders/create',
            '/reminders/4/edit',
        ]) {
            expect(sectionOf(path, items)).toBe('agenda');
        }
    });

    it('marks Budget on every page of the budget', () => {
        for (const path of [
            '/budget',
            '/budget/movements',
            '/budget/movements/expense/4',
            '/budget/comparison',
            '/budget/planned',
            '/budget/planned/expense/3/occurrences/2026-11-05',
        ]) {
            expect(sectionOf(path, items)).toBe('budget');
        }
    });

    it('marks Agenda on its page', () => {
        expect(sectionOf('/agenda', items)).toBe('agenda');
    });

    it('lists Budget between Documents and Family', () => {
        expect(items.sections.map((item) => item.section)).toEqual([
            'home',
            'agenda',
            'areas',
            'documents',
            'budget',
            'family',
        ]);
        expect(items.sections[4]).toMatchObject({
            label: 'Budget',
            href: '/budget',
        });
    });
});
