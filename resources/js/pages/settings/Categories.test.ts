import { router } from '@inertiajs/vue3';
import userEvent from '@testing-library/user-event';
import { fireEvent, screen, within } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { layOut, watchLayout } from '@/test/layout';
import {
    choose,
    openMenu,
    pressSave,
    renderPage,
    settle,
    withTrail,
} from '@/test/page';
import Categories from './Categories.vue';
import { answerLeave, leaveQuestion } from '@/lib/unsavedChanges';

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
    card_type: null,
    inherited_card_type: null,
    documents_count,
    children,
});

const cardTypes: App.Data.OptionData[] = [
    { value: 'home', label: 'Home', help: null },
    { value: 'utility', label: 'Utility', help: null },
];

const categories = [
    category(1, 'Home and utilities', 4, [
        category(2, 'Electricity', 2, [], 1),
        category(3, 'Waste tax', 0, [], 1),
    ]),
    category(4, 'Work', 5),
];

describe('Document categories', () => {
    it('writes the trail Settings > Document categories into the top bar', () => {
        const { state, provide } = withTrail();
        renderPage(
            Categories,
            { categories, cardTypes, canEdit: true },
            provide,
        );

        expect(state.pieces.value).toEqual([
            { label: 'Settings', href: '/settings' },
            { label: 'Document categories' },
        ]);
    });

    it('shows the tree with the number of documents, and opens the subcategories', async () => {
        renderPage(Categories, { categories, cardTypes, canEdit: true });

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
        renderPage(Categories, { categories, cardTypes, canEdit: true });

        await fireEvent.click(
            screen.getByRole('button', { name: /^Home and utilities/ }),
        );

        const sheet = await screen.findByRole('dialog');
        expect(within(sheet).getByLabelText('Name')).toHaveValue(
            'Home and utilities',
        );
        const inside = await openMenu('Inside');
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
            cardTypes,
            canEdit: true,
        });

        await fireEvent.click(screen.getByRole('button', { name: /^School/ }));

        const sheet = await screen.findByRole('dialog');
        expect(
            within(sheet).getByText(/There is 1 document in this category/),
        ).toBeInTheDocument();
    });

    it('lets an empty category be deleted', async () => {
        renderPage(Categories, { categories, cardTypes, canEdit: true });
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
        renderPage(Categories, { categories, cardTypes, canEdit: false });

        expect(
            screen.queryByRole('button', { name: 'New category' }),
        ).toBeNull();
        expect(screen.queryByRole('button', { name: /^Work/ })).toBeNull();
        expect(screen.getByText('Work')).toBeInTheDocument();
    });

    it('saves the category of the open panel with ⌘S, and nothing with the panel closed', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(Categories, { categories, cardTypes, canEdit: true });

        pressSave();
        expect(put).not.toHaveBeenCalled();
        await fireEvent.click(
            screen.getByRole('button', { name: /^Home and utilities/ }),
        );
        await screen.findByRole('dialog');
        pressSave();

        expect(put).toHaveBeenCalled();
    });

    it('puts New category in the menu when there is no room for it, and it still opens the panel', async () => {
        watchLayout();
        renderPage(Categories, { categories, cardTypes, canEdit: true });

        await layOut(40);

        expect(
            screen.queryByRole('button', { name: 'New category' }),
        ).not.toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );
        await userEvent.click(
            await screen.findByRole('menuitem', { name: 'New category' }),
        );
        expect(await screen.findByRole('dialog')).toHaveTextContent(
            'New category',
        );
    });
});

describe('Changes not saved', () => {
    it('asks before closing the panel with the × once the name changes', async () => {
        renderPage(Categories, { categories, cardTypes, canEdit: true });
        await fireEvent.click(
            screen.getByRole('button', { name: /^Home and utilities/ }),
        );
        const sheet = await screen.findByRole('dialog');
        await fireEvent.update(within(sheet).getByLabelText('Name'), 'Casa');
        await settle();

        await fireEvent.click(
            within(sheet).getByRole('button', { name: 'Close' }),
        );

        expect(leaveQuestion.open).toBe(true);
        answerLeave(false);
    });

    it('offers the card on a main category, first No card, and says what the choice does', async () => {
        renderPage(Categories, { categories, cardTypes, canEdit: true });
        await fireEvent.click(screen.getByRole('button', { name: /^Work/ }));

        const sheet = await screen.findByRole('dialog');
        const menu = await openMenu('Card');
        const labels = within(menu)
            .getAllByRole('option')
            .map((option) => option.textContent?.trim());
        expect(labels).toEqual(['No card', 'Home', 'Utility']);
        await fireEvent.keyDown(menu, { key: 'Escape' });
        expect(within(sheet).getByText(/The proposals of/).textContent).toBe(
            'The proposals of Import for the documents of this category start from here.',
        );
        expect(within(sheet).getByText('Import').tagName).toBe('EM');
    });

    it('names the card of the main category in the first voice of a subcategory', async () => {
        const withCard = [
            {
                ...category(1, 'Home and utilities', 0, [
                    {
                        ...category(2, 'Electricity', 2, [], 1),
                        inherited_card_type: 'utility',
                    },
                ]),
                card_type: 'utility',
            },
        ];
        renderPage(Categories, {
            categories: withCard,
            cardTypes,
            canEdit: true,
        });
        await fireEvent.click(
            screen.getByRole('button', { name: 'Open Home and utilities' }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: /^Electricity/ }),
        );
        await screen.findByRole('dialog');

        const menu = await openMenu('Card');
        expect(within(menu).getAllByRole('option')[0].textContent?.trim()).toBe(
            'Same as the main one (Utility)',
        );
    });

    it.each([
        ['the middle category has a card', 'utility', 'Utility'],
        ['only the main category has a card', null, 'Home'],
    ])(
        'names the card of the nearest category above in a deeper tree: %s',
        async (_case, middleCard, label) => {
            const deep = [
                {
                    ...category(1, 'Home and utilities', 0, [
                        {
                            ...category(
                                2,
                                'Electricity',
                                0,
                                [
                                    {
                                        ...category(5, 'Bills', 1, [], 2),
                                        inherited_card_type:
                                            middleCard ?? 'home',
                                    },
                                ],
                                1,
                            ),
                            card_type: middleCard,
                            inherited_card_type: 'home',
                        },
                    ]),
                    card_type: 'home',
                },
            ];
            renderPage(Categories, {
                categories: deep,
                cardTypes,
                canEdit: true,
            });
            await fireEvent.click(
                screen.getByRole('button', { name: 'Open Home and utilities' }),
            );
            await fireEvent.click(
                screen.getByRole('button', { name: 'Open Electricity' }),
            );
            await fireEvent.click(
                screen.getByRole('button', { name: /^Bills/ }),
            );
            await screen.findByRole('dialog');

            const menu = await openMenu('Card');
            expect(
                within(menu).getAllByRole('option')[0].textContent?.trim(),
            ).toBe(`Same as the main one (${label})`);
        },
    );

    it('drops the name of the card when the main category has none', async () => {
        renderPage(Categories, { categories, cardTypes, canEdit: true });
        await fireEvent.click(
            screen.getByRole('button', { name: 'Open Home and utilities' }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: /^Electricity/ }),
        );
        await screen.findByRole('dialog');

        const menu = await openMenu('Card');
        expect(within(menu).getAllByRole('option')[0].textContent?.trim()).toBe(
            'Same as the main one',
        );
    });

    it('saves the chosen card', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(Categories, { categories, cardTypes, canEdit: true });
        await fireEvent.click(screen.getByRole('button', { name: /^Work/ }));
        const sheet = await screen.findByRole('dialog');

        await choose('Card', 'Home');
        await fireEvent.click(
            within(sheet).getByRole('button', { name: 'Save' }),
        );

        expect(put.mock.calls.at(-1)?.[1]).toMatchObject({ card_type: 'home' });
    });

    it('saves null for the first voice', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(Categories, {
            categories: [{ ...category(4, 'Work', 5), card_type: 'home' }],
            cardTypes,
            canEdit: true,
        });
        await fireEvent.click(screen.getByRole('button', { name: /^Work/ }));
        const sheet = await screen.findByRole('dialog');

        await choose('Card', 'No card');
        await fireEvent.click(
            within(sheet).getByRole('button', { name: 'Save' }),
        );

        expect(put.mock.calls.at(-1)?.[1]).toMatchObject({ card_type: null });
    });
});
