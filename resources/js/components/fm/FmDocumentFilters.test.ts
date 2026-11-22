import { fireEvent, screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { documentFilterOptions } from '@/test/fixtures';
import {
    choose,
    openMenu,
    renderPage,
    search,
    settle,
    shown,
} from '@/test/page';
import FmDocumentFilters from './FmDocumentFilters.vue';

const none = {
    category: null,
    person: null,
    organization: null,
    status: null,
    expiring: false,
    search: null,
};

/** Whether every element follows the one before it in the page. */
function inOrder(elements: HTMLElement[]): boolean {
    return elements.every(
        (element, index) =>
            index === 0 ||
            Boolean(
                elements[index - 1].compareDocumentPosition(element) &
                Node.DOCUMENT_POSITION_FOLLOWING,
            ),
    );
}

describe('FmDocumentFilters', () => {
    it('sends the filters with the person chosen', async () => {
        const view = renderPage(FmDocumentFilters, {
            filters: none,
            options: documentFilterOptions,
        });

        await choose('Person', 'Laura Bianchi');

        expect(view.emitted('change')?.at(-1)).toEqual([
            { ...none, person: 7 },
        ]);
    });

    it('lists «All» first, then the categories indented under their main one', async () => {
        renderPage(FmDocumentFilters, {
            filters: none,
            options: documentFilterOptions,
        });

        const list = await openMenu('Category');

        expect(shown(list)).toEqual([
            'All',
            'Casa e utenze',
            'Luce',
            'Altro',
            'Lavoro',
            'Buste paga',
            'Altro',
        ]);
    });

    it('sends a subcategory chosen in the list, and shows its path in the field', async () => {
        const view = renderPage(FmDocumentFilters, {
            filters: none,
            options: documentFilterOptions,
        });

        await choose('Category', 'Lavoro › Altro');

        expect(view.emitted('change')?.at(-1)).toEqual([
            { ...none, category: 6 },
        ]);
        expect(screen.getByRole('combobox', { name: 'Category' })).toHaveValue(
            'Lavoro › Altro',
        );
    });

    it('searches the categories by their path, so a main one brings its subcategories', async () => {
        renderPage(FmDocumentFilters, {
            filters: none,
            options: documentFilterOptions,
        });

        const list = await search('Category', 'lav');

        expect(shown(list)).toEqual(['Lavoro', 'Buste paga', 'Altro']);
    });

    it('searches the people and the organizations', async () => {
        renderPage(FmDocumentFilters, {
            filters: none,
            options: documentFilterOptions,
        });

        expect(
            within(await search('Person', 'laura')).getByRole('option', {
                name: 'Laura Bianchi',
            }),
        ).toBeInTheDocument();
        expect(
            within(await search('Organization', 'bologna')).getByRole(
                'option',
                { name: 'Comune di Bologna' },
            ),
        ).toBeInTheDocument();
    });

    it('opens a filled menu with every option, and filters only what the person types', async () => {
        renderPage(FmDocumentFilters, {
            filters: { ...none, category: 4, person: 7, organization: 9 },
            options: documentFilterOptions,
        });

        expect(screen.getByRole('combobox', { name: 'Category' })).toHaveValue(
            'Lavoro › Buste paga',
        );
        expect(shown(await openMenu('Category'))).toEqual([
            'All',
            'Casa e utenze',
            'Luce',
            'Altro',
            'Lavoro',
            'Buste paga',
            'Altro',
        ]);
        expect(shown(await openMenu('Person'))).toEqual([
            'All',
            'Laura Bianchi',
        ]);
        expect(shown(await openMenu('Organization'))).toEqual([
            'All',
            'Comune di Bologna',
        ]);
        expect(shown(await search('Category', 'casa'))).toEqual([
            'Casa e utenze',
            'Luce',
            'Altro',
        ]);
    });

    it('sends the expiring documents only', async () => {
        const view = renderPage(FmDocumentFilters, {
            filters: none,
            options: documentFilterOptions,
        });

        await fireEvent.click(
            screen.getByRole('switch', { name: 'Expiring within 60 days' }),
        );
        await settle();

        expect(view.emitted('change')?.at(-1)).toEqual([
            { ...none, expiring: true },
        ]);
    });

    it('in columns every field is a cell of the grid', () => {
        const { container } = renderPage(FmDocumentFilters, {
            filters: { ...none, expiring: true },
            options: documentFilterOptions,
            columns: true,
        });

        const root = container.firstElementChild;
        expect(root).toHaveClass('grid-cols-2', 'computer:grid-cols-4');
        expect(
            inOrder([
                screen.getAllByText('Category')[0],
                screen.getAllByText('Person')[0],
                screen.getAllByText('Organization')[0],
                screen.getAllByText('Status')[0],
                screen.getByText('Expiring within 60 days'),
                screen.getByRole('button', { name: 'Remove the filters' }),
            ]),
        ).toBe(true);
    });

    it('in columns the filters are removed from the row below, only when one is on', async () => {
        const empty = renderPage(FmDocumentFilters, {
            filters: none,
            options: documentFilterOptions,
            columns: true,
        });
        expect(
            screen.queryByRole('button', { name: 'Remove the filters' }),
        ).not.toBeInTheDocument();
        empty.unmount();

        const view = renderPage(FmDocumentFilters, {
            filters: { ...none, person: 7, search: 'luce' },
            options: documentFilterOptions,
            columns: true,
        });
        await fireEvent.click(
            screen.getByRole('button', { name: 'Remove the filters' }),
        );

        expect(view.emitted('change')?.at(-1)).toEqual([
            {
                category: null,
                person: null,
                organization: null,
                status: null,
                expiring: false,
                search: null,
            },
        ]);
    });

    it('without columns the form stays a stack without the remove button', () => {
        const { container } = renderPage(FmDocumentFilters, {
            filters: { ...none, person: 7 },
            options: documentFilterOptions,
        });

        expect(container.firstElementChild).toHaveClass('flex-col');
        expect(container.firstElementChild).not.toHaveClass('grid-cols-2');
        expect(
            screen.queryByRole('button', { name: 'Remove the filters' }),
        ).not.toBeInTheDocument();
    });
});
