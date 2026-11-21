import { router } from '@inertiajs/vue3';
import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { octoberComparison } from '@/test/fixtures';
import { renderPage, screenWidth, withTrail } from '@/test/page';
import Comparison from './Comparison.vue';

const props = { comparison: octoberComparison };

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('Comparison', () => {
    it('shows the planned, the spent and the incomes of the month', () => {
        renderPage(Comparison, props);

        expect(screen.getByText('October 2026')).toBeInTheDocument();
        const planned = screen.getAllByText('Planned')[0]!.parentElement;
        expect(planned).toHaveTextContent('1.469,30 €');
        expect(planned).toHaveTextContent('going out in October');
        const spent = screen.getByText('Spent so far').parentElement;
        expect(spent).toHaveTextContent('1.284,20 €');
        expect(spent).toHaveTextContent('185,10 € less');
    });

    it('writes the spent in red when it is over the planned', () => {
        renderPage(Comparison, {
            comparison: { ...octoberComparison, spent: 1622.6 },
        });

        expect(screen.getByText('1.622,60 €')).toHaveClass('text-overdue');
    });

    it('writes the spent in the usual colour within the planned', () => {
        renderPage(Comparison, props);

        const spent = screen.getByText('Spent so far').parentElement!;
        expect(within(spent).getByText('1.284,20 €')).not.toHaveClass(
            'text-overdue',
        );
    });

    it('shows the incomes against the planned ones on the computer', () => {
        screenWidth(true);
        renderPage(Comparison, props);

        expect(screen.getByText('Income')).toBeInTheDocument();
        expect(screen.getByText('1.720,00 €')).toBeInTheDocument();
        expect(screen.getByText('of 3.570,00 € planned')).toBeInTheDocument();
    });

    it('does not show the incomes tile on a wide but short window', () => {
        screenWidth(1280, 400);
        renderPage(Comparison, props);

        expect(screen.queryByText('Income')).not.toBeInTheDocument();
    });

    it('says how many days the month in progress has left', () => {
        renderPage(Comparison, props);

        expect(
            screen.getByText('The month is in progress: 5 days left.'),
        ).toBeInTheDocument();
    });

    it('says nothing of the days in a month that is over', () => {
        renderPage(Comparison, {
            comparison: { ...octoberComparison, days_left: null },
        });

        expect(
            screen.queryByText(/The month is in progress/),
        ).not.toBeInTheDocument();
    });

    it('shows a row per category with its comment', () => {
        renderPage(Comparison, props);

        const section = screen.getByRole('region', { name: 'By category' });
        expect(
            within(section).getByText('Bollette e utenze'),
        ).toBeInTheDocument();
        expect(within(section).getByText('29,90 €')).toBeInTheDocument();
        expect(within(section).getByText('of 223,30 €')).toBeInTheDocument();
        expect(
            within(section).getByText('193,40 € less · luce late'),
        ).toBeInTheDocument();
        expect(
            within(section).getByText('108,40 € more · not planned'),
        ).toBeInTheDocument();
        expect(
            within(section).getByText(
                '169,90 € less · planned amount estimated',
            ),
        ).toBeInTheDocument();
    });

    it('draws the last six months', () => {
        renderPage(Comparison, props);

        const section = screen.getByRole('region', {
            name: 'Expenses, the last 6 months',
        });
        expect(within(section).getByRole('img')).toBeInTheDocument();
        expect(
            within(section).getByRole('button', { name: 'See as a table' }),
        ).toBeInTheDocument();
    });

    it('lists the due dates nobody has registered on the computer', () => {
        screenWidth(true);
        renderPage(Comparison, props);

        const section = screen.getByRole('region', {
            name: 'Planned and not yet registered',
        });
        expect(
            within(section).getByText('Bolletta luce di settembre'),
        ).toBeInTheDocument();
        expect(within(section).getByText('Bolletta gas')).toBeInTheDocument();
        expect(
            within(section).getByRole('link', {
                name: /Bolletta luce di settembre/,
            }),
        ).toHaveAttribute(
            'href',
            '/budget/planned/expense/7/occurrences/2026-10-23',
        );
    });

    it('does not list the due dates on the phone', () => {
        renderPage(Comparison, props);

        expect(
            screen.queryByRole('region', {
                name: 'Planned and not yet registered',
            }),
        ).not.toBeInTheDocument();
    });

    it('shows only two numbers on the phone', () => {
        renderPage(Comparison, props);

        expect(screen.queryByText('Income')).not.toBeInTheDocument();
        expect(screen.queryByText('1.720,00 €')).not.toBeInTheDocument();
        expect(
            screen.queryByText(/3\.570,00 € planned/),
        ).not.toBeInTheDocument();
    });

    it('goes to another month', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Comparison, props);

        await userEvent.click(
            screen.getByRole('button', { name: 'Previous month' }),
        );

        expect(get).toHaveBeenCalledWith(
            '/budget/comparison?month=2026-09',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('is a page under the Budget: its own title, the trail and no tabs', () => {
        const { state, provide } = withTrail();
        renderPage(Comparison, props, provide);

        expect(state.pieces.value).toEqual([
            { label: 'Budget', href: '/budget' },
            { label: 'Planned and spent' },
        ]);
        expect(
            screen.getByRole('heading', {
                level: 1,
                name: 'Planned and spent',
            }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Movements' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('navigation', { name: 'Budget' }),
        ).not.toBeInTheDocument();
    });

    it('wide: the categories on the left, the chart, the unregistered and the note on the right', () => {
        screenWidth(true);
        renderPage(Comparison, props);

        const categories = screen.getByRole('region', { name: 'By category' });
        const chart = screen.getByRole('region', {
            name: 'Expenses, the last 6 months',
        });
        const columns = categories.parentElement!;
        expect(chart.parentElement).not.toBe(columns);
        expect(
            chart.parentElement!.contains(
                screen.getByRole('region', {
                    name: 'Planned and not yet registered',
                }),
            ),
        ).toBe(true);
        expect(
            chart.parentElement!.contains(
                screen.getByText(/The plan comes from the planned expenses/),
            ),
        ).toBe(true);
    });

    it('on the phone: one column, the chart first', () => {
        renderPage(Comparison, props);

        const categories = screen.getByRole('region', { name: 'By category' });
        const chart = screen.getByRole('region', {
            name: 'Expenses, the last 6 months',
        });
        expect(chart.parentElement).toBe(categories.parentElement);
        expect(
            chart.compareDocumentPosition(categories) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
    });
});
