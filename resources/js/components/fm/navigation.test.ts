import { MessageSquareWarning } from '@lucide/vue';
import { describe, expect, it } from 'vite-plus/test';
import {
    allItems,
    barItems,
    navigationItems,
    SECTIONS,
    sectionOf,
} from './navigation';

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

    it('marks To sort on its page, which only the sidebar lists', () => {
        expect(items.toSort.href).toBe('/to-sort');
        expect(items.toSort.label).toBe('To sort');
        expect(items.sections.some((item) => item.section === 'toSort')).toBe(
            false,
        );
        expect(sectionOf('/to-sort', items)).toBe('toSort');
    });

    it('marks To sort on Upload and on Import, the pages of the files', () => {
        expect(sectionOf('/upload', items)).toBe('toSort');
        expect(sectionOf('/import', items)).toBe('toSort');
    });

    it('has Received mail, marked on every page of the mail', () => {
        expect(items.mail.href).toBe('/mail');
        expect(items.mail.label).toBe('Received mail');
        expect(items.mail.hint).toBe(
            'The mail that arrived at the address for bills',
        );
        expect(items.sections.some((item) => item.section === 'mail')).toBe(
            false,
        );
        for (const path of ['/mail', '/mail/7']) {
            expect(sectionOf(path, items)).toBe('mail');
        }
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

describe('navigationItems', () => {
    it('has the item that opens Report a problem or an idea, without an address', () => {
        expect(navigationItems().feedback).toEqual({
            label: 'Report a problem or an idea',
            hint: 'It reaches who makes Fameja.app',
            icon: MessageSquareWarning,
        });
    });
});

describe('the registry of the sections', () => {
    it('lists the items in the order the menus use', () => {
        expect(allItems(navigationItems()).map((item) => item.section)).toEqual(
            [
                'home',
                'agenda',
                'areas',
                'documents',
                'budget',
                'family',
                'toSort',
                'mail',
                'settings',
            ],
        );
    });

    it('has one entry per section, each with a group', () => {
        expect(SECTIONS.map((entry) => entry.key)).toEqual(
            allItems(navigationItems()).map((item) => item.section),
        );
        expect(
            SECTIONS.filter((entry) => entry.key !== 'settings').map(
                (entry) => entry.group,
            ),
        ).toEqual([
            'home',
            'every-day',
            'archive',
            'archive',
            'every-day',
            'archive',
            'incoming',
            'incoming',
        ]);
    });

    it('leaves out a section whose feature is off, and keeps the others', () => {
        const entries = SECTIONS.map((entry) =>
            entry.key === 'documents'
                ? { ...entry, feature: 'gallery' as const }
                : entry,
        );
        const names = (features: { gallery?: boolean } | null): string[] =>
            allItems(navigationItems(features, entries)).map(
                (item) => item.section,
            );

        expect(names(null)).toContain('documents');
        expect(names({ gallery: true })).toContain('documents');
        expect(names({ gallery: false })).not.toContain('documents');
        expect(names({ gallery: false })).toHaveLength(8);
    });

    it('puts Home, Budget and Areas in the bar of the phone', () => {
        expect(barItems(navigationItems()).map((item) => item.section)).toEqual(
            ['home', 'budget', 'areas'],
        );
    });

    it('takes the item marked as fallback when one of the bar is missing', () => {
        const items = navigationItems();
        const withoutBudget = {
            ...items,
            sections: items.sections.filter(
                (item) => item.section !== 'budget',
            ),
        };

        expect(barItems(withoutBudget).map((item) => item.section)).toEqual([
            'home',
            'agenda',
            'areas',
        ]);
    });
});
