import { fireEvent, screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import Categories from './Categories.vue';

const category = (
    id: number,
    name: string,
    documents_count: number,
    children: App.Data.CategoryData[] = [],
    parent_id: number | null = null,
): App.Data.CategoryData => ({
    id,
    name,
    parent_id,
    icon: parent_id ? null : 'house',
    color: 'home',
    documents_count,
    children,
});

const categories = [
    category(1, 'Home and utilities', 4, [
        category(2, 'Electricity', 2, [], 1),
        category(3, 'Waste tax', 0, [], 1),
    ]),
    category(4, 'Work', 5),
];

describe('Document categories', () => {
    it('shows the tree with the number of documents, and opens the subcategories', async () => {
        renderPage(Categories, { categories, canEdit: true });

        expect(screen.getByText('Home and utilities')).toBeInTheDocument();
        expect(screen.queryByText('Electricity')).toBeNull();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Open Home and utilities' }),
        );

        expect(screen.getByText('Electricity')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Close Home and utilities' }),
        ).toHaveAttribute('aria-expanded', 'true');
    });

    it('edits a category in the panel, and does not put it under itself', async () => {
        renderPage(Categories, { categories, canEdit: true });

        await fireEvent.click(
            screen.getByRole('button', { name: /^Home and utilities/ }),
        );

        const sheet = await screen.findByRole('dialog');
        expect(within(sheet).getByLabelText('Name')).toHaveValue(
            'Home and utilities',
        );
        const inside = within(sheet).getByLabelText('Inside');
        expect(
            within(inside).getByRole('option', {
                name: 'None: it is a main one',
            }),
        ).toBeInTheDocument();
        expect(
            within(inside).getByRole('option', { name: 'Work' }),
        ).toBeInTheDocument();
        expect(
            within(inside).queryByRole('option', {
                name: 'Home and utilities',
            }),
        ).toBeNull();
        expect(
            within(inside).queryByRole('option', { name: /Electricity/ }),
        ).toBeNull();
        expect(
            within(sheet).getByText(/4 documents in this category/),
        ).toBeInTheDocument();
        expect(
            within(sheet).queryByRole('button', { name: 'Delete' }),
        ).toBeNull();
    });

    it('writes the singular for a category with one document', async () => {
        renderPage(Categories, {
            categories: [category(5, 'School', 1)],
            canEdit: true,
        });

        await fireEvent.click(screen.getByRole('button', { name: /^School/ }));

        const sheet = await screen.findByRole('dialog');
        expect(
            within(sheet).getByText(/There is 1 document in this category/),
        ).toBeInTheDocument();
    });

    it('lets an empty category be deleted', async () => {
        renderPage(Categories, { categories, canEdit: true });
        await fireEvent.click(
            screen.getByRole('button', { name: 'Open Home and utilities' }),
        );

        await fireEvent.click(
            screen.getByRole('button', { name: /^Waste tax/ }),
        );

        const sheet = await screen.findByRole('dialog');
        expect(
            within(sheet).getByText(/No document is in this category/),
        ).toBeInTheDocument();
        expect(
            within(sheet).getByRole('button', { name: 'Delete' }),
        ).toBeInTheDocument();
    });

    it('shows the categories to a family member without letting them change them', () => {
        renderPage(Categories, { categories, canEdit: false });

        expect(
            screen.queryByRole('button', { name: 'New category' }),
        ).toBeNull();
        expect(screen.queryByRole('button', { name: /^Work/ })).toBeNull();
        expect(screen.getByText('Work')).toBeInTheDocument();
    });
});
