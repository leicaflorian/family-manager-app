import { fireEvent, render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { h } from 'vue';
import FmCategoryTree from './FmCategoryTree.vue';

/** A category of the tree, as the server sends it. */
function category(
    id: number,
    name: string,
    documents: number,
    children: App.Data.CategoryData[] = [],
): App.Data.CategoryData {
    return {
        id,
        name,
        parent_id: null,
        icon: null,
        color: 'home',
        documents_count: documents,
        children,
    };
}

const categories = [
    category(1, 'Casa e utenze', 5, [category(2, 'Luce', 3)]),
    category(3, 'Lavoro', 0),
];

describe('FmCategoryTree', () => {
    it('shows each category with the number of its documents', () => {
        render(FmCategoryTree, { props: { categories } });

        expect(screen.getByText('Casa e utenze')).toBeInTheDocument();
        expect(screen.getByText('5')).toBeInTheDocument();
        expect(screen.getByText('Lavoro')).toBeInTheDocument();
        expect(screen.getByText('0')).toBeInTheDocument();
    });

    it('keeps the subcategories hidden until the arrow is tapped, and hides them again', async () => {
        render(FmCategoryTree, { props: { categories } });

        expect(screen.queryByText('Luce')).not.toBeInTheDocument();
        // Only the category with subcategories has an arrow
        expect(screen.getAllByRole('button')).toHaveLength(1);

        const arrow = screen.getByRole('button', {
            name: 'Open Casa e utenze',
        });
        expect(arrow).toHaveAttribute('aria-expanded', 'false');
        await fireEvent.click(arrow);

        expect(screen.getByText('Luce')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Close Casa e utenze' }),
        ).toHaveAttribute('aria-expanded', 'true');

        await fireEvent.click(
            screen.getByRole('button', { name: 'Close Casa e utenze' }),
        );
        expect(screen.queryByText('Luce')).not.toBeInTheDocument();
    });

    it('does not let the rows be tapped unless it is selectable', async () => {
        const { emitted } = render(FmCategoryTree, { props: { categories } });

        await fireEvent.click(screen.getByText('Lavoro'));

        expect(emitted()).not.toHaveProperty('select');
    });

    it('tells which category was tapped, also in a lower level', async () => {
        const { emitted } = render(FmCategoryTree, {
            props: { categories, selectable: true },
        });

        await fireEvent.click(screen.getByText('Lavoro'));
        await fireEvent.click(
            screen.getByRole('button', { name: 'Open Casa e utenze' }),
        );
        await fireEvent.click(screen.getByText('Luce'));

        expect(emitted().select).toEqual([
            [categories[1]],
            [categories[0]!.children[0]],
        ]);
    });

    describe('with the slot and the options of the pages', () => {
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

        it('shows the content of the after slot in place of the number', () => {
            render(FmCategoryTree, {
                props: { categories },
                slots: {
                    after: ({
                        category,
                    }: {
                        category: App.Data.CategoryData;
                    }) => h('span', `Card of ${category.name}`),
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

        it('colours the tile and the dot of an expense category with its own colour', async () => {
            const { container } = render(FmCategoryTree, {
                props: {
                    categories: [
                        {
                            id: 3,
                            name: 'Auto',
                            icon: 'car',
                            color: '#0797B9',
                            children: [
                                {
                                    id: 4,
                                    name: 'Parcheggi',
                                    icon: null,
                                    color: '#0797B9',
                                    children: [],
                                },
                            ],
                        },
                    ],
                },
            });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Open Auto' }),
            );

            const coloured =
                container.querySelectorAll<HTMLElement>('span[style]');

            expect(coloured).toHaveLength(2);
            expect(coloured[0]).toHaveStyle({ color: '#0797B9' });
            expect(coloured[1]).toHaveStyle({ backgroundColor: '#0797B9' });
        });
    });
});
