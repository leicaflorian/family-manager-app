import { router } from '@inertiajs/vue3';
import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { danceRow, gasRow, netflixRow, salaryRow } from '@/test/fixtures';
import { renderPage, screenWidth } from '@/test/page';
import Planned from './Planned.vue';

/** The filters as the server sends them when none is on. */
const noFilters: App.Data.PlannedFiltersData = {
    search: null,
    min: null,
    max: null,
    from: null,
    to: null,
};

const props = {
    expenses: [gasRow, netflixRow, danceRow],
    incomes: [salaryRow],
    ended: [],
    monthly_out: 1482.6,
    monthly_in: 3570,
    canEdit: true,
    filters: noFilters,
};

/** Every filter on: «e», 10 to 100 €, next due date in November. */
const allFilters: App.Data.PlannedFiltersData = {
    search: 'e',
    min: 10,
    max: 100,
    from: '2026-11-01',
    to: '2026-11-30',
};

/** The page with every filter on: two expenses pass, no income. */
const filtered = {
    ...props,
    expenses: [netflixRow, danceRow],
    incomes: [],
    filters: allFilters,
};

/** The address of the page with every filter on. */
const allFiltersQuery = 'search=e&min=10&max=100&from=2026-11-01&to=2026-11-30';

/** How the page visits itself with other filters. */
const visit = { preserveState: true, preserveScroll: true, replace: true };

// The due dates of the fixtures are in 2026: a date of this year has no year
beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-26T10:00:00+01:00'));
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('Planned expenses', () => {
    it('shows the monthly averages at the top', () => {
        renderPage(Planned, props);

        expect(
            screen.getByRole('heading', { name: 'Planned expenses', level: 1 }),
        ).toBeInTheDocument();
        expect(screen.getByText('Going out, on average')).toBeInTheDocument();
        expect(screen.getByText('1.482,60 €')).toBeInTheDocument();
        expect(
            screen.getByText('per month, 3 planned expenses'),
        ).toBeInTheDocument();
        expect(screen.getByText('Coming in')).toBeInTheDocument();
        expect(screen.getByText('3.570,00 €')).toBeInTheDocument();
        expect(
            screen.getByText('per month, 1 planned income'),
        ).toBeInTheDocument();
    });

    it('lists the two sections with their number and the row of Netflix', () => {
        renderPage(Planned, props);

        expect(
            screen.getByRole('heading', { name: 'Going out · 3' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Coming in · 1' }),
        ).toBeInTheDocument();

        const netflix = screen.getByRole('link', { name: /Netflix/ });
        expect(netflix).toHaveAttribute('href', '/budget/planned/expense/11');
        expect(netflix).toHaveTextContent('every month, on day 4');
        expect(netflix).toHaveTextContent('Laura');
        expect(netflix).toHaveTextContent('Next on 04/11');
        expect(netflix).toHaveTextContent('13,99 €');
        expect(
            screen.getByRole('link', { name: /Stipendio di Marco/ }),
        ).toHaveAttribute('href', '/budget/planned/income/2');
    });

    it('opens the new planned expense and the new planned income', () => {
        renderPage(Planned, props);

        expect(
            screen.getByRole('link', { name: 'New planned expense' }),
        ).toHaveAttribute('href', '/budget/planned/expense/create');
        expect(
            screen.getByRole('link', { name: 'New planned income' }),
        ).toHaveAttribute('href', '/budget/planned/income/create');
    });

    it('has no way to add for a member', () => {
        renderPage(Planned, { ...props, canEdit: false });

        expect(
            screen.queryByRole('link', { name: 'New planned expense' }),
        ).not.toBeInTheDocument();
    });

    it('keeps the ended ones folded', async () => {
        renderPage(Planned, {
            ...props,
            ended: [
                {
                    ...danceRow,
                    id: 8,
                    title: 'Retta nido',
                    ended: true,
                    next_date: null,
                    end_date: '2026-06-30',
                },
            ],
        });

        expect(screen.queryByText('Retta nido')).not.toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Ended (1)' }),
        );

        const link = screen.getByRole('link', { name: /Retta nido/ });
        expect(
            within(link).getByText(/until 30\/06\/2026/),
        ).toBeInTheDocument();
    });

    it('invites to add the first planned expense when there is none', () => {
        renderPage(Planned, {
            ...props,
            expenses: [],
            incomes: [],
            monthly_out: 0,
            monthly_in: 0,
        });

        expect(screen.getByText('No planned expenses yet')).toBeInTheDocument();
        expect(
            screen.queryByRole('heading', { name: /Going out/ }),
        ).not.toBeInTheDocument();
    });

    it('has the tabs of the budget with this page marked', () => {
        renderPage(Planned, props);

        expect(
            screen.getByRole('link', { name: 'Planned expenses' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            screen.getByRole('link', { name: 'Planned and spent' }),
        ).toHaveAttribute('href', '/budget/comparison');
    });

    it('on the computer has the filters in a column, always', () => {
        screenWidth(true);
        renderPage(Planned, props);

        const column = screen.getByRole('complementary', { name: 'Filters' });
        expect(within(column).getByLabelText('At least')).toBeInTheDocument();
        expect(
            within(column).getByRole('group', { name: 'Next due date' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /^Filters/ }),
        ).not.toBeInTheDocument();
    });

    it('on the phone opens the filters in a panel from the button with the count', async () => {
        renderPage(Planned, filtered);

        expect(
            screen.queryByRole('complementary', { name: 'Filters' }),
        ).not.toBeInTheDocument();

        // the amounts and the days: the search is in the box
        await userEvent.click(
            screen.getByRole('button', { name: 'Filters, 2 on' }),
        );

        const sheet = await screen.findByRole('dialog', { name: 'Filters' });
        expect(within(sheet).getByLabelText('From')).toHaveValue('01/11/2026');
        expect(
            within(sheet).getByRole('button', {
                name: 'Show 2 planned expenses',
            }),
        ).toBeInTheDocument();
    });

    it('with filters shows only the rows that pass, without the averages and the empty section', () => {
        renderPage(Planned, filtered);

        expect(
            screen.queryByText('Going out, on average'),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Going out · 2' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('heading', { name: /Coming in/ }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText('2 planned expenses, by due date'),
        ).toBeInTheDocument();
        // a row still opens the page of the planned expense
        expect(screen.getByRole('link', { name: /Netflix/ })).toHaveAttribute(
            'href',
            '/budget/planned/expense/11',
        );
    });

    it('shows a pill for each filter on, whose × removes only that filter', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Planned, filtered);

        for (const label of [
            'From 10,00 € to 100,00 €',
            'Next from 01/11/2026 to 30/11/2026',
            '“e”',
        ]) {
            expect(
                screen.getByRole('button', {
                    name: `Remove the filter ${label}`,
                }),
            ).toBeInTheDocument();
        }

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Remove the filter Next from 01/11/2026 to 30/11/2026',
            }),
        );
        expect(get).toHaveBeenLastCalledWith(
            '/budget/planned?search=e&min=10&max=100',
            {},
            visit,
        );
    });

    it('removes every filter at once', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Planned, filtered);

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove the filters' }),
        );

        expect(get).toHaveBeenLastCalledWith('/budget/planned', {}, visit);
    });

    it('sends the search with Enter, keeping the other filters', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Planned, {
            ...filtered,
            filters: { ...allFilters, search: null },
        });

        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search a planned expense' }),
            '  e {Enter}',
        );

        expect(get).toHaveBeenLastCalledWith(
            `/budget/planned?${allFiltersQuery}`,
            {},
            visit,
        );
    });

    it('says when nothing passes the filters', () => {
        renderPage(Planned, {
            ...filtered,
            expenses: [],
        });

        expect(
            screen.getByText('No planned expense matches the filters'),
        ).toBeInTheDocument();
        expect(
            screen.queryByText('No planned expenses yet'),
        ).not.toBeInTheDocument();
    });

    it('names the column of the filters with a heading', () => {
        screenWidth(true);
        renderPage(Planned, props);

        expect(
            screen.getByRole('heading', { level: 2, name: 'Filter' }),
        ).toBeInTheDocument();
    });

    it('says on the phone whether the panel of the filters is open', async () => {
        renderPage(Planned, filtered);
        const button = screen.getByRole('button', { name: 'Filters, 2 on' });
        expect(button).toHaveAttribute('aria-expanded', 'false');

        await userEvent.click(button);

        expect(button).toHaveAttribute('aria-expanded', 'true');
    });
});
