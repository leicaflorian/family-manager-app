import { router } from '@inertiajs/vue3';
import userEvent from '@testing-library/user-event';
import { screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import type { FlashSimilar } from '@/types/ui';
import { bankFilters, bankTransactions } from '@/test/fixtures';
import { renderPage, screenWidth, settle } from '@/test/page';
import BankSimilarSheet from './BankSimilarSheet.vue';

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

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

/** The filter the flash carries: creates the expense «Ricarica Satispay», with the commission. */
const satispay = bankFilters.find((filter) => filter.action === 'create')!;

/** What the server flashes: `count` movements, all of them carried (up to 20). */
function similar(
    count: number,
    action: FlashSimilar['action'] = 'create',
): FlashSimilar {
    const filter =
        action === 'create'
            ? satispay
            : action === 'pay'
              ? bankFilters.find((one) => one.action === 'pay')!
              : {
                    ...satispay,
                    action: 'transfer',
                    transfer_account: 'Libretto postale',
                };

    return {
        count,
        kind: 'expense',
        rows: Array.from({ length: count }, (_, i) => row(50 + i, i + 1)),
        action,
        filter,
        reason: { kind: 'mandate', name: 'Satispay' },
        min: 10,
        max: 50,
        from: '2026-09-02',
        to: '2026-11-17',
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

    it('says what the filter would do, as the line Action of a filter', async () => {
        await open(similar(3, 'create'));

        expect(
            screen.getByText(/creates the expense «Ricarica Satispay»/),
        ).toBeInTheDocument();
        expect(screen.getByText(/with the commission/)).toBeInTheDocument();
    });

    it('says what a filter that pays does', async () => {
        await open(similar(3, 'pay'));

        expect(
            screen.getByText(/pays the due date of «Luce»/),
        ).toBeInTheDocument();
    });

    it('says why they are alike, with the account, the amounts and the days under the heading', async () => {
        await open(similar(10));

        expect(
            screen.getByText(/They are of the same mandate Satispay\./),
        ).toBeInTheDocument();
        expect(screen.getByText('The other 10 movements')).toBeInTheDocument();
        expect(
            screen.getByText(
                /Conto BancoPosta ·· 3307 · 10 movements · from 10,00.€ to 50,00.€ · between 02\/09 and 17\/11/,
            ),
        ).toBeInTheDocument();
    });

    it('says the words when there is no mandate', async () => {
        await open({
            ...similar(3),
            reason: { kind: 'words', name: 'BAR ROSSI' },
        });

        expect(
            screen.getByText(/They have the same words: BAR ROSSI\./),
        ).toBeInTheDocument();
    });

    it('is a panel at the side when wide, a sheet on the phone', async () => {
        screenWidth(true);
        const view = await open(similar(3));

        expect(screen.getByRole('dialog').tagName).toBe('ASIDE');
        view.unmount();

        screenWidth(false);
        await open(similar(3));

        expect(screen.getByRole('dialog').tagName).not.toBe('ASIDE');
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
