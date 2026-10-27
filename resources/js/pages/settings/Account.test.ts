import { fireEvent, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { account } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import Account from './Account.vue';

describe('My account', () => {
    it('shows first name, last name and email to change, and the photo', () => {
        renderPage(Account, { account });

        expect(screen.getByLabelText('First name')).toHaveValue('Marco');
        expect(screen.getByLabelText('Last name')).toHaveValue('Bianchi');
        expect(screen.getByLabelText('Email')).toHaveValue('marco@example.com');
        expect(screen.getByText('MB')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Change the photo' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Save' })).toHaveAttribute(
            'type',
            'submit',
        );
        expect(
            screen.getByRole('button', { name: 'Log out' }),
        ).toBeInTheDocument();
    });

    it('changes the password in the panel, with the current one', async () => {
        renderPage(Account, { account });

        await fireEvent.click(screen.getByRole('button', { name: /Password/ }));

        expect(
            await screen.findByLabelText('Current password'),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('New password')).toHaveAttribute(
            'type',
            'password',
        );
    });

    it('tells the last admin why they cannot delete themselves', () => {
        renderPage(Account, { account });

        expect(
            screen.queryByRole('button', { name: 'Delete my account' }),
        ).toBeNull();
        expect(
            screen.getByText(/You are the only administrator/),
        ).toBeInTheDocument();
    });

    it('lets the others delete their account with the password', async () => {
        renderPage(Account, {
            account: {
                ...account,
                role: 'Family member',
                is_admin: false,
                is_last_admin: false,
            },
        });

        expect(
            screen.getByText(/You see everything and upload documents/),
        ).toBeInTheDocument();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Delete my account' }),
        );

        expect(
            await screen.findByLabelText('Your password'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Delete forever' }),
        ).toBeInTheDocument();
    });
});
