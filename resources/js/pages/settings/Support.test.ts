import { router } from '@inertiajs/vue3';
import userEvent from '@testing-library/user-event';
import { screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { renderPage } from '@/test/page';
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
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('explains what support does and says it cannot come in', () => {
        renderPage(Support, { support_until: null, can_change: true });

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

    it('lets support in with a POST', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Support, { support_until: null, can_change: true });

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
            support_until: '2026-12-10 21:30',
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
            support_until: '2026-12-10 21:30',
            can_change: false,
        });

        expect(
            allByWholeText('Support can come in until 10/12/2026 21:30').length,
        ).toBeGreaterThan(0);
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
});
