import { describe, expect, it } from 'vite-plus/test';
import { categoryOptions } from './categories';

/** A category of the tree, with only what the options read. */
function category(
    id: number,
    name: string,
    children: App.Data.CategoryData[] = [],
): App.Data.CategoryData {
    return {
        id,
        name,
        parent_id: null,
        icon: null,
        color: null,
        documents_count: 0,
        children,
    };
}

describe('categoryOptions', () => {
    it('lists every category in the order of the tree, with the path of the subcategories', () => {
        const tree = [
            category(1, 'Home and utilities', [category(2, 'Electricity')]),
            category(3, 'Work', [
                category(4, 'Payslips', [category(5, '2026')]),
            ]),
        ];

        expect(categoryOptions(tree)).toEqual([
            { value: '1', label: 'Home and utilities' },
            { value: '2', label: 'Home and utilities › Electricity' },
            { value: '3', label: 'Work' },
            { value: '4', label: 'Work › Payslips' },
            { value: '5', label: 'Work › Payslips › 2026' },
        ]);
    });

    it('gives no options for an empty tree', () => {
        expect(categoryOptions([])).toEqual([]);
    });
});
