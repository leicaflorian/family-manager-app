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
import { choose, renderPage, settle } from '@/test/page';
import Movements from './Movements.vue';

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

const props = {
    summary: octoberSummary,
    movements: [parking, salaryMovement, danceMovement],
    kind: null,
    account: null,
    selected: null,
    occurrences: [],
    options: movementOptions,
};

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

/** Makes the page believe it is on a computer. */
function onComputer(): void {
    vi.stubGlobal('matchMedia', (query: string) => ({
        matches: query.includes('min-width'),
        media: query,
        addEventListener: () => {},
        removeEventListener: () => {},
        addListener: () => {},
        removeListener: () => {},
    }));
}

describe('Movements', () => {
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
        renderPage(Movements, { ...props, kind: 'expense' });

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

        await choose('Account', 'Carta Laura');
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

    it('opens a row beside the list on the computer', () => {
        onComputer();
        renderPage(Movements, props);

        expect(
            screen.getByRole('link', { name: /Parcheggio/ }),
        ).toHaveAttribute(
            'href',
            '/budget/movements?month=2026-10&movement=expense%3A40',
        );
    });

    it('shows the panel of the movement chosen', () => {
        onComputer();
        renderPage(Movements, { ...props, selected: danceMovement });

        expect(screen.getByLabelText('Title')).toHaveValue('Retta di novembre');
        expect(
            screen.getByRole('button', { name: 'Delete' }),
        ).toBeInTheDocument();
    });

    it('closes the panel with the cross', async () => {
        onComputer();
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Movements, { ...props, selected: danceMovement });

        await fireEvent.click(screen.getByRole('button', { name: 'Close' }));

        expect(get.mock.calls[0]![0]).toBe('/budget/movements?month=2026-10');
    });

    it('opens the panel of a new expense or income on the computer', async () => {
        onComputer();
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
});
