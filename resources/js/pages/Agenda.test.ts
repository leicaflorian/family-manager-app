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
        .filter((link) => link.getAttribute('href') === '/agenda');
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
    it('puts To sort out on top with the line about the passed deadlines', () => {
        renderPage(Agenda, agenda());

        const [first] = screen.getAllByRole('heading', { level: 2 });
        expect(first).toHaveTextContent('To sort out');
        expect(
            screen.getByText(
                'Deadlines passed in the last three months: renew them, or update the date on the card.',
            ),
        ).toBeInTheDocument();
        expect(screen.getByText('October 2026')).toBeInTheDocument();
        expect(screen.getByText('November 2026')).toBeInTheDocument();
        expect(rows()).toHaveLength(12);
    });

    it('sums up the outgoing payments of each month', () => {
        renderPage(Agenda, agenda());

        expect(screen.getByText('122,10 € outgoing')).toBeInTheDocument();
        expect(screen.getByText('1.295,39 € outgoing')).toBeInTheDocument();
    });

    it('turns an area off on the computer: the rows and the summary change', async () => {
        screenWidth(true);
        renderPage(Agenda, agenda());
        expect(
            screen.getByText(
                /12 entries in the next month, from 26\/10 to 26\/11/,
            ),
        ).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: /Work/ }));

        expect(rows()).toHaveLength(10);
        expect(
            screen.getByText(/10 entries in the next month.*2 hidden: Work/),
        ).toBeInTheDocument();
        expect(put).toHaveBeenCalled();
    });

    it('puts everything back with Clear filters', async () => {
        screenWidth(true);
        renderPage(
            Agenda,
            agenda({
                filters: { months: 1, person_id: null, hidden_areas: ['work'] },
            }),
        );
        expect(rows()).toHaveLength(10);

        await userEvent.click(
            screen.getByRole('button', { name: 'Clear filters' }),
        );

        expect(rows()).toHaveLength(12);
    });

    it('opens the filters from the line of the phone', async () => {
        renderPage(Agenda, agenda());

        expect(
            screen.getByText('Next month · Whole family · All areas'),
        ).toBeInTheDocument();
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Change' }));

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

    it('says in the end that late payments are in the Home', () => {
        renderPage(Agenda, agenda());

        expect(
            screen.getByText(
                'Payments show from today on: the late ones are on the Home.',
            ),
        ).toBeInTheDocument();
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
