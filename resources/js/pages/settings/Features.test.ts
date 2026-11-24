import { router } from '@inertiajs/vue3';
import userEvent from '@testing-library/user-event';
import { screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { renderPage, settle, withTrail } from '@/test/page';
import Features from './Features.vue';

const shared = vi.hoisted(() => ({ mailAvailable: true }));

// The shared props of the page: only whether this server can offer the mail
vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    usePage: () => ({
        props: {
            name: 'Fameja.app',
            family: { name: 'Bianchi', mail_available: shared.mailAvailable },
        },
    }),
}));

/** What the server sends to the page for an administrator of a family with both features on. */
const props = (change = true, budget = true, mail = true) => ({
    settings: { budget, mail, can_change: change },
});

afterEach(() => {
    vi.restoreAllMocks();
    shared.mailAvailable = true;
});

describe('Settings, What you use', () => {
    it('writes the trail Settings > What you use into the top bar', () => {
        const { state, provide } = withTrail();
        renderPage(Features, props(), provide);

        expect(state.pieces.value).toEqual([
            { label: 'Settings', href: '/settings' },
            { label: 'What you use' },
        ]);
    });

    it('does not offer the mail on a server without the mail domain', () => {
        shared.mailAvailable = false;
        renderPage(Features, props(true, true, false));

        expect(
            screen.getByRole('switch', { name: 'Budget' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('switch', { name: 'Mail for the bills' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByText('Mail for the bills'),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByText('When you turn the mail on'),
        ).not.toBeInTheDocument();
    });

    it('shows the two cards with their lists of what changes', () => {
        renderPage(Features, props());

        expect(
            screen.getByRole('heading', { name: 'What you use' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('switch', { name: 'Budget' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('switch', { name: 'Mail for the bills' }),
        ).toBeInTheDocument();
        expect(screen.getByText('When you turn it on')).toBeInTheDocument();
        expect(screen.getByText('When you turn it off')).toBeInTheDocument();
        expect(
            screen.getByText('When you turn the mail on'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('When you turn the mail off'),
        ).toBeInTheDocument();
        expect(
            screen.queryByText(
                'Only whoever manages the family can change them.',
            ),
        ).not.toBeInTheDocument();
    });

    it('reads the switches from the server', () => {
        renderPage(Features, props(true, true, false));

        expect(screen.getByRole('switch', { name: 'Budget' })).toBeChecked();
        expect(
            screen.getByRole('switch', { name: 'Mail for the bills' }),
        ).not.toBeChecked();
    });

    it('saves a switch at once with both values', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(Features, props());

        await userEvent.click(screen.getByRole('switch', { name: 'Budget' }));
        await settle();

        expect(put).toHaveBeenCalledWith(
            '/settings/features',
            { budget: false, mail: true },
            expect.anything(),
        );
    });

    it('puts a switch back when the save did not go through', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(Features, props());

        await userEvent.click(screen.getByRole('switch', { name: 'Budget' }));
        await settle();
        expect(
            screen.getByRole('switch', { name: 'Budget' }),
        ).not.toBeChecked();

        const options = put.mock.calls[0]?.[2];
        options?.onFinish?.({} as never);
        await settle();

        expect(screen.getByRole('switch', { name: 'Budget' })).toBeChecked();
    });

    it('locks the switches for a member and says why', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(Features, props(false));

        expect(
            screen.getByText(
                'Only whoever manages the family can change them.',
            ),
        ).toBeInTheDocument();
        expect(screen.getByRole('switch', { name: 'Budget' })).toBeDisabled();
        expect(
            screen.getByRole('switch', { name: 'Mail for the bills' }),
        ).toBeDisabled();

        await userEvent.click(screen.getByRole('switch', { name: 'Budget' }));

        expect(put).not.toHaveBeenCalled();
    });
});
