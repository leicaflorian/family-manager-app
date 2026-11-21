import { router } from '@inertiajs/vue3';
import type { VisitOptions } from '@inertiajs/core';
import { fireEvent, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { answerLeave, leaveQuestion } from '@/lib/unsavedChanges';
import { consumptionPage } from '@/test/fixtures';
import { renderPage, settle } from '@/test/page';
import ConsumptionSheet from './ConsumptionSheet.vue';

/** The electricity of the Bianchi. */
const utility = consumptionPage.utility;

/** The bill of January 2026: 290 kWh, 86,10 €, read from document 116. */
const january = consumptionPage.bills.find((bill) => bill.id === 16)!;

afterEach(() => {
    vi.restoreAllMocks();
});

/**
 * The options of the last visit sent with a spy of the router.
 *
 * @param spy - the spy of `router.put` or `router.post`
 * @returns its options
 */
function optionsOf(spy: ReturnType<typeof vi.spyOn>): VisitOptions {
    return spy.mock.calls.at(-1)?.[2] as VisitOptions;
}

describe('ConsumptionSheet', () => {
    it('corrects a bill: its period, its consumption in the unit of the utility, its amount and Estimated', async () => {
        renderPage(ConsumptionSheet, { utility, bill: january, open: true });

        const dialog = await screen.findByRole('dialog', {
            name: 'Correct the consumption',
        });
        expect(dialog).toHaveTextContent('Bill of January 2026');
        expect(dialog).toHaveTextContent(
            'Electricity - Edison · Casa di via Roma · read from the bill',
        );
        expect(
            within(dialog).getByRole('link', { name: 'View' }),
        ).toHaveAttribute('href', '/documents/116');
        expect(screen.getByLabelText('From')).toHaveValue('01/01/2026');
        expect(screen.getByLabelText('To')).toHaveValue('31/01/2026');
        expect(screen.getByLabelText('Consumption')).toHaveValue(290);
        expect(dialog).toHaveTextContent('kWh');
        expect(screen.getByLabelText('Amount')).toBeInTheDocument();
        expect(screen.getByLabelText('Estimated')).not.toBeChecked();
        expect(
            screen.getByText(
                "If you correct it, I won't read it again from the bill.",
            ),
        ).toBeInTheDocument();
    });

    it('sends the corrected values to its row, keeping the place in the page', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(ConsumptionSheet, { utility, bill: january, open: true });
        await screen.findByRole('dialog');

        await fireEvent.update(screen.getByLabelText('Consumption'), '310');
        await fireEvent.update(screen.getByLabelText('Amount'), '90,50');
        await userEvent.click(screen.getByLabelText('Estimated'));
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(put).toHaveBeenCalledWith(
            '/budget/consumption/rows/16',
            {
                period_start: '2026-01-01',
                period_end: '2026-01-31',
                quantity: 310,
                amount: 90.5,
                is_estimated: true,
            },
            expect.objectContaining({
                preserveScroll: true,
                preserveState: true,
            }),
        );
    });

    it('shows the messages of the server and stays open; closes after saving', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        const view = renderPage(ConsumptionSheet, {
            utility,
            bill: january,
            open: true,
        });
        await screen.findByRole('dialog');

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        optionsOf(put).onError?.({
            period_end: 'The period lasts at most 400 days.',
        });
        await settle();

        expect(
            screen.getByText('The period lasts at most 400 days.'),
        ).toBeInTheDocument();
        expect(view.emitted()['update:open']).toBeUndefined();

        optionsOf(put).onSuccess?.(
            {} as Parameters<NonNullable<VisitOptions['onSuccess']>>[0],
        );
        await settle();

        expect(view.emitted()['update:open']).toEqual([[false]]);
    });

    it('adds a consumption by hand to the utility, starting empty', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(ConsumptionSheet, { utility, open: true });

        const dialog = await screen.findByRole('dialog', {
            name: 'Add a consumption',
        });
        expect(dialog).toHaveTextContent(
            'Electricity - Edison · Casa di via Roma',
        );
        expect(within(dialog).queryByRole('link')).not.toBeInTheDocument();
        expect(screen.getByLabelText('Consumption')).toHaveValue(null);
        expect(
            screen.queryByText(
                "If you correct it, I won't read it again from the bill.",
            ),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Delete' }),
        ).not.toBeInTheDocument();

        await fireEvent.update(screen.getByLabelText('Consumption'), '120');
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(post).toHaveBeenCalledWith(
            '/budget/consumption/1/rows',
            {
                period_start: '',
                period_end: '',
                quantity: 120,
                amount: null,
                is_estimated: false,
            },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('writes the consumption of a bill to check, with its document', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(ConsumptionSheet, {
            utility,
            document: { id: 40, title: 'Bolletta gas 14/12/2025' },
            open: true,
        });

        const dialog = await screen.findByRole('dialog', {
            name: 'Add a consumption',
        });
        expect(dialog).toHaveTextContent('Bolletta gas 14/12/2025');
        expect(
            within(dialog).getByRole('link', { name: 'View' }),
        ).toHaveAttribute('href', '/documents/40');

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(post).toHaveBeenCalledWith(
            '/budget/consumption/1/rows',
            expect.objectContaining({ document_id: 40 }),
            expect.anything(),
        );
    });

    it('offers Delete only to who may delete it, and asks first', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        const first = renderPage(ConsumptionSheet, {
            utility,
            bill: { ...january, can_delete: false },
            open: true,
        });
        await screen.findByRole('dialog');

        expect(
            screen.queryByRole('button', { name: 'Delete' }),
        ).not.toBeInTheDocument();
        first.unmount();

        renderPage(ConsumptionSheet, { utility, bill: january, open: true });
        await userEvent.click(
            await screen.findByRole('button', { name: 'Delete' }),
        );
        expect(await screen.findByText('Its bill stays.')).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Delete the consumption' }),
        );

        expect(destroy).toHaveBeenCalledWith(
            '/budget/consumption/rows/16',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('asks before closing with the × when something changed', async () => {
        const view = renderPage(ConsumptionSheet, {
            utility,
            bill: january,
            open: true,
        });
        await screen.findByRole('dialog');
        await fireEvent.update(screen.getByLabelText('Consumption'), '300');
        await settle();

        await userEvent.click(screen.getByRole('button', { name: 'Close' }));

        expect(leaveQuestion.open).toBe(true);
        answerLeave(false);
        await settle();
        expect(view.emitted()['update:open']).toBeUndefined();
    });
});
