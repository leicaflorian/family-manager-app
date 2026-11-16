import { router } from '@inertiajs/vue3';
import { fireEvent, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    danceMovement,
    movementOptions,
    octoberSummary,
    salaryMovement,
} from '@/test/fixtures';
import { answerLeave, leaveQuestion } from '@/lib/unsavedChanges';
import { choose, renderPage, screenWidth, settle } from '@/test/page';
import Movements from './Movements.vue';

// The shared props of the page (the tools beside the title read the notices)
vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    usePage: () => ({
        url: '/',
        props: { name: 'Fameja.app', unread_notices: 2 },
    }),
}));

const parking: App.Data.MovementData = {
    ...danceMovement,
    id: 40,
    title: 'Parcheggio',
    date: '2026-10-24',
    amount: 12,
    organization: 'Tper',
    account: 'Conto BPER',
    account_id: 1,
    planned_id: null,
    occurrence_date: null,
};

/** The filters as the server sends them when none is on. */
const noFilters: App.Data.MovementFiltersData = {
    search: null,
    kind: null,
    account: null,
    min: null,
    max: null,
    from: null,
    to: null,
};

const props = {
    summary: octoberSummary,
    movements: [parking, salaryMovement, danceMovement],
    kind: null,
    account: null,
    filters: noFilters,
    search: null,
    selected: null,
    occurrences: [],
    options: movementOptions,
};

/**
 * Groceries found by a search, as the server sends them.
 *
 * @param id - the id of the expense
 * @param date - the day, `yyyy-mm-dd`
 * @param amount - the amount
 * @returns the movement
 */
const groceries = (
    id: number,
    date: string,
    amount: number,
): App.Data.MovementData => ({
    ...parking,
    id,
    title: 'Spesa',
    date,
    amount,
    organization: 'Coop',
});

/** Every filter on: expenses of Conto BPER with «spesa», 20 to 100 €, September and October. */
const allFilters: App.Data.MovementFiltersData = {
    search: 'spesa',
    kind: 'expense',
    account: 1,
    min: 20,
    max: 100,
    from: '2026-09-01',
    to: '2026-10-31',
};

/** The page in search mode, with every filter on. */
const searching = {
    ...props,
    kind: 'expense',
    account: 1,
    filters: allFilters,
    search: {
        months: [
            {
                month: '2026-10',
                total: 150.1,
                movements: [
                    groceries(51, '2026-10-18', 39.15),
                    groceries(52, '2026-10-11', 48.6),
                    groceries(53, '2026-10-04', 62.35),
                ],
            },
            {
                month: '2026-09',
                total: 160,
                movements: [
                    groceries(54, '2026-09-27', 54.3),
                    groceries(55, '2026-09-20', 71.8),
                    groceries(56, '2026-09-13', 33.9),
                ],
            },
        ],
        count: 6,
        total: 310.1,
    },
};

/** The address of the page with every filter on. */
const allFiltersQuery =
    'month=2026-10&kind=expense&account=1&search=spesa&min=20&max=100&from=2026-09-01&to=2026-10-31';

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('Movements', () => {
    it('has the magnifier and the bell beside the title on the phone only', () => {
        screenWidth(390);
        const phone = renderPage(Movements, props);

        expect(
            screen.getByRole('link', { name: 'Search in Fameja.app' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /Notices/ }),
        ).toBeInTheDocument();
        phone.unmount();

        screenWidth(1280);
        renderPage(Movements, props);

        expect(
            screen.queryByRole('link', { name: 'Search in Fameja.app' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /Notices/ }),
        ).not.toBeInTheDocument();
    });

    it('sums up the month: what came in, what went out and what is left', () => {
        renderPage(Movements, props);

        expect(screen.getByText('October 2026')).toBeInTheDocument();
        expect(screen.getByText('1.720,00 €')).toBeInTheDocument();
        expect(screen.getByText('1 salary out of 2')).toBeInTheDocument();
        expect(screen.getByText('1.284,20 €')).toBeInTheDocument();
        expect(screen.getByText('15 payments')).toBeInTheDocument();
        expect(screen.getByText('+435,80 €')).toBeInTheDocument();
        expect(screen.getByText('incomes minus expenses')).toBeInTheDocument();
    });

    it('lists the movements with the day, who it is with and the amount', () => {
        renderPage(Movements, props);

        const parkingRow = screen.getByRole('link', { name: /Parcheggio/ });
        expect(within(parkingRow).getByText('24')).toBeInTheDocument();
        expect(
            within(parkingRow).getByText('Tper · Conto BPER'),
        ).toBeInTheDocument();
        expect(within(parkingRow).getByText('12,00 €')).toBeInTheDocument();
        // On the phone a row opens the page of the movement
        expect(parkingRow).toHaveAttribute(
            'href',
            '/budget/movements/expense/40',
        );
    });

    it('shows an income in green with a plus', () => {
        renderPage(Movements, props);

        const amount = screen.getByText('+1.720,00 €', {
            selector: 'span.text-paid',
        });
        expect(amount).toBeInTheDocument();
        expect(screen.getByText('45,00 €')).not.toHaveClass('text-paid');
    });

    it('says when the month has nothing', () => {
        renderPage(Movements, { ...props, movements: [] });

        expect(screen.getByText('No movements this month')).toBeInTheDocument();
    });

    it('goes to the month before and after with the arrows, keeping the filters', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Movements, {
            ...props,
            kind: 'expense',
            filters: { ...noFilters, kind: 'expense' },
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Previous month' }),
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Next month' }),
        );

        expect(get.mock.calls[0]![0]).toBe(
            '/budget/movements?month=2026-09&kind=expense',
        );
        expect(get.mock.calls[1]![0]).toBe(
            '/budget/movements?month=2026-11&kind=expense',
        );
    });

    it('filters by kind and by account', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Movements, props);

        await userEvent.click(screen.getByRole('button', { name: 'Income' }));
        expect(get.mock.calls[0]![0]).toBe(
            '/budget/movements?month=2026-10&kind=income',
        );

        await choose('Bank account', 'Carta Laura');
        expect(get.mock.calls[1]![0]).toBe(
            '/budget/movements?month=2026-10&account=2',
        );
    });

    it('marks the filter in use', () => {
        renderPage(Movements, { ...props, kind: 'income' });

        expect(screen.getByRole('button', { name: 'Income' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        expect(
            screen.getByRole('button', { name: 'Everything' }),
        ).toHaveAttribute('aria-pressed', 'false');
    });

    it('opens a row on its own page on a wide but short window', () => {
        screenWidth(1280, 400);
        renderPage(Movements, props);

        expect(
            screen.getByRole('link', { name: /Parcheggio/ }),
        ).toHaveAttribute('href', '/budget/movements/expense/40');
    });

    it('opens a row beside the list on the computer', () => {
        screenWidth(true);
        renderPage(Movements, props);

        expect(
            screen.getByRole('link', { name: /Parcheggio/ }),
        ).toHaveAttribute(
            'href',
            '/budget/movements?month=2026-10&movement=expense%3A40',
        );
    });

    it('on the computer opens a row beside the list without scrolling the list back to the top', async () => {
        screenWidth(true);
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(Movements, props);

        await fireEvent.click(screen.getByRole('link', { name: /Parcheggio/ }));

        expect(visit).toHaveBeenCalledWith(
            expect.stringContaining('movement=expense%3A40'),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('keeps the panel of the movement in view while the list scrolls', () => {
        screenWidth(true);
        renderPage(Movements, { ...props, selected: danceMovement });

        // sticky under the top bar of the layout, not under the top of the window
        expect(screen.getByLabelText('Title').closest('aside')).toHaveClass(
            'sticky',
            'top-(--app-header)',
        );
    });

    it('shows the panel of the movement chosen', () => {
        screenWidth(true);
        renderPage(Movements, { ...props, selected: danceMovement });

        expect(screen.getByLabelText('Title')).toHaveValue('Retta di novembre');
        expect(
            screen.getByRole('button', { name: 'Delete' }),
        ).toBeInTheDocument();
    });

    it('closes the panel with the cross', async () => {
        screenWidth(true);
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Movements, { ...props, selected: danceMovement });

        await fireEvent.click(screen.getByRole('button', { name: 'Close' }));

        expect(get.mock.calls[0]![0]).toBe('/budget/movements?month=2026-10');
    });

    it('opens the panel of a new expense or income on the computer', async () => {
        screenWidth(true);
        renderPage(Movements, props);
        expect(screen.queryByLabelText('Title')).not.toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'New expense or income' }),
        );

        expect(screen.getByLabelText('Title')).toHaveValue('');
        await settle();
    });

    it('goes to the page of a new expense or income on the phone', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Movements, props);

        await userEvent.click(
            screen.getByRole('button', { name: 'New expense or income' }),
        );

        expect(get.mock.calls[0]![0]).toBe('/budget/movements/create');
    });

    it('has the tabs of the budget with this page marked', () => {
        renderPage(Movements, props);

        expect(screen.getByRole('link', { name: 'Movements' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(
            screen.getByRole('link', { name: 'Planned and spent' }),
        ).toHaveAttribute('href', '/budget/comparison');
    });

    it('on the computer has the filters in a column, and no kind or account beside the month', () => {
        screenWidth(true);
        renderPage(Movements, props);

        const column = screen.getByRole('complementary', { name: 'Filters' });
        expect(
            within(column).getByRole('button', { name: 'Expenses' }),
        ).toBeInTheDocument();
        expect(
            screen.getAllByRole('button', { name: 'Expenses' }),
        ).toHaveLength(1);
        expect(
            screen.getAllByRole('button', { name: /Bank account/ }),
        ).toHaveLength(1);
        expect(within(column).getByLabelText('At least')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /^Filters/ }),
        ).not.toBeInTheDocument();
    });

    it('on the phone opens the filters in a panel and keeps the kind on the page', async () => {
        renderPage(Movements, { ...props, filters: allFilters });

        expect(
            screen.queryByRole('complementary', { name: 'Filters' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Income' }),
        ).toBeInTheDocument();

        // the kind, the account, the amounts and the days: four on
        await userEvent.click(
            screen.getByRole('button', { name: 'Filters, 4 on' }),
        );

        const sheet = await screen.findByRole('dialog', { name: 'Filters' });
        expect(within(sheet).getByLabelText('At least')).toBeInTheDocument();
        expect(within(sheet).getByLabelText('From')).toHaveValue('01/09/2026');
        expect(
            within(sheet).getByRole('button', { name: 'Remove the filters' }),
        ).toBeInTheDocument();
    });

    it('in search mode shows how many were found, by month, instead of the month and its figures', () => {
        renderPage(Movements, searching);

        expect(
            screen.queryByRole('button', { name: 'Previous month' }),
        ).not.toBeInTheDocument();
        expect(screen.queryByText('Money in')).not.toBeInTheDocument();
        expect(
            screen.getByText('6 movements found, 310,10 € in all'),
        ).toBeInTheDocument();
        expect(screen.getByText('October 2026')).toBeInTheDocument();
        expect(screen.getByText('150,10 €')).toBeInTheDocument();
        expect(screen.getByText('September 2026')).toBeInTheDocument();
        expect(screen.getByText('160,00 €')).toBeInTheDocument();
        expect(screen.getAllByRole('link', { name: /Spesa/ })).toHaveLength(6);
    });

    it('shows a pill for each filter on, whose × removes only that filter', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Movements, searching);

        for (const label of [
            'Expenses',
            'Conto BPER',
            'From 20,00 € to 100,00 €',
            'From 01/09/2026 to 31/10/2026',
            '“spesa”',
        ]) {
            expect(
                screen.getByRole('button', {
                    name: `Remove the filter ${label}`,
                }),
            ).toBeInTheDocument();
        }

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Remove the filter From 20,00 € to 100,00 €',
            }),
        );
        expect(get.mock.calls[0]![0]).toBe(
            '/budget/movements?month=2026-10&kind=expense&account=1&search=spesa&from=2026-09-01&to=2026-10-31',
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove the filter “spesa”' }),
        );
        expect(get.mock.calls[1]![0]).toBe(
            '/budget/movements?month=2026-10&kind=expense&account=1&min=20&max=100&from=2026-09-01&to=2026-10-31',
        );
    });

    it('removes every filter at once', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Movements, searching);

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove the filters' }),
        );

        expect(get.mock.calls[0]![0]).toBe('/budget/movements?month=2026-10');
    });

    it('says when nothing passes the filters', () => {
        renderPage(Movements, {
            ...searching,
            search: { months: [], count: 0, total: 0 },
        });

        expect(
            screen.getByText('No movement matches the filters'),
        ).toBeInTheDocument();
        expect(screen.queryByText(/found/)).not.toBeInTheDocument();
    });

    it('says when the list shows only the most recent of what was found', () => {
        const cut = renderPage(Movements, {
            ...searching,
            search: { ...searching.search, count: 206, total: 9120.5 },
        });

        expect(
            screen.getByText(
                'Only the 6 most recent are shown: narrow the search to see the others.',
            ),
        ).toBeInTheDocument();

        cut.unmount();
        renderPage(Movements, searching);
        expect(
            screen.queryByText(/most recent are shown/),
        ).not.toBeInTheDocument();
    });

    it('announces how many were found', () => {
        renderPage(Movements, searching);

        expect(
            screen.getByText('6 movements found, 310,10 € in all'),
        ).toHaveAttribute('aria-live', 'polite');
    });

    it('names the column of the filters with a heading', () => {
        screenWidth(true);
        renderPage(Movements, props);

        expect(
            screen.getByRole('heading', { level: 2, name: 'Filter' }),
        ).toBeInTheDocument();
    });

    it('says on the phone whether the panel of the filters is open', async () => {
        screenWidth(390);
        renderPage(Movements, props);
        const button = screen.getByRole('button', { name: 'Filters' });
        expect(button).toHaveAttribute('aria-expanded', 'false');

        await userEvent.click(button);

        expect(button).toHaveAttribute('aria-expanded', 'true');
    });

    for (const [action, expected] of [
        [
            'sends a search',
            '/budget/movements?month=2026-10&kind=expense&search=retta&min=20',
        ],
        ['removes a filter', '/budget/movements?month=2026-10&kind=expense'],
        ['removes every filter', '/budget/movements?month=2026-10'],
    ] as const) {
        it(`asks before it ${action} while the movement open in the column has changes`, async () => {
            screenWidth(true);
            const get = vi.spyOn(router, 'get').mockImplementation(() => {});
            renderPage(Movements, {
                ...props,
                kind: 'expense',
                filters: { ...noFilters, kind: 'expense', min: 20 },
                selected: danceMovement,
            });
            await fireEvent.update(screen.getByLabelText('Title'), 'Retta');
            await settle();

            if (action === 'sends a search') {
                await userEvent.type(
                    screen.getByRole('searchbox', {
                        name: 'Search a movement',
                    }),
                    'retta{Enter}',
                );
            } else if (action === 'removes a filter') {
                await userEvent.click(
                    screen.getByRole('button', {
                        name: 'Remove the filter At least 20,00 €',
                    }),
                );
            } else {
                await userEvent.click(
                    screen.getByRole('button', { name: 'Remove the filters' }),
                );
            }

            expect(leaveQuestion.open).toBe(true);
            answerLeave(false);
            await settle();
            expect(get).not.toHaveBeenCalled();
            expect(screen.getByLabelText('Title')).toHaveValue('Retta');

            // asked again, this time the person leaves without saving
            if (action === 'sends a search') {
                await userEvent.type(
                    screen.getByRole('searchbox', {
                        name: 'Search a movement',
                    }),
                    '{Enter}',
                );
            } else if (action === 'removes a filter') {
                await userEvent.click(
                    screen.getByRole('button', {
                        name: 'Remove the filter At least 20,00 €',
                    }),
                );
            } else {
                await userEvent.click(
                    screen.getByRole('button', { name: 'Remove the filters' }),
                );
            }
            answerLeave(true);
            await settle();
            expect(get).toHaveBeenCalledTimes(1);
            expect(get.mock.calls[0]![0]).toBe(expected);
        });
    }

    it('closes a new expense or income with changes only after asking, when the filters change', async () => {
        screenWidth(true);
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Movements, {
            ...props,
            filters: { ...noFilters, min: 20 },
        });
        await userEvent.click(
            screen.getByRole('button', { name: 'New expense or income' }),
        );
        await fireEvent.update(screen.getByLabelText('Title'), 'Pizza');
        await settle();

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove the filters' }),
        );
        expect(leaveQuestion.open).toBe(true);
        answerLeave(true);
        await settle();

        expect(get.mock.calls[0]![0]).toBe('/budget/movements?month=2026-10');
        expect(
            screen.getByRole('complementary', { name: 'Filters' }),
        ).toBeInTheDocument();
    });

    it('changes the filters at once when the movement open in the column has no changes', async () => {
        screenWidth(true);
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Movements, {
            ...props,
            filters: { ...noFilters, min: 20 },
            selected: danceMovement,
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove the filters' }),
        );

        expect(leaveQuestion.open).toBe(false);
        expect(get.mock.calls[0]![0]).toBe('/budget/movements?month=2026-10');
    });

    it('sends the search with Enter', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Movements, props);

        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search a movement' }),
            '  spesa {Enter}',
        );

        expect(get).toHaveBeenCalledWith(
            '/budget/movements?month=2026-10&search=spesa',
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    });

    it('changing a filter in the column visits the page with all the filters', async () => {
        screenWidth(true);
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Movements, searching);

        const column = screen.getByRole('complementary', { name: 'Filters' });
        await userEvent.click(
            within(column).getByRole('button', { name: 'Income' }),
        );
        await settle();

        expect(get).toHaveBeenLastCalledWith(
            `/budget/movements?${allFiltersQuery.replace('kind=expense', 'kind=income')}`,
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    });

    it('opens a row keeping the filters, and marks it', () => {
        screenWidth(true);
        renderPage(Movements, {
            ...searching,
            selected: groceries(52, '2026-10-11', 48.6),
        });

        const links = screen.getAllByRole('link', { name: /Spesa/ });
        expect(links[0]).toHaveAttribute(
            'href',
            `/budget/movements?${allFiltersQuery}&movement=expense%3A51`,
        );
        expect(links[1]).toHaveAttribute('aria-current', 'page');
        expect(links[0]).not.toHaveAttribute('aria-current');
    });

    it('on the computer shows the movement in place of the filters, and goes back to them keeping the filters', async () => {
        screenWidth(true);
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Movements, {
            ...searching,
            selected: groceries(52, '2026-10-11', 48.6),
        });

        expect(
            screen.queryByRole('complementary', { name: 'Filters' }),
        ).not.toBeInTheDocument();
        const detail = screen.getByRole('complementary', { name: 'Spesa' });
        expect(within(detail).getByLabelText('Title')).toHaveValue('Spesa');
        // one × only: the one of the column
        expect(screen.getAllByRole('button', { name: 'Close' })).toHaveLength(
            1,
        );

        await fireEvent.click(
            within(detail).getByRole('button', { name: 'Back to the filters' }),
        );

        expect(get.mock.calls[0]![0]).toBe(
            `/budget/movements?${allFiltersQuery}`,
        );
    });

    it('asks before going back to the filters when the movement has changes', async () => {
        screenWidth(true);
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Movements, { ...props, selected: danceMovement });
        await fireEvent.update(screen.getByLabelText('Title'), 'Retta');
        await settle();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Back to the filters' }),
        );

        expect(leaveQuestion.open).toBe(true);
        answerLeave(false);
        await settle();
        expect(get).not.toHaveBeenCalled();
    });

    it('opens a new expense or income in place of the filters', async () => {
        screenWidth(true);
        renderPage(Movements, props);

        await userEvent.click(
            screen.getByRole('button', { name: 'New expense or income' }),
        );

        const detail = screen.getByRole('complementary', {
            name: 'New expense or income',
        });
        expect(within(detail).getByLabelText('Title')).toHaveValue('');

        await fireEvent.click(
            within(detail).getByRole('button', { name: 'Back to the filters' }),
        );
        await settle();
        expect(
            screen.getByRole('complementary', { name: 'Filters' }),
        ).toBeInTheDocument();
    });
});
