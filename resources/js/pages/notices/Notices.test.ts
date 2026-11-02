import { router } from '@inertiajs/vue3';
import { screen } from '@testing-library/vue';
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
import { renderPage } from '@/test/page';
import Notices from './Notices.vue';

let post: MockInstance<typeof router.post>;

beforeEach(() => {
    post = vi.spyOn(router, 'post').mockImplementation(() => undefined);
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('Notices', () => {
    it('has the title and the notices, the new ones apart from the seen ones', () => {
        renderPage(Notices, { unread: unreadNotices, seen: seenNotices });

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
        renderPage(Notices, { unread: unreadNotices, seen: seenNotices });

        await userEvent.click(
            screen.getByRole('button', { name: 'Mark all as read' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/notices/read',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('has no such link when nothing is new', () => {
        renderPage(Notices, { unread: [], seen: seenNotices });

        expect(
            screen.queryByRole('button', { name: 'Mark all as read' }),
        ).not.toBeInTheDocument();
    });
});
