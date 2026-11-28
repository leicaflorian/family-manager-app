import userEvent from '@testing-library/user-event';
import { screen, within } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { bankGroupRows, bankGroups } from '@/test/fixtures';
import { renderPage, screenWidth } from '@/test/page';
import BankGroupPanel from './BankGroupPanel.vue';

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({ url: '/budget/bank', props: { name: 'Fameja.app' } }),
}));

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

const group = bankGroups[0];

describe('BankGroupPanel', () => {
    it('lists the movements with their fee and what would happen to each', async () => {
        screenWidth(true);
        renderPage(BankGroupPanel, { group, rows: bankGroupRows });

        const panel = screen.getByRole('complementary', {
            name: 'Satispay Europe',
        });
        expect(
            within(panel).getByText('Conto BancoPosta ·· 3307 · money out'),
        ).toBeInTheDocument();
        expect(
            within(panel).getByText(
                '11 movements · from 10,00 € to 50,00 € · between 02/09 and 24/11',
            ),
        ).toBeInTheDocument();
        expect(
            within(panel).getByText(
                'Saving the filter: 10 new expenses, each with its commission of 0,40 €, and 1 already recorded to link. No duplicates.',
            ),
        ).toBeInTheDocument();
        expect(
            within(panel).getByRole('link', { name: 'Create the filter' }),
        ).toHaveAttribute(
            'href',
            '/budget/bank/filters/create?group=3f9a1c2b7d4e',
        );

        const list = within(panel).getByRole('list', {
            name: 'The movements of the group',
        });
        const items = within(list).getAllByRole('listitem');
        expect(items).toHaveLength(6);
        expect(within(items[0]).getByText('24/11')).toBeInTheDocument();
        expect(
            within(items[0]).getByText('+ 0,40 € commission, 25/11'),
        ).toBeInTheDocument();
        expect(within(items[0]).getByText('-10,00 €')).toBeInTheDocument();
        expect(within(items[0]).getByText('+ 0,40 €')).toBeInTheDocument();
        expect(
            within(items[0]).getByText(
                'Already recorded: link «Ricarica Satispay» of 24/11',
            ),
        ).toHaveClass('bg-paid-soft', 'text-paid');
        expect(within(items[1]).getByText('Create the expense')).toHaveClass(
            'bg-card-sunken',
            'text-ink-soft',
        );

        await userEvent.click(
            within(panel).getByRole('button', {
                name: 'Show 5 more movements, from 02/09',
            }),
        );
        expect(within(list).getAllByRole('listitem')).toHaveLength(11);
    });

    it('says the commission without its amount when the amounts differ, and not at all without one', () => {
        screenWidth(true);
        const mixed = bankGroupRows.map((row, index) =>
            index === 2 && row.fee
                ? { ...row, fee: { ...row.fee, amount: -0.5 } }
                : row,
        );
        const view = renderPage(BankGroupPanel, { group, rows: mixed });
        expect(
            screen.getByText(
                'Saving the filter: 10 new expenses, each with its commission, and 1 already recorded to link. No duplicates.',
            ),
        ).toBeInTheDocument();
        view.unmount();

        renderPage(BankGroupPanel, {
            group,
            rows: bankGroupRows.map((row) => ({ ...row, fee: null })),
        });
        expect(
            screen.getByText(
                'Saving the filter: 10 new expenses and 1 already recorded to link. No duplicates.',
            ),
        ).toBeInTheDocument();
    });

    it('waits for the movements while they are asked', () => {
        screenWidth(true);
        renderPage(BankGroupPanel, { group, rows: null });

        expect(
            screen.getByRole('list', { name: 'The movements of the group' }),
        ).toHaveAttribute('aria-busy', 'true');
    });

    it('each has It is not of this group', async () => {
        screenWidth(true);
        const view = renderPage(BankGroupPanel, { group, rows: bankGroupRows });

        const buttons = screen.getAllByRole('button', {
            name: 'It is not of this group',
        });
        expect(buttons).toHaveLength(6);
        // A button of a row, in the small text, as the board
        expect(buttons[0]).toHaveClass('text-body-sm');

        await userEvent.click(buttons[1]);
        expect(view.emitted('notOfGroup')).toEqual([
            [bankGroupRows[1].transaction],
        ]);
    });

    it('is a bottom sheet on the phone and a side panel when wide', async () => {
        screenWidth(false);
        const phone = renderPage(BankGroupPanel, {
            group,
            rows: bankGroupRows,
        });
        expect(phone.container.querySelector('[role="dialog"]')).toBeNull();
        expect(
            await screen.findByRole('dialog', { name: 'Satispay Europe' }),
        ).toBeInTheDocument();
        phone.unmount();

        screenWidth(true);
        const wide = renderPage(BankGroupPanel, { group, rows: bankGroupRows });
        expect(
            screen.getByRole('complementary', { name: 'Satispay Europe' }),
        ).toBeInTheDocument();
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Satispay Europe' }),
        ).toHaveClass('text-stat');
        await userEvent.click(screen.getByRole('button', { name: 'Close' }));
        expect(wide.emitted('close')).toHaveLength(1);
    });
});
