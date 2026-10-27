import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import VerifyEmail from './VerifyEmail.vue';

describe('Confirm the email', () => {
    it('offers to send the email again and to log out', () => {
        renderPage(VerifyEmail);

        expect(
            screen.getByRole('button', { name: 'Send me the email again' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Log out' }),
        ).toBeInTheDocument();
    });

    it('says so after the new sending', () => {
        renderPage(VerifyEmail, { status: 'verification-link-sent' });

        expect(screen.getByRole('status')).toHaveTextContent(
            'We sent you a new email',
        );
    });
});
