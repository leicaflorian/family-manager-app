import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { healthCard, policyDetail } from '@/test/fixtures';
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
        await userEvent.click(
            screen.getByRole('button', { name: 'Add a linked card' }),
        );

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
            screen.queryByRole('button', { name: 'Add a linked card' }),
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
                notices: ['Expires in 26 days'],
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
        expect(
            screen.getByRole('link', { name: 'Fiat Panda' }),
        ).toHaveAttribute('href', '/cards/vehicle/1');
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

    it('uploads a document already linked to the card', () => {
        renderPage(Card, { card: policyDetail, linkable: [] });

        expect(screen.getByRole('link', { name: 'Upload' })).toHaveAttribute(
            'href',
            '/upload?link=insurance_policy%3A1',
        );
    });

    it('links a document already uploaded', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Card, {
            card: policyDetail,
            linkable: [
                { ...healthCard, id: 30, title: 'Libretto di circolazione' },
            ],
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

    it('unlinks a document from the card', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(Card, { card: policyDetail, linkable: [] });

        await userEvent.click(
            screen.getByRole('button', {
                name: `Unlink ${healthCard.title}`,
            }),
        );

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
            screen.getByRole('link', { name: 'Upload' }),
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
});
