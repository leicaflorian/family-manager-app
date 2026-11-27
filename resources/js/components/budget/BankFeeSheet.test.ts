import userEvent from '@testing-library/user-event';
import { screen, within } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { bankTransactions } from '@/test/fixtures';
import { renderPage, settle } from '@/test/page';
import BankFeeSheet from './BankFeeSheet.vue';

/** The commission left alone of the board *Dalla banca, è la commissione di*. */
const fee: App.Data.BankTransactionData = {
    ...bankTransactions[1]!,
    id: 14,
    booked_on: '2026-08-26',
    value_on: '2026-08-26',
    text: 'COMMISSIONI DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Satispay Europe S.A.',
    counterparty: null,
    amount: -0.4,
    orphan_fee: true,
};

const recorded: App.Data.BankRecordedChoiceData[] = [
    {
        kind: 'expense',
        id: 41,
        title: 'Ricarica Satispay',
        organization: 'Satispay',
        date: '2026-08-25',
        amount: 10,
    },
    {
        kind: 'expense',
        id: 42,
        title: 'Ricarica Satispay',
        organization: 'Satispay',
        date: '2026-07-28',
        amount: 20,
    },
];

/**
 * Finds `fetch` with a server that answers every search as it does for a commission.
 *
 * @param organization - the name that gave the first list
 */
function stubServer(
    organization: string | null = 'Satispay',
): ReturnType<typeof vi.fn> {
    const fetchMock = vi.fn(() =>
        Promise.resolve({
            ok: true,
            json: () =>
                Promise.resolve({ planned: [], recorded, organization }),
        }),
    );
    vi.stubGlobal('fetch', fetchMock);

    return fetchMock;
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('BankFeeSheet', () => {
    it('the sheet starts from the expenses of the same organization', async () => {
        const fetchMock = stubServer();
        renderPage(BankFeeSheet, { open: true, transaction: fee });
        await settle();

        const dialog = screen.getByRole('dialog', {
            name: 'It is the commission of…',
        });
        expect(
            await within(dialog).findByText('Expenses with Satispay'),
        ).toBeInTheDocument();
        expect(
            within(dialog).queryByText('Fixed expenses'),
        ).not.toBeInTheDocument();
        expect(String(fetchMock.mock.calls[0]![0])).toContain(
            `/budget/bank/${fee.id}/candidates?fee=1`,
        );
        expect(
            within(dialog).getByText(
                'I show you the expenses with the same organization as the commission. You can search by name, organization or amount.',
            ),
        ).toBeInTheDocument();
        expect(
            within(dialog)
                .getAllByRole('radio')
                .map((radio) => radio.closest('label')?.textContent),
        ).toHaveLength(2);
    });

    it('keeps asking for commissions when the person types', async () => {
        const fetchMock = stubServer();
        renderPage(BankFeeSheet, { open: true, transaction: fee });
        await settle();

        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search the expense' }),
            'ricarica',
        );
        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));

        const url = String(fetchMock.mock.calls[1]![0]);
        expect(url).toContain('fee=1');
        expect(url).toContain('q=ricarica');
    });

    it('the line says the new amount and the fee', async () => {
        stubServer();
        renderPage(BankFeeSheet, { open: true, transaction: fee });
        await settle();

        expect(
            await screen.findByText(
                'The expense becomes 10,40 €, of which 0,40 € of commission.',
            ),
        ).toBeInTheDocument();

        await userEvent.click(screen.getAllByRole('radio')[1]!);

        expect(
            screen.getByText(
                'The expense becomes 20,40 €, of which 0,40 € of commission.',
            ),
        ).toBeInTheDocument();
    });

    it('says the expense chosen', async () => {
        stubServer();
        const view = renderPage(BankFeeSheet, {
            open: true,
            transaction: fee,
        });
        await settle();

        await userEvent.click(
            await screen.findByRole('button', { name: 'Link the commission' }),
        );

        expect(view.emitted().choose).toEqual([[41]]);
    });
});
