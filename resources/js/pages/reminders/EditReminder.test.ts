import { getNode } from '@formkit/core';
import { router } from '@inertiajs/vue3';
import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    plumberReminder,
    reminderGroups,
    reminderLinks,
    reminderPeople,
    remindersPage,
} from '@/test/fixtures';
import {
    choose,
    openMenu,
    renderPage,
    search,
    settle,
    shown,
    tapCancel,
} from '@/test/page';
import { hasChanges } from '@/lib/unsavedChanges';
import EditReminder from './EditReminder.vue';

const props = {
    reminder: null,
    remindable: null,
    people: reminderPeople,
    groups: reminderGroups,
    default_recipients: ['Marco', 'Laura'],
    default_offsets: [-7, -1, 0],
    can_delete: false,
    reminders: remindersPage,
    links: reminderLinks,
};

/** Makes the page believe it is on a computer, or on a phone. */
function screenWidth(computer: boolean): void {
    vi.stubGlobal('matchMedia', (query: string) => ({
        matches:
            query.includes('prefers-reduced-motion') ||
            (computer && query.includes('min-width')),
        media: query,
        addEventListener: () => {},
        removeEventListener: () => {},
        addListener: () => {},
        removeListener: () => {},
    }));
}

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
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

    it('shows a reminder of the default group as Default, once', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        // 5 is the default group: the server leaves it out of the groups
        renderPage(EditReminder, {
            ...props,
            reminder: { ...plumberReminder, group_id: 5 },
        });

        const list = await openMenu('Notices');
        expect(shown(list)).toEqual(['Default', 'Only Laura']);
        expect(
            within(list).getByRole('option', { name: 'Default' }),
        ).toHaveAttribute('aria-selected', 'true');
        await userEvent.keyboard('{Escape}');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledWith(
            '/reminders/2',
            expect.objectContaining({ reminder_group_id: '' }),
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

    it('says when the group notifies, and changes it with the group', async () => {
        renderPage(EditReminder, props);

        expect(
            screen.getByText(
                '7 days before, 1 day before, and on the day, until it is done.',
            ),
        ).toBeInTheDocument();

        await choose('Notices', 'Only Laura');

        expect(
            screen.getByText('3 days before, until it is done.'),
        ).toBeInTheDocument();
    });

    it('finds the person by typing', async () => {
        renderPage(EditReminder, props);

        const list = await search('Who takes care of it', 'Giu');

        expect(
            within(list).getByRole('option', { name: 'Giulia' }),
        ).toBeInTheDocument();
        expect(
            within(list).queryByRole('option', { name: 'Marco' }),
        ).not.toBeInTheDocument();
    });

    it('is the whole screen on the phone, without the list', () => {
        screenWidth(false);
        renderPage(EditReminder, props);

        expect(
            screen.queryByRole('region', { name: 'Late' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Save' }),
        ).toBeInTheDocument();
    });

    it('is a window over the list of the reminders on the computer', async () => {
        screenWidth(true);
        renderPage(EditReminder, props);

        // The list is behind the window, which hides it from screen readers
        expect(
            await screen.findByRole('region', { name: 'Late', hidden: true }),
        ).toBeInTheDocument();
        const window = await screen.findByRole('dialog', {
            name: 'New reminder',
        });
        expect(
            within(window).getByRole('button', { name: 'Save' }),
        ).toBeInTheDocument();
        expect(
            within(window).getByRole('textbox', { name: /What needs doing/ }),
        ).toBeInTheDocument();
    });

    it('goes back to the reminders when the window closes', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        screenWidth(true);
        renderPage(EditReminder, props);

        await userEvent.keyboard('{Escape}');

        expect(visit).toHaveBeenCalledWith('/reminders');
    });

    it('offers Delete only when the server says it can be deleted', async () => {
        renderPage(EditReminder, {
            ...props,
            reminder: plumberReminder,
            can_delete: true,
        });

        await userEvent.click(screen.getByRole('button', { name: 'Delete' }));

        expect(
            await screen.findByText('Delete this reminder?'),
        ).toBeInTheDocument();
    });

    it('has no Delete for a member or on a new reminder', () => {
        renderPage(EditReminder, { ...props, reminder: plumberReminder });

        expect(
            screen.queryByRole('button', { name: 'Delete' }),
        ).not.toBeInTheDocument();
    });
});

describe('Changes not saved', () => {
    it('has them once a field changes, and gives them up with Cancel', async () => {
        renderPage(EditReminder, props);
        expect(hasChanges()).toBe(false);

        await userEvent.type(
            screen.getByLabelText(/What needs doing/),
            'Pagare il bollo',
        );
        await settle();
        expect(hasChanges()).toBe(true);

        await tapCancel();
        expect(hasChanges()).toBe(false);
    });
});
