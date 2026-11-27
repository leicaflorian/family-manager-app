import userEvent from '@testing-library/user-event';
import { plugin as formkit } from '@formkit/vue';
import { fireEvent, render, screen } from '@testing-library/vue';
import { router } from '@inertiajs/vue3';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import formkitConfig from '@/formkit.config';
import type { VisitOptions } from '@inertiajs/core';
import {
    employmentFields,
    employmentTitleParts,
    loanFields,
    organizationTypes,
    policyFields,
    utilityFields,
    utilityFormFields,
    utilityTitleParts,
} from '@/test/fixtures';
import { hasChanges } from '@/lib/unsavedChanges';
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

    it('gives the live values to the after slot, above the buttons', async () => {
        render(FmCardForm, {
            props: { fields: policyFields, values },
            slots: {
                after: '<template #after="{ values: live }"><p>Type now: {{ live.type }}</p></template>',
            },
            global: { plugins: [[formkit, formkitConfig]] },
        });

        expect(screen.getByText('Type now: car')).toBeInTheDocument();

        await choose('Type', 'Home');

        expect(screen.getByText('Type now: home')).toBeInTheDocument();
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

    describe('the composed title', () => {
        it('shows, read-only at the top, the name the card will have, and follows the menus', async () => {
            renderPage(FmCardForm, {
                fields: utilityFormFields,
                values: { type: 'gas', organization_id: null, home_id: 1 },
                titleParts: utilityTitleParts,
            });

            const title = screen.getByLabelText('Title');
            expect(title).toHaveAttribute('readonly');
            expect(title).toHaveValue('Gas - Casa di via Roma');

            await choose('Supplier', 'Edison');

            expect(screen.getByLabelText('Title')).toHaveValue('Gas - Edison');
        });

        it('uses the first name of the person', () => {
            renderPage(FmCardForm, {
                fields: employmentFields,
                values: { person_id: 1, organization_id: 5 },
                titleParts: employmentTitleParts,
            });

            expect(screen.getByLabelText('Title')).toHaveValue(
                'Marco - Studio Rinaldi',
            );
        });

        it('says where it comes from while nothing composes it', () => {
            renderPage(FmCardForm, {
                fields: employmentFields,
                values: {},
                titleParts: employmentTitleParts,
            });

            expect(screen.getByLabelText('Title')).toHaveValue('');
            expect(screen.getByLabelText('Title')).toHaveAttribute(
                'placeholder',
                'It is made from the fields below',
            );
        });

        it('is not there for a type with its own name', () => {
            renderPage(FmCardForm, { fields: utilityFormFields, values: {} });

            expect(screen.queryByLabelText('Title')).not.toBeInTheDocument();
        });
    });

    describe('a new organization from the menu', () => {
        afterEach(() => {
            vi.restoreAllMocks();
        });

        /** The props of a utility being written, with the «+». */
        const utility = {
            fields: utilityFormFields,
            values: { type: 'electricity', organization_id: null, home_id: 1 },
            titleParts: utilityTitleParts,
            canCreateOrganization: true,
            organizationTypes,
        };

        it('offers the «+» beside every organization menu, only to who can add one', () => {
            const view = renderPage(FmCardForm, utility);

            expect(
                screen.getAllByRole('button', { name: 'New organization' }),
            ).toHaveLength(1);

            view.unmount();
            renderPage(FmCardForm, {
                ...utility,
                canCreateOrganization: false,
            });

            expect(
                screen.queryByRole('button', { name: 'New organization' }),
            ).not.toBeInTheDocument();
        });

        it('chooses the organization added, keeping what was written', async () => {
            let visit: VisitOptions | undefined;
            vi.spyOn(router, 'post').mockImplementation(
                (_url, _data, options) => {
                    visit = options as VisitOptions;
                },
            );
            const view = renderPage(FmCardForm, utility);
            await choose('Type', 'Gas');
            await fireEvent.update(
                screen.getByLabelText('Customer code'),
                'C-123',
            );
            await settle();

            await userEvent.click(
                screen.getByRole('button', { name: 'New organization' }),
            );
            await screen.findByRole('dialog', { name: 'New organization' });
            await userEvent.type(
                screen.getByLabelText('Name'),
                'Autofficina Rossi',
            );
            await settle();
            await userEvent.click(screen.getByRole('button', { name: 'Save' }));
            await settle();

            // The page comes back with the new option, then the flash arrives
            await view.rerender({
                ...utility,
                fields: utilityFormFields.map((field) =>
                    field.column === 'organization_id'
                        ? {
                              ...field,
                              options: [
                                  ...field.options,
                                  {
                                      value: '12',
                                      label: 'Autofficina Rossi',
                                      help: null,
                                      title: null,
                                  },
                              ],
                          }
                        : field,
                ),
                values: structuredClone(utility.values),
            });
            visit?.onFlash?.({
                organization: { id: 12, name: 'Autofficina Rossi' },
            });
            await settle();

            expect(
                screen.queryByRole('dialog', { name: 'New organization' }),
            ).not.toBeInTheDocument();
            expect(screen.getByLabelText('Supplier')).toHaveValue(
                'Autofficina Rossi',
            );
            expect(screen.getByLabelText('Customer code')).toHaveValue('C-123');
            expect(screen.getByLabelText('Title')).toHaveValue(
                'Gas - Autofficina Rossi',
            );
            expect(hasChanges()).toBe(true);

            await userEvent.click(
                screen.getAllByRole('button', { name: 'Save' }).at(-1)!,
            );
            await settle();

            expect(view.emitted('submit')?.[0]).toEqual([
                expect.objectContaining({
                    type: 'gas',
                    organization_id: '12',
                    customer_code: 'C-123',
                }),
            ]);
        });
    });
});
