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
import { agendaEntries, peopleList } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import Agenda from './Agenda.vue';

const areas = [
    ['general', 'People and documents'],
    ['home', 'Home'],
    ['vehicles', 'Vehicles'],
    ['insurance', 'Insurance'],
    ['banking', 'Banks and loans'],
    ['health', 'Health'],
    ['school', 'School and activities'],
    ['work', 'Work'],
    ['subscriptions', 'Subscriptions'],
    ['paperwork', 'Paperwork'],
].map(([value, label]) => ({ value: value!, label: label! }));

/** The props of the page on 26/10/2026, with one month, the whole family and every area. */
function agenda(
    change: Partial<App.Data.AgendaData> = {},
): Record<string, unknown> {
    return {
        agenda: {
            entries: agendaEntries,
            months: 1,
            from: '2026-10-26',
            to: '2026-11-26',
            people: peopleList,
            filters: { months: 1, person_id: null, hidden_areas: [] },
            areas,
            periods: [1, 3, 6, 12],
            open_reminders: 10,
            family_hidden: [],
            ...change,
        },
    };
}

/** Makes the page believe it is on a computer, or on a phone. */
function screenWidth(computer: boolean): void {
    vi.stubGlobal('matchMedia', (query: string) => ({
        // Reduced motion on, as in setup.ts: jsdom cannot animate
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

/** The rows of the list: the links to an entry. */
function rows(): HTMLElement[] {
    return screen
        .queryAllByRole('link')
        .filter(
            (link) =>
                link.getAttribute('href') === '/agenda' &&
                link.closest('nav') === null,
        );
}

let put: MockInstance<typeof router.put>;
let get: MockInstance<typeof router.get>;

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-26T10:00:00+01:00'));
    put = vi.spyOn(router, 'put').mockImplementation(() => undefined);
    get = vi.spyOn(router, 'get').mockImplementation(() => undefined);
    screenWidth(false);
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('Agenda', () => {
    it('has the tabs Deadlines and Reminders with the number still to do, and the way to a new reminder', () => {
        renderPage(Agenda, agenda());

        expect(screen.getByRole('link', { name: 'Deadlines' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(
            screen.getByRole('link', { name: 'Reminders 10' }),
        ).toHaveAttribute('href', '/reminders');
        expect(
            screen.getByRole('link', { name: 'New reminder' }),
        ).toHaveAttribute('href', '/reminders/create');
    });

    it('sums up on the computer what is to sort out, this week and the whole period', () => {
        screenWidth(true);
        renderPage(Agenda, agenda());

        const tiles = screen.getByRole('list', { name: 'In short' });
        const [overdue, week, period] = within(tiles).getAllByRole('listitem');
        expect(overdue).toHaveTextContent('To sort out1passed deadline');
        expect(week).toHaveTextContent(
            'This week122,10 €2 entries, from 26/10 to 01/11',
        );
        expect(period).toHaveTextContent(
            'Until 26/111.417,49 €10 outgoing entries in the next month',
        );
    });

    it('sums up on the phone this week and the whole period', () => {
        renderPage(Agenda, agenda());

        const tiles = screen.getByRole('list', { name: 'In short' });
        const items = within(tiles).getAllByRole('listitem');
        expect(items.map((item) => item.textContent)).toEqual([
            'This week122,10 €2 entries',
            'Until 26/111.417,49 €10 outgoing entries',
        ]);
    });

    it('puts To sort out on top, then this week, next week and the rest of the month', () => {
        renderPage(Agenda, agenda());

        expect(
            screen
                .getAllByRole('heading', { level: 2 })
                .map((heading) => heading.textContent?.trim()),
        ).toEqual([
            'To sort out',
            'This week',
            'Next week',
            'Later, until 26/11',
        ]);
        expect(
            screen.getByText(
                'A passed deadline stays here for three months, until you renew it or fix the date.',
            ),
        ).toBeInTheDocument();
        expect(rows()).toHaveLength(12);
    });

    it('groups by month after next week when the period is longer', () => {
        renderPage(
            Agenda,
            agenda({
                months: 3,
                to: '2027-01-26',
                filters: { months: 3, person_id: null, hidden_areas: [] },
            }),
        );

        expect(
            screen.getByRole('heading', { level: 2, name: 'November 2026' }),
        ).toBeInTheDocument();
    });

    it('sums up the outgoing payments of each group', () => {
        renderPage(Agenda, agenda());

        expect(screen.getByText('122,10 € outgoing')).toBeInTheDocument();
        expect(screen.getByText('76,39 € outgoing')).toBeInTheDocument();
        expect(screen.getByText('1.219,00 € outgoing')).toBeInTheDocument();
    });

    it('offers to update the date or renew only the passed deadlines', () => {
        renderPage(Agenda, agenda());

        const overdue = screen.getByRole('region', { name: 'To sort out' });
        expect(
            within(overdue).getByRole('button', { name: 'Update the date' }),
        ).toBeInTheDocument();
        expect(
            within(overdue).getByRole('button', { name: 'Renewed' }),
        ).toBeInTheDocument();
        expect(screen.getAllByRole('button', { name: 'Renewed' })).toHaveLength(
            1,
        );
    });

    it('opens the panel to update the date of a passed deadline', async () => {
        renderPage(Agenda, agenda());

        await userEvent.click(
            screen.getByRole('button', { name: 'Update the date' }),
        );

        const panel = await screen.findByRole('dialog', {
            name: 'Update the date',
        });
        expect(within(panel).getByLabelText('Correct date')).toHaveValue(
            '30/09/2026',
        );
    });

    it('opens the panel of the renewal with the date a year later', async () => {
        renderPage(Agenda, agenda());

        await userEvent.click(screen.getByRole('button', { name: 'Renewed' }));

        const panel = await screen.findByRole('dialog', { name: 'Renewed' });
        expect(within(panel).getByLabelText('New expiry date')).toHaveValue(
            '30/09/2027',
        );
    });

    it('starts the panel again from the right date when opened the other way', async () => {
        renderPage(Agenda, agenda());
        await userEvent.click(
            screen.getByRole('button', { name: 'Update the date' }),
        );
        const first = await screen.findByRole('dialog');
        await userEvent.click(
            within(first).getByRole('button', { name: 'Cancel' }),
        );

        await userEvent.click(screen.getByRole('button', { name: 'Renewed' }));

        const panel = await screen.findByRole('dialog', { name: 'Renewed' });
        expect(within(panel).getByLabelText('New expiry date')).toHaveValue(
            '30/09/2027',
        );
    });

    it('turns an area off on the computer from the menu of the areas', async () => {
        screenWidth(true);
        renderPage(Agenda, agenda());

        await userEvent.click(
            screen.getByRole('button', { name: 'All areas' }),
        );
        await userEvent.click(
            await screen.findByRole('button', { name: /Work/ }),
        );

        expect(rows()).toHaveLength(10);
        expect(put).toHaveBeenCalled();
    });

    it('leaves out of the menu of the areas an area the family hid, when it has no entries', async () => {
        screenWidth(true);
        renderPage(Agenda, agenda({ family_hidden: ['school'] }));

        await userEvent.click(
            screen.getByRole('button', { name: 'All areas' }),
        );

        expect(
            await screen.findByRole('button', { name: /Work/ }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /School and activities/ }),
        ).toBeNull();
    });

    it('opens the filters from the buttons of the phone', async () => {
        renderPage(Agenda, agenda());

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: '1 month' }));

        const sheet = screen.getByRole('dialog', { name: 'Filter the agenda' });
        expect(
            within(sheet).getByRole('button', { name: 'Show 12 entries' }),
        ).toBeInTheDocument();
    });

    it('asks for the next 3 months when nothing expires', async () => {
        renderPage(Agenda, agenda({ entries: [] }));

        expect(
            screen.getByText('Nothing expiring in the next month'),
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
                    hidden_areas: agendaEntries.map((entry) => entry.area),
                },
            }),
        );

        expect(
            screen.getByText('No entries with these filters'),
        ).toBeInTheDocument();
        expect(rows()).toHaveLength(0);

        await userEvent.click(
            screen.getByRole('button', { name: 'Clear filters' }),
        );

        expect(rows()).toHaveLength(12);
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

    it('saves the person chosen', async () => {
        screenWidth(true);
        renderPage(Agenda, agenda());

        await userEvent.click(screen.getByRole('radio', { name: /Laura/ }));

        expect(put).toHaveBeenCalledWith(
            '/agenda/filters',
            expect.objectContaining({ person_id: 2 }),
            expect.anything(),
        );
    });
});
