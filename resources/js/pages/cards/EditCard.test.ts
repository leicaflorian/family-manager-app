import { router } from '@inertiajs/vue3';
import { fireEvent, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    healthCard,
    loanFields,
    pandaRow,
    policyBudget,
    policyDetail,
    policyFields,
} from '@/test/fixtures';
import {
    choose,
    renderPage,
    withTrail,
    settle,
    tapCancel,
    screenWidth,
} from '@/test/page';
import EditCard from './EditCard.vue';
import { hasChanges } from '@/lib/unsavedChanges';

/** The family of the shared props: undefined as in the other tests (everything on), or with its features. */
const shared = vi.hoisted(() => ({
    family: undefined as { features: { budget: boolean } } | undefined,
}));

// As `setup.ts`, with the family of the shared props when a test gives one
vi.mock('@inertiajs/vue3', async (original) => {
    const inertia = await original<typeof import('@inertiajs/vue3')>();
    const { sharedPageProps } = await import('@/test/fixtures');

    return {
        ...inertia,
        Head: { render: () => null },
        usePage: () =>
            new Proxy(inertia.usePage(), {
                get: (page, key) =>
                    key === 'props' && page.props === undefined
                        ? {
                              name: sharedPageProps.name,
                              ...(shared.family && { family: shared.family }),
                          }
                        : Reflect.get(page, key),
            }),
    };
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    shared.family = undefined;
});

describe('Edit a card', () => {
    it('writes the trail of a change and of a new card into the top bar', () => {
        const edit = withTrail();
        const first = renderPage(
            EditCard,
            {
                type: {
                    type: 'insurance_policy',
                    label: 'Insurance policy',
                    plural_label: 'Policies',
                    description: '',
                    area: 'insurance',
                    area_label: 'Insurance',
                },
                fields: policyFields,
                values: {},
                card: policyDetail.card,
            },
            edit.provide,
        );

        expect(edit.state.pieces.value).toEqual([
            { label: 'Areas', href: '/areas' },
            { label: 'Insurance', href: '/areas/insurance' },
            {
                label: 'RC auto Panda - UnipolSai',
                href: '/cards/insurance_policy/1',
            },
            { label: 'Edit the insurance policy' },
        ]);
        first.unmount();

        const created = withTrail();
        renderPage(
            EditCard,
            {
                type: {
                    type: 'insurance_policy',
                    label: 'Insurance policy',
                    plural_label: 'Policies',
                    description: '',
                    area: 'insurance',
                    area_label: 'Insurance',
                },
                fields: policyFields,
                values: {},
                card: null,
            },
            created.provide,
        );

        expect(created.state.pieces.value).toEqual([
            { label: 'Areas', href: '/areas' },
            { label: 'Insurance', href: '/areas/insurance' },
            { label: 'New insurance policy' },
        ]);
    });

    const type: App.Data.CardTypeData = {
        type: 'insurance_policy',
        label: 'Insurance policy',
        plural_label: 'Policies',
        description: '',
        area: 'insurance',
        area_label: 'Insurance',
    };

    it('names a new card and a change', () => {
        const first = renderPage(EditCard, {
            type,
            fields: policyFields,
            values: {},
            card: null,
        });
        expect(
            screen.getByRole('heading', { name: 'New insurance policy' }),
        ).toBeInTheDocument();
        first.unmount();

        renderPage(EditCard, {
            type,
            fields: policyFields,
            values: { title: 'RC auto Panda', type: 'car' },
            card: policyDetail.card,
        });
        expect(
            screen.getByRole('heading', { name: 'Edit the insurance policy' }),
        ).toBeInTheDocument();
    });

    it('saves a change as a POST with _method put, so files can go with it, and a new card with POST', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const first = renderPage(EditCard, {
            type,
            fields: policyFields,
            values: { title: 'RC auto Panda', type: 'car', vehicle_id: '1' },
            card: policyDetail.card,
        });

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(post).toHaveBeenCalledWith(
            '/cards/insurance_policy/1',
            expect.objectContaining({ title: 'RC auto Panda', _method: 'put' }),
            expect.anything(),
        );
        first.unmount();

        renderPage(EditCard, {
            type,
            fields: policyFields,
            values: { title: 'Nuova', type: 'car' },
            card: null,
        });
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(post).toHaveBeenCalledWith(
            '/cards/insurance_policy',
            expect.objectContaining({ title: 'Nuova' }),
            expect.anything(),
        );
    });

    it('cancels back to the card, or to the area for a new one', () => {
        const first = renderPage(EditCard, {
            type,
            fields: policyFields,
            values: {},
            card: policyDetail.card,
        });
        expect(screen.getByRole('link', { name: 'Cancel' })).toHaveAttribute(
            'href',
            '/cards/insurance_policy/1',
        );
        first.unmount();

        renderPage(EditCard, {
            type,
            fields: policyFields,
            values: {},
            card: null,
        });
        expect(screen.getByRole('link', { name: 'Cancel' })).toHaveAttribute(
            'href',
            '/areas/insurance',
        );
    });

    describe('documents of the card', () => {
        const libretto = new File(['%PDF-1.4'], 'Libretto.pdf', {
            type: 'application/pdf',
        });

        /**
         * Chooses files in the zone of the documents.
         *
         * @param files - the files
         */
        async function choose(...files: File[]): Promise<void> {
            await fireEvent.change(screen.getByLabelText('Choose the files'), {
                target: { files },
            });
        }

        it('asks for them on a new card, and shows the linked ones when editing', () => {
            const first = renderPage(EditCard, {
                type,
                fields: policyFields,
                values: {},
                card: null,
            });
            expect(
                screen.getByText(
                    'Its documents? Drag them here, or choose them',
                ),
            ).toBeInTheDocument();
            expect(screen.getByText('No documents yet')).toBeInTheDocument();
            first.unmount();

            renderPage(EditCard, {
                type,
                fields: policyFields,
                values: {},
                card: policyDetail.card,
                documents: [healthCard],
                canDeleteDocuments: true,
            });
            expect(screen.getByText('Tessera sanitaria')).toBeInTheDocument();
            expect(
                screen.getByRole('button', {
                    name: 'More actions on Tessera sanitaria',
                }),
            ).toBeInTheDocument();
            expect(
                screen.getByLabelText('Choose the files'),
            ).toBeInTheDocument();
        });

        it('sends the new files with a change too', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(EditCard, {
                type,
                fields: policyFields,
                values: { title: 'RC auto Panda', type: 'car' },
                card: policyDetail.card,
                documents: [healthCard],
            });

            await choose(libretto);
            await userEvent.click(screen.getByRole('button', { name: 'Save' }));
            await settle();

            expect(post).toHaveBeenCalledWith(
                '/cards/insurance_policy/1',
                expect.objectContaining({ _method: 'put', files: [libretto] }),
                expect.anything(),
            );
        });

        it('lists the chosen files, which can be removed', async () => {
            renderPage(EditCard, {
                type,
                fields: policyFields,
                values: {},
                card: null,
            });
            const bollo = new File(['%PDF-1.4'], 'Bollo.pdf', {
                type: 'application/pdf',
            });

            await choose(libretto);
            await choose(bollo);
            expect(screen.getByText('Libretto.pdf')).toBeInTheDocument();
            expect(screen.getByText('Bollo.pdf')).toBeInTheDocument();

            await userEvent.click(
                screen.getByRole('button', { name: 'Remove Libretto.pdf' }),
            );
            expect(screen.queryByText('Libretto.pdf')).not.toBeInTheDocument();
            expect(screen.getByText('Bollo.pdf')).toBeInTheDocument();
        });

        it('sends them with the card when it is saved', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(EditCard, {
                type,
                fields: policyFields,
                values: { title: 'Nuova', type: 'car' },
                card: null,
            });

            await choose(libretto);
            await userEvent.click(screen.getByRole('button', { name: 'Save' }));
            await settle();

            expect(post).toHaveBeenCalledWith(
                '/cards/insurance_policy',
                expect.objectContaining({ title: 'Nuova', files: [libretto] }),
                expect.anything(),
            );
        });

        it('links and unlinks the documents chosen only when the card is saved', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            const destroy = vi
                .spyOn(router, 'delete')
                .mockImplementation(() => {});
            renderPage(EditCard, {
                type,
                fields: policyFields,
                values: { title: 'RC auto Panda', type: 'car' },
                card: policyDetail.card,
                documents: [healthCard],
                linkable: [{ ...healthCard, id: 30, title: 'Libretto' }],
                hasLinkable: true,
            });

            await userEvent.click(
                screen.getByRole('button', { name: 'Link document' }),
            );
            await userEvent.click(
                await screen.findByRole('button', { name: /^Libretto/ }),
            );
            await userEvent.click(
                screen.getByRole('button', {
                    name: `More actions on ${healthCard.title}`,
                }),
            );
            await userEvent.click(
                await screen.findByRole('menuitem', { name: 'Unlink' }),
            );
            expect(post).not.toHaveBeenCalled();
            expect(destroy).not.toHaveBeenCalled();

            await userEvent.click(screen.getByRole('button', { name: 'Save' }));
            await settle();

            expect(post).toHaveBeenCalledWith(
                '/cards/insurance_policy/1',
                expect.objectContaining({
                    _method: 'put',
                    link_documents: [30],
                    unlink_documents: [healthCard.id],
                }),
                expect.anything(),
            );
        });

        it('starts a new card with the documents it was opened with, linked when it is saved', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(EditCard, {
                type,
                fields: policyFields,
                values: { title: 'RC auto Panda', type: 'car' },
                card: null,
                linked: [healthCard],
            });

            expect(screen.getByText('Tessera sanitaria')).toBeInTheDocument();
            expect(hasChanges()).toBe(false);

            await userEvent.click(screen.getByRole('button', { name: 'Save' }));
            await settle();

            expect(post).toHaveBeenCalledWith(
                '/cards/insurance_policy',
                expect.objectContaining({ link_documents: [healthCard.id] }),
                expect.anything(),
            );
        });

        it('sends no files when none is chosen', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(EditCard, {
                type,
                fields: policyFields,
                values: { title: 'Nuova', type: 'car' },
                card: null,
            });

            await userEvent.click(screen.getByRole('button', { name: 'Save' }));
            await settle();

            expect(post.mock.calls[0]?.[1]).not.toHaveProperty('files');
            expect(post.mock.calls[0]?.[1]).not.toHaveProperty(
                'link_documents',
            );
            expect(post.mock.calls[0]?.[1]).not.toHaveProperty(
                'unlink_documents',
            );
        });

        it('shows why the server refused a file', async () => {
            vi.spyOn(router, 'post').mockImplementation(
                (_url, _data, visit) => {
                    visit?.onError?.({
                        'files.0':
                            'Only PDF, JPG, PNG and HEIC files can be uploaded.',
                    });
                },
            );
            renderPage(EditCard, {
                type,
                fields: policyFields,
                values: { title: 'Nuova', type: 'car' },
                card: null,
            });

            await choose(libretto);
            await userEvent.click(screen.getByRole('button', { name: 'Save' }));
            await settle();

            expect(
                screen.getByText(
                    'Only PDF, JPG, PNG and HEIC files can be uploaded.',
                ),
            ).toBeInTheDocument();
        });
    });

    it('shows beside the form, on the computer, the planned expenses and the payments of the card', () => {
        renderPage(EditCard, {
            type,
            fields: policyFields,
            values: { title: 'RC auto Panda', type: 'car' },
            card: policyDetail.card,
            budget: policyBudget,
        });

        const column = screen.getByTestId('card-payments');
        expect(column).toHaveClass('hidden', 'wide:flex');
        expect(
            within(column).getByRole('heading', { name: 'Expected expenses' }),
        ).toBeInTheDocument();
        expect(
            within(column).getByRole('heading', { name: 'Premiums paid' }),
        ).toBeInTheDocument();
        // the planned expense and the premium paid
        expect(within(column).getAllByText('Assicurazione auto')).toHaveLength(
            2,
        );
    });

    it('has no column for a new card', () => {
        renderPage(EditCard, {
            type,
            fields: policyFields,
            values: {},
            card: null,
        });

        expect(screen.queryByTestId('card-payments')).not.toBeInTheDocument();
    });

    it('shows the total to pay and the cost under the instalment of a loan', () => {
        renderPage(EditCard, {
            type: {
                type: 'loan',
                label: 'Loan',
                description: '',
                area: 'banks',
                area_label: 'Banks',
            },
            fields: loanFields,
            values: { name: 'Finanziamento TV' },
            card: null,
            loanFigures: { total_to_pay: 1200, cost: 100 },
        });

        expect(screen.getByText('Total to pay')).toBeInTheDocument();
        expect(screen.getByText('1.200,00 €')).toBeInTheDocument();
    });

    it('writes the saved total to pay and cost as soon as a loan opens', () => {
        renderPage(EditCard, {
            type: {
                type: 'loan',
                label: 'Loan',
                description: '',
                area: 'banks',
                area_label: 'Banks',
            },
            fields: loanFields,
            values: { name: 'Finanziamento TV' },
            card: pandaRow,
            loanFigures: { total_to_pay: 1200, cost: 100 },
        });

        expect(screen.getByText('1.200,00 €')).toBeInTheDocument();
        expect(screen.getByText('100,00 €')).toBeInTheDocument();
    });

    it('waits for the first figures of a new loan, as dashes', () => {
        renderPage(EditCard, {
            type: {
                type: 'loan',
                label: 'Loan',
                description: '',
                area: 'banks',
                area_label: 'Banks',
            },
            fields: loanFields,
            values: {},
            card: null,
        });

        expect(screen.getAllByText('—')).toHaveLength(2);
    });

    it('shows no figures for a card that is not a loan', () => {
        renderPage(EditCard, {
            type,
            fields: policyFields,
            values: {},
            card: null,
        });

        expect(screen.queryByText('Total to pay')).not.toBeInTheDocument();
    });
});

describe('Changes not saved', () => {
    it('has them once a field changes, and gives them up with Cancel', async () => {
        renderPage(EditCard, {
            type: {
                type: 'insurance_policy',
                label: 'Insurance policy',
                plural_label: 'Policies',
                description: '',
                area: 'insurance',
                area_label: 'Insurance',
            },
            fields: policyFields,
            values: {},
            card: null,
        });
        expect(hasChanges()).toBe(false);

        await fireEvent.update(screen.getByLabelText('Title'), 'RC auto Panda');
        await settle();
        expect(hasChanges()).toBe(true);

        await tapCancel();
        expect(hasChanges()).toBe(false);
    });
});

describe('EditCard on the computer', () => {
    const type: App.Data.CardTypeData = {
        type: 'insurance_policy',
        label: 'Insurance policy',
        plural_label: 'Policies',
        description: '',
        area: 'insurance',
        area_label: 'Insurance',
    };

    it('closes with Back or Cancel: no Close button in the title on any layout', () => {
        for (const width of [1280, 820, 390]) {
            screenWidth(width);
            const view = renderPage(EditCard, {
                type,
                fields: policyFields,
                values: {},
                card: null,
            });

            expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(
                1,
            );
            expect(
                screen.queryByRole('link', { name: 'Close' }),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByRole('button', { name: 'Close' }),
            ).not.toBeInTheDocument();
            view.unmount();
        }
    });
});

describe('The box that cancels the old policy', () => {
    const type: App.Data.CardTypeData = {
        type: 'insurance_policy',
        label: 'Insurance policy',
        plural_label: 'Policies',
        description: '',
        area: 'insurance',
        area_label: 'Insurance',
    };
    const old: App.Data.ReplaceablePolicyData = {
        id: 7,
        label: 'RC Auto · 40/123456 · UnipolSai',
        expiry_date: '2026-11-21',
        type: 'car',
        vehicle_id: 1,
        home_id: null,
        employment_id: null,
    };
    const page = {
        type,
        fields: policyFields,
        values: { title: 'RC Auto', type: 'car', vehicle_id: '1' },
        card: null,
        replaceable: [old],
    };

    it('ticks the old policy when it comes from a renewal', () => {
        renderPage(EditCard, { ...page, replaces: [7], payment: 33 });

        expect(
            screen.getByRole('checkbox', {
                name: /Cancel the policy RC Auto · 40\/123456 · UnipolSai/,
            }),
        ).toBeChecked();
        expect(
            screen.getByText(
                'Expires on 21/11/2026. Its planned expense moves to the new one.',
            ),
        ).toBeInTheDocument();
    });

    it('shows the box when the server sends no card prop for a new policy', () => {
        const { card: _card, ...asSent } = page;
        renderPage(EditCard, { ...asSent, replaces: [7], payment: 33 });

        expect(
            screen.getByRole('checkbox', { name: /Cancel the policy RC Auto/ }),
        ).toBeChecked();
    });

    it('leaves the box unticked for a new policy by hand', () => {
        renderPage(EditCard, { ...page, replaces: [], payment: null });

        expect(
            screen.getByRole('checkbox', { name: /Cancel the policy RC Auto/ }),
        ).not.toBeChecked();
    });

    it('drops the old policy when the type changes', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditCard, { ...page, replaces: [7], payment: 33 });

        await choose('Type', 'Home');

        expect(
            screen.queryByRole('checkbox', { name: /Cancel the policy/ }),
        ).not.toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        const body = post.mock.calls[0]![1] as Record<string, unknown>;
        expect(body).not.toHaveProperty('replace_policies');
        expect(body).not.toHaveProperty('replace_payment');
    });

    it('sends the payment with the policy to replace', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditCard, { ...page, replaces: [7], payment: 33 });

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(post).toHaveBeenCalledWith(
            '/cards/insurance_policy',
            expect.objectContaining({
                replace_policies: [7],
                replace_payment: 33,
            }),
            expect.anything(),
        );
    });

    it('sends no payment when the box is unticked', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditCard, { ...page, replaces: [7], payment: 33 });

        await userEvent.click(
            screen.getByRole('checkbox', { name: /Cancel the policy/ }),
        );
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        const body = post.mock.calls[0]![1] as Record<string, unknown>;
        expect(body).not.toHaveProperty('replace_policies');
        expect(body).not.toHaveProperty('replace_payment');
    });
});

describe('The planned expense of a new subscription', () => {
    const type: App.Data.CardTypeData = {
        type: 'subscription',
        label: 'Subscription',
        plural_label: 'Subscriptions',
        description: '',
        area: 'subscriptions',
        area_label: 'Subscriptions',
    };
    const fields: App.Data.CardFieldData[] = [
        {
            column: 'name',
            label: 'Name',
            kind: 'text',
            required: true,
            options: [],
            show_when: {},
            label_when: {},
        },
        {
            column: 'activation_date',
            label: 'Activation',
            kind: 'date',
            required: false,
            options: [],
            show_when: {},
            label_when: {},
        },
    ];
    const page = {
        type,
        fields,
        values: { name: 'Hostinger', activation_date: '2026-11-15' },
        expenseCategories: [{ value: '4', label: 'Abbonamenti', help: null }],
    };

    it('asks for it on a new subscription', () => {
        renderPage(EditCard, page);

        expect(
            screen.getByRole('group', { name: 'Planned expense' }),
        ).toBeInTheDocument();
    });

    it('is not asked when changing a subscription', () => {
        renderPage(EditCard, {
            ...page,
            card: { type: 'subscription', id: 3, title: 'Hostinger' },
        });
        expect(
            screen.queryByRole('group', { name: 'Planned expense' }),
        ).not.toBeInTheDocument();
    });

    it('is not asked for a new card of another type', () => {
        renderPage(EditCard, {
            type: { ...type, type: 'insurance_policy' },
            fields: policyFields,
            values: { title: 'RC Auto', type: 'car' },
        });

        expect(
            screen.queryByRole('group', { name: 'Planned expense' }),
        ).not.toBeInTheDocument();
    });

    it('sends nothing of it without a cost', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditCard, page);
        await settle();

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        const body = post.mock.calls[0]![1] as Record<string, unknown>;
        expect(body).not.toHaveProperty('planned');
    });

    it('sends it with the card when there is a cost', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditCard, page);
        await settle();

        await fireEvent.update(screen.getByLabelText('Cost'), '35,88');
        await settle();
        await userEvent.click(
            screen.getByRole('switch', { name: 'I already paid it' }),
        );
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(post).toHaveBeenCalledWith(
            '/cards/subscription',
            expect.objectContaining({
                name: 'Hostinger',
                planned: {
                    amount: 35.88,
                    interval: 1,
                    interval_unit: 'month',
                    start_date: '2026-11-15',
                    category_id: null,
                    paid: true,
                },
            }),
            expect.anything(),
        );
    });
});

describe('With the Budget off', () => {
    const subscription: App.Data.CardTypeData = {
        type: 'subscription',
        label: 'Subscription',
        plural_label: 'Subscriptions',
        description: '',
        area: 'subscriptions',
        area_label: 'Subscriptions',
    };
    const policy: App.Data.CardTypeData = {
        type: 'insurance_policy',
        label: 'Insurance policy',
        plural_label: 'Policies',
        description: '',
        area: 'insurance',
        area_label: 'Insurance',
    };

    it('asks nothing of the planned expense of a new subscription, and sends none', async () => {
        shared.family = { features: { budget: false } };
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditCard, {
            type: subscription,
            fields: [
                {
                    column: 'name',
                    label: 'Name',
                    kind: 'text',
                    required: true,
                    options: [],
                    show_when: {},
                    label_when: {},
                },
            ],
            values: { name: 'Hostinger' },
        });
        await settle();

        expect(
            screen.queryByRole('group', { name: 'Planned expense' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('switch', { name: 'I already paid it' }),
        ).not.toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(post.mock.calls[0]![1]).not.toHaveProperty('planned');
    });

    it('does not say that the premium of the old policy moves', () => {
        shared.family = { features: { budget: false } };
        renderPage(EditCard, {
            type: policy,
            fields: policyFields,
            values: { title: 'RC Auto', type: 'car', vehicle_id: '1' },
            card: null,
            replaceable: [
                {
                    id: 7,
                    label: 'RC Auto · 40/123456 · UnipolSai',
                    expiry_date: '2026-11-21',
                    type: 'car',
                    vehicle_id: 1,
                    home_id: null,
                    employment_id: null,
                },
            ],
            replaces: [7],
        });

        expect(screen.getByText('Expires on 21/11/2026.')).toBeInTheDocument();
        expect(
            screen.queryByText(/Its planned expense moves to the new one/),
        ).not.toBeInTheDocument();
    });

    it('has no payments beside the form of a card', () => {
        shared.family = { features: { budget: false } };
        renderPage(EditCard, {
            type: policy,
            fields: policyFields,
            values: { title: 'RC auto Panda', type: 'car' },
            card: policyDetail.card,
            budget: null,
        });

        expect(screen.queryByTestId('card-payments')).not.toBeInTheDocument();
    });
});
