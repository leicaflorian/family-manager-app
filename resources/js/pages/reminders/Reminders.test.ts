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
import { inspectionReminder, remindersPage } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import Reminders from './Reminders.vue';

let post: MockInstance<typeof router.post>;
let remove: MockInstance<typeof router.delete>;
let get: MockInstance<typeof router.get>;

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

beforeEach(() => {
    // Monday 26/10: the plumber (31/10) is this week, the inspection later
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-26T10:00:00+01:00'));
    post = vi.spyOn(router, 'post').mockImplementation(() => undefined);
    remove = vi.spyOn(router, 'delete').mockImplementation(() => undefined);
    get = vi.spyOn(router, 'get').mockImplementation(() => undefined);
    screenWidth(true);
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

/** The props of the page, with some changed. */
function props(
    change: Partial<App.Data.RemindersPageData> = {},
): Record<string, unknown> {
    return { reminders: { ...remindersPage, ...change } };
}

/** The titles of the reminders to do that the list shows, read from their switch *Done*. */
function titles(): string[] {
    return screen
        .queryAllByRole('switch')
        .map(
            (toggle) =>
                toggle.getAttribute('aria-label')?.replace(/^Done: /, '') ?? '',
        );
}

describe('Reminders', () => {
    it('has the tabs of the Agenda with this one marked, and the number of the open reminders', () => {
        renderPage(Reminders, props());

        expect(
            screen.getByRole('heading', { name: 'Agenda', level: 1 }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Reminders 10' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(screen.getByRole('link', { name: 'Deadlines' })).toHaveAttribute(
            'href',
            '/agenda',
        );
        expect(
            screen.getByRole('link', { name: 'New reminder' }),
        ).toHaveAttribute('href', '/reminders/create');
    });

    it('splits the reminders to do into Late, This week and Later, each with its number', () => {
        renderPage(Reminders, props());

        const late = screen.getByRole('region', { name: 'Late' });
        expect(
            within(late).getByRole('link', { name: 'Renew the health card' }),
        ).toBeInTheDocument();
        expect(within(late).getByText('1')).toBeInTheDocument();
        expect(
            within(screen.getByRole('region', { name: 'This week' })).getByRole(
                'link',
                { name: "Chiamare l'idraulico per il preventivo del bagno" },
            ),
        ).toBeInTheDocument();
        expect(
            within(screen.getByRole('region', { name: 'Later' })).getByRole(
                'link',
                { name: 'Do the inspection' },
            ),
        ).toBeInTheDocument();
    });

    it('shows the first ten of a group, and the others on request', async () => {
        const many = Array.from({ length: 13 }, (_, index) => ({
            ...inspectionReminder,
            id: 100 + index,
            title: `Inspection ${index + 1}`,
        }));
        renderPage(Reminders, props({ open: many }));

        const later = screen.getByRole('region', { name: 'Later' });
        expect(within(later).getAllByRole('switch')).toHaveLength(10);
        expect(within(later).getByText('13')).toBeInTheDocument();

        await userEvent.click(
            within(later).getByRole('button', { name: 'Show the other 3' }),
        );

        expect(within(later).getAllByRole('switch')).toHaveLength(13);
        expect(
            within(later).queryByRole('button', { name: /Show the other/ }),
        ).toBeNull();
    });

    it('chooses between To do, with its number, and Completed, which can be reopened', async () => {
        renderPage(Reminders, props());

        expect(screen.getByRole('radio', { name: 'To do · 3' })).toBeChecked();
        await userEvent.click(screen.getByRole('radio', { name: 'Completed' }));

        expect(
            screen.getByRole('link', { name: 'Pay the road tax' }),
        ).toBeInTheDocument();
        expect(screen.queryByRole('region', { name: 'Late' })).toBeNull();
        await userEvent.click(screen.getByRole('button', { name: 'Reopen' }));
        expect(remove).toHaveBeenCalledWith(
            '/reminders/4/done',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('takes the row away when it is marked done, and Undo brings it back', async () => {
        post.mockImplementation((_url, _data, options) => {
            options?.onSuccess?.(undefined as never);
        });
        renderPage(Reminders, props());

        await userEvent.click(
            screen.getByRole('switch', { name: 'Done: Renew the health card' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/reminders/1/done',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(
            screen.queryByRole('link', { name: 'Renew the health card' }),
        ).not.toBeInTheDocument();
        expect(screen.getByRole('radio', { name: 'To do · 2' })).toBeChecked();

        remove.mockImplementation((_url, options) => {
            options?.onSuccess?.(undefined as never);
        });
        await userEvent.click(screen.getByRole('button', { name: 'Undo' }));

        expect(remove).toHaveBeenCalledWith(
            '/reminders/1/done',
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(
            screen.getByRole('link', { name: 'Renew the health card' }),
        ).toBeInTheDocument();
    });

    it('counts the open reminders once on the tab after one is marked done and the server sends the list again', async () => {
        post.mockImplementation((_url, _data, options) => {
            options?.onSuccess?.(undefined as never);
        });
        const view = renderPage(Reminders, props());

        await userEvent.click(
            screen.getByRole('switch', { name: 'Done: Renew the health card' }),
        );
        // The server answers with the list without the reminder and the family-wide count one lower.
        await view.rerender(
            props({
                open: remindersPage.open.filter((item) => item.id !== 1),
                open_total: remindersPage.open_total - 1,
            }),
        );

        expect(
            screen.getByRole('link', { name: 'Reminders 9' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('radio', { name: 'To do · 2' }),
        ).toBeInTheDocument();
    });

    it('shows the error of a renewal that cannot be confirmed, and turns the switch off again', async () => {
        post.mockImplementation((_url, _data, options) => {
            options?.onError?.({ status: 'Confirm the renewal first.' });
            options?.onFinish?.(undefined as never);
        });
        renderPage(Reminders, props());

        await userEvent.click(
            screen.getByRole('switch', { name: 'Done: Renew the health card' }),
        );

        expect(screen.getByText('Confirm the renewal first.')).toBeVisible();
        expect(
            screen.getByRole('switch', { name: 'Done: Renew the health card' }),
        ).not.toBeChecked();
    });

    it('filters by person on the server', async () => {
        const view = renderPage(Reminders, props());

        await userEvent.click(screen.getByRole('radio', { name: 'LB Laura' }));

        expect(get).toHaveBeenCalledWith(
            '/reminders',
            { person: '2' },
            expect.objectContaining({ preserveScroll: true }),
        );

        await view.rerender(props({ person_id: 2 }));
        await userEvent.click(screen.getByRole('radio', { name: 'Everyone' }));

        expect(get).toHaveBeenLastCalledWith(
            '/reminders',
            { person: undefined },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('searches in the titles and in the things they refer to, without accents', async () => {
        renderPage(Reminders, props());

        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search a reminder' }),
            'PANDÀ',
        );

        expect(titles()).toEqual(['Do the inspection']);
    });

    it('turns an area off from the menu of the areas, which counts the reminders', async () => {
        renderPage(Reminders, props());

        await userEvent.click(
            screen.getByRole('button', { name: 'All areas' }),
        );
        const vehicles = await screen.findByRole('button', {
            name: /Vehicles/,
        });
        expect(vehicles).toHaveTextContent('Vehicles1');
        await userEvent.click(vehicles);

        expect(titles()).not.toContain('Do the inspection');
        expect(titles()).toHaveLength(2);
    });

    it('says when nothing matches the filters, and takes them off', async () => {
        renderPage(Reminders, props({ person_id: 2 }));

        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search a reminder' }),
            'nothing like this',
        );

        expect(
            screen.getByText('No reminders with these filters'),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Clear filters' }),
        );

        expect(titles()).toHaveLength(3);
        expect(get).toHaveBeenCalledWith(
            '/reminders',
            { person: undefined },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('postpones a late reminder to tomorrow', async () => {
        renderPage(Reminders, props());

        await userEvent.click(screen.getByRole('button', { name: 'Postpone' }));
        await userEvent.click(
            await screen.findByRole('menuitem', { name: 'Tomorrow' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/reminders/1/postpone',
            { to: '2026-10-27' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('opens the panel of the day from Choose a date…', async () => {
        renderPage(Reminders, props());

        await userEvent.click(screen.getByRole('button', { name: 'Postpone' }));
        await userEvent.click(
            await screen.findByRole('menuitem', { name: 'Choose a date…' }),
        );

        const dialog = await screen.findByRole('dialog', { name: 'Postpone' });
        expect(
            within(dialog).getByText('Renew the health card'),
        ).toBeInTheDocument();
    });

    it('deletes a reminder written by hand after asking', async () => {
        renderPage(Reminders, props());

        await userEvent.click(
            screen.getByRole('button', {
                name: "More actions on Chiamare l'idraulico per il preventivo del bagno",
            }),
        );
        await userEvent.click(
            await screen.findByRole('menuitem', { name: 'Delete' }),
        );
        const dialog = await screen.findByRole('dialog', {
            name: 'Delete this reminder?',
        });
        await userEvent.click(
            within(dialog).getByRole('button', { name: 'Delete the reminder' }),
        );

        expect(remove).toHaveBeenCalledWith(
            '/reminders/2',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('on the phone, chooses the list with buttons beside the filters', async () => {
        screenWidth(false);
        renderPage(Reminders, props());

        const group = screen.getByRole('group', { name: 'Filters' });
        expect(
            within(group).getByRole('button', { name: 'To do · 3' }),
        ).toHaveAttribute('aria-pressed', 'true');
        await userEvent.click(
            within(group).getByRole('button', { name: 'Completed' }),
        );

        expect(
            screen.getByRole('link', { name: 'Pay the road tax' }),
        ).toBeInTheDocument();
        expect(screen.queryByRole('searchbox')).toBeNull();
    });

    it('says so when there is nothing to do', () => {
        renderPage(Reminders, props({ open: [] }));

        expect(screen.getByText('Nothing to do')).toBeInTheDocument();
        expect(
            screen.getByRole('radio', { name: 'To do · 0' }),
        ).toBeInTheDocument();
    });
});
