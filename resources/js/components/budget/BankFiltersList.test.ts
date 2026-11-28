import { router } from '@inertiajs/vue3';
import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { bankFilters } from '@/test/fixtures';
import { renderPage, screenWidth } from '@/test/page';
import BankFiltersList from './BankFiltersList.vue';

const satispay = bankFilters[1]!;
const luce = bankFilters[0]!;

/** The row of a filter, by its name. */
function rowOf(name: string): HTMLElement {
    return screen
        .getByText(name, { selector: '[data-filter-name]' })
        .closest<HTMLElement>('li')!;
}

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('BankFiltersList', () => {
    it('a row shows the criteria, the action and how many movements it took', () => {
        renderPage(BankFiltersList, { filters: [satispay] });
        const row = rowOf('Satispay');

        expect(within(row).getByText('Matches:')).toBeInTheDocument();
        expect(within(row).getByText('SATISPAY')).toBeInTheDocument();
        expect(within(row).getByText('DOMICILIAZIONE')).toBeInTheDocument();
        expect(within(row).getByText('COMMISSIONI')).toHaveClass(
            'line-through',
        );
        expect(row).toHaveTextContent('going out · all the accounts');
        expect(within(row).getByText('Action:')).toBeInTheDocument();
        expect(within(row).getByText('«Ricarica Satispay»').tagName).toBe(
            'STRONG',
        );
        expect(row).toHaveTextContent(
            'creates the expense «Ricarica Satispay» · Varie · Satispay · with the commission',
        );
        expect(row).toHaveTextContent('2 movements in the last 90 days');
    });

    it('an advanced filter is tagged and says it changes only as text', () => {
        renderPage(BankFiltersList, {
            filters: [
                {
                    ...luce,
                    name: 'Luce Enel',
                    pattern: 'ENEL\\s+ENERGIA.*(BOLL|FATT)',
                    contains: [],
                    missing: null,
                },
            ],
        });
        const row = rowOf('Luce Enel');

        expect(within(row).getByText('Advanced filter')).toBeInTheDocument();
        expect(
            within(row).getByText('ENEL\\s+ENERGIA.*(BOLL|FATT)').tagName,
        ).toBe('CODE');
        expect(
            within(row).getByText(
                'It comes from the rule of the planned expense Luce: it changes only as text.',
            ),
        ).toBeInTheDocument();
    });

    it('Edit and Delete are in the row menu on the phone', async () => {
        screenWidth(false);
        renderPage(BankFiltersList, { filters: [satispay] });
        const row = rowOf('Satispay');

        expect(
            within(row).queryByRole('link', { name: 'Edit' }),
        ).not.toBeInTheDocument();
        await userEvent.click(
            within(row).getByRole('button', { name: 'More for Satispay' }),
        );

        expect(screen.getByRole('menuitem', { name: 'Edit' })).toHaveAttribute(
            'href',
            '/budget/bank/filters/1/edit',
        );
        expect(
            screen.getByRole('menuitem', { name: 'Delete' }),
        ).toBeInTheDocument();
    });

    it('has Edit and Delete in the row on the computer', () => {
        screenWidth(true);
        renderPage(BankFiltersList, { filters: [satispay] });
        const row = rowOf('Satispay');

        expect(within(row).getByRole('link', { name: 'Edit' })).toHaveAttribute(
            'href',
            '/budget/bank/filters/1/edit',
        );
        expect(
            within(row).getByRole('button', { name: 'Delete' }),
        ).toBeInTheDocument();
        expect(row).toHaveTextContent('2 movements');
    });

    it('Delete asks before', async () => {
        screenWidth(true);
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(BankFiltersList, { filters: [satispay] });

        await userEvent.click(
            within(rowOf('Satispay')).getByRole('button', { name: 'Delete' }),
        );

        expect(destroy).not.toHaveBeenCalled();
        expect(
            await screen.findByRole('heading', {
                name: 'Delete the filter Satispay?',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('What it sorted stays as it is.'),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Delete the filter' }),
        );

        expect(destroy).toHaveBeenCalledWith(
            '/budget/bank/filters/1',
            expect.anything(),
        );
    });

    it('a filter with something missing says what', () => {
        renderPage(BankFiltersList, { filters: [luce] });

        expect(
            within(rowOf('Luce')).getByText(
                'The planned expense ended on 05/06/2026.',
            ),
        ).toBeInTheDocument();
    });

    it('leaves out what who looks cannot do', () => {
        screenWidth(true);
        renderPage(BankFiltersList, {
            filters: [{ ...satispay, can: { update: false, delete: false } }],
        });
        const row = rowOf('Satispay');

        expect(
            within(row).queryByRole('link', { name: 'Edit' }),
        ).not.toBeInTheDocument();
        expect(
            within(row).queryByRole('button', { name: 'Delete' }),
        ).not.toBeInTheDocument();
    });
});
