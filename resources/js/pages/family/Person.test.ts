import { router } from '@inertiajs/vue3';
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

/** The document props of *A person* as the controller sends them: one page, nothing more to load. */
function documentProps(
    personal: App.Data.DocumentData[] = [healthCard],
    documents: App.Data.DocumentData[] = [
        {
            ...healthCard,
            id: 19,
            title: 'Iscrizione danza 2026/27',
            expiry_date: null,
        },
    ],
): Record<string, unknown> {
    return {
        personal,
        personalNext: null,
        personalTotal: personal.length,
        documents,
        documentsNext: null,
        documentsTotal: documents.length,
        pageSizes: { personal: 5, documents: 10 },
    };
}

/** *A person* for Giulia, with the health card expired. */
function renderGiulia(
    person: App.Data.PersonDetailData = personDetail,
    reminders: App.Data.ReminderData[] = [],
    documents: Record<string, unknown> = documentProps(),
): void {
    renderPage(Person, {
        person,
        reminders,
        remindersCount: reminders.length,
        ...documents,
        links,
    });
}

/** Seven open reminders of Giulia, the first one late. */
function sevenReminders(): App.Data.ReminderData[] {
    return [
        healthCardReminder,
        ...Array.from({ length: 6 }, (_, index) => ({
            ...plumberReminder,
            id: 100 + index,
            title: `Task ${index + 1}`,
            due_date: '2026-10-31',
        })),
    ];
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
                ...documentProps([], []),
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

    it('shows the person, her documents and her records', () => {
        renderGiulia();

        expect(
            screen.getByRole('heading', { name: 'Giulia Bianchi', level: 1 }),
        ).toBeInTheDocument();
        expect(screen.getByText('Child · 10 years')).toBeInTheDocument();
        expect(
            screen.queryByRole('heading', { name: 'Expiring' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Personal documents 1' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Other documents 1' }),
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

    it('has the photo before the one title of the page, from FmTitle', () => {
        renderGiulia({ ...personDetail, photo: '/files/40' });

        const title = screen.getByRole('heading', { level: 1 });
        expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
        expect(title).toHaveTextContent('Giulia Bianchi');
        expect(
            screen
                .getByRole('img', { name: 'Giulia Bianchi' })
                .compareDocumentPosition(title) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
    });

    it('shows her photo beside the name', () => {
        renderGiulia({ ...personDetail, photo: '/files/40' });

        expect(
            screen.getByRole('img', { name: 'Giulia Bianchi' }),
        ).toHaveAttribute('src', '/files/40');
    });

    it('shows no reminder box and no To do entry without open reminders', () => {
        renderGiulia();

        expect(
            screen.queryByRole('link', { name: /To do/ }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Agenda' }),
        ).not.toBeInTheDocument();
    });

    it('shows the late reminder in red above the To do box, with the link to the Agenda of the person', () => {
        renderGiulia(personDetail, [
            healthCardReminder,
            { ...plumberReminder, due_date: '2026-10-31' },
        ]);

        const late = screen.getByRole('alert');
        expect(late).toHaveClass('text-overdue');
        expect(
            within(late).getByText('Renew the health card'),
        ).toBeInTheDocument();
        expect(
            within(late).getByText(/Overdue by 26 days/),
        ).toBeInTheDocument();
        expect(
            within(late).getByRole('link', { name: 'Agenda' }),
        ).toHaveAttribute('href', '/reminders?person=3');
        // The coming one is a row of To do, not a notice of its own
        expect(
            within(screen.getByRole('region', { name: 'To do' })).getByText(
                /in 5 days/,
            ),
        ).toBeInTheDocument();
        expect(
            screen
                .queryAllByRole('status')
                .filter((notice) => /in 5 days/.test(notice.textContent ?? '')),
        ).toHaveLength(0);
    });

    it('lists the open reminders in the To do box, five at a time', async () => {
        renderGiulia(personDetail, sevenReminders());

        const box = screen.getByRole('region', { name: 'To do' });
        expect(
            within(box).getByRole('heading', { name: 'To do 7' }),
        ).toBeInTheDocument();
        expect(
            within(box).getByRole('link', { name: 'Open in the Agenda' }),
        ).toHaveAttribute('href', '/reminders?person=3');
        expect(
            within(box).getAllByRole('link', { name: /Overdue|in 5 days/ }),
        ).toHaveLength(5);

        await userEvent.click(
            within(box).getByRole('button', { name: 'Show 2 more · 2 left' }),
        );

        expect(
            within(box).getAllByRole('link', { name: /Overdue|in 5 days/ }),
        ).toHaveLength(7);
        expect(
            within(box).queryByRole('button', { name: /Show .* more/ }),
        ).not.toBeInTheDocument();
    });

    it('asks the server for the next personal documents, keeping the address', async () => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        renderGiulia(personDetail, [], {
            ...documentProps(),
            personalNext: 2,
            personalTotal: 7,
        });

        const box = screen.getByRole('region', { name: 'Personal documents' });
        expect(
            within(box).getByRole('heading', { name: 'Personal documents 7' }),
        ).toBeInTheDocument();
        expect(within(box).getByRole('link', { name: 'Add' })).toHaveAttribute(
            'href',
            '/upload',
        );
        await userEvent.click(
            within(box).getByRole('button', {
                name: 'Show 5 more documents · 6 left',
            }),
        );

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({
                data: { personal_page: 2 },
                only: ['personal', 'personalNext', 'personalTotal'],
                preserveUrl: true,
            }),
        );
        reload.mockRestore();
    });

    it('promises as many personal documents as a page of the server holds', () => {
        renderGiulia(personDetail, [], {
            ...documentProps(),
            personalNext: 2,
            personalTotal: 7,
            pageSizes: { personal: 3, documents: 10 },
        });

        expect(
            screen.getByRole('button', {
                name: 'Show 3 more documents · 6 left',
            }),
        ).toBeInTheDocument();
    });

    it('asks the server for the next other documents, and leads to the Archive of the person', async () => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        renderGiulia(personDetail, [], {
            ...documentProps(),
            documentsNext: 2,
            documentsTotal: 12,
        });

        const box = screen.getByRole('region', { name: 'Other documents' });
        expect(
            within(box).getByRole('link', { name: 'Open in the Archive' }),
        ).toHaveAttribute('href', '/documents?person=3');
        await userEvent.click(
            within(box).getByRole('button', {
                name: 'Show 10 more documents · 11 left',
            }),
        );

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({
                data: { documents_page: 2 },
                only: ['documents', 'documentsNext', 'documentsTotal'],
                preserveUrl: true,
            }),
        );
        reload.mockRestore();
    });

    it('keeps Show more of a list disabled while its page loads, also when the other list asks for its own', async () => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        renderGiulia(personDetail, [], {
            ...documentProps(),
            personalNext: 2,
            personalTotal: 7,
            documentsNext: 2,
            documentsTotal: 12,
        });

        const personal = screen.getByRole('button', {
            name: 'Show 5 more documents · 6 left',
        });
        await userEvent.click(personal);
        await userEvent.click(
            screen.getByRole('button', {
                name: 'Show 10 more documents · 11 left',
            }),
        );

        expect(personal).toBeDisabled();
        expect(reload).toHaveBeenCalledTimes(2);
        reload.mockRestore();
    });

    it('says when there are no personal documents, without a number', () => {
        renderGiulia(personDetail, [], documentProps([], []));

        expect(
            screen.getByRole('heading', { name: 'Personal documents' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('No personal documents yet.'),
        ).toBeInTheDocument();
    });
});
