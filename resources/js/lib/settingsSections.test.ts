import { describe, expect, it } from 'vite-plus/test';
import { settingsSections } from './settingsSections';

/** The keys of the sections, in order. */
function keys(features: Record<string, boolean> | null): string[] {
    return settingsSections(features, () => true).map((s) => s.key);
}

describe('settingsSections', () => {
    it('lists the sections of the settings in the order of the board, with the bank on', () => {
        expect(keys({ bank_feed: true })).toEqual([
            'account',
            'calendar',
            'family',
            'notices',
            'mail',
            'banks',
            'categories',
            'expense-categories',
            'areas',
            'support',
            'news',
        ]);
    });

    it('leaves out Linked banks while the bank is off', () => {
        expect(keys({ bank_feed: false })).not.toContain('banks');
        expect(keys({})).not.toContain('banks');
    });

    it('has everything on without a family', () => {
        expect(keys(null)).toContain('banks');
    });

    it('puts the sections in the three groups of the index', () => {
        const groups = settingsSections(null, () => true).map((s) => [
            s.key,
            s.group,
        ]);

        expect(groups[0]).toEqual(['account', 'mine']);
        expect(groups[2]).toEqual(['family', 'family']);
        expect(groups.at(-1)).toEqual(['news', 'app']);
    });

    it('gives the addresses', () => {
        const hrefs = Object.fromEntries(
            settingsSections(null, () => true).map((s) => [s.key, s.href]),
        );

        expect(hrefs['banks']).toBe('/settings/banks');
        expect(hrefs['areas']).toBe('/settings/areas');
    });

    it('writes the subtitles that come from the props of the page', () => {
        const rows = Object.fromEntries(
            settingsSections(null, () => true).map((s) => [s.key, s]),
        );
        const props = {
            account: {
                family: 'Bianchi',
                first_name: 'Marco',
                last_name: 'Bianchi',
                role: 'Admin',
            },
            mainCategories: 10,
            mainExpenseCategories: 1,
            areaCount: 9,
            hiddenAreas: 0,
            bankCount: 2,
            release: '1.2.0',
            releasedOn: null,
            date: (value: string): string => value,
        } as unknown as Parameters<
            NonNullable<(typeof rows)[string]['subtitle']>
        >[0];

        expect(rows['family']!.subtitle?.(props)).toBe('Bianchi');
        expect(rows['banks']!.subtitle?.(props)).toBe('2 banks linked');
        expect(rows['categories']!.subtitle?.(props)).toBe(
            '10 main categories',
        );
        expect(rows['areas']!.subtitle?.(props)).toBe('9 areas');
        expect(rows['news']!.subtitle?.(props)).toBe('Version 1.2.0');
        expect(rows['mail']!.subtitle?.(props)).toBe(
            'The bills arrive by email by themselves',
        );
    });

    it('leaves out a section the person cannot use', () => {
        expect(
            settingsSections(null, (ability) => ability !== 'manage').map(
                (s) => s.key,
            ),
        ).toContain('account');
    });
});
