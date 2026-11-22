import { router } from '@inertiajs/vue3';
import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import FmDocumentRow from './FmDocumentRow.vue';

const document: App.Data.DocumentData = {
    id: 5,
    title: 'Tessera sanitaria',
    category: 'Anagrafica e pratiche',
    person: 'Giulia',
    organization: null,
    date: null,
    expiry_date: '2026-10-20',
    status: 'active',
    mime_type: 'application/pdf',
    file_url: '/files/5',
    download_url: '/files/5/download',
    pinned: false,
    can_edit: true,
    can_pin: true,
};

describe('FmDocumentRow', () => {
    it('leads to the document, with what it is and its date', () => {
        render(FmDocumentRow, {
            props: {
                document: { ...document, date: '2020-10-20' },
                href: '/documents/5',
            },
        });

        const link = screen.getByRole('link');
        expect(link).toHaveAttribute('href', '/documents/5');
        expect(link).toHaveTextContent('Tessera sanitaria');
        expect(link).toHaveTextContent('Anagrafica e pratiche · Giulia');
        expect(link).toHaveTextContent('20/10/2020');
    });

    it('without a date shows the expiry', () => {
        render(FmDocumentRow, { props: { document, href: '/documents/5' } });

        expect(screen.getByRole('link')).toHaveTextContent(
            'expires 20/10/2026',
        );
    });

    it('with expiry shows the expiry in its colour: red once passed', () => {
        render(FmDocumentRow, {
            props: {
                document,
                href: '/documents/5',
                expiry: true,
                today: '2026-10-27',
            },
        });

        const subtitle = screen.getByText('Expired on 20/10/2026');
        expect(subtitle).toHaveClass('text-overdue');
    });

    it('with expiry, a date still ahead is orange', () => {
        render(FmDocumentRow, {
            props: {
                document: { ...document, expiry_date: '2026-12-11' },
                href: '/documents/5',
                expiry: true,
                today: '2026-10-27',
            },
        });

        expect(screen.getByText('Expires on 11/12/2026')).toHaveClass(
            'text-warning',
        );
    });

    it('without href shows the document without leading anywhere', () => {
        render(FmDocumentRow, { props: { document } });

        expect(screen.queryByRole('link')).not.toBeInTheDocument();
        expect(screen.getByText('Tessera sanitaria')).toBeInTheDocument();
    });

    it('with dateless leaves the date on the right out', () => {
        render(FmDocumentRow, {
            props: {
                document: { ...document, date: '2020-10-20' },
                href: '/documents/5',
                dateless: true,
            },
        });

        const link = screen.getByRole('link');
        expect(link).not.toHaveTextContent('20/10/2020');
        // Nor the arrow a row without anything on the right would get
        expect(link.querySelectorAll('svg')).toHaveLength(1);
    });

    it('marks a pinned document with a pin and a text for screen readers', () => {
        render(FmDocumentRow, {
            props: { document: { ...document, pinned: true }, href: '/d' },
        });

        expect(screen.getByText('Favourite')).toHaveClass('sr-only');
        // file tile and pin: the date takes the place of the arrow, as on an unpinned row
        expect(screen.getByRole('link').querySelectorAll('svg')).toHaveLength(
            2,
        );
    });

    it('keeps the arrow on a pinned row with the expiry line', () => {
        render(FmDocumentRow, {
            props: {
                document: { ...document, pinned: true },
                href: '/d',
                expiry: true,
            },
        });

        const link = screen.getByRole('link');
        expect(screen.getByText('Favourite')).toBeInTheDocument();
        // file tile, pin, arrow
        expect(link.querySelectorAll('svg')).toHaveLength(3);
    });

    it('shows the pin without an arrow when dateless', () => {
        render(FmDocumentRow, {
            props: {
                document: { ...document, pinned: true },
                href: '/d',
                dateless: true,
            },
        });

        expect(screen.getByText('Favourite')).toBeInTheDocument();
        expect(screen.getByRole('link').querySelectorAll('svg')).toHaveLength(
            2,
        );
    });

    it('has no pin marker when not pinned', () => {
        render(FmDocumentRow, { props: { document, href: '/d' } });

        expect(screen.queryByText('Favourite')).toBeNull();
    });

    it('with the menu, highlights the whole wrapper when current', () => {
        const { container } = render(FmDocumentRow, {
            props: { document, href: '/d', menu: true, current: true },
        });

        const wrapper = container.firstElementChild as HTMLElement;
        expect(wrapper).toHaveClass('bg-action-soft');
        const link = screen.getByRole('link');
        expect(link).toHaveAttribute('aria-current', 'page');
        expect(link).not.toHaveClass('bg-action-soft');
    });

    it('with the menu, does not highlight the wrapper when not current', () => {
        const { container } = render(FmDocumentRow, {
            props: { document, href: '/d', menu: true },
        });

        expect(container.firstElementChild).not.toHaveClass('bg-action-soft');
    });

    describe('the ⋯ menu', () => {
        const props = { document, href: '/documents/5', menu: true };
        const button = (): HTMLElement =>
            screen.getByRole('button', {
                name: 'More actions for «Tessera sanitaria»',
            });

        afterEach(() => {
            vi.restoreAllMocks();
        });

        it('has no button without menu', () => {
            render(FmDocumentRow, {
                props: { document, href: '/documents/5' },
            });

            expect(screen.queryByRole('button')).not.toBeInTheDocument();
        });

        it('sits beside the link, never inside it, and does not open the document', async () => {
            const visit = vi
                .spyOn(router, 'visit')
                .mockImplementation(() => {});
            render(FmDocumentRow, { props });

            expect(button().closest('a')).toBeNull();
            await userEvent.click(button());

            expect(visit).not.toHaveBeenCalled();
        });

        it('lists Pin, Download, Open in a new tab and Edit in that order', async () => {
            render(FmDocumentRow, { props });
            await userEvent.click(button());

            const items = await screen.findAllByRole('menuitem');
            expect(items.map((item) => item.textContent?.trim())).toEqual([
                'Mark as favourite',
                'Download',
                'Open in a new tab',
                'Edit',
            ]);
            expect(items[1]).toHaveAttribute('href', '/files/5/download');
            expect(items[1]).toHaveAttribute('download');
            expect(items[2]).toHaveAttribute('href', '/files/5');
            expect(items[2]).toHaveAttribute('target', '_blank');
            expect(items[3]).toHaveAttribute('href', '/documents/5/edit');
        });

        it('pins with a PUT', async () => {
            const put = vi.spyOn(router, 'put').mockImplementation(() => {});
            render(FmDocumentRow, { props });
            await userEvent.click(button());
            await userEvent.click(
                await screen.findByRole('menuitem', {
                    name: 'Mark as favourite',
                }),
            );

            expect(put).toHaveBeenCalledWith(
                '/documents/5/pin',
                {},
                { preserveScroll: true },
            );
        });

        it('unpins a pinned document with a DELETE', async () => {
            const del = vi.spyOn(router, 'delete').mockImplementation(() => {});
            render(FmDocumentRow, {
                props: { ...props, document: { ...document, pinned: true } },
            });
            await userEvent.click(button());
            await userEvent.click(
                await screen.findByRole('menuitem', {
                    name: 'Remove from favourites',
                }),
            );

            expect(del).toHaveBeenCalledWith('/documents/5/pin', {
                preserveScroll: true,
            });
        });

        it('leaves out Pin without can_pin and Edit without can_edit', async () => {
            render(FmDocumentRow, {
                props: {
                    ...props,
                    document: {
                        ...document,
                        can_pin: false,
                        can_edit: false,
                    },
                },
            });
            await userEvent.click(button());

            const items = await screen.findAllByRole('menuitem');
            expect(items.map((item) => item.textContent?.trim())).toEqual([
                'Download',
                'Open in a new tab',
            ]);
        });
    });
});
