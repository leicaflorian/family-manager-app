import { screen } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import AcceptInvitation from './AcceptInvitation.vue';

/** What the page asks the layout to show on the blue panel. */
const layout = vi.hoisted(() => ({ panel: { title: '', text: '' } }));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    usePage: () => ({ props: { name: 'Fameja.app' } }),
    setLayoutProps: (props: { panel: { title: string; text: string } }) => {
        layout.panel = props.panel;
    },
}));

const invitation: App.Data.InvitationData = {
    first_name: 'Rosa',
    family: 'Bianchi',
    inviter: 'Marco',
    email: 'rosa.bianchi@example.com',
    expires_on: '2026-11-02',
    people: [
        { id: 1, first_name: 'Marco', last_name: 'Bianchi', initials: 'MB' },
        { id: 2, first_name: 'Laura', last_name: 'Bianchi', initials: 'LB' },
    ],
};

describe('Invitation accepted', () => {
    it('greets by name, shows the family and asks only for the password', () => {
        renderPage(AcceptInvitation, { token: 'abc', invitation });

        expect(
            screen.getByRole('heading', { name: 'Hi, Rosa' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/Marco invited you to the Bianchi family/),
        ).toBeInTheDocument();
        expect(screen.getByText('MB')).toBeInTheDocument();
        expect(screen.getByLabelText('Your email')).toHaveValue(
            'rosa.bianchi@example.com',
        );
        expect(screen.getByLabelText('Your email')).toBeDisabled();
        expect(screen.getByLabelText('Choose a password')).toHaveAttribute(
            'type',
            'password',
        );
        expect(
            screen.getByRole('button', { name: 'Join the family' }),
        ).toHaveAttribute('type', 'submit');
        expect(
            screen.getByText('The invitation is valid until 02/11/2026'),
        ).toBeInTheDocument();
    });

    it('says on the panel who already uses the app, with its name', () => {
        renderPage(AcceptInvitation, { token: 'abc', invitation });

        expect(layout.panel.text).toBe(
            'Marco and Laura already use Fameja.app for the deadlines, the bills and the documents of the home.',
        );
    });

    it('says it for one person too', () => {
        renderPage(AcceptInvitation, {
            token: 'abc',
            invitation: { ...invitation, people: [invitation.people[0]] },
        });

        expect(layout.panel.text).toBe(
            'Marco already uses Fameja.app for the deadlines, the bills and the documents of the home.',
        );
    });
});
