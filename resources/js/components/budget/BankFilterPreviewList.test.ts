import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { bankFilterPreview } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import BankFilterPreviewList from './BankFilterPreviewList.vue';

const row = bankFilterPreview.rows[0]!;

/** The preview with twelve movements, one a day from 13/11 to 24/11. */
const twelve: App.Data.BankFilterPreviewData = {
    ...bankFilterPreview,
    rows: Array.from({ length: 12 }, (_, index) => {
        const day = `2026-11-${String(24 - index).padStart(2, '0')}`;

        return {
            ...row,
            fee: null,
            will: 'create',
            transaction: {
                ...row.transaction,
                id: 100 + index,
                booked_on: day,
                value_on: day,
                amount: -10 - index,
            },
        };
    }),
};

describe('BankFilterPreviewList', () => {
    it('says how many, the days and the amounts, and shows ten then the others', async () => {
        renderPage(BankFilterPreviewList, { preview: twelve, feeMode: 'auto' });

        expect(
            screen.getByRole('heading', {
                name: 'The movements it takes · 12',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'Last 90 days, from 13/11 to 24/11, from 10,00 € to 21,00 €',
            ),
        ).toBeInTheDocument();
        expect(screen.getAllByText('Create the expense')).toHaveLength(10);
        expect(screen.getAllByText('no commission')).toHaveLength(10);

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Show the other 2 (from 13/11)',
            }),
        );

        expect(screen.getAllByText('Create the expense')).toHaveLength(12);
    });

    it('says what each movement becomes and when the list is empty', () => {
        const first = renderPage(BankFilterPreviewList, {
            preview: {
                ...bankFilterPreview,
                rows: [
                    { ...row, will: 'pay', will_on: '2026-12-05' },
                    {
                        ...row,
                        transaction: { ...row.transaction, id: 2 },
                        will: 'none',
                    },
                ],
            },
            feeMode: 'none',
        });

        expect(
            screen.getByText('Pay the due date of 05/12'),
        ).toBeInTheDocument();
        expect(screen.getByText('Stays to sort')).toBeInTheDocument();
        first.unmount();

        renderPage(BankFilterPreviewList, {
            preview: { ...bankFilterPreview, rows: [] },
            feeMode: 'auto',
        });
        expect(
            screen.getByText('No movement of the last 90 days matches.'),
        ).toBeInTheDocument();
    });
});
