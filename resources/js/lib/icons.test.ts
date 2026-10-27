import { File, Folder, House } from '@lucide/vue';
import { describe, expect, it } from 'vite-plus/test';
import { categoryIcon } from './icons';

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
