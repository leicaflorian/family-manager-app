import { router } from '@inertiajs/vue3';
import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { isoDate } from '@/lib/format';
import { pickFile, renderPage, settle } from '@/test/page';
import { accountOptions, danceDeadline, salaryDeadline } from '@/test/fixtures';
import FmPaySheet from './FmPaySheet.vue';

const props = {
    deadline: { ...danceDeadline, amount: 45, base_amount: null },
    accounts: accountOptions,
    open: true,
    action: '/budget/planned/expense/4/2026-12-05/payment',
};

afterEach(() => {
    vi.restoreAllMocks();
});

describe('FmPaySheet', () => {
    it('asks when, how much and with what, starting from today, the amount and the account of the due date', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmPaySheet, props);

        expect(
            await screen.findByRole('dialog', { name: 'I paid it' }),
        ).toBeInTheDocument();
        expect(screen.getByText(/Retta danza/)).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Confirm' }));
        await settle();

        expect(post).toHaveBeenCalledTimes(1);
        const [url, data, options] = post.mock.calls[0]!;
        expect(url).toBe(props.action);
        expect(data).toMatchObject({
            date: isoDate(),
            amount: 45,
            financial_account_id: '2',
        });
        expect(options).toMatchObject({ forceFormData: true });
    });

    it('sends another account when it is chosen', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmPaySheet, props);
        await screen.findByRole('dialog');

        await userEvent.click(
            screen.getByRole('button', { name: 'Conto BPER' }),
        );
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Confirm' }));
        await settle();

        expect(post.mock.calls[0]![1]).toMatchObject({
            financial_account_id: '1',
        });
    });

    it('sends the receipt that is chosen', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmPaySheet, props);
        await screen.findByRole('dialog');
        const receipt = new File(['x'], 'receipt.pdf', {
            type: 'application/pdf',
        });

        await pickFile('Add the receipt (optional)', receipt);
        expect(screen.getByText('receipt.pdf')).toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: 'Confirm' }));
        await settle();

        expect(post.mock.calls[0]![1]).toMatchObject({ receipt });
    });

    it('says what an income becomes', async () => {
        renderPage(FmPaySheet, {
            ...props,
            deadline: salaryDeadline,
            action: '/budget/planned/income/2/2026-10-27/payment',
        });

        expect(
            await screen.findByRole('dialog', { name: 'It arrived' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'It becomes the income Stipendio di Marco October 2026 of Marco, linked to Marco - Studio Rinaldi.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/It was expected .*1\.850,00 €/),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Conto BPER' }),
        ).toBeInTheDocument();
    });

    it('tells the parent when it closes', async () => {
        const view = renderPage(FmPaySheet, props);
        await screen.findByRole('dialog');

        await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(view.emitted('close')).toHaveLength(1);
    });
});
