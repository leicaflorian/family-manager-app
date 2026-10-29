import { CircleDollarSign, File, Flame, Folder, House } from '@lucide/vue';
import { describe, expect, it } from 'vite-plus/test';
import { categoryIcon, expenseIcon } from './icons';

describe('categoryIcon', () => {
    it('gives the Lucide icon from the name saved in the category', () => {
        expect(categoryIcon('house')).toBe(House);
    });

    it('gives the folder without a name, or with a name it does not know', () => {
        expect(categoryIcon(null)).toBe(Folder);
        expect(categoryIcon('does-not-exist')).toBe(Folder);
        expect(categoryIcon('file')).not.toBe(File);
    });
});

describe('expenseIcon', () => {
    it('gives the Lucide icon from the name saved in an expense category', () => {
        expect(expenseIcon('flame')).toBe(Flame);
        expect(expenseIcon('house')).toBe(House);
    });

    it('gives the coin without a name, or with a name it does not know', () => {
        expect(expenseIcon(null)).toBe(CircleDollarSign);
        expect(expenseIcon('does-not-exist')).toBe(CircleDollarSign);
    });
});
