import { describe, expect, it } from 'vite-plus/test';
import { settingsSections } from './settingsSections';

/** The keys of the sections, in order. */
function keys(features: Record<string, boolean> | null): string[] {
    return settingsSections(features).map((s) => s.key);
}

describe('settingsSections', () => {
    it('lists the sections of the settings in the order of the board, with the bank on', () => {
        expect(keys({ budget: true, bank_feed: true, mail: true })).toEqual([
            'account',
            'calendar',
            'family',
            'features',
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

    it('leaves out the banks and the expense categories while the Budget is off', () => {
        const off = keys({ budget: false, bank_feed: false });

        expect(off).not.toContain('banks');
        expect(off).not.toContain('expense-categories');
        expect(off).toContain('categories');
    });

    it('leaves out the address for the bills while the Mail is off', () => {
        expect(keys({ mail: false })).not.toContain('mail');
        expect(keys({ mail: true })).toContain('mail');
    });

    it('has everything on without a family', () => {
        expect(keys(null)).toContain('banks');
    });

    it('puts the sections in the three groups of the index', () => {
        const groups = settingsSections(null).map((s) => [s.key, s.group]);

        expect(groups[0]).toEqual(['account', 'mine']);
        expect(groups[2]).toEqual(['family', 'family']);
        expect(groups.at(-1)).toEqual(['news', 'app']);
    });

    it('gives the addresses', () => {
        const hrefs = Object.fromEntries(
            settingsSections(null).map((s) => [s.key, s.href]),
        );

        expect(hrefs['banks']).toBe('/settings/banks');
        expect(hrefs['areas']).toBe('/settings/areas');
    });

    it('writes the subtitles that come from the props of the page', () => {
        const rows = Object.fromEntries(
            settingsSections(null).map((s) => [s.key, s]),
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
            features: { budget: true, mail: false },
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
        expect(rows['features']!.subtitle?.(props)).toBe('Budget on');
        expect(rows['mail']!.subtitle?.(props)).toBe(
            'The bills arrive by email by themselves',
        );
    });

    it('does not name the mail in What you use on a server without its domain', () => {
        const features = settingsSections(null, false).find(
            (s) => s.key === 'features',
        )!;
        const subtitle = (
            budget: boolean,
            mail: boolean,
        ): string | null | undefined =>
            features.subtitle?.({
                features: { budget, mail },
            } as unknown as Parameters<
                NonNullable<typeof features.subtitle>
            >[0]);

        expect(subtitle(true, true)).toBe('Budget on');
        expect(subtitle(false, true)).toBe('Budget off');
        expect(subtitle(false, false)).toBe('Budget off');
    });
});
