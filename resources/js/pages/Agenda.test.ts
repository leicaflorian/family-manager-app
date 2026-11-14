import { router } from '@inertiajs/vue3';
import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import type { MockInstance } from 'vite-plus/test';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import {
    accountOptions,
    agendaEntries,
    agendaEntry,
    agendaReminder,
    lateDeadline,
    peopleList,
} from '@/test/fixtures';
import { answerLeave, leaveQuestion } from '@/lib/unsavedChanges';
import { renderPage, screenWidth, settle } from '@/test/page';
import Agenda from './Agenda.vue';

const areas = [
    ['general', 'People and documents'],
    ['home', 'Home'],
    ['vehicles', 'Vehicles'],
    ['insurance', 'Insurance'],
    ['banks', 'Banks and loans'],
    ['health', 'Health'],
    ['school', 'School and activities'],
    ['work', 'Work'],
    ['subscriptions', 'Subscriptions'],
    ['paperwork', 'Paperwork'],
].map(([value, label]) => ({ value: value!, label: label! }));

/** The gas bill of 23/10, not paid yet: a late payment. */
const lateGas = agendaEntry({
    key: 'payment:7:2026-10-23',
    date: '2026-10-23',
    title: 'Bolletta luce di settembre',
    name: 'Luce - Edison',
    detail: 'Bill',
    amount: 71.3,
    href: '/budget/planned/expense/7/occurrences/2026-10-23?pay=1',
});

/** A task of Laura, written by hand. */
const plumber = agendaEntry({
    kind: 'task',
    key: 'task:9',
    date: '2026-10-29',
    title: "Chiamare l'idraulico",
    name: "Chiamare l'idraulico",
    detail: 'Task',
    person_id: 2,
    person: 'Laura',
    href: '/reminders/9/edit',
    reminder: agendaReminder({ id: 9 }),
});

/** The things to do as the controller sends them on 26/10/2026: no incomes, a late payment and a task. */
const entries: App.Data.AgendaEntryData[] = [
    ...agendaEntries.filter((entry) => entry.kind !== 'income'),
    lateGas,
    plumber,
].sort((a, b) => a.date.localeCompare(b.date));

/**
 * A done entry of the Done list.
 *
 * @param id - the id of its reminder
 * @param title - what it was
 * @param doneOn - the day it was closed
 * @param doneBy - who closed it
 * @returns the entry as the server sends it
 */
function doneEntry(
    id: number,
    title: string,
    doneOn: string,
    doneBy: string,
): App.Data.AgendaEntryData {
    return agendaEntry({
        kind: 'task',
        key: `task:${id}`,
        date: doneOn,
        title,
        name: title,
        detail: 'Task',
        reminder_done: true,
        reminder: agendaReminder({
            id,
            state: 'done' as App.Enums.AgendaReminderState,
            done_on: doneOn,
            done_by: doneBy,
        }),
    });
}

/** The done things of the Bianchi: two in November, one in October. */
const doneEntries = [
    doneEntry(21, 'Revisione Panda', '2026-11-01', 'Marco'),
    doneEntry(22, 'Rinnovare la palestra', '2026-11-01', 'Laura'),
    doneEntry(23, 'Ritirare gli occhiali', '2026-10-22', 'Laura'),
];

/** The props of the page on 26/10/2026, with one month, the whole family and every area. */
function agenda(
    change: Partial<App.Data.AgendaData> = {},
    page: Record<string, unknown> = {},
): Record<string, unknown> {
    return {
        agenda: {
            entries,
            months: 1,
            from: '2026-10-26',
            to: '2026-11-26',
            people: peopleList,
            filters: { months: 1, person_id: null, hidden_areas: [] },
            areas,
            periods: [1, 3, 6, 12],
            view: 'todo',
            family_hidden: [],
            ...change,
        },
        done: [],
        done_next_page: null,
        ...page,
    };
}

/** The props of the Done view, with the first page of the done things. */
function doneView(page: Record<string, unknown> = {}): Record<string, unknown> {
    return agenda({ view: 'done' }, { done: doneEntries, ...page });
}

/**
 * The row of an entry: a button that opens its panel.
 *
 * @param name - a part of what it says
 */
function row(name: string | RegExp): HTMLElement {
    return screen.getByRole('button', { name });
}

let put: MockInstance<typeof router.put>;
let get: MockInstance<typeof router.get>;
let post: MockInstance<typeof router.post>;
let reload: MockInstance<typeof router.reload>;

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-26T10:00:00+01:00'));
    put = vi.spyOn(router, 'put').mockImplementation(() => undefined);
    get = vi.spyOn(router, 'get').mockImplementation(() => undefined);
    post = vi.spyOn(router, 'post').mockImplementation(() => undefined);
    reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    screenWidth(false);
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('Agenda', () => {
    it('has no tiles nor outgoing sums, and says how many things are late', () => {
        renderPage(Agenda, agenda());

        expect(
            screen.queryByRole('list', { name: 'In short' }),
        ).not.toBeInTheDocument();
        expect(screen.queryByText(/outgoing/)).not.toBeInTheDocument();
        expect(screen.getByRole('status')).toHaveTextContent('2 things late');
    });

    it('counts only the late things the filters show', () => {
        renderPage(
            Agenda,
            agenda({
                filters: { months: 1, person_id: 1, hidden_areas: [] },
            }),
        );

        expect(screen.getByRole('status')).toHaveTextContent('1 thing late');
        expect(
            screen.getByRole('link', { name: 'To do, 1 late' }),
        ).toBeInTheDocument();
    });

    it('counts no late thing in the areas turned off', () => {
        renderPage(
            Agenda,
            agenda({
                filters: {
                    months: 1,
                    person_id: null,
                    hidden_areas: ['home', 'general'],
                },
            }),
        );

        expect(screen.queryByRole('status')).not.toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'To do' })).toBeInTheDocument();
    });

    it('says nothing is late when nothing is', () => {
        renderPage(
            Agenda,
            agenda({
                entries: entries.filter((entry) => entry.date >= '2026-10-26'),
            }),
        );

        expect(screen.queryByRole('status')).not.toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'To do' })).toBeInTheDocument();
    });

    it('switches between To do, with the late ones, and Done, and leads to a new task', () => {
        renderPage(Agenda, agenda());

        const views = screen.getByRole('navigation', { name: 'Agenda views' });
        expect(
            within(views).getByRole('link', { name: 'To do, 2 late' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            within(views).getByRole('link', { name: 'Completed' }),
        ).toHaveAttribute('href', '/agenda?view=done');
        expect(screen.getByRole('link', { name: 'New task' })).toHaveAttribute(
            'href',
            '/reminders/create',
        );
    });

    it('puts the late things on top, then this week, next week and the rest of the month', () => {
        renderPage(Agenda, agenda());

        expect(
            screen
                .getAllByRole('heading', { level: 2 })
                .map((heading) => heading.textContent?.trim()),
        ).toEqual(['Late', 'This week', 'Next week', 'Later, until 26/11']);
        const late = screen.getByRole('region', { name: 'Late' });
        expect(within(late).getAllByRole('button')).toHaveLength(2);
    });

    it('opens the panel of a row from below on the phone', async () => {
        renderPage(Agenda, agenda());

        await userEvent.click(row(/Chiamare l'idraulico/));

        const panel = await screen.findByRole('dialog', {
            name: "Chiamare l'idraulico",
        });
        expect(
            within(panel).getByRole('button', { name: 'Postpone' }),
        ).toBeInTheDocument();
    });

    it('opens the panel of a row beside the list on the computer', async () => {
        screenWidth(true);
        renderPage(Agenda, agenda());

        await userEvent.click(row(/Tessera sanitaria di Giulia/));

        const panel = screen.getByRole('dialog', {
            name: 'Tessera sanitaria di Giulia',
        });
        expect(panel).toHaveAttribute('aria-modal', 'false');

        await userEvent.click(
            within(panel).getByRole('button', { name: 'Close' }),
        );

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('asks for the new day with Postpone from the panel', async () => {
        renderPage(Agenda, agenda());
        await userEvent.click(row(/Chiamare l'idraulico/));
        const panel = await screen.findByRole('dialog', {
            name: "Chiamare l'idraulico",
        });

        await userEvent.click(
            within(panel).getByRole('button', { name: 'Postpone' }),
        );

        expect(
            await screen.findByRole('dialog', { name: 'Postpone' }),
        ).toBeInTheDocument();
    });

    it('asks for the new date with Update the date from the panel', async () => {
        renderPage(Agenda, agenda());
        await userEvent.click(row(/Tessera sanitaria di Giulia/));
        const panel = await screen.findByRole('dialog', {
            name: 'Tessera sanitaria di Giulia',
        });

        await userEvent.click(
            within(panel).getByRole('button', { name: 'Update the date' }),
        );

        const sheet = await screen.findByRole('dialog', {
            name: 'Update the date',
        });
        expect(within(sheet).getByLabelText('Correct date')).toHaveValue(
            '30/09/2026',
        );
    });

    it('proposes the date a year later with Renewed from the panel', async () => {
        renderPage(Agenda, agenda());
        await userEvent.click(row(/Tessera sanitaria di Giulia/));
        const panel = await screen.findByRole('dialog', {
            name: 'Tessera sanitaria di Giulia',
        });

        await userEvent.click(
            within(panel).getByRole('button', { name: 'Renewed' }),
        );

        const sheet = await screen.findByRole('dialog', { name: 'Renewed' });
        expect(within(sheet).getByLabelText('New expiry date')).toHaveValue(
            '30/09/2027',
        );
    });

    it('opening a payment asks for its due date and records it in one tap', async () => {
        const view = renderPage(Agenda, agenda());
        await userEvent.click(row(/Luce - Edison/));

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ['payment', 'accounts'],
                data: { payment: 'payment:7:2026-10-23' },
                preserveUrl: true,
            }),
        );

        await view.rerender(
            agenda({}, { payment: lateDeadline, accounts: accountOptions }),
        );
        const panel = await screen.findByRole('dialog', {
            name: 'Luce - Edison',
        });
        await userEvent.click(
            within(panel).getByRole('button', { name: 'I paid it' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/budget/planned/expense/7/occurrences/2026-10-23/payment?from_agenda=1',
            expect.anything(),
            expect.anything(),
        );
        // No second panel: the payment is recorded from the panel of the row
        expect(
            screen.queryByRole('dialog', { name: 'I paid it' }),
        ).not.toBeInTheDocument();
    });

    it('closes the panel of a payment once it is recorded', async () => {
        post.mockImplementation((_url, _data, options) => {
            options?.onSuccess?.({} as never);
        });
        const view = renderPage(Agenda, agenda());
        await userEvent.click(row(/Luce - Edison/));
        await view.rerender(
            agenda({}, { payment: lateDeadline, accounts: accountOptions }),
        );
        const panel = await screen.findByRole('dialog', {
            name: 'Luce - Edison',
        });

        await userEvent.click(
            within(panel).getByRole('button', { name: 'I paid it' }),
        );

        await vi.waitFor(() =>
            expect(
                screen.queryByRole('dialog', { name: 'Luce - Edison' }),
            ).not.toBeInTheDocument(),
        );
    });

    it('asks before closing the panel of a payment with changes', async () => {
        const view = renderPage(Agenda, agenda());
        await userEvent.click(row(/Luce - Edison/));
        await view.rerender(
            agenda({}, { payment: lateDeadline, accounts: accountOptions }),
        );
        const panel = await screen.findByRole('dialog', {
            name: 'Luce - Edison',
        });
        await userEvent.click(
            within(panel).getByRole('button', { name: 'Carta Laura' }),
        );
        await settle();

        await userEvent.click(
            within(panel).getByRole('button', { name: 'Close' }),
        );

        expect(leaveQuestion.open).toBe(true);
        answerLeave(false);
        await settle();
        expect(
            screen.getByRole('dialog', { name: 'Luce - Edison' }),
        ).toBeInTheDocument();
    });

    it('does not show the due date of another payment', async () => {
        const view = renderPage(
            Agenda,
            agenda({}, { payment: lateDeadline, accounts: accountOptions }),
        );
        await view.rerender(
            agenda({}, { payment: lateDeadline, accounts: accountOptions }),
        );

        await userEvent.click(row(/Bolletta gas/));
        const panel = await screen.findByRole('dialog', {
            name: 'Gas - Enel',
        });

        expect(
            within(panel).getByRole('button', { name: 'I paid it' }),
        ).toBeDisabled();
        expect(
            within(panel).queryByLabelText('How much'),
        ).not.toBeInTheDocument();
    });

    it('closes the panel once Done is saved', async () => {
        post.mockImplementation((_url, _data, options) => {
            options?.onSuccess?.({} as never);
        });
        renderPage(Agenda, agenda());
        await userEvent.click(row(/Chiamare l'idraulico/));
        const panel = await screen.findByRole('dialog', {
            name: "Chiamare l'idraulico",
        });

        await userEvent.click(
            within(panel).getByRole('button', { name: 'Done' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/reminders/9/done',
            expect.anything(),
            expect.anything(),
        );
        await vi.waitFor(() =>
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
        );
    });

    it('hides the task of another person with the person filter', async () => {
        screenWidth(true);
        renderPage(Agenda, agenda());

        expect(row(/Chiamare l'idraulico/)).toBeInTheDocument();

        await userEvent.click(screen.getByRole('radio', { name: /Marco/ }));

        expect(
            screen.queryByRole('button', { name: /Chiamare l'idraulico/ }),
        ).not.toBeInTheDocument();
        expect(put).toHaveBeenCalledWith(
            '/agenda/filters',
            expect.objectContaining({ person_id: 1 }),
            expect.anything(),
        );
    });

    it('turns an area off on the computer from the menu of the areas', async () => {
        screenWidth(true);
        renderPage(Agenda, agenda());

        await userEvent.click(
            screen.getByRole('button', { name: 'All areas' }),
        );
        await userEvent.click(
            await screen.findByRole('button', { name: /Subscriptions/ }),
        );

        expect(
            screen.queryByRole('button', { name: /Primo pagamento/ }),
        ).not.toBeInTheDocument();
        expect(put).toHaveBeenCalled();
    });

    it('opens the filters from the buttons of the phone', async () => {
        renderPage(Agenda, agenda());

        await userEvent.click(screen.getByRole('button', { name: '1 month' }));

        expect(
            screen.getByRole('dialog', { name: 'Filter the agenda' }),
        ).toBeInTheDocument();
    });

    it('asks for the next 3 months when there is nothing to do', async () => {
        renderPage(Agenda, agenda({ entries: [] }));

        expect(
            screen.getByText('Nothing to do in the next month'),
        ).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'See the next 3 months' }),
        );

        expect(get).toHaveBeenCalledWith(
            '/agenda',
            { months: 3 },
            expect.anything(),
        );
    });

    it('shows a way out when the filters hide every entry', async () => {
        renderPage(
            Agenda,
            agenda({
                filters: {
                    months: 1,
                    person_id: null,
                    hidden_areas: areas.map((area) => area.value),
                },
            }),
        );

        expect(
            screen.getByText('No entries with these filters'),
        ).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Clear filters' }),
        );

        expect(row(/Chiamare l'idraulico/)).toBeInTheDocument();
    });

    it('loads the period with a visit and saves nothing else', async () => {
        screenWidth(true);
        renderPage(Agenda, agenda());

        await userEvent.click(screen.getByRole('radio', { name: '3 months' }));

        expect(get).toHaveBeenCalledWith(
            '/agenda',
            { months: 3 },
            expect.anything(),
        );
        expect(put).not.toHaveBeenCalled();
    });
});

describe('Agenda, the Done view', () => {
    it('groups the done things by month, saying who closed them and when', () => {
        renderPage(Agenda, doneView());

        expect(
            screen
                .getAllByRole('heading', { level: 2 })
                .map((heading) => heading.textContent?.trim()),
        ).toEqual(['November', 'October']);
        const november = screen.getByRole('region', { name: 'November' });
        expect(within(november).getAllByRole('button')).toHaveLength(2);
        expect(
            within(november).getByText('Done on 01/11 · Marco'),
        ).toBeInTheDocument();
        expect(screen.getByText('Done on 22/10 · Laura')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Completed' })).toHaveAttribute(
            'aria-current',
            'page',
        );
    });

    it('has no late line nor period, and says where the paid expenses are', () => {
        screenWidth(true);
        renderPage(Agenda, doneView());

        expect(screen.queryByRole('status')).not.toBeInTheDocument();
        expect(
            screen.queryByRole('radio', { name: '3 months' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText(
                'Paid expenses are in the movements of the Budget',
            ),
        ).toBeInTheDocument();
    });

    it('opens the panel of a done thing, with Reopen', async () => {
        renderPage(Agenda, doneView());

        await userEvent.click(row(/Revisione Panda/));

        const panel = await screen.findByRole('dialog', {
            name: 'Revisione Panda',
        });
        expect(
            within(panel).getByRole('button', { name: 'Reopen' }),
        ).toBeInTheDocument();
        expect(
            within(panel).queryByRole('button', { name: 'Done' }),
        ).not.toBeInTheDocument();
    });

    it('loads the earlier months with a button, without changing the address', async () => {
        renderPage(Agenda, doneView({ done_next_page: 2 }));

        await userEvent.click(
            screen.getByRole('button', { name: 'Show earlier months' }),
        );

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({
                data: { done_page: 2 },
                only: ['done', 'done_next_page'],
                preserveUrl: true,
            }),
        );
    });

    it('has no button for earlier months on the last page', () => {
        renderPage(Agenda, doneView());

        expect(
            screen.queryByRole('button', { name: 'Show earlier months' }),
        ).not.toBeInTheDocument();
    });

    it('has Reopen on each done row on the computer, and only in the panel on the phone', () => {
        screenWidth(true);
        const view = renderPage(Agenda, doneView());

        expect(
            screen.getByRole('button', { name: 'Reopen: Revisione Panda' }),
        ).toBeInTheDocument();
        view.unmount();

        screenWidth(false);
        renderPage(Agenda, doneView());
        expect(
            screen.queryByRole('button', { name: 'Reopen: Revisione Panda' }),
        ).not.toBeInTheDocument();
    });

    it('shows only the done things the server sends after a filter changed them', async () => {
        const view = renderPage(Agenda, doneView());

        await view.rerender(doneView({ done: [doneEntries[2]] }));

        expect(
            screen.queryByRole('button', { name: /Revisione Panda/ }),
        ).not.toBeInTheDocument();
        expect(row(/Ritirare gli occhiali/)).toBeInTheDocument();
    });

    it('reloads the whole list when the person changes, so the pages start again', async () => {
        screenWidth(true);
        renderPage(Agenda, doneView({ done_next_page: 2 }));

        await userEvent.click(screen.getByRole('radio', { name: /Laura/ }));

        const options = put.mock.calls[0]?.[2];
        expect(options).toBeDefined();
        expect(options).not.toHaveProperty('only');
        expect(options).not.toHaveProperty('reset');
    });
});
