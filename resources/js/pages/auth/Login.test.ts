import { screen } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { setLayoutProps } from '@inertiajs/vue3';
import { renderPage } from '@/test/page';
import Login from './Login.vue';

// The layout is not rendered with a page in the tests: what the page asks of it is read from here
vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    setLayoutProps: vi.fn(),
}));

describe('Log in', () => {
    it('asks for email and password, with Stay signed in already chosen', () => {
        renderPage(Login, { canResetPassword: true, canRegister: true });

        expect(screen.getByLabelText('Email')).toHaveAttribute('type', 'email');
        expect(screen.getByLabelText('Password')).toHaveAttribute(
            'type',
            'password',
        );
        expect(
            screen.getByRole('switch', { name: 'Stay signed in' }),
        ).toBeChecked();
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
            canRegister: true,
            status: 'Your password has been reset.',
        });

        expect(screen.getByRole('status')).toHaveTextContent(
            'Your password has been reset.',
        );
    });

    it('hides the sign-up link when sign-up is closed', () => {
        vi.mocked(setLayoutProps).mockClear();
        renderPage(Login, { canResetPassword: true, canRegister: false });
        expect(setLayoutProps).not.toHaveBeenCalled();

        renderPage(Login, { canResetPassword: true, canRegister: true });
        expect(setLayoutProps).toHaveBeenCalledWith({
            otherWay: expect.objectContaining({
                link: 'Sign up your family',
                href: '/register',
            }),
        });
    });
});
