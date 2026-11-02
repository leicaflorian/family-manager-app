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
import { remindersPage } from '@/test/fixtures';
import { choose, renderPage } from '@/test/page';
import Reminders from './Reminders.vue';

let post: MockInstance<typeof router.post>;
let remove: MockInstance<typeof router.delete>;
let get: MockInstance<typeof router.get>;

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-26T10:00:00+01:00'));
    post = vi.spyOn(router, 'post').mockImplementation(() => undefined);
    remove = vi.spyOn(router, 'delete').mockImplementation(() => undefined);
    get = vi.spyOn(router, 'get').mockImplementation(() => undefined);
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
});

/** The props of the page, with some changed. */
function props(
    change: Partial<App.Data.RemindersPageData> = {},
): Record<string, unknown> {
    return { reminders: { ...remindersPage, ...change } };
}

describe('Reminders', () => {
    it('has the tabs of the Agenda with this one marked, and the number of the open reminders', () => {
        renderPage(Reminders, props());

        expect(
            screen.getByRole('heading', { name: 'Agenda', level: 1 }),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Reminders/ })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(screen.getByRole('link', { name: 'Deadlines' })).toHaveAttribute(
            'href',
            '/agenda',
        );
        expect(
            screen.getByRole('link', { name: 'Reminders 3' }),
        ).toBeInTheDocument();
    });

    it('shows To do with its number and Done, and the late ones apart from the coming ones', async () => {
        renderPage(Reminders, props());

        expect(
            screen.getByRole('button', { name: 'To do · 3' }),
        ).toHaveAttribute('aria-pressed', 'true');
        const late = screen.getByRole('region', { name: 'Overdue' });
        const coming = screen.getByRole('region', { name: 'Coming up' });
        expect(
            within(late).getByRole('link', { name: 'Renew the health card' }),
        ).toBeInTheDocument();
        expect(
            within(coming).getAllByRole('link', {
                name: /idraulico|inspection/,
            }),
        ).toHaveLength(2);

        await userEvent.click(screen.getByRole('button', { name: 'Done' }));

        expect(
            screen.getByRole('link', { name: 'Pay the road tax' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('region', { name: 'Overdue' }),
        ).not.toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: 'Reopen' }));
        expect(remove).toHaveBeenCalledWith(
            '/reminders/4/done',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('opens the card of an automatic reminder and the form of one written by hand', () => {
        renderPage(Reminders, props());

        expect(
            screen.getByRole('link', { name: 'Renew the health card' }),
        ).toHaveAttribute('href', '/documents/4');
        expect(
            screen.getByRole('link', {
                name: "Chiamare l'idraulico per il preventivo del bagno",
            }),
        ).toHaveAttribute('href', '/reminders/2/edit');
        expect(
            screen.getByRole('link', { name: 'New reminder' }),
        ).toHaveAttribute('href', '/reminders/create');
    });

    it('takes the row away when it is marked done, and Undo brings it back', async () => {
        post.mockImplementation((_url, _data, options) => {
            options?.onSuccess?.(undefined as never);
        });
        renderPage(Reminders, props());

        const [circle] = within(
            screen.getByRole('region', { name: 'Overdue' }),
        ).getAllByRole('button', { name: 'Mark as done' });
        await userEvent.click(circle!);

        expect(post).toHaveBeenCalledWith(
            '/reminders/1/done',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(
            screen.queryByRole('link', { name: 'Renew the health card' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'To do · 2' }),
        ).toBeInTheDocument();

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

    it('shows the error of a renewal that cannot be confirmed', async () => {
        post.mockImplementation((_url, _data, options) => {
            options?.onError?.({ status: 'Confirm the renewal first.' });
        });
        renderPage(Reminders, props());

        await userEvent.click(
            screen.getAllByRole('button', { name: 'Mark as done' })[0]!,
        );

        expect(screen.getByText('Confirm the renewal first.')).toBeVisible();
        expect(
            screen.getByRole('link', { name: 'Renew the health card' }),
        ).toBeInTheDocument();
    });

    it('filters by person with Who', async () => {
        renderPage(Reminders, props());

        await choose('Who', 'Laura');

        expect(get).toHaveBeenCalledWith(
            '/reminders',
            { person: '3' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('says so when there is nothing to do', () => {
        renderPage(Reminders, props({ open: [] }));

        expect(screen.getByText('Nothing to do')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'To do · 0' }),
        ).toBeInTheDocument();
    });
});
