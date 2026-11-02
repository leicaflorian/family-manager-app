import { router } from '@inertiajs/vue3';
import { screen, within } from '@testing-library/vue';
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
import { renderPage } from '@/test/page';
import Notices from './Notices.vue';

let post: MockInstance<typeof router.post>;

beforeEach(() => {
    vi.spyOn(router, 'visit').mockImplementation(() => {});
    post = vi.spyOn(router, 'post').mockImplementation(() => undefined);
});

afterEach(() => {
    vi.restoreAllMocks();
});

/** A notice as the server sends it. */
const notice = (
    change: Partial<App.Data.ReminderNoticeData>,
): App.Data.ReminderNoticeData => ({
    id: 1,
    title: 'Pay the road tax',
    subject: null,
    area: 'general',
    due_text: 'By 31/10/2026, in 5 days',
    sent_at: '2026-10-24T06:00',
    read: false,
    state: null,
    href: '/reminders/1/edit',
    ...change,
});

const unread = [
    notice({ id: 1 }),
    notice({
        id: 2,
        title: 'Renew the policy · Panda car insurance',
        area: 'insurance',
        href: '/cards/insurance/2',
    }),
];
const seen = [
    notice({
        id: 3,
        title: 'Renew the document · Health card',
        read: true,
        state: 'expired',
    }),
    notice({ id: 4, title: 'Renew the ID card', read: true, state: 'done' }),
];

describe('Notices', () => {
    it('has the title and the new notices apart from the seen ones', () => {
        renderPage(Notices, { unread, seen });

        expect(
            screen.getByRole('heading', { name: 'Notices', level: 1 }),
        ).toBeInTheDocument();
        const news = screen.getByRole('region', { name: 'New notices' });
        expect(
            within(news).getByRole('link', { name: /Pay the road tax/ }),
        ).toBeInTheDocument();
        expect(
            within(news).getByRole('link', { name: /Panda car insurance/ }),
        ).toHaveAttribute('href', '/cards/insurance/2');
        const old = screen.getByRole('region', { name: 'Already seen' });
        expect(
            within(old).getByRole('link', { name: /Health card/ }),
        ).toBeInTheDocument();
        expect(within(old).getByText('Overdue')).toBeInTheDocument();
        expect(within(old).getByText('Done')).toBeInTheDocument();
    });

    it('marks every notice as read from the link beside the title', async () => {
        renderPage(Notices, { unread, seen });

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
        renderPage(Notices, { unread: [], seen });

        expect(
            screen.queryByRole('button', { name: 'Mark all as read' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('region', { name: 'New notices' }),
        ).toBeNull();
    });

    it('marks a new notice as read when it is opened, and not a seen one', async () => {
        renderPage(Notices, { unread, seen });

        await userEvent.click(
            screen.getByRole('link', { name: /Pay the road tax/ }),
        );
        expect(post).toHaveBeenCalledWith('/notices/1/read');

        post.mockClear();
        await userEvent.click(
            screen.getByRole('link', { name: /Renew the ID card/ }),
        );
        expect(post).not.toHaveBeenCalled();
    });

    it('says so when there are no notices at all', () => {
        renderPage(Notices, { unread: [], seen: [] });

        expect(screen.getByText('No notices yet')).toBeInTheDocument();
    });

    it('says the same notices arrive by email', () => {
        renderPage(Notices, { unread, seen });

        expect(
            screen.getByText('The same notices arrive by email.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Choose which ones in Settings' }),
        ).toBeInTheDocument();
    });
});
