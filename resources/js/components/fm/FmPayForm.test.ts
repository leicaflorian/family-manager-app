import { router } from '@inertiajs/vue3';
import type { VisitOptions } from '@inertiajs/core';
import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { isoDate } from '@/lib/format';
import { pickFile, renderPage, settle } from '@/test/page';
import { accountOptions, danceDeadline, salaryDeadline } from '@/test/fixtures';
import FmPayForm from './FmPayForm.vue';

const deadline = { ...danceDeadline, amount: 45, base_amount: null };

const props = {
    deadline,
    accounts: accountOptions,
    action: '/budget/planned/expense/4/2026-12-05/payment',
};

afterEach(() => {
    vi.restoreAllMocks();
});

describe('FmPayForm', () => {
    it('sends today, the amount and the account of the due date', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmPayForm, props);

        expect(await screen.findByLabelText('How much')).toHaveValue('45,00 €');
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
        expect(options).toMatchObject({
            forceFormData: true,
            preserveScroll: true,
        });
    });

    it('sends another account when it is chosen', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmPayForm, props);

        await userEvent.click(
            await screen.findByRole('button', { name: 'Conto BPER' }),
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
        renderPage(FmPayForm, props);
        await screen.findByLabelText('How much');
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
        renderPage(FmPayForm, {
            ...props,
            deadline: salaryDeadline,
            action: '/budget/planned/income/2/2026-10-27/payment',
        });

        expect(
            await screen.findByText(
                'It becomes the income Stipendio di Marco October 2026 of Marco, linked to Marco - Studio Rinaldi.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/It was expected .*1\.850,00 €/),
        ).toBeInTheDocument();
    });

    it('names the month of the original due date when the day was moved', async () => {
        renderPage(FmPayForm, {
            ...props,
            deadline: {
                ...salaryDeadline,
                date: '2026-11-02',
                original_date: '2026-10-31',
            },
        });

        expect(
            await screen.findByText(/Stipendio di Marco October 2026/),
        ).toBeInTheDocument();
    });

    it('names its button with submitLabel, without Cancel', async () => {
        renderPage(FmPayForm, { ...props, submitLabel: 'I paid it' });

        expect(
            await screen.findByRole('button', { name: 'I paid it' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Cancel' }),
        ).not.toBeInTheDocument();
    });

    it('with cancellable has Cancel, which tells the parent', async () => {
        const view = renderPage(FmPayForm, { ...props, cancellable: true });

        await userEvent.click(
            await screen.findByRole('button', { name: 'Cancel' }),
        );

        expect(view.emitted('cancel')).toHaveLength(1);
    });

    it('shows the messages of the server and stays as it is', async () => {
        vi.spyOn(router, 'post').mockImplementation(
            (_url, _data, options?: VisitOptions) => {
                options?.onError?.({ amount: 'The amount is required.' });
                options?.onFinish?.({} as never);
            },
        );
        const view = renderPage(FmPayForm, props);

        await userEvent.click(
            await screen.findByRole('button', { name: 'Confirm' }),
        );
        await settle();

        expect(screen.getByText('The amount is required.')).toBeInTheDocument();
        expect(view.emitted('saved')).toBeUndefined();
    });

    it('tells the parent once the payment is recorded', async () => {
        vi.spyOn(router, 'post').mockImplementation(
            (_url, _data, options?: VisitOptions) => {
                options?.onSuccess?.({} as never);
                options?.onFinish?.({} as never);
            },
        );
        const view = renderPage(FmPayForm, props);

        await userEvent.click(
            await screen.findByRole('button', { name: 'Confirm' }),
        );
        await settle();

        expect(view.emitted('saved')).toHaveLength(1);
    });

    it('starts again from a new due date', async () => {
        const view = renderPage(FmPayForm, props);
        await userEvent.click(
            await screen.findByRole('button', { name: 'Conto BPER' }),
        );
        await settle();

        await view.rerender({
            ...props,
            deadline: { ...deadline, planned_id: 9, amount: 12 },
        });
        await settle();

        expect(screen.getByLabelText('How much')).toHaveValue('12,00 €');
        // Back to the account of the due date, Carta Laura
        expect(
            screen.getByRole('button', { name: 'Carta Laura' }),
        ).toHaveAttribute('aria-pressed', 'true');
    });

    describe('the premium of a policy near its expiry', () => {
        /** The car premium of 21/11/2026, whose policy expires the same day. */
        const policyProps = {
            ...props,
            deadline: {
                ...deadline,
                title: 'Rata RC auto',
                planned_title: 'Assicurazione auto',
                date: '2026-11-21',
                original_date: '2026-11-21',
                amount: 418,
                renewal: {
                    policy_id: 5,
                    policy: 'RC auto Panda · UnipolSai',
                    expiry_date: '2026-11-21',
                    proposed_expiry: '2027-11-21',
                },
            },
        };

        it('shows the choice only with a renewal', async () => {
            renderPage(FmPayForm, props);
            await screen.findByLabelText('How much');

            expect(
                screen.queryByRole('button', { name: 'Only the payment' }),
            ).not.toBeInTheDocument();
        });

        it('starts on only the payment', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(FmPayForm, policyProps);

            expect(
                await screen.findByRole('button', { name: 'Only the payment' }),
            ).toHaveAttribute('aria-pressed', 'true');
            expect(
                screen.queryByLabelText('New expiry date'),
            ).not.toBeInTheDocument();
            await userEvent.click(
                screen.getByRole('button', { name: 'Confirm' }),
            );
            await settle();

            expect(post.mock.calls[0]![1]).toMatchObject({ renewal: 'only' });
            expect(post.mock.calls[0]![1]).not.toHaveProperty('new_expiry');
        });

        it('asks the new expiry with the same company', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(FmPayForm, policyProps);

            await userEvent.click(
                await screen.findByRole('button', {
                    name: 'Same company, renewed',
                }),
            );
            await settle();

            expect(screen.getByLabelText('New expiry date')).toHaveValue(
                '21/11/2027',
            );
            expect(
                screen.getByText(
                    'The policy stays active until this date, and the renewal reminder is marked done.',
                ),
            ).toBeInTheDocument();
            await userEvent.click(
                screen.getByRole('button', { name: 'Confirm' }),
            );
            await settle();

            expect(post.mock.calls[0]![1]).toMatchObject({
                renewal: 'same',
                new_expiry: '2027-11-21',
                policy_expiry: '2026-11-21',
            });
        });

        it('sends changed when the company changed', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(FmPayForm, policyProps);

            await userEvent.click(
                await screen.findByRole('button', {
                    name: 'I changed company',
                }),
            );
            await settle();

            expect(
                screen.queryByLabelText('New expiry date'),
            ).not.toBeInTheDocument();
            await userEvent.click(
                screen.getByRole('button', { name: 'Confirm' }),
            );
            await settle();

            expect(post.mock.calls[0]![1]).toMatchObject({
                renewal: 'changed',
                policy_expiry: '2026-11-21',
            });
            expect(post.mock.calls[0]![1]).not.toHaveProperty('new_expiry');
        });

        it('shows a refusal of the renewal even when the choice is gone', async () => {
            vi.spyOn(router, 'post').mockImplementation(
                (_url, _data, options?: VisitOptions) => {
                    options?.onError?.({
                        renewal: 'This policy cannot be renewed here.',
                    });
                    options?.onFinish?.({} as never);
                },
            );
            const view = renderPage(FmPayForm, policyProps);
            await userEvent.click(
                await screen.findByRole('button', {
                    name: 'Same company, renewed',
                }),
            );
            await settle();
            await userEvent.click(
                screen.getByRole('button', { name: 'Confirm' }),
            );
            await settle();

            await view.rerender({
                ...policyProps,
                deadline: { ...policyProps.deadline, renewal: null },
            });
            await settle();

            expect(
                screen.queryByRole('button', { name: 'Only the payment' }),
            ).not.toBeInTheDocument();
            expect(
                screen.getByText('This policy cannot be renewed here.'),
            ).toBeInTheDocument();
        });

        it('goes back to only the payment when it starts again', async () => {
            const view = renderPage(FmPayForm, policyProps);
            await userEvent.click(
                await screen.findByRole('button', {
                    name: 'I changed company',
                }),
            );
            await settle();

            await view.rerender({
                ...policyProps,
                deadline: {
                    ...policyProps.deadline,
                    original_date: '2027-11-21',
                },
            });
            await settle();

            expect(
                screen.getByRole('button', { name: 'Only the payment' }),
            ).toHaveAttribute('aria-pressed', 'true');
        });
    });
});
