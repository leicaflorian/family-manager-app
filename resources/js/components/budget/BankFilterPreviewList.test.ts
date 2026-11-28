import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { bankFilterPreview } from '@/test/fixtures';
import { renderPage, screenWidth } from '@/test/page';
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

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('BankFilterPreviewList', () => {
    it('says how many, the days and the amounts, and on the phone shows five then the others', async () => {
        screenWidth(false);
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
        expect(screen.getAllByText('Create the expense')).toHaveLength(5);
        expect(screen.getAllByText('no commission')).toHaveLength(5);

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Show the other 7 (from 13/11)',
            }),
        );

        expect(screen.getAllByText('Create the expense')).toHaveLength(12);
    });

    it('shows eight when wide, and the amounts in the currency of the movements', () => {
        screenWidth(true);
        renderPage(BankFilterPreviewList, {
            preview: {
                ...twelve,
                rows: twelve.rows.map((item) => ({
                    ...item,
                    transaction: { ...item.transaction, currency: 'CHF' },
                })),
            },
            feeMode: 'auto',
        });

        expect(screen.getAllByText('Create the expense')).toHaveLength(8);
        expect(
            screen.getByRole('button', {
                name: 'Show the other 4 (from 13/11)',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                /^Last 90 days, from 13\/11 to 24\/11, from .*CHF/,
            ),
        ).toBeInTheDocument();
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

    it('with droppable each movement has It is not of this group, which says which one', async () => {
        const view = renderPage(BankFilterPreviewList, {
            preview: bankFilterPreview,
            feeMode: 'auto',
            droppable: true,
            grouped: true,
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'It is not of this group' }),
        );

        expect(view.emitted().drop).toEqual([[row]]);
    });

    it('without droppable there is no It is not of this group', () => {
        renderPage(BankFilterPreviewList, {
            preview: bankFilterPreview,
            feeMode: 'auto',
        });

        expect(
            screen.queryByRole('button', { name: 'It is not of this group' }),
        ).toBeNull();
    });
});
