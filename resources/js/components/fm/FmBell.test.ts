import { router } from '@inertiajs/vue3';
import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { seenNotices, unreadNotices } from '@/test/fixtures';
import FmBell from './FmBell.vue';

afterEach(() => {
    vi.restoreAllMocks();
});

describe('FmBell', () => {
    it('has no number when there is nothing new', () => {
        render(FmBell, { props: { count: 0 } });

        expect(
            screen.getByRole('button', { name: 'Notices' }),
        ).toBeInTheDocument();
        expect(screen.queryByText('0')).not.toBeInTheDocument();
    });

    it('shows how many notices are new, and says it', () => {
        render(FmBell, { props: { count: 3 } });

        expect(screen.getByText('3')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Notices: 3 new' }),
        ).toBeInTheDocument();
    });

    it('gives the classes of the page to the bell itself: round or square', () => {
        render(FmBell, {
            props: { count: 0 },
            attrs: { class: 'rounded-lg border border-edge-soft bg-card' },
        });

        expect(screen.getByRole('button', { name: 'Notices' })).toHaveClass(
            'rounded-lg',
            'border-edge-soft',
            'bg-card',
        );
    });

    it('stops counting at 99', () => {
        render(FmBell, { props: { count: 120 } });

        expect(screen.getByText('99+')).toBeInTheDocument();
    });

    it('opens the menu and asks for the notices, which are still on their way', async () => {
        const view = render(FmBell, { props: { count: 2 } });

        await userEvent.click(
            screen.getByRole('button', { name: 'Notices: 2 new' }),
        );

        expect(view.emitted().open).toHaveLength(1);
        const menu = screen.getByRole('dialog', { name: /^Notices/ });
        expect(
            within(menu).getByRole('heading', { name: 'Notices' }),
        ).toBeInTheDocument();
        expect(within(menu).getByText('Loading…')).toBeInTheDocument();
    });

    it('shows the new notices and the seen ones in the menu', async () => {
        render(FmBell, {
            props: {
                count: 2,
                notices: { unread: unreadNotices, seen: seenNotices },
            },
        });

        await userEvent.click(screen.getByRole('button', { name: /Notices/ }));

        const menu = screen.getByRole('dialog', { name: /^Notices/ });
        expect(
            within(menu).getByRole('region', { name: 'New notices' }),
        ).toHaveTextContent('Pay the road tax');
        expect(
            within(menu).getByRole('region', { name: 'Already seen' }),
        ).toHaveTextContent('Renew the ID card');
        expect(
            within(menu).getByText('The same notices arrive by email.'),
        ).toBeInTheDocument();
    });

    it('marks all as read from the menu and reloads only the notices', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        render(FmBell, {
            props: {
                count: 2,
                notices: { unread: unreadNotices, seen: seenNotices },
            },
        });
        await userEvent.click(screen.getByRole('button', { name: /Notices/ }));

        await userEvent.click(
            screen.getByRole('button', { name: 'Mark all as read' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/notices/read',
            {},
            expect.objectContaining({
                preserveScroll: true,
                preserveState: true,
                only: ['notices', 'unread_notices'],
            }),
        );
    });

    it('has nothing to mark when nothing is new', async () => {
        render(FmBell, {
            props: { count: 0, notices: { unread: [], seen: seenNotices } },
        });

        await userEvent.click(screen.getByRole('button', { name: 'Notices' }));

        expect(
            screen.queryByRole('button', { name: 'Mark all as read' }),
        ).not.toBeInTheDocument();
    });

    it('ends the menu with the way to the page of all the notices', async () => {
        render(FmBell, {
            props: {
                count: 2,
                notices: { unread: unreadNotices, seen: seenNotices },
            },
        });

        await userEvent.click(screen.getByRole('button', { name: /Notices/ }));

        expect(
            screen.getByRole('link', { name: 'See all the notices' }),
        ).toHaveAttribute('href', '/notices');
    });
});
