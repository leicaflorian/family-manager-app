import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
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
};

afterEach(() => {
    shared.to_sort_count = 0;
    address.url = '/documents';
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('Documents', () => {
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
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Documents, props);

        await choose('Person', 'Laura Bianchi');

        expect(get).toHaveBeenCalledWith(
            '/documents',
            { person: 7 },
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
            '/documents',
            { search: 'danza' },
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
            '/documents',
            { expiring: 1 },
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

    it('shows the chosen document on the right', () => {
        renderPage(Documents, { ...props, selected: documentDetail });

        expect(
            screen.getByRole('heading', {
                name: 'Busta paga di ottobre',
                level: 2,
            }),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { current: 'page' })).toHaveTextContent(
            'Busta paga di ottobre',
        );
        expect(screen.getByRole('link', { name: 'Download' })).toHaveAttribute(
            'title',
            'Download',
        );
        expect(
            screen.getByRole('button', { name: 'More actions' }),
        ).toBeInTheDocument();
    });
    it('from 1440 px shows the chosen document in a third column', () => {
        screenWidth(1440);
        renderPage(Documents, { ...props, selected: documentDetail });

        expect(
            screen.getByRole('heading', {
                name: 'Busta paga di ottobre',
                level: 2,
            }),
        ).toBeInTheDocument();
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Close' }),
        ).not.toBeInTheDocument();
        // sticky under the top bar of the layout, not under the top of the window
        expect(
            screen
                .getByRole('heading', {
                    name: 'Busta paga di ottobre',
                    level: 2,
                })
                .closest('section'),
        ).toHaveClass('sticky', 'top-(--app-header)');
    });

    it('narrower, shows it in a floating panel that the × closes, keeping filters and page', async () => {
        screenWidth(1280);
        address.url = '/documents?person=3&page=2&document=5';
        const visit = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Documents, {
            ...props,
            filters: { ...none, person: 3 },
            selected: documentDetail,
        });

        const panel = screen.getByRole('dialog', {
            name: 'Busta paga di ottobre',
        });
        expect(panel).toHaveTextContent('Lavoro › Buste paga');

        await fireEvent.click(screen.getByRole('button', { name: 'Close' }));

        expect(visit).toHaveBeenCalledWith(
            '/documents?person=3&page=2',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});
