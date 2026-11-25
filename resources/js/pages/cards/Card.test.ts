import { router } from '@inertiajs/vue3';
import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    healthCard,
    luceBudget,
    luceRow,
    pandaDetail,
    policyBudget,
    policyDetail,
} from '@/test/fixtures';
import { formatEuro, formatQuantity } from '@/lib/format';
import { renderPage, withTrail } from '@/test/page';
import Card from './Card.vue';

afterEach(() => {
    vi.restoreAllMocks();
});

describe('A card', () => {
    it('writes the trail Areas > area > card into the top bar', () => {
        const { state, provide } = withTrail();
        renderPage(Card, { card: policyDetail, linkable: [] }, provide);

        expect(state.pieces.value).toEqual([
            { label: 'Areas', href: '/areas' },
            { label: 'Insurance', href: '/areas/insurance' },
            { label: 'RC auto Panda - UnipolSai' },
        ]);
    });

    it('shows the header, the dates, the data, the documents and the linked cards', () => {
        renderPage(Card, { card: policyDetail, linkable: [] });

        expect(
            screen.getByRole('heading', {
                name: 'RC auto Panda - UnipolSai',
                level: 1,
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Car · policyholder Marco Bianchi'),
        ).toBeInTheDocument();
        expect(screen.getByText('20/11/2026')).toBeInTheDocument();
        // The status is a green figure beside the dates
        expect(screen.getByText('Status')).toBeInTheDocument();
        expect(screen.getByText('Active')).toHaveClass('text-paid');
        expect(
            screen.getByRole('heading', { name: 'Data' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Documents' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Fiat Panda/ }),
        ).toHaveAttribute('href', '/cards/vehicle/1');
    });

    it('links to all the linked cards, with their number, when there are more than the ones shown', () => {
        renderPage(Card, {
            card: {
                ...policyDetail,
                related_more: { href: '/cards/vehicle/1/related', count: 7 },
            },
            linkable: [],
        });

        expect(
            screen.getByRole('link', { name: 'See all the links (7)' }),
        ).toHaveAttribute('href', '/cards/vehicle/1/related');
    });

    it('has no link to all the linked cards when they are all shown', () => {
        renderPage(Card, { card: policyDetail, linkable: [] });

        expect(
            screen.queryByRole('link', { name: /See all the links/ }),
        ).not.toBeInTheDocument();
    });

    it('offers the cards that can be added linked to this one', async () => {
        renderPage(Card, {
            card: {
                ...policyDetail,
                related: [],
                addable: [
                    {
                        type: 'maintenance_record',
                        label: 'Maintenance job',
                        href: '/cards/create?card=maintenance_record&target=vehicle&vehicle_id=1',
                    },
                    {
                        type: 'insurance_policy',
                        label: 'Policy',
                        href: '/cards/create?card=insurance_policy&type=car&vehicle_id=1',
                    },
                ],
            },
            linkable: [],
        });

        expect(
            screen.getByRole('heading', { name: 'Linked to' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Nothing linked yet.')).toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: 'Add' }));

        expect(
            await screen.findByRole('menuitem', { name: 'Maintenance job' }),
        ).toHaveAttribute(
            'href',
            '/cards/create?card=maintenance_record&target=vehicle&vehicle_id=1',
        );
        expect(
            screen.getByRole('menuitem', { name: 'Policy' }),
        ).toHaveAttribute(
            'href',
            '/cards/create?card=insurance_policy&type=car&vehicle_id=1',
        );
    });

    it('offers nothing to add, and hides Linked to when empty, to whoever cannot create', () => {
        renderPage(Card, {
            card: { ...policyDetail, related: [], addable: [] },
            linkable: [],
        });

        expect(
            screen.queryByRole('heading', { name: 'Linked to' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Add' }),
        ).not.toBeInTheDocument();
    });

    it('shows the notices and the values that lead to another card', () => {
        renderPage(Card, {
            card: {
                ...policyDetail,
                notices: [
                    {
                        key: 'n',
                        tone: 'warning',
                        title: '',
                        text: 'Expires in 26 days',
                        action_label: null,
                        action_href: null,
                    },
                ],
                values: [
                    {
                        label: 'Vehicle',
                        text: 'Fiat Panda',
                        href: '/cards/vehicle/1',
                    },
                ],
            },
            linkable: [],
        });

        expect(screen.getByText('Expires in 26 days')).toBeInTheDocument();
        const panda = screen.getByRole('link', { name: 'Fiat Panda' });
        expect(panda).toHaveAttribute('href', '/cards/vehicle/1');
        expect(panda).toHaveAttribute('target', '_blank');
        expect(panda).toHaveClass('text-action');
    });

    it('shows a notice with its title, its text and its action', () => {
        renderPage(Card, {
            card: {
                ...policyDetail,
                notices: [
                    {
                        key: 'deadline',
                        tone: 'warning',
                        title: 'Expires in 26 days',
                        text: 'The reminder went out on 07/10/2026.',
                        action_label: null,
                        action_href: null,
                    },
                    {
                        key: 'document:1',
                        tone: 'danger',
                        title: 'The certificate expired on 20/10/2026',
                        text: null,
                        action_label: 'Upload the new one',
                        action_href: '/upload?link=policy%3A1',
                    },
                ],
            },
            linkable: [],
        });

        const [soon] = screen.getAllByRole('status');
        const late = screen.getByRole('alert');
        expect(
            within(soon).getByText('Expires in 26 days'),
        ).toBeInTheDocument();
        expect(
            within(soon).getByText('The reminder went out on 07/10/2026.'),
        ).toBeInTheDocument();
        expect(within(soon).queryByRole('link')).not.toBeInTheDocument();
        expect(late).toHaveClass('text-overdue');
        expect(
            within(late).getByRole('link', { name: 'Upload the new one' }),
        ).toHaveAttribute('href', '/upload?link=policy%3A1');
    });

    it('shows a notice that is only a text without a title', () => {
        renderPage(Card, {
            card: {
                ...policyDetail,
                notices: [
                    {
                        key: 'n',
                        tone: 'info',
                        title: '',
                        text: 'Only a text.',
                        action_label: null,
                        action_href: null,
                    },
                ],
            },
            linkable: [],
        });

        const [notice] = screen.getAllByRole('status');
        expect(notice.querySelector('strong')).toBeNull();
        expect(within(notice).getByText('Only a text.')).toBeInTheDocument();
    });

    it('shows nothing of the budget', () => {
        renderPage(Card, { card: policyDetail, linkable: [] });

        for (const text of [
            'How much you pay',
            'Planned expenses',
            'Payments',
            'Record payment',
        ]) {
            expect(screen.queryByText(text)).not.toBeInTheDocument();
        }
    });

    it('shows no payments nor consumption of a utility with the Budget off', () => {
        renderPage(Card, {
            card: {
                ...policyDetail,
                card: luceRow,
                heading: 'Electricity · Casa di via Roma',
                dates: [],
                budget: null,
                consumption: null,
            },
            linkable: [],
        });

        for (const text of [
            'To pay',
            'Expected expenses',
            'Bills paid',
            'Record payment',
            'See the consumption',
        ]) {
            expect(screen.queryByText(text)).not.toBeInTheDocument();
        }
        expect(screen.getByText('Data')).toBeInTheDocument();
    });

    it('shows the instalment, the total and the instalments past of a loan with the Budget off, without payments', () => {
        renderPage(Card, {
            card: {
                ...policyDetail,
                heading: 'Loan · BPER Banca',
                dates: [],
                budget: {
                    kind: 'expense',
                    next: null,
                    next_label: '',
                    installment_number: null,
                    installments_count: 240,
                    installments_paid: 23,
                    paid_amount: 14950,
                    left_amount: 141050,
                    numbers: [
                        {
                            label: 'Instalment',
                            amount: 650,
                            count: null,
                            hint: null,
                            hint_amount: null,
                            text: null,
                            tone: null,
                            estimated: false,
                        },
                        {
                            label: 'Total to pay',
                            amount: 156000,
                            count: null,
                            hint: null,
                            hint_amount: null,
                            text: null,
                            tone: null,
                            estimated: false,
                        },
                    ],
                    due_label: null,
                    due: [],
                    planned_label: null,
                    planned: [],
                    can_add_planned: false,
                    payments_label: null,
                    payments_more: '',
                    payments: [],
                    pay: [],
                    can_pay: false,
                    can_add_expense: false,
                    payments_previous: null,
                    payments_next: null,
                },
            },
            linkable: [],
        });

        expect(screen.getByText('Instalment')).toBeInTheDocument();
        expect(screen.getByText('Total to pay')).toBeInTheDocument();
        expect(
            screen.getByRole('progressbar', { name: 'Instalments paid' }),
        ).toHaveAttribute('aria-valuenow', '23');
        expect(screen.getByText('23 of 240')).toBeInTheDocument();
        for (const text of [
            'Next instalment',
            'Expected expenses',
            'Instalments paid in the year',
            'Record payment',
        ]) {
            expect(screen.queryByText(text)).not.toBeInTheDocument();
        }
    });

    it('has the documents as a section, with Link document and Upload as plain links', () => {
        renderPage(Card, {
            card: policyDetail,
            linkable: [],
            hasLinkable: true,
        });

        const documents = screen.getByRole('region', { name: 'Documents' });
        for (const name of ['Link document', 'Upload']) {
            expect(within(documents).getByRole('button', { name })).toHaveClass(
                'text-body',
                'hover:underline',
            );
        }
    });

    it('uploads a document already linked to the card, from the panel', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Card, { card: policyDetail, linkable: [] });

        await userEvent.click(screen.getByRole('button', { name: 'Upload' }));
        const file = new File(['%PDF-1.4'], 'Polizza.pdf', {
            type: 'application/pdf',
        });
        await fireEvent.change(
            await screen.findByLabelText('Choose the files'),
            { target: { files: [file] } },
        );

        expect(
            screen.getByRole('dialog', { name: 'Upload a document' }),
        ).toBeInTheDocument();
        expect(decodeURIComponent(`${post.mock.calls[0]?.[0] as string}`)).toBe(
            '/upload?link=insurance_policy:1',
        );
    });

    it('links a document already uploaded', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Card, {
            card: policyDetail,
            linkable: [
                { ...healthCard, id: 30, title: 'Libretto di circolazione' },
            ],
            hasLinkable: true,
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Link document' }),
        );
        await userEvent.click(
            await screen.findByRole('button', {
                name: /Libretto di circolazione/,
            }),
        );

        expect(post).toHaveBeenCalledWith(
            '/cards/insurance_policy/1/documents',
            { document_id: 30 },
            expect.anything(),
        );
    });

    it('opens each document to link in a new tab', async () => {
        renderPage(Card, {
            card: policyDetail,
            linkable: [{ ...healthCard, id: 30, title: 'Libretto' }],
            hasLinkable: true,
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Link document' }),
        );
        const open = await screen.findByRole('link', {
            name: 'Open Libretto in a new tab',
        });

        expect(open).toHaveAttribute('href', healthCard.file_url);
        expect(open).toHaveAttribute('target', '_blank');
    });

    it('searches the documents to link while typing', async () => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        renderPage(Card, {
            card: policyDetail,
            linkable: [{ ...healthCard, id: 30, title: 'Libretto' }],
            linkableNext: null,
            linkableSearch: '',
            hasLinkable: true,
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Link document' }),
        );
        await userEvent.type(
            await screen.findByRole('searchbox', { name: 'Search a document' }),
            'gas',
        );

        await waitFor(() =>
            expect(reload).toHaveBeenCalledWith(
                expect.objectContaining({
                    data: { link_search: 'gas', link_page: 1 },
                    reset: ['linkable'],
                    preserveUrl: true,
                }),
            ),
        );
    });

    it('says when the search finds no document to link', async () => {
        renderPage(Card, {
            card: policyDetail,
            linkable: [],
            linkableNext: null,
            linkableSearch: 'zzz',
            hasLinkable: true,
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Link document' }),
        );

        expect(
            await screen.findByText('No document found.'),
        ).toBeInTheDocument();
    });

    it('loads the next documents to link when the end of the list shows', async () => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        vi.stubGlobal(
            'IntersectionObserver',
            class {
                constructor(
                    private readonly callback: (
                        entries: { isIntersecting: boolean }[],
                    ) => void,
                ) {}
                observe(): void {
                    this.callback([{ isIntersecting: true }]);
                }
                disconnect(): void {}
            },
        );
        renderPage(Card, {
            card: policyDetail,
            linkable: [{ ...healthCard, id: 30, title: 'Libretto' }],
            linkableNext: 2,
            linkableSearch: 'li',
            hasLinkable: true,
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Link document' }),
        );

        await waitFor(() =>
            expect(reload).toHaveBeenCalledWith(
                expect.objectContaining({
                    data: { link_search: 'li', link_page: 2 },
                    preserveUrl: true,
                }),
            ),
        );
        vi.unstubAllGlobals();
    });

    it('unlinks a document from the card', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(Card, { card: policyDetail, linkable: [] });

        await userEvent.click(
            screen.getByRole('button', {
                name: `More actions on ${healthCard.title}`,
            }),
        );
        await userEvent.click(
            await screen.findByRole('menuitem', { name: 'Unlink' }),
        );
        await userEvent.click(screen.getByRole('button', { name: 'Unlink' }));

        expect(destroy).toHaveBeenCalledWith(
            `/cards/insurance_policy/1/documents/${healthCard.id}`,
            expect.anything(),
        );
    });

    it('lets only an admin edit and delete', () => {
        renderPage(Card, {
            card: { ...policyDetail, can_edit: false, can_delete: false },
            linkable: [],
        });

        expect(
            screen.queryByRole('link', { name: 'Edit' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Delete' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Upload' }),
        ).toBeInTheDocument();
    });

    it('edits with the link of the header', () => {
        renderPage(Card, { card: policyDetail, linkable: [] });

        expect(screen.getByRole('link', { name: 'Edit' })).toHaveAttribute(
            'href',
            '/cards/insurance_policy/1/edit',
        );
    });

    it('deletes from the ⋯ menu after asking', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(Card, { card: policyDetail, linkable: [] });

        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );
        await userEvent.click(
            await screen.findByRole('menuitem', { name: 'Delete' }),
        );
        expect(destroy).not.toHaveBeenCalled();
        await fireEvent.click(
            await screen.findByRole('button', { name: 'Delete the card' }),
        );

        expect(destroy).toHaveBeenCalledWith(
            '/cards/insurance_policy/1',
            expect.anything(),
        );
    });

    describe('with its payments', () => {
        const luceDetail: App.Data.CardDetailData = {
            ...policyDetail,
            card: luceRow,
            heading: 'Electricity · Casa di via Roma',
            dates: [],
            budget: luceBudget,
        };

        it('shows the sections of the electricity in the order of the board', () => {
            renderPage(Card, { card: luceDetail, linkable: [] });

            expect(
                screen
                    .getAllByRole('heading', { level: 2 })
                    .map((heading) => heading.textContent?.trim()),
            ).toEqual([
                'To pay',
                'Data',
                'Linked to',
                'Documents',
                'Expected expenses',
                'Bills paid',
            ]);
            expect(screen.getByText('9 bills')).toBeInTheDocument();
        });

        it('links to all the documents when the card has more than the 5 shown, with no pager', () => {
            renderPage(Card, {
                card: {
                    ...luceDetail,
                    documents_more: '/documents?card=utility%3A3',
                },
                linkable: [],
            });

            expect(
                screen.getByRole('link', { name: 'See all the documents' }),
            ).toHaveAttribute('href', '/documents?card=utility%3A3');
            expect(
                screen.queryByRole('link', { name: 'Next' }),
            ).not.toBeInTheDocument();
        });

        it('has no link to all the documents when it has no more', () => {
            renderPage(Card, { card: luceDetail, linkable: [] });

            expect(
                screen.queryByRole('link', { name: 'See all the documents' }),
            ).not.toBeInTheDocument();
        });

        it('adds a planned expense already linked to the card', () => {
            renderPage(Card, { card: luceDetail, linkable: [] });

            expect(screen.getByRole('link', { name: 'Add' })).toHaveAttribute(
                'href',
                '/budget/planned/expense/create?card=utility%3A3',
            );
        });

        it('shows the last 12 months of consumption under the payments, with the link to the page', () => {
            renderPage(Card, {
                card: {
                    ...luceDetail,
                    consumption: {
                        quantity: 2711,
                        unit: 'kwh',
                        price: 0.31,
                        href: '/budget/consumption?utility=3',
                    },
                },
                linkable: [],
            });

            expect(
                screen
                    .getAllByRole('heading', { level: 2 })
                    .map((heading) => heading.textContent?.trim())
                    .at(-1),
            ).toBe('Usage');
            expect(screen.getByText('Last 12 months')).toBeInTheDocument();
            expect(
                screen.getByText(
                    `${formatQuantity(2711, 'kwh')} · ${formatEuro(0.31)} per kWh`,
                ),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('link', { name: 'See the consumption' }),
            ).toHaveAttribute('href', '/budget/consumption?utility=3');
        });

        it('writes only the quantity when the price is unknown', () => {
            renderPage(Card, {
                card: {
                    ...luceDetail,
                    consumption: {
                        quantity: 2711,
                        unit: 'kwh',
                        price: null,
                        href: '/budget/consumption?utility=3',
                    },
                },
                linkable: [],
            });

            expect(
                screen.getByText(formatQuantity(2711, 'kwh')),
            ).toBeInTheDocument();
        });

        it('has no consumption without it', () => {
            renderPage(Card, { card: luceDetail, linkable: [] });

            expect(
                screen.queryByText('Last 12 months'),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByRole('link', { name: 'See the consumption' }),
            ).not.toBeInTheDocument();
        });

        it('keeps the date tiles beside the figures', () => {
            renderPage(Card, {
                card: { ...policyDetail, budget: policyBudget },
                linkable: [],
            });

            expect(screen.getByText('Premium')).toBeInTheDocument();
            expect(screen.getByText('20/11/2026')).toBeInTheDocument();
            expect(
                screen.getByRole('link', { name: /Renewal/ }),
            ).toBeInTheDocument();
        });
    });
});

describe('The card of a vehicle', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('lists its deadlines, with how far they are, instead of the tiles of the dates', () => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-10-26T10:00:00+01:00'));
        renderPage(Card, { card: pandaDetail, linkable: [] });

        const deadlines = screen.getByRole('region', { name: 'Deadlines' });
        expect(
            within(deadlines).getByRole('link', { name: /Carburante/ }),
        ).toHaveAttribute('href', '/budget/planned/expense/11');
        expect(
            within(deadlines).getByText('01/11/2026 · in 6 days · every month'),
        ).toBeInTheDocument();
        expect(
            within(deadlines).getByText(`~${formatEuro(80)}`),
        ).toBeInTheDocument();
        expect(
            within(deadlines).getByText(
                '21/11/2026 · in 26 days · RC auto Panda',
            ),
        ).toBeInTheDocument();
        expect(
            within(deadlines).getByText(formatEuro(418)),
        ).toBeInTheDocument();
        expect(
            within(deadlines).getByRole('link', {
                name: /Inspection.*Add the expense/,
            }),
        ).toHaveAttribute(
            'href',
            '/budget/planned/expense/create?card=vehicle%3A1&deadline=vehicles.inspection_due_date',
        );
        expect(
            within(deadlines).getByRole('link', {
                name: /Road tax.*Add the date/,
            }),
        ).toHaveAttribute('href', '/cards/vehicle/1/edit');
        expect(
            within(deadlines).getByText(
                'The date of the next deadline is missing',
            ),
        ).toBeInTheDocument();
    });

    it('puts its status first in the data, green, with no box of figures', () => {
        renderPage(Card, { card: pandaDetail, linkable: [] });

        const data = screen.getByRole('region', { name: 'Data' });
        expect(within(data).getByText('Active')).toHaveClass('text-paid');
        expect(screen.queryByText('Next due date')).not.toBeInTheDocument();
    });

    it('writes the deadlines as plain rows for whoever cannot add what is missing', () => {
        renderPage(Card, {
            card: {
                ...pandaDetail,
                deadlines: (pandaDetail.deadlines ?? []).map((row) => ({
                    ...row,
                    add_expense_href: null,
                    add_date_href: null,
                })),
            },
            linkable: [],
        });

        const deadlines = screen.getByRole('region', { name: 'Deadlines' });
        expect(within(deadlines).getByText('Inspection')).toBeInTheDocument();
        expect(
            within(deadlines).queryByText('Add the expense'),
        ).not.toBeInTheDocument();
        expect(
            within(deadlines).queryByText('Add the date'),
        ).not.toBeInTheDocument();
        expect(within(deadlines).getAllByRole('link')).toHaveLength(2);
    });

    it('has no section of deadlines on the other cards', () => {
        renderPage(Card, { card: policyDetail, linkable: [] });

        expect(
            screen.queryByRole('region', { name: 'Deadlines' }),
        ).not.toBeInTheDocument();
    });
});
