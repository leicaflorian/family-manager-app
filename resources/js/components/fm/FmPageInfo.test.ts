import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';

const state = vi.hoisted(() => ({
    wide: false,
    features: null as Record<string, boolean> | null,
    role: 'admin' as string | undefined,
}));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({
        component: 'mail/ReceivedMails',
        props: {
            name: 'Fameja.app',
            address: 'bianchi.k7m2x9@mg.fameja.app',
            family: state.features ? { features: state.features } : null,
            auth: { user: { name: 'Marco', role: state.role } },
        },
    }),
}));

vi.mock('@/composables/useWideLayout', async () => {
    const { ref } = await import('vue');

    return { useWideLayout: () => ref(state.wide) };
});

beforeEach(() => {
    state.wide = false;
    state.features = null;
    state.role = 'admin';
});

import FmPageInfo from './FmPageInfo.vue';

describe('FmPageInfo', () => {
    it('is a button named What is this page, closed at first', () => {
        render(FmPageInfo, { props: { infoKey: 'mail' } });

        const button = screen.getByRole('button', {
            name: 'What is this page',
        });

        expect(button).toHaveAttribute('aria-expanded', 'false');
        expect(button).toHaveAttribute('aria-haspopup', 'dialog');
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('opens a panel from below on the phone, with the title and the three parts', async () => {
        render(FmPageInfo, { props: { infoKey: 'mail' } });

        await userEvent.click(
            screen.getByRole('button', { name: 'What is this page' }),
        );

        const panel = screen.getByRole('dialog', { name: 'Received mail' });

        expect(panel).toHaveAttribute('data-sheet');
        expect(
            within(panel).getByRole('heading', { name: 'Received mail' }),
        ).toBeInTheDocument();
        expect(
            within(panel).getByRole('heading', { name: 'What it is for' }),
        ).toBeInTheDocument();
        expect(
            within(panel).getByRole('heading', { name: 'How to use it' }),
        ).toBeInTheDocument();
        expect(
            within(panel).getByRole('heading', { name: 'Good to know' }),
        ).toBeInTheDocument();
        expect(within(panel).getAllByRole('listitem').length).toBeGreaterThan(
            3,
        );
        // The page behind the panel is hidden from screen readers while it is open
        expect(
            screen.getByRole('button', {
                name: 'What is this page',
                hidden: true,
            }),
        ).toHaveAttribute('aria-expanded', 'true');
    });

    it('shows the address of the page to copy and the link inside a step, as the board Posta-info', async () => {
        render(FmPageInfo, { props: { infoKey: 'mail' } });

        await userEvent.click(
            screen.getByRole('button', { name: 'What is this page' }),
        );

        const panel = screen.getByRole('dialog', { name: 'Received mail' });

        expect(
            within(panel).getByText('bianchi.k7m2x9@mg.fameja.app'),
        ).toBeInTheDocument();
        expect(
            within(panel).getByRole('button', {
                name: 'Copy the address bianchi.k7m2x9@mg.fameja.app',
            }),
        ).toBeInTheDocument();
        expect(
            within(panel).getByRole('link', { name: 'To sort' }),
        ).toHaveAttribute('href', '/to-sort');
        expect(panel).not.toHaveTextContent(':link');
        expect(
            within(panel).getByRole('link', {
                name: 'Settings of the address',
            }),
        ).toHaveAttribute('href', '/settings/mail');
    });

    it('closes with Got it', async () => {
        render(FmPageInfo, { props: { infoKey: 'mail' } });

        await userEvent.click(
            screen.getByRole('button', { name: 'What is this page' }),
        );
        await userEvent.click(screen.getByRole('button', { name: 'Got it' }));

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'What is this page' }),
        ).toHaveAttribute('aria-expanded', 'false');
    });

    it('opens beside the page in the wide layout, without a modal dialog', async () => {
        state.wide = true;
        render(FmPageInfo, { props: { infoKey: 'mail' } });

        await userEvent.click(
            screen.getByRole('button', { name: 'What is this page' }),
        );

        const panel = screen.getByRole('dialog', {
            name: 'Received mail, what is this page',
        });

        expect(panel).toHaveAttribute('aria-modal', 'false');
        expect(panel).not.toHaveAttribute('data-sheet');
        expect(
            within(panel).getByRole('heading', { name: 'What it is for' }),
        ).toBeInTheDocument();

        await userEvent.click(
            within(panel).getByRole('button', { name: 'Close' }),
        );

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('leaves out what belongs to a feature that is off', async () => {
        state.features = { budget: false, mail: true };
        render(FmPageInfo, { props: { infoKey: 'home' } });

        await userEvent.click(
            screen.getByRole('button', { name: 'What is this page' }),
        );

        expect(screen.getByRole('dialog')).not.toHaveTextContent(/pay/i);
    });
    it('has the link to Invite for an administrator', async () => {
        render(FmPageInfo, { props: { infoKey: 'people' } });
        await userEvent.click(
            screen.getByRole('button', { name: 'What is this page' }),
        );

        expect(
            within(screen.getByRole('dialog')).getByRole('link', {
                name: 'Invite a family member',
            }),
        ).toBeInTheDocument();
    });

    it('has no link to Invite for a member', async () => {
        state.role = 'member';
        render(FmPageInfo, { props: { infoKey: 'people' } });
        await userEvent.click(
            screen.getByRole('button', { name: 'What is this page' }),
        );

        expect(
            within(screen.getByRole('dialog')).queryByRole('link', {
                name: 'Invite a family member',
            }),
        ).not.toBeInTheDocument();
    });
});
