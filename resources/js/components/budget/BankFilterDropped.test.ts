import { http } from '@inertiajs/vue3';
import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { bankFilterPreview } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import BankFilterDropped from './BankFilterDropped.vue';

type Client = ReturnType<typeof http.getClient>;
type Config = Parameters<Client['request']>[0];

const real = http.getClient();
const request = vi.fn<(config: Config) => Promise<unknown>>();

/** The car wash taken out of the group *Eni (POS)*. */
const wash: App.Data.BankTransactionData = {
    ...bankFilterPreview.rows[0]!.transaction,
    id: 23,
    text: 'PAGAMENTO POS ENI LAVAGGIO SELF MODENA',
    amount: -12,
    booked_on: '2026-11-15',
    value_on: '2026-11-15',
};

/** The accounts for *It is a transfer*. */
const accounts = [{ id: 6, name: 'Libretto postale', connected: false }];

beforeEach(() => {
    request.mockReset();
    request.mockResolvedValue({ status: 200, headers: {}, data: '{}' });
    http.setClient({ request } as unknown as Client);
});

afterEach(() => {
    http.setClient(real);
    vi.restoreAllMocks();
});

describe('BankFilterDropped', () => {
    it('says where it was taken out from, marks the word and asks what it is', () => {
        renderPage(BankFilterDropped, {
            transaction: wash,
            group: 'Eni (POS)',
            word: 'LAVAGGIO',
            accounts,
        });

        expect(
            screen.getByText('Taken out of the group Eni (POS)'),
        ).toBeInTheDocument();
        expect(screen.getByText('It does not come back')).toBeInTheDocument();
        expect(
            screen.getByText('15/11 · Conto BancoPosta ·· 3307'),
        ).toBeInTheDocument();
        expect(screen.getByText('LAVAGGIO').tagName).toBe('MARK');
        expect(screen.getByText('What is this movement?')).toBeInTheDocument();
    });

    it('out of a filter without a group it does not say it never comes back', () => {
        renderPage(BankFilterDropped, { transaction: wash, accounts });

        expect(screen.getByText('Taken out of the filter')).toBeInTheDocument();
        expect(screen.queryByText('It does not come back')).toBeNull();
    });

    it('an income asks for an income', () => {
        renderPage(BankFilterDropped, {
            transaction: { ...wash, amount: 12 },
            accounts,
        });

        expect(
            screen.getByRole('button', { name: 'It belongs to an income' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Create the income' }),
        ).toBeInTheDocument();
    });

    it('Ignore is sent without leaving, and a refusal says why', async () => {
        request.mockRejectedValueOnce({
            response: {
                status: 422,
                data: {
                    message:
                        'This movement is already sorted, so it cannot be ignored.',
                },
            },
        });
        renderPage(BankFilterDropped, { transaction: wash, accounts });

        await userEvent.click(screen.getByRole('button', { name: 'Ignore' }));

        expect(
            await screen.findByText(
                'This movement is already sorted, so it cannot be ignored.',
            ),
        ).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Ignore' }));

        expect(
            await screen.findByText('Done: the movement is sorted.'),
        ).toBeInTheDocument();
        expect(request).toHaveBeenLastCalledWith(
            expect.objectContaining({
                method: 'post',
                url: '/budget/bank/23/ignore',
            }),
        );
    });
});
