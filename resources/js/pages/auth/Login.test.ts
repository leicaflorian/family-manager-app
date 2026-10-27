import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import Login from './Login.vue';

describe('Log in', () => {
    it('asks for email and password, with Stay signed in already chosen', () => {
        renderPage(Login, { canResetPassword: true, canRegister: true });

        expect(screen.getByLabelText('Email')).toHaveAttribute('type', 'email');
        expect(screen.getByLabelText('Password')).toHaveAttribute(
            'type',
            'password',
        );
        expect(screen.getByLabelText('Stay signed in')).toBeChecked();
        expect(
            screen.getByRole('link', { name: 'Forgot your password?' }),
        ).toHaveAttribute('href', '/forgot-password');
        expect(screen.getByRole('button', { name: 'Log in' })).toHaveAttribute(
            'type',
            'submit',
        );
    });

    it('shows the message that comes from the server', () => {
        renderPage(Login, {
            canResetPassword: true,
            status: 'Your password has been reset.',
        });

        expect(screen.getByRole('status')).toHaveTextContent(
            'Your password has been reset.',
        );
    });
});
