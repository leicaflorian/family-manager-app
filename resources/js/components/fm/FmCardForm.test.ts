import userEvent from '@testing-library/user-event';
import { plugin as formkit } from '@formkit/vue';
import { fireEvent, render, screen } from '@testing-library/vue';
import { router } from '@inertiajs/vue3';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import formkitConfig from '@/formkit.config';
import { loanFields, policyFields, utilityFields } from '@/test/fixtures';
import { choose, openMenu, renderPage, settle, shown } from '@/test/page';
import FmCardForm from './FmCardForm.vue';

const values = {
    title: 'RC auto Panda',
    type: 'car',
    vehicle_id: '1',
    home_id: null,
};

describe('FmCardForm', () => {
    it('asks the vehicle for a car policy and the home for a home policy', async () => {
        renderPage(FmCardForm, { fields: policyFields, values });

        expect(screen.getByLabelText('Vehicle')).toBeInTheDocument();
        expect(screen.queryByLabelText('Residence')).not.toBeInTheDocument();

        await choose('Type', 'Home');

        expect(screen.queryByLabelText('Vehicle')).not.toBeInTheDocument();
        expect(screen.getByLabelText('Residence')).toBeInTheDocument();
    });

    it('names a field by the type chosen', async () => {
        renderPage(FmCardForm, {
            fields: utilityFields,
            values: { type: 'electricity', supply_point_code: null },
        });

        expect(screen.getByLabelText('POD code')).toBeInTheDocument();

        await choose('Type', 'Gas');

        expect(screen.queryByLabelText('POD code')).not.toBeInTheDocument();
        expect(screen.getByLabelText('PDR code')).toBeInTheDocument();
    });

    it('sends a hidden field empty', async () => {
        const view = renderPage(FmCardForm, {
            fields: policyFields,
            values,
        });

        await choose('Type', 'Home');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(view.emitted('submit')?.[0]).toEqual([
            expect.objectContaining({ type: 'home', vehicle_id: null }),
        ]);
    });

    it('does not save without a required field', async () => {
        const view = renderPage(FmCardForm, {
            fields: policyFields,
            values: { title: '', type: 'car' },
        });

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(view.emitted('submit')).toBeUndefined();
        expect(screen.getByText('This field is required.')).toBeInTheDocument();
    });

    it('shows the error of the server under its field', () => {
        renderPage(FmCardForm, {
            fields: policyFields,
            values,
            serverErrors: { title: 'The title is taken.' },
        });

        expect(screen.getByText('The title is taken.')).toBeInTheDocument();
    });

    it('waits while it is being sent', () => {
        renderPage(FmCardForm, {
            fields: policyFields,
            values,
            processing: true,
        });

        expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();
    });

    it('sends a field left empty as null', async () => {
        const view = renderPage(FmCardForm, {
            fields: policyFields,
            values: { ...values, vehicle_id: '' },
        });

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(view.emitted('submit')?.[0]).toEqual([
            expect.objectContaining({ vehicle_id: null }),
        ]);
    });

    it('offers to choose nothing in a menu that is not required', async () => {
        renderPage(FmCardForm, { fields: policyFields, values });

        const list = await openMenu('Vehicle');

        expect(shown(list)).toEqual(['None', 'Fiat Panda']);
    });

    it('does not offer it in a required menu', async () => {
        renderPage(FmCardForm, { fields: policyFields, values });

        const list = await openMenu('Type');

        expect(shown(list)).toEqual(['Car', 'Home']);
    });

    it('shows what the page puts in the start slot', () => {
        render(FmCardForm, {
            props: { fields: policyFields, values },
            slots: { start: '<button type="button">Delete</button>' },
            global: { plugins: [[formkit, formkitConfig]] },
        });

        expect(
            screen.getByRole('button', { name: 'Delete' }),
        ).toBeInTheDocument();
    });

    it('shows the name of a linked card when the server sends its id as a number', () => {
        renderPage(FmCardForm, {
            fields: policyFields,
            values: { ...values, vehicle_id: 1 },
        });

        expect(screen.getByLabelText('Vehicle')).toHaveValue('Fiat Panda');
    });

    it('sends an untouched switch as off, never as null', async () => {
        const view = renderPage(FmCardForm, {
            fields: [
                policyFields[0],
                {
                    column: 'is_owned',
                    label: 'Owned',
                    kind: 'toggle',
                    required: false,
                    options: [],
                    show_when: {},
                    label_when: {},
                },
            ],
            values: { title: 'Casa al mare', is_owned: null },
        });

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(view.emitted('submit')?.[0]).toEqual([
            { title: 'Casa al mare', is_owned: false },
        ]);
    });

    describe('with the figures of a loan', () => {
        afterEach(() => {
            vi.useRealTimers();
            vi.restoreAllMocks();
        });

        const loanValues = {
            name: 'Finanziamento TV',
            total_amount: '1100',
            installments_count: 12,
            installment_amount: 100,
            final_installment_amount: null,
            start_date: '2026-01-05',
        };

        it('asks the server for the figures once, after a pause in the typing', async () => {
            vi.useFakeTimers({ shouldAdvanceTime: true });
            const reload = vi
                .spyOn(router, 'reload')
                .mockImplementation(() => {});
            renderPage(FmCardForm, {
                fields: loanFields,
                values: loanValues,
                loanFigures: null,
            });

            await fireEvent.update(screen.getByLabelText('Instalment'), '10');
            await vi.advanceTimersByTimeAsync(200);
            await fireEvent.update(screen.getByLabelText('Instalment'), '105');
            expect(reload).not.toHaveBeenCalled();

            await vi.advanceTimersByTimeAsync(400);

            expect(reload).toHaveBeenCalledTimes(1);
            expect(reload).toHaveBeenCalledWith(
                expect.objectContaining({
                    only: ['loanFigures'],
                    preserveUrl: true,
                    data: {
                        figures: expect.objectContaining({
                            total_amount: '1100',
                            installments_count: 12,
                            installment_amount: '105',
                            start_date: '2026-01-05',
                        }),
                    },
                }),
            );
        });

        it('asks again when the status changes, and sends it', async () => {
            vi.useFakeTimers({ shouldAdvanceTime: true });
            const reload = vi
                .spyOn(router, 'reload')
                .mockImplementation(() => {});
            renderPage(FmCardForm, {
                fields: loanFields,
                values: { ...loanValues, status: 'active' },
                loanFigures: null,
            });

            await userEvent.click(
                screen.getByRole('button', { name: 'Paid off' }),
            );
            await vi.advanceTimersByTimeAsync(400);

            expect(reload).toHaveBeenCalledTimes(1);
            expect(reload).toHaveBeenCalledWith(
                expect.objectContaining({
                    data: {
                        figures: expect.objectContaining({
                            status: 'paid_off',
                        }),
                    },
                }),
            );
        });

        it('does not ask after the form is gone', async () => {
            vi.useFakeTimers({ shouldAdvanceTime: true });
            const reload = vi
                .spyOn(router, 'reload')
                .mockImplementation(() => {});
            const view = renderPage(FmCardForm, {
                fields: loanFields,
                values: loanValues,
                loanFigures: null,
            });

            await fireEvent.update(screen.getByLabelText('Instalment'), '105');
            view.unmount();
            await vi.advanceTimersByTimeAsync(400);

            expect(reload).not.toHaveBeenCalled();
        });

        it('writes the figures that arrived under the instalment', () => {
            renderPage(FmCardForm, {
                fields: loanFields,
                values: loanValues,
                loanFigures: { total_to_pay: 1200, cost: 100 },
            });

            expect(screen.getByText('1.200,00 €')).toBeInTheDocument();
            expect(screen.getByText('100,00 €')).toBeInTheDocument();
        });

        it('does not ask for figures from a card that is not a loan', async () => {
            vi.useFakeTimers({ shouldAdvanceTime: true });
            const reload = vi
                .spyOn(router, 'reload')
                .mockImplementation(() => {});
            const user = userEvent.setup({
                advanceTimers: vi.advanceTimersByTime,
            });
            renderPage(FmCardForm, { fields: policyFields, values });

            await user.type(screen.getByLabelText('Title'), 'x');
            await vi.advanceTimersByTimeAsync(400);

            expect(reload).not.toHaveBeenCalled();
            expect(
                screen.queryByTestId('loan-figures'),
            ).not.toBeInTheDocument();
        });
    });
});
