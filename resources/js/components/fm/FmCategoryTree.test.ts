import { fireEvent, render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { h } from 'vue';
import FmCategoryTree from './FmCategoryTree.vue';

const categories: App.Data.CategoryData[] = [
    {
        id: 1,
        name: 'Home and utilities',
        parent_id: null,
        icon: 'house',
        color: 'home',
        documents_count: 4,
        children: [],
    },
];

describe('FmCategoryTree', () => {
    it('shows the number of documents of each category', () => {
        render(FmCategoryTree, { props: { categories } });

        expect(screen.getByText('Home and utilities')).toBeInTheDocument();
        expect(screen.getByText('4')).toBeInTheDocument();
    });

    it('shows the content of the after slot in place of the number', () => {
        render(FmCategoryTree, {
            props: { categories },
            slots: {
                after: ({ category }: { category: App.Data.CategoryData }) =>
                    h('span', `Card of ${category.name}`),
            },
        });

        expect(
            screen.getByText('Card of Home and utilities'),
        ).toBeInTheDocument();
        expect(screen.queryByText('4')).toBeNull();
    });

    it('lets only the main categories be tapped with mainOnly', async () => {
        render(FmCategoryTree, {
            props: {
                categories: [
                    {
                        ...categories[0]!,
                        children: [
                            {
                                id: 2,
                                name: 'Electricity',
                                parent_id: 1,
                                icon: null,
                                color: 'home',
                                documents_count: 2,
                                children: [],
                            },
                        ],
                    },
                ],
                selectable: true,
                mainOnly: true,
            },
        });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Open Home and utilities' }),
        );

        expect(
            screen.getByRole('button', { name: /^Home and utilities/ }),
        ).toBeInTheDocument();
        expect(screen.getByText('Electricity')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /^Electricity/ }),
        ).toBeNull();
    });
});
