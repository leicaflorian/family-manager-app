import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage, settle } from '@/test/page';
import FmPlannedFilters from './FmPlannedFilters.vue';

const none: App.Data.PlannedFiltersData = {
    search: null,
    min: null,
    max: null,
    from: null,
    to: null,
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

describe('FmPlannedFilters', () => {
    it('has the amounts and the next due dates, and says what happens without dates', () => {
        renderPage(FmPlannedFilters, {
            filters: { ...none, from: '2026-11-01', to: '2026-11-30' },
        });

        expect(
            screen.getByRole('group', { name: 'Amount' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('At least')).toBeInTheDocument();
        expect(screen.getByLabelText('At most')).toBeInTheDocument();
        expect(
            screen.getByRole('group', { name: 'Next due date' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('From')).toHaveValue('01/11/2026');
        expect(screen.getByLabelText('To')).toHaveValue('30/11/2026');
        expect(
            screen.getByText('Without dates you see them all.'),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /Type|Bank account/ }),
        ).not.toBeInTheDocument();
    });

    it('offers to remove the filters only when one is on, and removes them all', async () => {
        const empty = renderPage(FmPlannedFilters, { filters: none });
        expect(
            screen.queryByRole('button', { name: 'Remove the filters' }),
        ).not.toBeInTheDocument();
        empty.unmount();

        const view = renderPage(FmPlannedFilters, {
            filters: {
                search: 'luce',
                min: 20,
                max: 200,
                from: '2026-11-01',
                to: '2026-11-30',
            },
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove the filters' }),
        );

        expect(view.emitted('change')?.at(-1)).toEqual([none]);
    });

    it('empties the days when the page takes the filters away, without sending anything', async () => {
        const view = renderPage(FmPlannedFilters, {
            filters: { ...none, search: 'luce', from: '2026-11-01' },
        });
        await settle();
        expect(screen.getByLabelText('From')).toHaveValue('01/11/2026');

        await view.rerender({ filters: none });
        await settle();

        expect(screen.getByLabelText('From')).not.toHaveValue('01/11/2026');
        expect(view.emitted('change')).toBeUndefined();
    });

    it('shows the problem the server found on a field', () => {
        renderPage(FmPlannedFilters, {
            filters: { ...none, from: '2026-11-10', to: '2026-11-01' },
            errors: {
                to: 'The to field must be a date after or equal to from.',
            },
        });

        expect(
            screen.getByText(
                'The to field must be a date after or equal to from.',
            ),
        ).toBeInTheDocument();
    });

    it('in columns every field is a cell of the grid', () => {
        const { container } = renderPage(FmPlannedFilters, {
            filters: { ...none, min: 20 },
            columns: true,
        });

        expect(container.firstElementChild).toHaveClass(
            'grid-cols-2',
            'computer:grid-cols-4',
        );
        expect(container.firstElementChild).not.toHaveClass('@container');
        expect(
            inOrder([
                screen.getByRole('group', { name: 'Amount' }),
                screen.getByRole('group', { name: 'Next due date' }),
                screen.getByRole('button', { name: 'Remove the filters' }),
            ]),
        ).toBe(true);
        expect(
            screen.getByRole('button', { name: 'Remove the filters' }),
        ).toHaveClass('col-span-full');
    });
});
