import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { account, themes } from '@/test/fixtures';
import { pressSave, renderPage, settle } from '@/test/page';
import Account from './Account.vue';
import { answerLeave, hasChanges, leaveQuestion } from '@/lib/unsavedChanges';

describe('My account', () => {
    it('shows first name, last name and email to change, and the photo', () => {
        renderPage(Account, { account, theme: 'system', themes });

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
        renderPage(Account, { account, theme: 'system', themes });

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
        renderPage(Account, { account, theme: 'system', themes });

        expect(
            screen.queryByRole('button', { name: 'Delete my account' }),
        ).toBeNull();
        expect(
            screen.getByText(/You are the only administrator/),
        ).toBeInTheDocument();
    });

    it('lets the others delete their account with the password', async () => {
        renderPage(Account, {
            theme: 'system',
            themes,
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

    it('saves first name, last name and email with ⌘S too', () => {
        const patch = vi.spyOn(router, 'patch').mockImplementation(() => {});
        renderPage(Account, { account, theme: 'system', themes });

        pressSave();

        expect(patch).toHaveBeenCalled();
    });
});

describe('Changes not saved', () => {
    it('has them once a detail changes', async () => {
        renderPage(Account, { account, theme: 'system', themes });
        expect(hasChanges()).toBe(false);

        await fireEvent.update(
            screen.getByLabelText('First name'),
            'Marcolino',
        );
        await settle();

        expect(hasChanges()).toBe(true);
    });

    it('asks before closing the password panel with the × once something is written', async () => {
        renderPage(Account, { account, theme: 'system', themes });
        await fireEvent.click(screen.getByRole('button', { name: /Password/ }));
        await fireEvent.update(
            await screen.findByLabelText('Current password'),
            'vecchia',
        );
        await settle();

        await fireEvent.click(screen.getByRole('button', { name: 'Close' }));

        expect(leaveQuestion.open).toBe(true);
        answerLeave(false);
    });
});

describe('Theme', () => {
    const props = { account, theme: 'system', themes };

    it('shows Theme with Automatic, Light and Dark, on the current one', () => {
        renderPage(Account, { ...props, theme: 'light' });

        expect(
            screen.getByRole('heading', { name: 'Theme', level: 2 }),
        ).toBeInTheDocument();
        for (const label of ['Automatic', 'Light', 'Dark']) {
            expect(
                screen.getByRole('button', { name: label }),
            ).toBeInTheDocument();
        }
        expect(screen.getByRole('button', { name: 'Light' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        expect(screen.getByRole('button', { name: 'Dark' })).toHaveAttribute(
            'aria-pressed',
            'false',
        );
        expect(
            screen.getByText(/Automatic follows your phone or computer/),
        ).toBeInTheDocument();
    });

    it('saves the theme as soon as I choose it', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(Account, props);

        await fireEvent.click(screen.getByRole('button', { name: 'Dark' }));
        await settle();

        expect(put).toHaveBeenCalledWith(
            '/settings/account/theme',
            { theme: 'dark' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('does not count the theme as an unsaved change of the profile', async () => {
        vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(Account, props);

        await fireEvent.click(screen.getByRole('button', { name: 'Dark' }));
        await settle();

        expect(hasChanges()).toBe(false);
        expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled();
    });
});
