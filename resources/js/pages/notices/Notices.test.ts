import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import type { MockInstance } from 'vite-plus/test';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { seenNotices, unreadNotices } from '@/test/fixtures';
import { renderPage, withTrail } from '@/test/page';
import Notices from './Notices.vue';

const shared = vi.hoisted(() => ({ unread_notices: 2 }));

// The shared props of the page, and no <Head> as in `setup.ts`
vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    usePage: () => ({ url: '/notices', props: { ...shared } }),
}));

let post: MockInstance<typeof router.post>;

beforeEach(() => {
    post = vi.spyOn(router, 'post').mockImplementation(() => undefined);
    shared.unread_notices = 2;
});

afterEach(() => {
    vi.restoreAllMocks();
});

/** The first page of Marco, as the server sends it. */
const props = {
    unread: unreadNotices,
    seen: seenNotices,
    search: '',
    nextPage: null,
    previousPage: null,
    hasNotices: true,
};

describe('Notices', () => {
    it('writes the trail Home > Notices into the top bar', () => {
        const { state, provide } = withTrail();
        renderPage(
            Notices,
            { unread: unreadNotices, seen: seenNotices, search: '' },
            provide,
        );

        expect(state.pieces.value).toEqual([
            { label: 'Home', href: '/dashboard' },
            { label: 'Notices' },
        ]);
    });

    it('has the title and the notices, the new ones apart from the seen ones', () => {
        renderPage(Notices, props);

        expect(
            screen.getByRole('heading', { name: 'Notices', level: 1 }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('region', { name: 'New notices' }),
        ).toHaveTextContent('Pay the road tax');
        expect(
            screen.getByRole('region', { name: 'Already seen' }),
        ).toHaveTextContent('Renew the ID card');
    });

    it('marks every notice as read from the link beside the title', async () => {
        renderPage(Notices, props);

        await userEvent.click(
            screen.getByRole('button', { name: 'Mark all as read' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/notices/read',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('offers to mark all even on a page with no new notice, while some are new', () => {
        renderPage(Notices, { ...props, unread: [] });

        expect(
            screen.getByRole('button', { name: 'Mark all as read' }),
        ).toBeInTheDocument();
    });

    it('has no such link when nothing is new', () => {
        shared.unread_notices = 0;
        renderPage(Notices, { ...props, unread: [] });

        expect(
            screen.queryByRole('button', { name: 'Mark all as read' }),
        ).not.toBeInTheDocument();
    });

    it('searches in the notices, from the first page', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Notices, props);

        await fireEvent.update(
            screen.getByLabelText('Search a notice'),
            '  Panda ',
        );
        await fireEvent.submit(screen.getByRole('search'));

        expect(get).toHaveBeenCalledWith(
            '/notices',
            { search: 'Panda' },
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('goes back to all the notices when the search is emptied', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Notices, { ...props, search: 'Panda' });

        expect(screen.getByLabelText('Search a notice')).toHaveValue('Panda');
        await fireEvent.update(screen.getByLabelText('Search a notice'), '');
        await fireEvent.submit(screen.getByRole('search'));

        expect(get).toHaveBeenCalledWith('/notices', {}, expect.anything());
    });

    it('has the pages under the list', () => {
        renderPage(Notices, {
            ...props,
            nextPage: '/notices?page=3',
            previousPage: '/notices?page=1',
        });

        const pages = screen.getByRole('navigation', { name: 'Pages' });
        expect(pages).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Next/ })).toHaveAttribute(
            'href',
            '/notices?page=3',
        );
    });

    it('says when the search finds nothing', () => {
        renderPage(Notices, { ...props, unread: [], seen: [], search: 'xyz' });

        expect(screen.getByText('No notice found')).toBeInTheDocument();
        expect(screen.queryByText('No notices yet')).not.toBeInTheDocument();
    });

    it('says the notices arrive here when there are none at all', () => {
        shared.unread_notices = 0;
        renderPage(Notices, {
            ...props,
            unread: [],
            seen: [],
            hasNotices: false,
        });

        expect(screen.getByText('No notices yet')).toBeInTheDocument();
        expect(screen.queryByRole('search')).not.toBeInTheDocument();
    });
});
