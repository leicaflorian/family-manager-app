import { router } from '@inertiajs/vue3';
import { screen, waitFor, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { WIDE_QUERY } from '@/composables/useWideLayout';
import { agendaEntries, seenNotices, unreadNotices } from '@/test/fixtures';
import { press, renderPage } from '@/test/page';
import Home from './Home.vue';

const shared = vi.hoisted(() => ({ unread_notices: 0 }));

// The shared props of the page, and no <Head> as in `setup.ts`
vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    usePage: () => ({ url: '/dashboard', props: { ...shared } }),
}));

/** The Home of the Bianchi on 26/10/2026, as the server sends it. */
const home: App.Data.HomeData = {
    first_name: 'Marco',
    today: '2026-10-26',
    total: 1488.79,
    payments: 8,
    late: 1,
    entries: agendaEntries.filter((entry) => entry.kind !== 'income'),
    more: false,
    incoming: agendaEntries.filter((entry) => entry.kind === 'income'),
    pending_count: 0,
    checklist: [],
    missing: [],
    mails: [],
};

/** The Home of a new family: nothing to pay, four steps to begin. */
const newFamily: App.Data.HomeData = {
    ...home,
    total: 0,
    payments: 0,
    late: 0,
    entries: [],
    incoming: [],
    checklist: [
        { key: 'utilities', done: true, name: null },
        { key: 'first_bill', done: false, name: null },
        { key: 'card', done: false, name: null },
        { key: 'invite', done: false, name: 'Laura' },
    ],
};

/**
 * Makes the window wide, as a computer, or narrow, as a phone.
 *
 * @param on - true for the computer
 */
function wide(on: boolean): void {
    vi.stubGlobal('matchMedia', (query: string) => ({
        matches: on && query === WIDE_QUERY,
        media: query,
        addEventListener: () => {},
        removeEventListener: () => {},
    }));
}

/** The first results for «Pan», as the suggest route sends them: 10 in all. */
const pan: App.Data.Search.SearchResultsData = {
    query: 'Pan',
    type: null,
    total: 10,
    groups: [
        {
            group: 'cards',
            label: 'Cards',
            count: 3,
            results: [
                {
                    key: 'card-1',
                    group: 'cards',
                    title: 'Fiat Panda',
                    subtitle: 'Vehicles',
                    date: null,
                    amount: null,
                    href: '/card-1',
                    area: 'vehicles',
                    icon: null,
                    tone: null,
                    flag: null,
                    due_in: null,
                },
            ],
        },
    ],
};

describe('Home search', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() =>
                Promise.resolve({
                    ok: true,
                    status: 200,
                    json: () => Promise.resolve(pan),
                }),
            ),
        );
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('on the computer has the search box with its shortcut beside the square bell', () => {
        wide(true);
        renderPage(Home, { home });

        const box = screen.getByRole('combobox', {
            name: 'Search in Family Manager',
        });
        expect(box).toHaveAttribute('aria-keyshortcuts', 'Meta+K Control+K');
        expect(screen.getByRole('button', { name: /Notices/ })).toHaveClass(
            'rounded-lg',
            'border-edge-soft',
        );
        expect(
            screen.queryByRole('link', { name: 'Search in Family Manager' }),
        ).not.toBeInTheDocument();
    });

    it('focuses the box with ⌘K', () => {
        wide(true);
        renderPage(Home, { home });

        press({ key: 'k', metaKey: true });

        expect(
            screen.getByRole('combobox', { name: 'Search in Family Manager' }),
        ).toHaveFocus();
    });

    it('opens the menu with the first results while typing, and Enter searches the text', async () => {
        wide(true);
        const visit = vi
            .spyOn(router, 'visit')
            .mockImplementation(() => undefined);
        renderPage(Home, { home });
        const box = screen.getByRole('combobox', {
            name: 'Search in Family Manager',
        });

        await userEvent.type(box, 'Pan');

        expect(
            await screen.findByRole('link', { name: /See all 10 results/ }),
        ).toHaveAttribute('href', '/search?q=Pan');
        expect(box).toHaveAttribute('aria-expanded', 'true');

        await userEvent.keyboard('{Enter}');
        expect(visit).toHaveBeenCalledWith('/search?q=Pan');
    });

    it('closes the menu with Esc', async () => {
        wide(true);
        renderPage(Home, { home });
        const box = screen.getByRole('combobox', {
            name: 'Search in Family Manager',
        });
        await userEvent.type(box, 'Pan');
        await screen.findByRole('link', { name: /See all 10 results/ });

        await userEvent.keyboard('{Escape}');

        await waitFor(() =>
            expect(
                screen.queryByRole('link', { name: /See all 10 results/ }),
            ).not.toBeInTheDocument(),
        );
    });

    it('on the phone has the magnifier that opens Search, and no box', () => {
        wide(false);
        renderPage(Home, { home });

        expect(
            screen.getByRole('link', { name: 'Search in Family Manager' }),
        ).toHaveAttribute('href', '/search');
        expect(
            screen.queryByRole('combobox', {
                name: 'Search in Family Manager',
            }),
        ).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Notices/ })).toHaveClass(
            'rounded-full',
        );
    });
});

describe('Home', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-10-26T10:00:00+01:00'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('greets with the day and the first name', () => {
        renderPage(Home, { home });

        expect(screen.getByText('Monday 26 October 2026')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Hi, Marco', level: 1 }),
        ).toBeInTheDocument();
    });

    it('has the bell beside the greeting, with how many notices are new', () => {
        shared.unread_notices = 2;
        renderPage(Home, { home });

        expect(
            screen.getByRole('button', { name: 'Notices: 2 new' }),
        ).toBeInTheDocument();
        shared.unread_notices = 0;
    });

    it('loads only the notices when the bell opens its menu, without changing the address', async () => {
        const reload = vi
            .spyOn(router, 'reload')
            .mockImplementation(() => undefined);
        renderPage(Home, { home });

        await userEvent.click(screen.getByRole('button', { name: 'Notices' }));

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({ only: ['notices'], preserveUrl: true }),
        );
        reload.mockRestore();
    });

    it('shows the notices in the menu of the bell once they arrive', async () => {
        vi.spyOn(router, 'reload').mockImplementation(() => undefined);
        renderPage(Home, {
            home,
            notices: { unread: unreadNotices, seen: seenNotices },
        });

        await userEvent.click(screen.getByRole('button', { name: 'Notices' }));

        expect(
            screen.getByRole('region', { name: 'New notices' }),
        ).toHaveTextContent('Pay the road tax');
        vi.restoreAllMocks();
    });

    it('shows the total and the deadlines, the late one first', () => {
        renderPage(Home, { home });

        const total = screen.getByRole('region', {
            name: 'To pay in the next 30 days',
        });
        expect(total).toHaveTextContent('1.488,79');
        expect(total).toHaveTextContent('8 payments, one already late');
        const deadlines = screen
            .getByRole('heading', { name: 'The deadlines' })
            .closest('section') as HTMLElement;
        const rows = within(deadlines).getAllByRole('link');
        expect(rows).toHaveLength(10);
        expect(rows[0]).toHaveTextContent('Tessera sanitaria di Giulia');
        expect(rows[0]).toHaveTextContent('Late by 26 days · 30/09/2026');
        expect(rows[1]).toHaveTextContent('Bolletta gas');
        expect(rows[1]).toHaveTextContent('in 4 days');
    });

    it('offers the Agenda only when there are more than the ones shown', () => {
        const view = renderPage(Home, { home });
        expect(
            screen.queryByRole('link', {
                name: 'See everything in the Agenda',
            }),
        ).not.toBeInTheDocument();
        view.unmount();

        renderPage(Home, { home: { ...home, more: true } });
        expect(
            screen.getByRole('link', { name: 'See everything in the Agenda' }),
        ).toHaveAttribute('href', '/agenda');
    });

    it('shows the incomes on their way after the deadlines', () => {
        renderPage(Home, { home });

        const incoming = screen
            .getByRole('heading', { name: 'Incoming' })
            .closest('section') as HTMLElement;
        expect(
            within(incoming).getByText('Stipendio di Marco'),
        ).toBeInTheDocument();
        expect(
            within(incoming).getByText('Stipendio di Laura'),
        ).toBeInTheDocument();
        const headings = screen
            .getAllByRole('heading', { level: 2 })
            .map((heading) => heading.textContent);
        expect(headings.indexOf('Incoming')).toBeGreaterThan(
            headings.indexOf('The deadlines'),
        );
    });

    it('shows the documents to complete on top, only when there are some', () => {
        const view = renderPage(Home, { home });
        expect(
            screen.queryByRole('region', { name: 'Documents to complete' }),
        ).not.toBeInTheDocument();
        view.unmount();

        renderPage(Home, { home: { ...home, pending_count: 5 } });
        const waiting = screen.getByRole('region', {
            name: 'Documents to complete',
        });
        expect(waiting).toHaveTextContent('5 documents to complete');
        expect(
            within(waiting).getByText('Check what I read and save them'),
        ).toBeInTheDocument();
        expect(
            within(waiting).getByRole('link', { name: 'Complete' }),
        ).toHaveAttribute('href', '/to-sort');
        expect(
            screen
                .getByRole('heading', { level: 1 })
                .compareDocumentPosition(waiting) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
        expect(
            waiting.compareDocumentPosition(
                screen.getByRole('region', {
                    name: 'To pay in the next 30 days',
                }),
            ) & Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
    });

    it('says one document to complete', () => {
        renderPage(Home, { home: { ...home, pending_count: 1 } });

        expect(
            screen.getByRole('region', { name: 'Documents to complete' }),
        ).toHaveTextContent('1 document to complete');
    });

    it('guides a new family: 0,00 €, the steps to begin and the empty incomes', () => {
        renderPage(Home, { home: newFamily });

        const total = screen.getByRole('region', {
            name: 'To pay in the next 30 days',
        });
        expect(total).toHaveTextContent('0,00');
        expect(
            screen.queryByRole('heading', { name: 'The deadlines' }),
        ).not.toBeInTheDocument();
        const begin = screen
            .getByRole('heading', { name: 'To begin' })
            .closest('section') as HTMLElement;
        const links = within(begin).getAllByRole('link');
        expect(links.map((link) => link.getAttribute('href'))).toEqual([
            '/areas',
            '/upload',
            '/cards/create',
            '/family/invite',
        ]);
        expect(
            within(begin).getByText('Take a photo or choose the PDF'),
        ).toBeInTheDocument();
        expect(
            within(begin).getByRole('link', { name: /Invite Laura/ }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'Salaries and other incomes show here when you add the card of a job.',
            ),
        ).toBeInTheDocument();
    });

    it('has no steps to begin once all are done', () => {
        renderPage(Home, { home });

        expect(
            screen.queryByRole('heading', { name: 'To begin' }),
        ).not.toBeInTheDocument();
    });

    it('says what is missing only when something is', () => {
        const view = renderPage(Home, { home });
        expect(
            screen.queryByRole('heading', { name: 'Something is missing' }),
        ).not.toBeInTheDocument();
        view.unmount();

        renderPage(Home, {
            home: {
                ...home,
                missing: [
                    {
                        key: '/upload?link=vehicle%3A1#1',
                        card: 'Fiat Panda',
                        href: '/upload?link=vehicle%3A1',
                        document: 'Vehicle registration document',
                    },
                ],
            },
        });

        const missing = screen
            .getByRole('heading', { name: 'Something is missing' })
            .closest('section') as HTMLElement;
        const row = within(missing).getByRole('link', {
            name: /Vehicle registration document/,
        });
        expect(row).toHaveAttribute('href', '/upload?link=vehicle%3A1');
        expect(within(missing).getByText('Fiat Panda')).toBeInTheDocument();
    });

    it('shows the mail that arrived only when there is some, linked to Received mail', () => {
        const view = renderPage(Home, { home });
        expect(
            screen.queryByRole('heading', { name: 'Mail that arrived' }),
        ).not.toBeInTheDocument();
        view.unmount();

        renderPage(Home, {
            home: {
                ...home,
                mails: [
                    {
                        id: 7,
                        sender: 'Enel Energia',
                        subject: 'La tua bolletta',
                        received_at: '2026-10-25T09:41:00+01:00',
                        links: ['Gas - Enel Energia'],
                    },
                    {
                        id: 6,
                        sender: 'Fastweb',
                        subject: null,
                        received_at: '2026-10-24T09:12:00+02:00',
                        links: [],
                    },
                ],
            },
        });

        const box = screen
            .getByRole('heading', { name: 'Mail that arrived' })
            .closest('section') as HTMLElement;
        const enel = within(box).getByRole('link', {
            name: /Enel Energia · La tua bolletta/,
        });
        expect(enel).toHaveAttribute('href', '/mail');
        expect(
            within(enel).getByText('Yesterday · linked to Gas - Enel Energia'),
        ).toBeInTheDocument();
        // No subject: the sender alone; no card: only the day
        const fastweb = within(box).getByRole('link', { name: /Fastweb/ });
        expect(within(fastweb).getByText('2 days ago')).toBeInTheDocument();
    });

    it('keeps the right column for what is missing even without incomes', () => {
        renderPage(Home, {
            home: {
                ...home,
                incoming: [],
                missing: [
                    {
                        key: '/upload?link=vehicle%3A1#1',
                        card: 'Fiat Panda',
                        href: '/upload?link=vehicle%3A1',
                        document: 'Vehicle registration document',
                    },
                ],
            },
        });

        expect(
            screen.getByRole('heading', { name: 'Something is missing' }),
        ).toBeInTheDocument();
    });

    it('hides the empty Incoming box on the phone when only what is missing has content', () => {
        renderPage(Home, {
            home: {
                ...home,
                incoming: [],
                missing: [
                    {
                        key: '/upload?link=vehicle%3A1#1',
                        card: 'Fiat Panda',
                        href: '/upload?link=vehicle%3A1',
                        document: 'Vehicle registration document',
                    },
                ],
            },
        });

        expect(
            screen
                .getByRole('heading', { name: 'Incoming' })
                .closest('section'),
        ).toHaveClass('max-lg:hidden');
        expect(
            screen
                .getByRole('heading', { name: 'Something is missing' })
                .closest('section'),
        ).not.toHaveClass('max-lg:hidden');
    });
});
