import { describe, expect, it } from 'vite-plus/test';
import { filterLinks } from './toSort';

const filters = [
    { value: null, label: 'All', count: 3 },
    { value: 'mail', label: 'By mail', count: 0 },
    { value: 'phone', label: 'From the phone', count: 3 },
];

describe('filterLinks', () => {
    it('gives each filter the address of the page with it, All without a filter', () => {
        expect(filterLinks(filters, null).map((link) => link.href)).toEqual([
            '/to-sort',
            '/to-sort?source=mail',
            '/to-sort?source=phone',
        ]);
    });

    it('keeps the name, the value and the count of each filter', () => {
        expect(filterLinks(filters, null)[2]).toMatchObject({
            value: 'phone',
            label: 'From the phone',
            count: 3,
        });
    });

    it('marks the filter chosen, All when there is none', () => {
        expect(filterLinks(filters, null).map((link) => link.current)).toEqual([
            true,
            false,
            false,
        ]);
        expect(
            filterLinks(filters, 'phone').map((link) => link.current),
        ).toEqual([false, false, true]);
    });

    it('disables a filter with no file, but never All', () => {
        expect(
            filterLinks(
                [{ value: null, label: 'All', count: 0 }, ...filters.slice(1)],
                null,
            ).map((link) => link.disabled),
        ).toEqual([false, true, false]);
    });
});
