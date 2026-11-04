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
    agendaEntries,
    pendingPayslip,
    seenNotices,
    unreadNotices,
} from '@/test/fixtures';
import { renderPage } from '@/test/page';
import Home from './Home.vue';

const shared = vi.hoisted(() => ({ unread_notices: 0 }));

// The shared props of the page, and no <Head> as in `setup.ts`
vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    usePage: () => ({ url: '/dashboard', props: { ...shared } }),
}));

const photo = {
    ...pendingPayslip,
    id: 13,
    name: 'IMG_2041.heic',
    mime_type: 'image/heic',
    size: 1_258_291,
    url: '/files/13',
    title: 'IMG_2041',
};

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
    pending: [],
    pending_count: 0,
    checklist: [],
    missing: [],
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

    it('shows the files waiting on top, only when there are some', () => {
        const view = renderPage(Home, { home });
        expect(
            screen.queryByRole('heading', { name: 'Waiting' }),
        ).not.toBeInTheDocument();
        view.unmount();

        renderPage(Home, {
            home: {
                ...home,
                pending: [pendingPayslip, photo],
                pending_count: 7,
            },
        });
        const waiting = screen
            .getByRole('heading', { name: 'Waiting' })
            .closest('section') as HTMLElement;
        expect(
            within(waiting).getByText('Busta paga novembre 2026'),
        ).toBeInTheDocument();
        expect(within(waiting).getByText('1,2 MB')).toBeInTheDocument();
        expect(within(waiting).getByText('+5 more')).toBeInTheDocument();
        expect(
            within(waiting).getByRole('link', { name: 'Finish' }),
        ).toHaveAttribute('href', '/to-sort');
        const headings = screen
            .getAllByRole('heading', { level: 2 })
            .map((heading) => heading.textContent);
        expect(headings[0]).toBe('Waiting');
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
