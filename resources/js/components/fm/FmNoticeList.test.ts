import { router } from '@inertiajs/vue3';
import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { seenNotices, unreadNotices } from '@/test/fixtures';
import FmNoticeList from './FmNoticeList.vue';

afterEach(() => {
    vi.restoreAllMocks();
});

describe('FmNoticeList', () => {
    it('shows the new notices apart from the seen ones', () => {
        render(FmNoticeList, {
            props: { unread: unreadNotices, seen: seenNotices },
        });

        const news = screen.getByRole('region', { name: 'New notices' });
        expect(
            within(news).getByRole('button', { name: /Pay the road tax/ }),
        ).toBeInTheDocument();
        expect(
            within(news).getByRole('button', { name: /Panda car insurance/ }),
        ).toBeInTheDocument();
        const old = screen.getByRole('region', { name: 'Already seen' });
        expect(
            within(old).getByRole('link', { name: /Health card/ }),
        ).toBeInTheDocument();
        expect(within(old).getByText('Overdue')).toBeInTheDocument();
        expect(within(old).getByText('Done')).toBeInTheDocument();
    });

    it('has no box of new notices when nothing is new', () => {
        render(FmNoticeList, { props: { unread: [], seen: seenNotices } });

        expect(
            screen.queryByRole('region', { name: 'New notices' }),
        ).not.toBeInTheDocument();
    });

    it('opens a new notice with one visit, a POST to the read address', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        render(FmNoticeList, {
            props: { unread: unreadNotices, seen: seenNotices },
        });

        await userEvent.click(
            screen.getByRole('button', { name: /Pay the road tax/ }),
        );

        expect(visit).toHaveBeenCalledTimes(1);
        expect(visit.mock.calls[0]?.[0]).toBe('/notices/1/read');
        expect(visit.mock.calls[0]?.[1]).toMatchObject({ method: 'post' });
    });

    it('opens a seen notice with one GET to its page', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        render(FmNoticeList, {
            props: { unread: unreadNotices, seen: seenNotices },
        });

        await userEvent.click(
            screen.getByRole('link', { name: /Renew the ID card/ }),
        );

        expect(visit).toHaveBeenCalledTimes(1);
        expect(visit.mock.calls[0]?.[0]).toBe('/reminders/1/edit');
        expect(visit.mock.calls[0]?.[1]).not.toMatchObject({ method: 'post' });
    });

    it('says so when there are no notices at all', () => {
        render(FmNoticeList, { props: { unread: [], seen: [] } });

        expect(screen.getByText('No notices yet')).toBeInTheDocument();
    });

    it('says the same notices arrive by email, with the way to Settings', () => {
        render(FmNoticeList, {
            props: { unread: unreadNotices, seen: seenNotices },
        });

        expect(
            screen.getByText('The same notices arrive by email.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Choose which ones in Settings' }),
        ).toHaveAttribute('href', '/settings');
    });
});
