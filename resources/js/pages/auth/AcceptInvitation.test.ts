import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import AcceptInvitation from './AcceptInvitation.vue';

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
});
