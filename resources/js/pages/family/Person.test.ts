import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import {
    healthCard,
    healthCardReminder,
    personDetail,
    plumberReminder,
} from '@/test/fixtures';
import { layOut, watchLayout } from '@/test/layout';
import { renderPage, withTrail } from '@/test/page';
import Person from './Person.vue';

const links: App.Data.LinkGroupData[] = [
    {
        type: 'enrollment',
        label: 'School and activities',
        options: [{ value: '2', label: 'Danza Giulia', help: null }],
    },
];

/** *A person* for Giulia, with the health card expired. */
function renderGiulia(
    person: App.Data.PersonDetailData = personDetail,
    reminders: App.Data.ReminderData[] = [],
): void {
    renderPage(Person, {
        person,
        reminders,
        remindersCount: reminders.length,
        expiring: [healthCard],
        personal: [healthCard],
        documents: [
            {
                ...healthCard,
                id: 19,
                title: 'Iscrizione danza 2026/27',
                expiry_date: null,
            },
        ],
        links,
    });
}

describe('A person', () => {
    it('writes the trail Family > the person into the top bar', () => {
        const { state, provide } = withTrail();
        renderPage(
            Person,
            {
                person: personDetail,
                reminders: [],
                remindersCount: 0,
                expiring: [],
                personal: [],
                documents: [],
                links,
            },
            provide,
        );

        expect(state.pieces.value).toEqual([
            { label: 'Family', href: '/family' },
            { label: 'Giulia Bianchi' },
        ]);
    });

    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-10-26T10:00:00+01:00'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('shows the person, the health card at the top, her documents and her records', () => {
        renderGiulia();

        expect(
            screen.getByRole('heading', { name: 'Giulia Bianchi', level: 1 }),
        ).toBeInTheDocument();
        expect(screen.getByText('Child · 10 years')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Expiring' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/^Expired on 20\/10\/2026$/),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Personal documents' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Iscrizione danza/ }),
        ).toHaveAttribute('href', '/documents/19');
        expect(screen.getByText('Danza Giulia')).toBeInTheDocument();
        expect(screen.getByText('BNCGLI16E64A944K')).toBeInTheDocument();
        expect(screen.getByText('24/05/2016')).toBeInTheDocument();
        expect(screen.getByText(/Giulia has no account/)).toBeInTheDocument();
    });

    it('lets an admin edit and delete a person without an account', () => {
        renderGiulia();

        expect(screen.getByRole('link', { name: 'Edit' })).toHaveAttribute(
            'href',
            '/family/people/3/edit',
        );
        expect(
            screen.getByRole('button', { name: 'Delete' }),
        ).toBeInTheDocument();
    });

    it('a person with an account cannot be deleted, and without the permissions cannot be edited', () => {
        renderGiulia({
            ...personDetail,
            account_label: 'Family member',
            can_edit: false,
            can_delete: false,
        });

        expect(
            screen.queryByRole('link', { name: 'Edit' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Delete' }),
        ).not.toBeInTheDocument();
        expect(screen.getByText('Family member')).toBeInTheDocument();
    });

    it('puts Edit and Delete in the menu when there is no room for them', async () => {
        watchLayout();
        renderGiulia();

        await layOut(100);

        expect(
            screen.queryByRole('link', { name: 'Edit' }),
        ).not.toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );
        expect(
            await screen.findByRole('menuitem', { name: 'Edit' }),
        ).toBeInTheDocument();
        await userEvent.click(screen.getByRole('menuitem', { name: 'Delete' }));
        expect(
            await screen.findByRole('heading', { name: 'Delete Giulia?' }),
        ).toBeInTheDocument();
    });

    it('shows her photo beside the name', () => {
        renderGiulia({ ...personDetail, photo: '/files/40' });

        expect(
            screen.getByRole('img', { name: 'Giulia Bianchi' }),
        ).toHaveAttribute('src', '/files/40');
    });

    it('shows no reminder box and no Reminders entry without open reminders', () => {
        renderGiulia();

        expect(
            screen.queryByRole('link', { name: /Reminders/ }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Reminders' }),
        ).not.toBeInTheDocument();
    });

    it('shows the open reminders at the top, the late one in red, with the link to Reminders', () => {
        renderGiulia(personDetail, [
            healthCardReminder,
            { ...plumberReminder, due_date: '2026-10-31' },
        ]);

        const late = screen.getByRole('alert');
        const [coming] = screen.getAllByRole('status');
        expect(late).toHaveClass('text-overdue');
        expect(
            within(late).getByText('Renew the health card'),
        ).toBeInTheDocument();
        expect(
            within(late).getByText(/Tessera sanitaria di Giulia/),
        ).toBeInTheDocument();
        expect(
            within(late).getByText(/Overdue by 26 days/),
        ).toBeInTheDocument();
        expect(
            within(late).getByRole('link', { name: 'Reminders' }),
        ).toHaveAttribute('href', '/reminders?person=3');
        expect(
            screen.getByRole('link', { name: 'Reminders 2' }),
        ).toHaveAttribute('href', '/reminders?person=3');
        expect(coming).not.toHaveClass('text-overdue');
        expect(within(coming).getByText(/in 5 days/)).toBeInTheDocument();
    });
});
