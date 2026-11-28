import { router } from '@inertiajs/vue3';
import userEvent from '@testing-library/user-event';
import { screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import type { FlashSimilar } from '@/types/ui';
import { bankTransactions } from '@/test/fixtures';
import { renderPage, settle } from '@/test/page';
import BankSimilarSheet from './BankSimilarSheet.vue';

afterEach(() => vi.restoreAllMocks());

/** One movement of the group: a Satispay top-up with its commission, on its own day. */
function row(id: number, day: number): App.Data.BankTransactionData {
    return {
        ...bankTransactions[1]!,
        id,
        booked_on: `2026-11-${String(day).padStart(2, '0')}`,
        value_on: `2026-11-${String(day).padStart(2, '0')}`,
        text: 'SATISPAY EUROPE',
        amount: -20,
        fee: { id: id + 100, amount: -0.4, booked_on: '2026-11-19' },
    };
}

/** What the server flashes: `count` movements, all of them carried (up to 20). */
function similar(
    count: number,
    action: FlashSimilar['action'] = 'create',
): FlashSimilar {
    return {
        count,
        rows: Array.from({ length: count }, (_, i) => row(50 + i, i + 1)),
        action,
        url: '/budget/bank/filters/create?from=9',
    };
}

/** Opens the sheet. */
async function open(
    flash: FlashSimilar,
): Promise<ReturnType<typeof renderPage>> {
    const view = renderPage(BankSimilarSheet, { open: true, similar: flash });
    await settle();

    return view;
}

describe('BankSimilarSheet', () => {
    it('says «I found 10 more movements like this»', async () => {
        await open(similar(10));

        expect(
            screen.getAllByText('I found 10 more movements like this').length,
        ).toBeGreaterThan(0);
    });

    it('says «one more» for a single movement', async () => {
        await open(similar(1));

        expect(
            screen.getAllByText('I found one more movement like this').length,
        ).toBeGreaterThan(0);
    });

    it('lists five and offers to show the other 5', async () => {
        await open(similar(10));

        expect(screen.getAllByText(/\+ 0,40.*commission/)).toHaveLength(5);

        await userEvent.click(
            screen.getByRole('button', { name: 'Show the other 5' }),
        );

        expect(screen.getAllByText(/\+ 0,40.*commission/)).toHaveLength(10);
        expect(
            screen.queryByRole('button', { name: /Show the other/ }),
        ).toBeNull();
    });

    it('has no button to show more when there are five or fewer', async () => {
        await open(similar(4));

        expect(
            screen.queryByRole('button', { name: /Show the other/ }),
        ).toBeNull();
    });

    it('says what the filter would do for each action', async () => {
        const view = await open(similar(3, 'create'));
        expect(
            screen.getByText(
                /Creates an expense like the one you have just made/,
            ),
        ).toBeInTheDocument();
        view.unmount();

        const pay = await open(similar(3, 'pay'));
        expect(
            screen.getByText(
                /Pays the due date of the one you have just sorted/,
            ),
        ).toBeInTheDocument();
        pay.unmount();

        await open(similar(3, 'transfer'));
        expect(
            screen.getByText(
                /Marks them as transfers, like the one you have just sorted/,
            ),
        ).toBeInTheDocument();
    });

    it('«Create the filter» leads to the new filter', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        await open(similar(10));

        await userEvent.click(
            screen.getByRole('link', { name: 'Create the filter' }),
        );

        expect(visit).toHaveBeenCalledWith(
            '/budget/bank/filters/create?from=9',
            expect.anything(),
        );
    });

    it('«No, leave it so» closes it', async () => {
        const view = await open(similar(10));

        await userEvent.click(
            screen.getByRole('button', { name: 'No, leave it so' }),
        );

        expect(view.emitted()['update:open']?.at(-1)).toEqual([false]);
    });
});
