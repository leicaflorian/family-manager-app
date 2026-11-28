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
