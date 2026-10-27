import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import ForgotPassword from './ForgotPassword.vue';

describe('Forgot password', () => {
    it('asks for the email to send the link', () => {
        renderPage(ForgotPassword);

        expect(screen.getByLabelText('Email')).toHaveAttribute('type', 'email');
        expect(
            screen.getByRole('button', { name: 'Send me the link' }),
        ).toBeInTheDocument();
        expect(screen.queryByRole('status')).toBeNull();
    });

    it('says where to look after sending and offers to send it again', () => {
        renderPage(ForgotPassword, {
            status: 'We have emailed your password reset link.',
        });

        expect(screen.getByRole('status')).toHaveTextContent(
            'The link is valid for one hour',
        );
        expect(
            screen.getByRole('button', { name: 'Send me the link again' }),
        ).toBeInTheDocument();
    });
});
