import { screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { choose, openMenu, renderPage } from '@/test/page';
import Invite from './Invite.vue';

const props = {
    people: [
        { id: 7, first_name: 'Laura', last_name: 'Bianchi', initials: 'LB' },
    ],
    levels: [
        {
            value: 'admin',
            label: 'Everything, like you',
            help: 'Settings, invitations and the subscription too',
        },
        {
            value: 'member',
            label: 'Sees everything and uploads documents',
            help: 'Pays, records and uploads, but does not change the settings',
        },
    ],
    roles: [
        { value: 'parent', label: 'Parent', help: null },
        { value: 'other', label: 'Other', help: null },
    ],
};

describe('Invite a family member', () => {
    it('asks for the email, who they are and what they can do, as the board', async () => {
        renderPage(Invite, props);

        expect(screen.getByLabelText('Their email')).toHaveAttribute(
            'type',
            'email',
        );
        const who = await openMenu('Who they are');
        expect(
            within(who).getByRole('option', { name: 'Laura Bianchi' }),
        ).toBeInTheDocument();
        expect(
            within(who).getByRole('option', { name: 'A new person' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('radio', { name: /Everything, like you/ }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('radio', {
                name: /Sees everything and uploads documents/,
            }),
        ).toBeChecked();
        expect(
            screen.getByText(
                'Pays, records and uploads, but does not change the settings',
            ),
        ).toBeInTheDocument();
        expect(screen.getByText(/valid for 7 days/)).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Send the invitation' }),
        ).toHaveAttribute('type', 'submit');
    });

    it('asks first name, last name and role in the family for a new person', async () => {
        renderPage(Invite, props);
        expect(screen.queryByLabelText('First name')).not.toBeInTheDocument();

        await choose('Who they are', 'A new person');

        expect(await screen.findByLabelText('First name')).toBeInTheDocument();
        expect(screen.getByLabelText('Last name')).toBeInTheDocument();
        expect(
            screen.getByLabelText('In the family they are'),
        ).toBeInTheDocument();
    });

    it('starts from a new person when nobody can be invited', () => {
        renderPage(Invite, { ...props, people: [] });

        expect(screen.getByLabelText('First name')).toBeInTheDocument();
    });
});
