import { router } from '@inertiajs/vue3';
import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    healthCard,
    luceBudget,
    luceRow,
    policyBudget,
    policyDetail,
} from '@/test/fixtures';
import { renderPage } from '@/test/page';
import Card from './Card.vue';

afterEach(() => {
    vi.restoreAllMocks();
});

describe('A card', () => {
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

    it('goes back to the area', () => {
        renderPage(Card, { card: policyDetail, linkable: [] });

        expect(screen.getByRole('link', { name: 'Insurance' })).toHaveAttribute(
            'href',
            '/areas/insurance',
        );
    });

    it('shows the notices and the values that lead to another card', () => {
        renderPage(Card, {
            card: {
                ...policyDetail,
                notices: [
                    {
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
        expect(panda).toHaveClass('hover:text-action');
    });

    it('shows a notice with its title, its text and its action', () => {
        renderPage(Card, {
            card: {
                ...policyDetail,
                notices: [
                    {
                        tone: 'warning',
                        title: 'Expires in 26 days',
                        text: 'The reminder went out on 07/10/2026.',
                        action_label: null,
                        action_href: null,
                    },
                    {
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

        const [soon, late] = screen.getAllByRole('status');
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

    it('has the documents as a section, with Link document and Upload as plain links', () => {
        renderPage(Card, {
            card: policyDetail,
            linkable: [],
            hasLinkable: true,
        });

        const documents = screen.getByRole('region', { name: 'Documents' });
        for (const name of ['Link document', 'Upload']) {
            expect(within(documents).getByRole('button', { name })).toHaveClass(
                'font-normal',
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

        it('shows the pages of the documents, 5 at a time', () => {
            renderPage(Card, {
                card: {
                    ...luceDetail,
                    documents_next: '/cards/utility/3?documents_page=2',
                },
                linkable: [],
            });

            expect(screen.getByRole('link', { name: 'Next' })).toHaveAttribute(
                'href',
                '/cards/utility/3?documents_page=2',
            );
        });

        it('adds a planned expense already linked to the card', () => {
            renderPage(Card, { card: luceDetail, linkable: [] });

            expect(screen.getByRole('link', { name: 'Add' })).toHaveAttribute(
                'href',
                '/budget/planned/expense/create?card=utility%3A3',
            );
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
