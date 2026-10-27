import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import ResetPassword from './ResetPassword.vue';

describe('New password', () => {
    it('shows the email and asks for the new password', () => {
        renderPage(ResetPassword, {
            token: 'abc',
            email: 'marco.bianchi@example.com',
            passwordRules: '',
        });

        expect(screen.getByLabelText('Email')).toHaveValue(
            'marco.bianchi@example.com',
        );
        expect(screen.getByLabelText('Email')).toHaveAttribute('readonly');
        expect(screen.getByLabelText('Choose a new password')).toHaveAttribute(
            'type',
            'password',
        );
        expect(
            screen.getByRole('button', { name: 'Save the password' }),
        ).toBeInTheDocument();
    });
});
