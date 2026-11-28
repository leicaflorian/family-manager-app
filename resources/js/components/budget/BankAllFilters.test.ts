import { router } from '@inertiajs/vue3';
import userEvent from '@testing-library/user-event';
import { screen, within } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { choose, renderPage, screenWidth } from '@/test/page';
import BankAllFilters from './BankAllFilters.vue';

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-11-20T14:30:00Z'));
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

const none: {
    q: string | null;
    account: number | null;
    status: string | null;
    month: string | null;
    direction: string | null;
} = { q: null, account: null, status: null, month: null, direction: null };
const accounts = [
    { id: 4, name: 'Conto BancoPosta ·· 3307' },
    { id: 5, name: 'Buddy Bank ·· 5521' },
];

/** The filters with some chosen, and the total the server counted. */
function show(
    chosen: Partial<typeof none> = {},
    total = { count: 94, sum: -1842.3 },
): ReturnType<typeof renderPage> {
    return renderPage(BankAllFilters, {
        filters: { ...none, ...chosen },
        accounts,
        total,
    });
}

describe('BankAllFilters', () => {
    it('wide, writes the small name of each menu before the value', () => {
        screenWidth(true);
        show({ account: 4 });

        const account = screen.getByRole('button', { name: /Bank account/ });
        expect(account).toHaveTextContent(
            'Bank accountConto BancoPosta ·· 3307',
        );
        expect(
            screen.getByRole('button', { name: /^Direction/ }),
        ).toHaveTextContent('DirectionMoney out and in');
    });

    it('on the phone writes only the value, cut when it is long', () => {
        screenWidth(false);
        show({ account: 4 });

        const account = screen.getByRole('button', { name: /Bank account/ });
        expect(account).toHaveTextContent('Conto BancoPosta ·· 3307');
        expect(account).not.toHaveTextContent('Bank account');
        expect(
            within(account).getByText('Conto BancoPosta ·· 3307'),
        ).toHaveClass('truncate', 'text-label');
    });

    it('choosing a status asks the server with the filter and resets the rows', async () => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        show();

        await choose('Status', 'Ignored');

        expect(reload).toHaveBeenCalledOnce();
        const options = reload.mock.calls[0]![0]!;
        expect(options.data).toMatchObject({ show: 'all', status: 'ignored' });
        expect(options.reset).toEqual(['rows']);
        expect(options.only).toEqual(['rows', 'next', 'total', 'filters']);
        expect(options.replace).toBe(true);
    });

    it('choosing an account keeps the other filters', async () => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        show({ status: 'linked' });

        await choose('Bank account', 'Buddy Bank ·· 5521');

        expect(reload.mock.calls[0]![0]!.data).toMatchObject({
            show: 'all',
            status: 'linked',
            account: 5,
        });
    });

    it('shows the count and the sum', () => {
        show();

        expect(screen.getByRole('status')).toHaveTextContent(
            '94 movements · -1.842,30 €',
        );
    });

    it('Remove the filters appears with a filter on and empties them all', async () => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        const view = show();
        expect(
            screen.queryByRole('button', { name: 'Remove the filters' }),
        ).toBeNull();
        view.unmount();

        show({ status: 'ignored', q: 'atm' });
        await userEvent.click(
            screen.getByRole('button', { name: 'Remove the filters' }),
        );

        expect(reload.mock.calls[0]![0]!.data).toEqual({
            show: 'all',
            q: '',
            account: '',
            status: '',
            month: '',
            direction: '',
        });
    });

    it('asks after a pause in typing, not at every key', async () => {
        vi.useFakeTimers();
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        show();

        await userEvent.setup({ advanceTimers: vi.advanceTimersByTime }).type(
            screen.getByRole('searchbox', {
                name: 'Search in the text of the movement',
            }),
            'atm',
        );
        expect(reload).not.toHaveBeenCalled();
        await vi.advanceTimersByTimeAsync(400);

        expect(reload).toHaveBeenCalledOnce();
        expect(reload.mock.calls[0]![0]!.data).toMatchObject({ q: 'atm' });
    });
});
