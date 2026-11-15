import { router } from '@inertiajs/vue3';
import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { renderPage, settle } from '@/test/page';
import ResetPassword from './ResetPassword.vue';

const props = {
    token: 'abc',
    email: 'marco.bianchi@example.com',
    passwordRules: '',
};

describe('New password', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('shows the email and asks for the new password twice', () => {
        renderPage(ResetPassword, props);

        expect(screen.getByLabelText('Email')).toHaveValue(
            'marco.bianchi@example.com',
        );
        expect(screen.getByLabelText('Email')).toHaveAttribute('readonly');
        expect(screen.getByLabelText('Choose a new password')).toHaveAttribute(
            'type',
            'password',
        );
        expect(
            screen.getByLabelText('Write the new password again'),
        ).toHaveAttribute('type', 'password');
        expect(
            screen.getByRole('button', { name: 'Save the password' }),
        ).toBeInTheDocument();
    });

    it('sends the confirmation as typed, not a copy of the password', async () => {
        const visit = vi
            .spyOn(router, 'visit')
            .mockImplementation(() => undefined);
        const user = userEvent.setup();
        renderPage(ResetPassword, props);

        await user.type(
            screen.getByLabelText('Choose a new password'),
            'una-password-nuova',
        );
        await user.type(
            screen.getByLabelText('Write the new password again'),
            'una-password-diversa',
        );
        await settle();
        await user.click(
            screen.getByRole('button', { name: 'Save the password' }),
        );

        expect(visit).toHaveBeenCalledWith(
            expect.anything(),
            expect.objectContaining({
                data: expect.objectContaining({
                    password: 'una-password-nuova',
                    password_confirmation: 'una-password-diversa',
                }),
            }),
        );
    });
});
