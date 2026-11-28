import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import { formValues } from '@/lib/bankFilters';
import { bankFilters } from '@/test/fixtures';
import { renderPage, settle } from '@/test/page';
import BankFilterFeeFields from './BankFilterFeeFields.vue';

// FormKit Pro starts a timer of half a second on mount: it must end before jsdom goes
afterEach(() => new Promise((resolve) => setTimeout(resolve, 600)));

describe('BankFilterFeeFields', () => {
    it('has three choices, each with what it means, and the words only for Recognise it with these words', async () => {
        const values = formValues(bankFilters[0]!);
        renderPage(BankFilterFeeFields, { modelValue: values });

        expect(screen.getAllByRole('radio')).toHaveLength(3);
        expect(
            screen.getByText('The one with the same mandate, within 5 days.'),
        ).toBeInTheDocument();
        expect(screen.getByText('The movement has none.')).toBeInTheDocument();
        expect(
            screen.queryByRole('combobox', { name: 'Contains the words' }),
        ).not.toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('radio', {
                name: /Recognise it with these words/,
            }),
        );
        await settle();

        expect(values.fee_mode).toBe('words');
        const words = screen.getByRole('combobox', {
            name: 'Contains the words',
        });
        // The words sit right under their choice, before *No commission*
        const wordsChoice = screen.getByRole('radio', {
            name: /Recognise it with these words/,
        });
        const none = screen.getByRole('radio', { name: /No commission/ });
        expect(
            wordsChoice.compareDocumentPosition(words) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
        expect(
            words.compareDocumentPosition(none) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
        expect(
            screen.getByRole('radiogroup', { name: 'Commission' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'On the same account, within 5 days of the movement, with a smaller amount.',
            ),
        ).toBeInTheDocument();
    });
});
