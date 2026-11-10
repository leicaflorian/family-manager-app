import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { useTheme } from '@/composables/useTheme';
import { account, linkedPhone, themes } from '@/test/fixtures';
import { pressSave, renderPage, settle, withTrail } from '@/test/page';
import Account from './Account.vue';
import { answerLeave, hasChanges, leaveQuestion } from '@/lib/unsavedChanges';

describe('My account', () => {
    it('writes the trail Settings > My account into the top bar', () => {
        const { state, provide } = withTrail();
        renderPage(Account, { account, themes, devices: [] }, provide);

        expect(state.pieces.value).toEqual([
            { label: 'Settings', href: '/settings' },
            { label: 'My account' },
        ]);
    });

    it('shows first name, last name and email to change, and the photo', () => {
        renderPage(Account, { account, themes, devices: [] });

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
        renderPage(Account, { account, themes, devices: [] });

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
        renderPage(Account, { account, themes, devices: [] });

        expect(
            screen.queryByRole('button', { name: 'Delete my account' }),
        ).toBeNull();
        expect(
            screen.getByText(/You are the only administrator/),
        ).toBeInTheDocument();
    });

    it('lets the others delete their account with the password', async () => {
        renderPage(Account, {
            themes,
            devices: [],
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
        renderPage(Account, { account, themes, devices: [] });

        pressSave();

        expect(patch).toHaveBeenCalled();
    });
});

describe('Changes not saved', () => {
    it('has them once a detail changes', async () => {
        renderPage(Account, { account, themes, devices: [] });
        expect(hasChanges()).toBe(false);

        await fireEvent.update(
            screen.getByLabelText('First name'),
            'Marcolino',
        );
        await settle();

        expect(hasChanges()).toBe(true);
    });

    it('asks before closing the password panel with the × once something is written', async () => {
        renderPage(Account, { account, themes, devices: [] });
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
    const props = { account, themes, devices: [] };

    /**
     * The server answers the save with a redirect back to this same page: Inertia
     * fires `success` and no `navigate` (`Response.handle` and `visit()` in @inertiajs/core).
     */
    function serverSends(theme: string): void {
        document.dispatchEvent(
            new CustomEvent('inertia:success', {
                detail: { page: { props: { theme } } },
            }),
        );
    }

    beforeEach(() => {
        useTheme();
        serverSends('system');
    });

    it('shows Theme with Automatic, Light and Dark, on the current one', () => {
        serverSends('light');
        renderPage(Account, props);

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

    it('keeps the choice when the page comes back from the history with an older theme', async () => {
        serverSends('dark');
        renderPage(Account, props);

        document.dispatchEvent(
            new CustomEvent('inertia:navigate', {
                detail: {
                    page: { props: { theme: 'system' } },
                    type: 'history',
                    cached: false,
                },
            }),
        );
        await settle();

        expect(screen.getByRole('button', { name: 'Dark' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
    });

    it('sends only the last of two quick choices, once the first save is over', async () => {
        const finishes: Array<() => void> = [];
        const put = vi
            .spyOn(router, 'put')
            .mockImplementation((_url, _data, options) => {
                finishes.push(() => options?.onFinish?.({} as never));
            });
        renderPage(Account, props);

        await fireEvent.click(screen.getByRole('button', { name: 'Dark' }));
        await settle();
        await fireEvent.click(screen.getByRole('button', { name: 'Light' }));
        await settle();
        await fireEvent.click(screen.getByRole('button', { name: 'Dark' }));
        await settle();

        expect(put).toHaveBeenCalledTimes(1);

        finishes[0]();

        expect(put).toHaveBeenCalledTimes(2);
        expect(put).toHaveBeenLastCalledWith(
            '/settings/account/theme',
            { theme: 'dark' },
            expect.anything(),
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

    describe('Access', () => {
        it('lists my linked phones under the password, with when they were linked', () => {
            renderPage(Account, { account, themes, devices: [linkedPhone] });

            expect(screen.getByText('iPhone of Marco')).toBeInTheDocument();
            expect(
                screen.getByText(
                    'Linked on 12/10/2026 · last upload on 24/10/2026',
                ),
            ).toBeInTheDocument();
        });

        it('says linked now for a phone that has just entered', () => {
            renderPage(Account, {
                account,
                themes,
                devices: [{ ...linkedPhone, linked_now: true }],
            });

            expect(screen.getByText('Linked now')).toBeInTheDocument();
        });

        it('asks before unlinking a phone, then unlinks it', async () => {
            const deleted = vi
                .spyOn(router, 'delete')
                .mockImplementation(() => undefined);
            renderPage(Account, { account, themes, devices: [linkedPhone] });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Unlink iPhone of Marco' }),
            );

            expect(deleted).not.toHaveBeenCalled();

            await fireEvent.click(
                await screen.findByRole('button', { name: 'Unlink' }),
            );

            expect(deleted).toHaveBeenCalledWith(
                '/settings/devices/4',
                expect.anything(),
            );
        });

        it('opens the panel to link a phone', async () => {
            vi.stubGlobal(
                'fetch',
                vi.fn().mockResolvedValue({
                    ok: true,
                    json: () =>
                        Promise.resolve({
                            code: 'K7QM-XPA9',
                            qr: '<svg xmlns="http://www.w3.org/2000/svg"/>',
                            address: 'familymanager.it/connect',
                            expires_at: '2999-01-01T00:00:00+00:00',
                            person: 'Marco',
                            family: 'Bianchi',
                            devices: [],
                        }),
                }),
            );
            renderPage(Account, { account, themes, devices: [] });

            await fireEvent.click(
                screen.getByRole('button', { name: 'Link a phone' }),
            );

            expect(await screen.findByText('K7QM-XPA9')).toBeInTheDocument();
            vi.unstubAllGlobals();
        });
    });
});
