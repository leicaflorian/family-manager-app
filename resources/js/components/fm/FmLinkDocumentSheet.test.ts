import { router } from '@inertiajs/vue3';
import { screen, waitFor } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { healthCard } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import FmLinkDocumentSheet from './FmLinkDocumentSheet.vue';

const booklet = { ...healthCard, id: 30, title: 'Libretto di circolazione' };

afterEach(() => {
    vi.restoreAllMocks();
});

describe('FmLinkDocumentSheet', () => {
    it('gives the document chosen and closes', async () => {
        const onPick = vi.fn();
        const onOpen = vi.fn();
        renderPage(FmLinkDocumentSheet, {
            open: true,
            linkable: [booklet],
            onPick,
            'onUpdate:open': onOpen,
        });

        await userEvent.click(
            await screen.findByRole('button', {
                name: /Libretto di circolazione/,
            }),
        );

        expect(onPick).toHaveBeenCalledWith(booklet);
        expect(onOpen).toHaveBeenCalledWith(false);
    });

    it('leaves out the documents already chosen', async () => {
        renderPage(FmLinkDocumentSheet, {
            open: true,
            linkable: [booklet, healthCard],
            hidden: [booklet.id],
        });

        expect(
            await screen.findByRole('button', { name: /Tessera sanitaria/ }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /Libretto di circolazione/ }),
        ).not.toBeInTheDocument();
    });

    it('has no arrow on the rows, only the ↗ beside them', async () => {
        renderPage(FmLinkDocumentSheet, { open: true, linkable: [booklet] });

        const row = await screen.findByRole('button', {
            name: /Libretto di circolazione/,
        });
        expect(row.querySelectorAll('svg')).toHaveLength(0);
    });

    it('opens each document in a new tab, with the button beside the documents of a card', async () => {
        renderPage(FmLinkDocumentSheet, { open: true, linkable: [booklet] });

        const open = await screen.findByRole('link', {
            name: 'Open Libretto di circolazione in a new tab',
        });
        expect(open).toHaveAttribute('href', booklet.file_url);
        expect(open).toHaveAttribute('target', '_blank');
        expect(open).toHaveClass('size-10', 'bg-transparent');
    });

    it('searches while typing, from the first page, keeping the address', async () => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        renderPage(FmLinkDocumentSheet, { open: true, linkable: [booklet] });

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

    it('says when nothing is found', async () => {
        renderPage(FmLinkDocumentSheet, {
            open: true,
            linkable: [booklet],
            hidden: [booklet.id],
            linkableSearch: 'zzz',
        });

        expect(
            await screen.findByText('No document found.'),
        ).toBeInTheDocument();
    });
});
