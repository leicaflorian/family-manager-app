import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import { formValues } from '@/lib/bankFilters';
import { bankFilterOptions, bankFilters } from '@/test/fixtures';
import { openMenu, renderPage, settle, shown } from '@/test/page';
import BankFilterCriteriaFields from './BankFilterCriteriaFields.vue';

// FormKit Pro starts a timer of half a second on mount: it must end before jsdom goes
afterEach(() => new Promise((resolve) => setTimeout(resolve, 600)));

describe('BankFilterCriteriaFields', () => {
    it('has the words, the direction, the accounts with All the accounts and the amounts', async () => {
        const values = formValues(bankFilters[1]!);
        renderPage(BankFilterCriteriaFields, {
            modelValue: values,
            options: bankFilterOptions,
        });

        const words = screen
            .getByRole('combobox', { name: 'Contains the words' })
            .closest<HTMLElement>('[data-type]')!;
        expect(within(words).getByText('SATISPAY')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Outflow' }),
        ).toBeInTheDocument();
        expect(shown(await openMenu('Bank account'))).toEqual([
            'All the accounts',
            'Conto BancoPosta ·· 3307',
        ]);
        const amount = screen.getByRole('group', { name: /^Amount/ });
        expect(amount).toHaveTextContent('Amount (optional)');
        expect(
            within(amount).getByRole('textbox', { name: 'Amount from' }),
        ).toHaveAttribute('placeholder', 'from €');
        expect(
            within(amount).getByRole('textbox', { name: 'Amount up to' }),
        ).toHaveAttribute('placeholder', 'to €');

        await userEvent.type(
            screen.getByRole('combobox', { name: 'Contains the words' }),
            'EUROPE{Enter}',
        );
        await settle();
        expect(values.contains).toEqual([
            'SATISPAY',
            'DOMICILIAZIONE',
            'EUROPE',
        ]);
    });

    it('an advanced filter has its expression instead of the words', () => {
        renderPage(BankFilterCriteriaFields, {
            modelValue: formValues({
                ...bankFilters[0]!,
                pattern: 'ENEL.*BOLL',
            }),
            options: bankFilterOptions,
        });

        expect(screen.getByLabelText('Expression')).toHaveValue('ENEL.*BOLL');
        expect(
            screen.getByText(
                'It comes from the rule of a planned item: it changes only as text.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('combobox', { name: 'Contains the words' }),
        ).not.toBeInTheDocument();
    });
});
