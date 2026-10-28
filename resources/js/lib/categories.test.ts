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
    it('lists every category in the order of the tree, each with its name, its depth and its path', () => {
        const tree = [
            category(1, 'Home and utilities', [category(2, 'Electricity')]),
            category(3, 'Work', [
                category(4, 'Payslips', [category(5, '2026')]),
            ]),
        ];

        expect(categoryOptions(tree)).toEqual([
            {
                value: '1',
                label: 'Home and utilities',
                name: 'Home and utilities',
                depth: 0,
            },
            {
                value: '2',
                label: 'Home and utilities › Electricity',
                name: 'Electricity',
                depth: 1,
            },
            { value: '3', label: 'Work', name: 'Work', depth: 0 },
            {
                value: '4',
                label: 'Work › Payslips',
                name: 'Payslips',
                depth: 1,
            },
            {
                value: '5',
                label: 'Work › Payslips › 2026',
                name: '2026',
                depth: 2,
            },
        ]);
    });

    it('gives no options for an empty tree', () => {
        expect(categoryOptions([])).toEqual([]);
    });
});
