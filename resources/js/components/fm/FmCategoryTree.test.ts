import { fireEvent, render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
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
});
