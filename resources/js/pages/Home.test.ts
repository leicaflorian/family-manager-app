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
import { agendaEntries, seenNotices, unreadNotices } from '@/test/fixtures';
import { renderPage, screenWidth } from '@/test/page';
import Home from './Home.vue';

const shared = vi.hoisted(() => ({
    name: 'Fameja.app',
    unread_notices: 0,
    family: undefined as
        | {
              features: { budget: boolean; mail?: boolean };
              mail_available?: boolean;
          }
        | undefined,
    bell_notices: undefined as
        | { unread: unknown[]; seen: unknown[] }
        | undefined,
}));

// The shared props of the page, and no <Head> as in `setup.ts`
vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    usePage: () => ({
        component: 'Home',
        url: '/dashboard',
        props: { ...shared },
    }),
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
    can_change_features: true,
};

/** The Home of a new family after the Welcome with Laura: nothing to pay, five steps to begin, the first done. */
const newFamily: App.Data.HomeData = {
    ...home,
    total: 0,
    payments: 0,
    late: 0,
    entries: [],
    incoming: [],
    checklist: [
        { key: 'people', done: true, name: null, names: ['Marco', 'Laura'] },
        { key: 'utilities', done: false, name: null, names: [] },
        { key: 'first_bill', done: false, name: null, names: [] },
        { key: 'card', done: false, name: null, names: [] },
        { key: 'invite', done: false, name: null, names: [] },
    ],
};

describe('Home tools', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('on the phone has the magnifier and the round bell beside the greeting', () => {
        screenWidth(390);
        renderPage(Home, { home });

        expect(
            screen.getByRole('link', { name: 'Search in Fameja.app' }),
        ).toHaveAttribute('href', '/search');
        expect(screen.getByRole('button', { name: /Notices/ })).toHaveClass(
            'rounded-full',
        );
    });

    it('on the computer leaves the search and the bell to the top bar', () => {
        screenWidth(1280);
        renderPage(Home, { home });

        expect(
            screen.queryByRole('link', { name: 'Search in Fameja.app' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('combobox', {
                name: 'Search in Fameja.app',
            }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /Notices/ }),
        ).not.toBeInTheDocument();
    });
});

describe('Home', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-10-26T10:00:00+01:00'));
    });

    afterEach(() => {
        vi.useRealTimers();
        shared.family = undefined;
    });

    it('greets with the day and the first name', () => {
        renderPage(Home, { home });

        expect(screen.getByText('Monday 26 October 2026')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Hi, Marco', level: 1 }),
        ).toBeInTheDocument();
    });

    it('has exactly one level 1 heading, the greeting drawn by FmTitle, with the day above it', () => {
        renderPage(Home, { home });

        const titles = screen.getAllByRole('heading', { level: 1 });
        expect(titles).toHaveLength(1);
        expect(titles[0]).toHaveTextContent('Hi, Marco');
        expect(
            screen
                .getByText('Monday 26 October 2026')
                .compareDocumentPosition(titles[0]!) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
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
            expect.objectContaining({
                only: ['bell_notices'],
                preserveUrl: true,
            }),
        );
        reload.mockRestore();
    });

    it('shows the notices in the menu of the bell once they arrive', async () => {
        vi.spyOn(router, 'reload').mockImplementation(() => undefined);
        shared.bell_notices = { unread: unreadNotices, seen: seenNotices };
        renderPage(Home, { home });

        await userEvent.click(screen.getByRole('button', { name: 'Notices' }));

        expect(
            screen.getByRole('region', { name: 'New notices' }),
        ).toHaveTextContent('Pay the road tax');
        shared.bell_notices = undefined;
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

    it('has no money with the Budget off: no total and no incomes box', () => {
        shared.family = { features: { budget: false } };
        renderPage(Home, {
            home: { ...home, total: null, payments: 0, late: 0, incoming: [] },
        });

        expect(
            screen.queryByRole('region', {
                name: 'To pay in the next 30 days',
            }),
        ).toBeNull();
        expect(screen.queryByText('Incoming')).toBeNull();
        expect(
            screen.getByRole('heading', { name: 'The deadlines' }),
        ).toBeInTheDocument();
        shared.family = undefined;
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
        expect(links.map((link) => link.textContent)).toEqual([
            expect.stringContaining('Add whoever lives with you'),
            expect.stringContaining('The bills of the house'),
            expect.stringContaining('Upload the first document'),
            expect.stringContaining('Create the first card'),
            expect.stringContaining('Invite whoever lives with you'),
        ]);
        expect(links.map((link) => link.getAttribute('href'))).toEqual([
            '/family/people',
            '/areas',
            '/upload',
            '/cards/create',
            '/family/invite',
        ]);
        expect(within(begin).getByText('1 of 5')).toBeInTheDocument();
        expect(
            within(begin).getByRole('progressbar', { name: 'To begin' }),
        ).toHaveAttribute('aria-valuenow', '1');
        expect(
            within(begin).getByText(
                'Five things to get started. Tick them off as you go.',
            ),
        ).toBeInTheDocument();
        expect(
            within(begin).getByText('Done in the Welcome: Marco and Laura'),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'Salaries and other incomes show here when you add the card of a job.',
            ),
        ).toBeInTheDocument();
    });

    it('names the person to invite, and has the mail step with the mail on', () => {
        renderPage(Home, {
            home: {
                ...newFamily,
                checklist: [
                    { key: 'mail', done: false, name: null, names: [] },
                    { key: 'invite', done: false, name: 'Laura', names: [] },
                ],
            },
        });

        expect(
            screen.getByRole('link', {
                name: /Send the bills to the address of the family/,
            }),
        ).toHaveAttribute('href', '/mail');
        expect(
            screen.getByRole('link', { name: /Invite Laura/ }),
        ).toHaveAttribute('href', '/family/invite');
    });

    it('hides the list with Hide and takes a step away with Not needed', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Home, { home: newFamily });

        await userEvent.click(screen.getByRole('button', { name: 'Hide' }));
        expect(post).toHaveBeenCalledWith(
            '/dashboard/checklist/hide',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Remove the step Invite whoever lives with you',
            }),
        );
        expect(post).toHaveBeenCalledWith(
            '/dashboard/checklist/skip',
            { step: 'invite' },
            expect.objectContaining({ preserveScroll: true }),
        );
        post.mockRestore();
    });

    it('does not offer the Mail in the footer on a server without the mail domain', () => {
        shared.family = {
            features: { budget: false, mail: false },
            mail_available: false,
        };
        const view = renderPage(Home, { home: newFamily });

        expect(
            screen.getByText('Do you also need the Budget?', { exact: false }),
        ).toBeInTheDocument();
        expect(
            screen.queryByText(/the Mail for the bills/),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('link', {
                name: 'Turn it on from Settings › What you use.',
            }),
        ).toHaveAttribute('href', '/settings/features');
        view.unmount();

        // With the Budget on and nothing else to offer, there is no footer at all
        shared.family = {
            features: { budget: true, mail: false },
            mail_available: false,
        };
        renderPage(Home, { home: newFamily });
        expect(screen.queryByText(/Do you also need/)).not.toBeInTheDocument();
        shared.family = undefined;
    });

    it('leads to What you use only for an admin with the Budget or the Mail off', () => {
        const link = (): HTMLElement | null =>
            screen.queryByRole('link', {
                name: 'Turn them on from Settings › What you use.',
            });

        shared.family = { features: { budget: false, mail: true } };
        const view = renderPage(Home, { home: newFamily });
        expect(link()).toHaveAttribute('href', '/settings/features');
        expect(
            screen.getByText(
                'Do you also need the Budget or the Mail for the bills?',
                { exact: false },
            ),
        ).toBeInTheDocument();
        view.unmount();

        const member = renderPage(Home, {
            home: { ...newFamily, can_change_features: false },
        });
        expect(link()).toBeNull();
        member.unmount();

        shared.family = { features: { budget: true, mail: true } };
        renderPage(Home, { home: newFamily });
        expect(link()).toBeNull();
        shared.family = undefined;
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
        ).toHaveClass('not-wide:hidden');
        expect(
            screen
                .getByRole('heading', { name: 'Something is missing' })
                .closest('section'),
        ).not.toHaveClass('not-wide:hidden');
    });
});
