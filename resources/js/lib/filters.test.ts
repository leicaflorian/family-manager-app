import { describe, expect, it } from 'vite-plus/test';
import { anyFilterOn, rangePills, withoutFilters } from './filters';

const none = { search: null, min: null, max: null, from: null, to: null };

describe('rangePills', () => {
    it('has no pill without filters', () => {
        expect(rangePills(none)).toEqual([]);
    });

    it('has the amounts, the days and the text, each with what removing it leaves', () => {
        expect(
            rangePills({
                search: 'spesa',
                min: 20,
                max: 100,
                from: '2026-09-01',
                to: '2026-10-31',
            }),
        ).toEqual([
            {
                key: 'amount',
                label: 'From 20,00 € to 100,00 €',
                without: { min: null, max: null },
            },
            {
                key: 'days',
                label: 'From 01/09/2026 to 31/10/2026',
                without: { from: null, to: null },
            },
            { key: 'search', label: '“spesa”', without: { search: null } },
        ]);
    });

    it('says one bound alone', () => {
        expect(
            rangePills({ ...none, min: 20, from: '2026-09-01' }).map(
                (pill) => pill.label,
            ),
        ).toEqual(['At least 20,00 €', 'From 01/09/2026']);
        expect(
            rangePills({ ...none, max: 100, to: '2026-10-31' }).map(
                (pill) => pill.label,
            ),
        ).toEqual(['Up to 100,00 €', 'Until 31/10/2026']);
    });

    it('says the next due date when the days are those of the next due date', () => {
        expect(
            rangePills(
                { ...none, from: '2026-11-01', to: '2026-11-30' },
                'next',
            ).map((pill) => pill.label),
        ).toEqual(['Next from 01/11/2026 to 30/11/2026']);
        expect(
            rangePills({ ...none, from: '2026-11-01' }, 'next')[0]!.label,
        ).toBe('Next from 01/11/2026');
        expect(
            rangePills({ ...none, to: '2026-11-30' }, 'next')[0]!.label,
        ).toBe('Next until 30/11/2026');
    });
});

describe('withoutFilters', () => {
    it('gives every filter as null', () => {
        expect(
            withoutFilters({ search: 'spesa', kind: 'expense', min: 20 }),
        ).toEqual({ search: null, kind: null, min: null });
    });
});

describe('anyFilterOn', () => {
    it('says whether a filter has a value', () => {
        expect(anyFilterOn(none)).toBe(false);
        expect(anyFilterOn({ ...none, min: 0 })).toBe(true);
    });
});
