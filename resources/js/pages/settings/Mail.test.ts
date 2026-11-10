import { router } from '@inertiajs/vue3';
import userEvent from '@testing-library/user-event';
import { fireEvent, screen, within } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { renderPage, settle, withTrail } from '@/test/page';
import Mail from './Mail.vue';

const settings: App.Data.MailSettingsData = {
    address: 'bianchi.k7m2x9@bollette.familymanager.it',
    senders: [
        { name: 'Edison', address: 'fatture@edison.example' },
        {
            name: 'Comune di Bologna',
            address: 'tributi@comune.bologna.example',
        },
    ],
    accounts: [
        { id: 1, name: 'Marco', email: 'marco.bianchi@email.it', chosen: true },
        { id: 2, name: 'Laura', email: 'laura.bianchi@email.it', chosen: true },
    ],
    people_without_account: ['Giulia'],
    attachments_only: true,
    can_edit: true,
};

describe('Settings, Address for the bills', () => {
    it('writes the trail Settings > Address for the bills into the top bar', () => {
        const { state, provide } = withTrail();
        renderPage(Mail, { settings }, provide);

        expect(state.pieces.value).toEqual([
            { label: 'Settings', href: '/settings' },
            { label: 'Address for the bills' },
        ]);
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('shows the address, the steps, the senders and the accounts', () => {
        renderPage(Mail, { settings });

        expect(screen.getByLabelText('Address')).toHaveValue(
            'bianchi.k7m2x9@bollette.familymanager.it',
        );
        expect(
            screen.getByText(/Gmail, on the computer: open a mail from Edison/),
        ).toBeInTheDocument();
        expect(screen.getByText('Comune di Bologna')).toBeInTheDocument();
        expect(screen.getByLabelText('Marco')).toBeChecked();
        expect(screen.getByLabelText('Laura')).toBeChecked();
        expect(screen.getByText('Giulia')).toBeInTheDocument();
        expect(screen.getByText('Has no account')).toBeInTheDocument();
        expect(
            screen.getByLabelText('Only for mails with files'),
        ).toBeChecked();
    });

    it('copies the address and says so', async () => {
        const copy = vi.fn().mockResolvedValue(undefined);
        Object.assign(navigator, { clipboard: { writeText: copy } });
        renderPage(Mail, { settings });

        await fireEvent.click(screen.getByRole('button', { name: 'Copy' }));

        expect(copy).toHaveBeenCalledWith(
            'bianchi.k7m2x9@bollette.familymanager.it',
        );
        expect(await screen.findByText('Address copied')).toBeInTheDocument();
    });

    it('asks to copy by hand when the browser refuses', async () => {
        Object.assign(navigator, {
            clipboard: {
                writeText: vi.fn().mockRejectedValue(new Error('no')),
            },
        });
        renderPage(Mail, { settings });

        await fireEvent.click(screen.getByRole('button', { name: 'Copy' }));

        expect(
            await screen.findByText(
                'Could not copy: select the text and copy it by hand.',
            ),
        ).toBeInTheDocument();
    });

    it('says the mail is not active yet, without an address', () => {
        renderPage(Mail, { settings: { ...settings, address: null } });

        expect(
            screen.getByText('The mail is not active yet.'),
        ).toBeInTheDocument();
        expect(screen.queryByLabelText('Address')).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /Change the address/ }),
        ).not.toBeInTheDocument();
    });

    it('asks before changing the address, and warns that the forwards must be redone', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Mail, { settings });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Change the address' }),
        );
        expect(post).not.toHaveBeenCalled();

        const dialog = await screen.findByRole('dialog');
        expect(
            within(dialog).getByText(
                'The old address stops receiving: the forwards you set up (Gmail, filters) must be redone with the new one.',
            ),
        ).toBeInTheDocument();
        await fireEvent.click(
            within(dialog).getByRole('button', { name: 'Change the address' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/settings/mail/address',
            {},
            expect.anything(),
        );
    });

    it('saves who gets the notice and whether it is only for mails with files', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(Mail, { settings });

        await userEvent.click(screen.getByLabelText('Marco'));
        await userEvent.click(
            screen.getByLabelText('Only for mails with files'),
        );
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(put).toHaveBeenCalledWith(
            '/settings/mail',
            { notify: [2], attachments_only: false },
            expect.anything(),
        );
    });

    it('sends every account when all are checked, for the server to save everybody', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(Mail, { settings });

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(put).toHaveBeenCalledWith(
            '/settings/mail',
            { notify: [1, 2], attachments_only: true },
            expect.anything(),
        );
    });

    it('says nobody is told when no account is checked', async () => {
        renderPage(Mail, { settings });

        await userEvent.click(screen.getByLabelText('Marco'));
        await userEvent.click(screen.getByLabelText('Laura'));
        await settle();

        expect(
            screen.getByText(
                'Nobody is told: the mails still arrive in To sort.',
            ),
        ).toBeInTheDocument();
    });

    it('starts from the accounts the server says are chosen', () => {
        renderPage(Mail, {
            settings: {
                ...settings,
                accounts: [
                    { ...settings.accounts[0]!, chosen: false },
                    settings.accounts[1]!,
                ],
            },
        });

        expect(screen.getByLabelText('Marco')).not.toBeChecked();
        expect(screen.getByLabelText('Laura')).toBeChecked();
    });

    it('lets a member see and copy the address, nothing else', () => {
        renderPage(Mail, { settings: { ...settings, can_edit: false } });

        expect(screen.getByLabelText('Address')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Copy' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Change the address' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Save' }),
        ).not.toBeInTheDocument();
        expect(screen.getByLabelText('Marco')).toBeDisabled();
        expect(
            screen.getByLabelText('Only for mails with files'),
        ).toBeDisabled();
    });

    it('links to the received mail', () => {
        renderPage(Mail, { settings });

        expect(
            screen.getAllByRole('link', { name: 'Received mail' })[0],
        ).toHaveAttribute('href', '/mail');
    });
});
