import { screen } from '@testing-library/vue';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import { formValues } from '@/lib/bankFilters';
import { bankFilterOptions, bankFilters } from '@/test/fixtures';
import { choose, openMenu, renderPage, shown } from '@/test/page';
import BankFilterActionFields from './BankFilterActionFields.vue';

// FormKit Pro starts a timer of half a second on mount: it must end before jsdom goes
afterEach(() => new Promise((resolve) => setTimeout(resolve, 600)));

describe('BankFilterActionFields', () => {
    it('offers the four actions, in the words of the direction', async () => {
        renderPage(BankFilterActionFields, {
            modelValue: { ...formValues(bankFilters[1]!), direction: 'income' },
            options: bankFilterOptions,
            canPay: true,
        });

        expect(shown(await openMenu('What it does'))).toEqual([
            'Mark an income received',
            'Create the income',
            'It is a transfer',
            'Ignore',
        ]);
        expect(
            screen.queryByRole('combobox', { name: /^Category/ }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText(
                'Mark an income received, Create the income, It is a transfer or Ignore.',
            ),
        ).toBeInTheDocument();
    });

    it('says the actions under What it does, without the one a member cannot choose', () => {
        const first = renderPage(BankFilterActionFields, {
            modelValue: formValues(bankFilters[1]!),
            options: bankFilterOptions,
            canPay: true,
        });
        expect(
            screen.getByText(
                'Pay a due date, Create the expense, It is a transfer or Ignore.',
            ),
        ).toBeInTheDocument();
        first.unmount();

        renderPage(BankFilterActionFields, {
            modelValue: formValues(bankFilters[1]!),
            options: bankFilterOptions,
            canPay: false,
        });
        expect(
            screen.getByText('Create the expense, It is a transfer or Ignore.'),
        ).toBeInTheDocument();
    });

    it('a transfer asks the other account, not the one the filter reads', async () => {
        const values = {
            ...formValues(bankFilters[1]!),
            financial_account_id: '4',
        };
        renderPage(BankFilterActionFields, {
            modelValue: values,
            options: {
                ...bankFilterOptions,
                transfer_accounts: [
                    ...bankFilterOptions.transfer_accounts,
                    {
                        id: 4,
                        name: 'Conto BancoPosta ·· 3307',
                        connected: true,
                    },
                ],
            },
            canPay: false,
        });

        await choose('What it does', 'It is a transfer');

        expect(values.action).toBe('transfer');
        expect(shown(await openMenu('The other account'))).toEqual([
            'Libretto postale',
        ]);
    });
});
