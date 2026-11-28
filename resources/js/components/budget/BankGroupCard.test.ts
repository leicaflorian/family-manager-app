import userEvent from '@testing-library/user-event';
import { screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { bankGroups } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import BankGroupCard from './BankGroupCard.vue';

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({ url: '/budget/bank', props: { name: 'Fameja.app' } }),
}));

afterEach(() => vi.restoreAllMocks());

const [satispay, inps, , cashback, virgin] = bankGroups;

describe('BankGroupCard', () => {
    it('shows the name, how many movements, the amounts and the months', () => {
        renderPage(BankGroupCard, { group: satispay });

        expect(screen.getByText('Satispay Europe')).toBeInTheDocument();
        expect(
            screen.getByText('11 movements · from 10,00 € to 50,00 €'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('from September to November'),
        ).toBeInTheDocument();
    });

    it('writes the incomes and one amount for all of them', () => {
        renderPage(BankGroupCard, { group: inps });

        expect(
            screen.getByText('3 incomes · 407,60 € each'),
        ).toBeInTheDocument();
    });

    it('says the proposal or «Nessuna spesa simile trovata»', () => {
        const view = renderPage(BankGroupCard, { group: satispay });
        expect(
            screen.getByText(
                'Proposal: create the expense «Ricarica Satispay» · Varie · Satispay',
            ),
        ).toBeInTheDocument();
        view.unmount();

        const planned = renderPage(BankGroupCard, { group: inps });
        expect(
            screen.getByText(
                'Proposal: mark the planned income «Assegno Unico» as received',
            ),
        ).toBeInTheDocument();
        planned.unmount();

        renderPage(BankGroupCard, { group: cashback });
        expect(
            screen.getByText(
                'No similar expense found. When you create the filter, the AI suggests title and category.',
            ),
        ).toBeInTheDocument();
    });

    it('Create the filter leads to the new filter of the group', () => {
        renderPage(BankGroupCard, { group: satispay });

        expect(
            screen.getByRole('link', { name: 'Create the filter' }),
        ).toHaveAttribute(
            'href',
            '/budget/bank/filters/create?group=3f9a1c2b7d4e',
        );
    });

    it('shows It comes back every month only with its address', () => {
        const view = renderPage(BankGroupCard, { group: virgin });
        expect(
            screen.getByRole('link', {
                name: 'It comes back every month: create a planned expense?',
            }),
        ).toHaveAttribute('href', '/budget/planned/expense/create?bank=71');
        view.unmount();

        renderPage(BankGroupCard, { group: satispay });
        expect(
            screen.queryByRole('link', { name: /It comes back every month/ }),
        ).toBeNull();
    });

    it('opens the group from its head', async () => {
        const view = renderPage(BankGroupCard, { group: satispay });

        await userEvent.click(
            screen.getByRole('button', { name: /Satispay Europe/ }),
        );

        expect(view.emitted('open')).toHaveLength(1);
    });

    it('as a row of the list beside the open group, says the proposal in short and marks the one open', async () => {
        const view = renderPage(BankGroupCard, {
            group: satispay,
            variant: 'row',
            selected: true,
        });

        const head = screen.getByRole('button', { name: /Satispay Europe/ });
        expect(head).toHaveAttribute('aria-current', 'true');
        expect(
            screen.getByText(
                'create the expense «Ricarica Satispay» · Varie · Satispay',
            ),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Create the filter' }),
        ).toBeNull();
        await userEvent.click(head);
        expect(view.emitted('open')).toHaveLength(1);
        view.unmount();

        renderPage(BankGroupCard, { group: cashback, variant: 'row' });
        expect(
            screen.getByText('No similar expense found'),
        ).toBeInTheDocument();
    });
});
