import { router } from '@inertiajs/vue3';
import { fireEvent, render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { formPlugins } from '@/lib/formPlugins';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import type { Component } from 'vue';
import { documentDetail, documentFilterOptions } from '@/test/fixtures';
import { choose, renderPage, screenWidth } from '@/test/page';
import Documents from './Documents.vue';

const shared = vi.hoisted(() => ({ to_sort_count: 0 }));
const address = vi.hoisted(() => ({ url: '/documents' }));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    usePage: () => ({ url: address.url, props: { ...shared } }),
}));

const none = {
    category: null,
    person: null,
    organization: null,
    status: null,
    expiring: false,
    search: null,
};

const payslip = {
    id: 5,
    title: 'Busta paga di ottobre',
    category: 'Lavoro',
    person: 'Laura',
    organization: 'Comune di Bologna',
    date: '2026-10-23',
    expiry_date: null,
    status: 'active',
    mime_type: 'application/pdf',
};

const props = {
    documents: [payslip],
    total: 18,
    nextPage: '/documents?page=2',
    previousPage: null,
    filters: none,
    hasDocuments: true,
    options: documentFilterOptions,
    selected: null,
    gallery: null,
};

afterEach(() => {
    shared.to_sort_count = 0;
    address.url = '/documents';
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('Documents', () => {
    it('shows the gallery card with count, people and date', () => {
        vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(Documents, {
            ...props,
            gallery: {
                count: 48,
                people: ['Giulia'],
                last_uploaded_on: '2026-11-14',
                thumbnails: ['/a.jpg', '/b.jpg', '/c.jpg', '/d.jpg'],
                url: '/documents/gallery',
            },
        });

        const card = screen.getByRole('link', { name: /Drawings and crafts/ });
        expect(card).toHaveAttribute('href', '/documents/gallery');
        expect(
            within(card).getByText('48 by Giulia · the last on 14/11/2026'),
        ).toBeInTheDocument();
        expect(card.querySelectorAll('img')).toHaveLength(4);
        expect(within(card).getByText('+44')).toBeInTheDocument();
    });

    it('hides it when gallery is null', () => {
        renderPage(Documents, props);

        expect(
            screen.queryByRole('link', { name: /Drawings and crafts/ }),
        ).not.toBeInTheDocument();
    });

    it('leads to the files to sort when there are some', () => {
        shared.to_sort_count = 3;
        renderPage(Documents, props);

        expect(
            screen.getByRole('link', { name: '3 files to sort' }),
        ).toHaveAttribute('href', '/to-sort');
    });

    it('does not mention the files to sort when there are none', () => {
        renderPage(Documents, props);

        expect(
            screen.queryByRole('link', { name: /to sort/ }),
        ).not.toBeInTheDocument();
    });

    it('lists the documents, the most recent first', () => {
        renderPage(Documents, props);

        expect(
            screen.getByRole('heading', { name: 'Documents' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('18 documents, the most recent first'),
        ).toBeInTheDocument();
        expect(screen.getByText('Busta paga di ottobre')).toBeInTheDocument();
        expect(
            screen.getByText('Lavoro · Laura · Comune di Bologna'),
        ).toBeInTheDocument();
        expect(screen.getByText('23/10/2026')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Next' })).toHaveAttribute(
            'href',
            '/documents?page=2',
        );
    });

    it('a filter narrows the list', async () => {
        screenWidth(1280);
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Documents, props);

        await choose('Person', 'Laura Bianchi');

        expect(get).toHaveBeenCalledWith(
            '/documents?person=7',
            {},
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('searches in the documents', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Documents, props);

        await fireEvent.update(
            screen.getByLabelText('Search a document'),
            'danza',
        );
        await fireEvent.submit(screen.getByRole('search'));

        expect(get).toHaveBeenCalledWith(
            '/documents?search=danza',
            {},
            expect.anything(),
        );
    });

    it('shows the filters in use, and removes one with the ×', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Documents, {
            ...props,
            filters: { ...none, person: 7, expiring: true },
        });

        await fireEvent.click(
            screen.getByRole('button', {
                name: 'Remove the filter Laura Bianchi',
            }),
        );

        expect(get).toHaveBeenCalledWith(
            '/documents?expiring=1',
            {},
            expect.anything(),
        );
    });

    it('says when no document matches the filters', () => {
        renderPage(Documents, {
            ...props,
            documents: [],
            total: 0,
            nextPage: null,
            filters: { ...none, search: 'xyz' },
        });

        expect(
            screen.getByText('No document matches the filters'),
        ).toBeInTheDocument();
    });

    it('without any document invites to upload the first one', () => {
        renderPage(Documents, {
            ...props,
            documents: [],
            total: 0,
            nextPage: null,
            hasDocuments: false,
        });

        expect(screen.getByText('No documents, for now')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Upload the first document' }),
        ).toHaveAttribute('href', '/upload');
    });

    it('on the computer shows the filters in a column from 1280 px, not only from 1440', () => {
        screenWidth(1280);
        renderPage(Documents, props);

        const aside = screen.getByRole('complementary', { name: 'Filters' });
        expect(aside).toHaveClass('sticky', 'top-(--app-header)');
        expect(
            screen.queryByRole('button', { name: /Filters/ }),
        ).not.toBeInTheDocument();
    });

    it('on the computer shows the chosen document in the left column in place of the filters', () => {
        screenWidth(1280);
        renderPage(Documents, { ...props, selected: documentDetail });

        const aside = screen.getByRole('complementary', {
            name: 'Busta paga di ottobre',
        });
        expect(
            within(aside).getByRole('heading', {
                name: 'Busta paga di ottobre',
                level: 2,
            }),
        ).toBeInTheDocument();
        expect(
            within(aside).getByRole('button', { name: 'Back to the filters' }),
        ).toBeInTheDocument();
        expect(
            within(aside).getByRole('link', {
                name: 'Open the page of the document',
            }),
        ).toBeInTheDocument();
        expect(screen.queryByLabelText('Person')).not.toBeInTheDocument();
        // no third column and no floating panel
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(screen.getAllByRole('complementary')).toHaveLength(1);
        expect(screen.getByRole('link', { current: 'page' })).toHaveTextContent(
            'Busta paga di ottobre',
        );
    });

    it('Back to the filters and the × leave the document, keeping filters and page', async () => {
        screenWidth(1280);
        address.url = '/documents?person=3&page=2&document=5';
        const visit = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Documents, {
            ...props,
            filters: { ...none, person: 3 },
            selected: documentDetail,
        });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Back to the filters' }),
        );
        await fireEvent.click(screen.getByRole('button', { name: 'Close' }));

        expect(visit).toHaveBeenCalledTimes(2);
        expect(visit).toHaveBeenNthCalledWith(
            1,
            '/documents?person=3&page=2',
            {},
            expect.objectContaining({
                preserveState: true,
                preserveScroll: true,
            }),
        );
    });

    it('on the computer opens a document beside the list without scrolling the list back to the top', async () => {
        screenWidth(1280);
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(Documents, props);

        await fireEvent.click(
            screen.getByRole('link', { name: /Busta paga di ottobre/ }),
        );

        expect(visit).toHaveBeenCalledWith(
            expect.stringContaining('document=5'),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('below the computer layout the filter button opens the sheet and a row leads to its page', async () => {
        screenWidth(false);
        renderPage(Documents, props);

        expect(screen.queryByRole('complementary')).not.toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Busta paga di ottobre/ }),
        ).toHaveAttribute('href', '/documents/5');

        await fireEvent.click(screen.getByRole('button', { name: /Filters/ }));

        expect(
            screen.getByRole('dialog', { name: 'Filters' }),
        ).toBeInTheDocument();
    });

    it('opening a row on page 2 keeps the page', async () => {
        screenWidth(1280);
        address.url = '/documents?person=3&page=2';
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(Documents, { ...props, filters: { ...none, person: 3 } });

        await fireEvent.click(
            screen.getByRole('link', { name: /Busta paga di ottobre/ }),
        );

        const url = JSON.stringify(visit.mock.calls[0][0]);
        expect(url).toContain('page=2');
        expect(url).toContain('person=3');
        expect(url).toContain('document=5');
    });

    it('the pills have Remove the filters, which takes all the filters away', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Documents, {
            ...props,
            filters: { ...none, person: 7, expiring: true },
        });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Remove the filters' }),
        );

        expect(get).toHaveBeenCalledWith('/documents', {}, expect.anything());
    });

    it('on a short window 1280 px wide, a row leads to the page of the document', () => {
        screenWidth(1280, 400);
        renderPage(Documents, props);

        expect(
            screen.queryByRole('link', { name: /Busta paga di ottobre/ }),
        ).toHaveAttribute('href', '/documents/5');
    });

    it('with filters on says how many were found beside the pills, as the other lists do', () => {
        renderPage(Documents, {
            ...props,
            filters: { ...none, person: 7 },
        });

        const found = screen.getByText('18 documents, the most recent first');
        expect(found).toHaveAttribute('aria-live', 'polite');
        expect(screen.getAllByText(/the most recent first/)).toHaveLength(1);
    });

    it('has the panel of the filters only below the computer layout', () => {
        const sheet = { template: '<div data-testid="filters-sheet" />' };

        for (const [computer, count] of [
            [true, 0],
            [false, 1],
        ] as const) {
            screenWidth(computer);
            // the props of the file are looser than the page's types, as renderPage takes them
            const view = render(Documents as Component, {
                props,
                global: {
                    plugins: formPlugins,
                    stubs: { FmBottomSheet: sheet },
                },
            });

            expect(view.queryAllByTestId('filters-sheet')).toHaveLength(count);
            view.unmount();
            vi.unstubAllGlobals();
        }
    });

    it('says on the phone whether the panel of the filters is open', async () => {
        renderPage(Documents, props);
        const button = screen.getByRole('button', { name: 'Filters' });
        expect(button).toHaveAttribute('aria-expanded', 'false');

        await userEvent.click(button);

        expect(button).toHaveAttribute('aria-expanded', 'true');
    });
});
