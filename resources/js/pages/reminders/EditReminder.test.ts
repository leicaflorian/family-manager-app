import { getNode } from '@formkit/core';
import { router } from '@inertiajs/vue3';
import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    plumberReminder,
    reminderGroups,
    reminderLinks,
    reminderPeople,
} from '@/test/fixtures';
import { choose, renderPage, search, settle } from '@/test/page';
import EditReminder from './EditReminder.vue';

const props = {
    reminder: null,
    remindable: null,
    people: reminderPeople,
    groups: reminderGroups,
    default_recipients: ['Marco', 'Laura'],
    links: reminderLinks,
};

afterEach(() => {
    vi.restoreAllMocks();
});

describe('Edit a reminder', () => {
    it('is the empty form, with the recipients of the default group', () => {
        renderPage(EditReminder, props);

        expect(
            screen.getByRole('heading', { name: 'New reminder', level: 1 }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'Notices arrive in the bell and by email to those with an account: Marco and Laura.',
            ),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Cancel' })).toHaveAttribute(
            'href',
            '/reminders',
        );
    });

    it('does not send an empty title or date', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditReminder, props);

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).not.toHaveBeenCalled();
        expect(
            screen.getAllByText('This field is required.').length,
        ).toBeGreaterThanOrEqual(2);
    });

    it('sends the title, the day, the person, the thing and the group', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditReminder, props);

        await userEvent.type(
            screen.getByRole('textbox', { name: /What needs doing/ }),
            'Call the plumber',
        );
        // The calendar of the datepicker cannot be driven in jsdom: the day is set on the field
        await getNode('due_date')?.input('2026-10-31');
        await settle();
        await choose('Who takes care of it', 'Laura');
        await search('Refers to', 'Casa');
        await userEvent.click(
            screen.getByRole('option', { name: /Casa di via Roma/ }),
        );
        await settle();
        await choose('Notices', 'Only Laura');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledWith(
            '/reminders',
            {
                title: 'Call the plumber',
                due_date: '2026-10-31',
                person_id: '3',
                remindable: 'home:1',
                reminder_group_id: '6',
            },
            expect.anything(),
        );
    });

    it('names the people of the group chosen', async () => {
        renderPage(EditReminder, props);

        await choose('Notices', 'Only Laura');

        expect(
            screen.getByText(
                'Notices arrive in the bell and by email to those with an account: Laura.',
            ),
        ).toBeInTheDocument();
    });

    it('starts from the values of a reminder as the server sends them, and saves it as a PUT', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditReminder, {
            ...props,
            reminder: { ...plumberReminder, group_id: 6 },
            remindable: 'home:1',
        });

        expect(
            screen.getByRole('heading', { name: 'Edit reminder', level: 1 }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('textbox', { name: /What needs doing/ }),
        ).toHaveValue("Chiamare l'idraulico per il preventivo del bagno");
        expect(screen.getByRole('combobox', { name: 'Refers to' })).toHaveValue(
            'Homes · Casa di via Roma',
        );
        expect(
            screen.getByText(
                'Notices arrive in the bell and by email to those with an account: Laura.',
            ),
        ).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledWith(
            '/reminders/2',
            {
                title: "Chiamare l'idraulico per il preventivo del bagno",
                due_date: '2026-10-31',
                person_id: '3',
                remindable: 'home:1',
                reminder_group_id: '6',
                _method: 'put',
            },
            expect.anything(),
        );
    });

    it('sends an empty person, thing and group as empty', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditReminder, {
            ...props,
            reminder: {
                ...plumberReminder,
                person: null,
                subject: null,
                group_id: null,
            },
        });

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledWith(
            '/reminders/2',
            expect.objectContaining({
                person_id: '',
                remindable: '',
                reminder_group_id: '',
            }),
            expect.anything(),
        );
    });
});
