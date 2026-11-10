import { router } from '@inertiajs/vue3';
import userEvent from '@testing-library/user-event';
import { screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { renderPage, withTrail } from '@/test/page';
import Support from './Support.vue';

/**
 * Finds the elements whose whole text is this, even with a bold part inside.
 */
function allByWholeText(text: string): HTMLElement[] {
    return screen.getAllByText(
        (_, element) => element?.textContent?.trim() === text,
    );
}

describe('Settings, Support', () => {
    it('writes the trail Settings > Support into the top bar', () => {
        const { state, provide } = withTrail();
        renderPage(
            Support,
            {
                support_until: null,
                can_change: true,
                admin_level: 'Everything, like you',
            },
            provide,
        );

        expect(state.pieces.value).toEqual([
            { label: 'Settings', href: '/settings' },
            { label: 'Support' },
        ]);
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('explains what support does and says it cannot come in', () => {
        renderPage(Support, {
            support_until: null,
            can_change: true,
            admin_level: 'Everything, like you',
        });

        expect(
            screen.getByRole('heading', { name: 'Support' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                /we can look at Family Manager as one of your accounts/,
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Support cannot come in right now.'),
        ).toBeInTheDocument();
        expect(
            allByWholeText(
                'The permission ends by itself after 24 hours, and you can take it back sooner. Only someone with Everything, like you can give it.',
            ).length,
        ).toBeGreaterThan(0);
        expect(
            screen.queryByText('With the permission'),
        ).not.toBeInTheDocument();
    });

    it('shows the level from the server in the note', () => {
        renderPage(Support, {
            support_until: null,
            can_change: true,
            admin_level: 'Tutto, come te',
        });

        expect(
            allByWholeText(
                'The permission ends by itself after 24 hours, and you can take it back sooner. Only someone with Tutto, come te can give it.',
            ).length,
        ).toBeGreaterThan(0);
    });

    it('has the lifebuoy tile beside the intro', () => {
        const { container } = renderPage(Support, {
            support_until: null,
            can_change: true,
            admin_level: 'Everything, like you',
        });

        expect(container.querySelector('svg.lucide-life-buoy')).not.toBeNull();
    });

    it('lets support in with a POST', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Support, {
            support_until: null,
            can_change: true,
            admin_level: 'Everything, like you',
        });

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Let support in for 24 hours',
            }),
        );

        expect(post).toHaveBeenCalledWith(
            '/settings/support',
            {},
            expect.anything(),
        );
    });

    it('says until when, and takes the permission back with a DELETE', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(Support, {
            support_until: '2026-12-10T21:30:00+01:00',
            admin_level: 'Everything, like you',
            can_change: true,
        });

        expect(screen.getByText('With the permission')).toBeInTheDocument();
        expect(
            allByWholeText('Support can come in until 10/12/2026 21:30').length,
        ).toBeGreaterThan(0);
        expect(
            screen.queryByRole('button', {
                name: 'Let support in for 24 hours',
            }),
        ).not.toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Take the permission back' }),
        );

        expect(destroy).toHaveBeenCalledWith(
            '/settings/support',
            expect.anything(),
        );
    });

    it('shows a member the state and no buttons', () => {
        renderPage(Support, {
            support_until: '2026-12-10T21:30:00+01:00',
            admin_level: 'Everything, like you',
            can_change: false,
        });

        expect(
            allByWholeText('Support can come in until 10/12/2026 21:30').length,
        ).toBeGreaterThan(0);
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
});
