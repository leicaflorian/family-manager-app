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
import { bankConnections, bankInstitutions } from '@/test/fixtures';
import { renderPage, screenWidth, withTrail } from '@/test/page';
import Banks from './Banks.vue';

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-11-20T14:30:00Z'));
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
});

/** The props of the page as the controller sends them: no list of banks until the panel asks. */
function props(extra: Record<string, unknown> = {}): Record<string, unknown> {
    return {
        connections: bankConnections,
        can_change: true,
        sync_hours: [7, 13, 19],
        consent_days: 180,
        ...extra,
    };
}

describe('Settings, Linked banks', () => {
    it('writes the trail Settings > Linked banks into the top bar', () => {
        const { state, provide } = withTrail();
        renderPage(Banks, props(), provide);

        expect(state.pieces.value).toEqual([
            { label: 'Settings', href: '/settings' },
            { label: 'Linked banks' },
        ]);
    });

    it('lists the banks with their state, their accounts and the last read', () => {
        renderPage(Banks, props());

        expect(
            screen.getByRole('heading', { name: 'Linked banks' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'The transactions arrive by themselves at 7, at 13, and at 19',
            ),
        ).toBeInTheDocument();
        expect(screen.getByText('BancoPosta')).toBeInTheDocument();
        expect(
            screen.getByText('Connected until 18/05/2027'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Last update today at 13:00'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Conto BancoPosta ·· 3307'),
        ).toBeInTheDocument();

        expect(screen.getByText('Buddy Bank')).toBeInTheDocument();
        expect(screen.getByText('To connect again')).toBeInTheDocument();
        expect(
            screen.getByText('The connection ended on 12/11/2026'),
        ).toBeInTheDocument();
        expect(screen.getByText('Conto Buddy ·· 7730')).toBeInTheDocument();
        expect(
            screen.getByText(
                /it cannot make payments and does not see your bank password/,
            ),
        ).toBeInTheDocument();
    });

    it('says a bank is being read while its first read is not done', () => {
        const pending: App.Data.BankConnectionData = {
            ...bankConnections[0]!,
            last_synced_at: null,
            valid_until: null,
            syncing: true,
        };
        renderPage(Banks, props({ connections: [pending] }));

        expect(
            screen.getByText('Reading the transactions…'),
        ).toBeInTheDocument();
        expect(screen.getByText('Connected')).toBeInTheDocument();
    });

    it('says when a bank does not work', () => {
        const broken: App.Data.BankConnectionData = {
            ...bankConnections[0]!,
            status: 'error',
        };
        renderPage(Banks, props({ connections: [broken] }));

        expect(screen.getByText('Not working')).toHaveClass('bg-overdue-soft');
        expect(
            screen.getByText('We could not read this bank. We will try again.'),
        ).toBeInTheDocument();
    });

    it('puts the button to connect under the title on a phone, in the row of the title on a computer', () => {
        screenWidth(false);
        const phone = renderPage(Banks, props());
        const heading = screen.getByRole('heading', { name: 'Linked banks' });
        const button = screen.getByRole('button', { name: 'Connect a bank' });
        const row = heading.closest('[class*="justify-between"]')!;

        expect(row).not.toContainElement(button);
        expect(
            heading.compareDocumentPosition(button) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
        phone.unmount();

        screenWidth(true);
        renderPage(Banks, props());
        const wide = screen
            .getByRole('heading', { name: 'Linked banks' })
            .closest('[class*="justify-between"]')!;

        expect(wide).toContainElement(
            screen.getByRole('button', { name: 'Connect a bank' }),
        );
        vi.unstubAllGlobals();
    });

    it('invites to connect the first bank when there is none', () => {
        renderPage(Banks, props({ connections: [] }));

        expect(screen.getByText('No bank connected')).toBeInTheDocument();
        expect(
            screen.getAllByRole('button', { name: 'Connect a bank' }),
        ).toHaveLength(1);
    });

    it('writes the read times and the consent length it is given', () => {
        renderPage(Banks, props({ sync_hours: [6, 18], consent_days: 90 }));

        expect(
            screen.getByText(
                'The transactions arrive by themselves at 6 and at 18',
            ),
        ).toBeInTheDocument();
        expect(screen.getByText(/lasts up to 90 days/)).toBeInTheDocument();
    });

    it('tells a member with no bank who connects them', () => {
        renderPage(Banks, props({ connections: [], can_change: false }));

        expect(screen.getByText('No bank connected')).toBeInTheDocument();
        expect(
            screen.getByText(
                'Only who manages the family connects and disconnects the banks.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Connect a bank' }),
        ).not.toBeInTheDocument();
    });

    it('sends the chosen bank to the server to be taken to it', async () => {
        const post = vi
            .spyOn(router, 'post')
            .mockImplementation(() => undefined);
        vi.spyOn(router, 'reload').mockImplementation(() => undefined);
        renderPage(Banks, props({ institutions: bankInstitutions }));

        await userEvent.click(
            screen.getByRole('button', { name: 'Connect a bank' }),
        );
        await userEvent.click(
            within(
                screen.getByRole('dialog', { name: 'Connect a bank' }),
            ).getByRole('button', { name: 'Intesa Sanpaolo' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/settings/banks',
            { institution: 'Intesa Sanpaolo' },
            expect.anything(),
        );
    });

    it('connects again the bank that ended, with its own name', async () => {
        const post = vi
            .spyOn(router, 'post')
            .mockImplementation(() => undefined);
        renderPage(Banks, props());

        expect(
            screen.getAllByRole('button', { name: 'Reconnect' }),
        ).toHaveLength(1);
        // The link icon of the boards, not the arrows of Update
        expect(
            screen
                .getByRole('button', { name: 'Reconnect' })
                .querySelector('.lucide-link'),
        ).not.toBeNull();
        await userEvent.click(
            screen.getByRole('button', { name: 'Reconnect' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/settings/banks',
            { institution: 'Buddy Bank' },
            expect.anything(),
        );
    });

    it('asks before disconnecting a bank, then deletes it', async () => {
        const destroy = vi
            .spyOn(router, 'delete')
            .mockImplementation(() => undefined);
        renderPage(Banks, props());

        await userEvent.click(
            screen.getAllByRole('button', { name: 'Disconnect' })[0]!,
        );
        const dialog = screen.getByRole('dialog', {
            name: 'Disconnect BancoPosta?',
        });
        expect(destroy).not.toHaveBeenCalled();
        expect(
            within(dialog).getByText(/stops reading its transactions/),
        ).toBeInTheDocument();

        await userEvent.click(
            within(dialog).getByRole('button', { name: 'Disconnect' }),
        );

        expect(destroy).toHaveBeenCalledWith(
            '/settings/banks/1',
            expect.anything(),
        );
    });

    it('gives a member no button to reconnect or disconnect', () => {
        renderPage(Banks, props({ can_change: false }));

        expect(
            screen.queryByRole('button', { name: 'Reconnect' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Disconnect' }),
        ).not.toBeInTheDocument();
    });

    it('gives a member the list without a button and says who can change it', () => {
        renderPage(Banks, props({ can_change: false }));

        expect(screen.getByText('BancoPosta')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Connect a bank' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText(
                'Only who manages the family connects and disconnects the banks.',
            ),
        ).toBeInTheDocument();
    });

    it('asks for the banks when the panel opens, and shows them', async () => {
        const reload = vi
            .spyOn(router, 'reload')
            .mockImplementation(() => undefined);
        const view = renderPage(Banks, props());

        await userEvent.click(
            screen.getByRole('button', { name: 'Connect a bank' }),
        );

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ['institutions'],
                preserveUrl: true,
            }),
        );
        expect(screen.getByText('Loading the banks…')).toBeInTheDocument();

        await view.rerender(props({ institutions: bankInstitutions }));

        expect(
            screen.getByRole('button', { name: 'Intesa Sanpaolo' }),
        ).toBeInTheDocument();
        expect(screen.getByText(/4 banks of 4/)).toBeInTheDocument();
    });

    it('searches the banks as the person types, without accents or capitals', async () => {
        vi.spyOn(router, 'reload').mockImplementation(() => undefined);
        renderPage(Banks, props({ institutions: bankInstitutions }));

        await userEvent.click(
            screen.getByRole('button', { name: 'Connect a bank' }),
        );
        const dialog = screen.getByRole('dialog', { name: 'Connect a bank' });
        await userEvent.type(
            within(dialog).getByRole('searchbox', { name: 'Search your bank' }),
            'POST',
        );

        expect(
            within(dialog).getByRole('button', { name: 'BancoPosta' }),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByRole('button', { name: 'Postepay' }),
        ).toBeInTheDocument();
        expect(
            within(dialog).queryByRole('button', { name: 'UniCredit' }),
        ).not.toBeInTheDocument();
        expect(
            within(dialog).getByText(
                '2 banks of 4. Not finding yours? It cannot be connected for now.',
            ),
        ).toBeInTheDocument();
    });

    it('says when the list of banks could not be read, and asks again', async () => {
        const reload = vi
            .spyOn(router, 'reload')
            .mockImplementation(() => undefined);
        renderPage(Banks, props({ institutions: null }));

        await userEvent.click(
            screen.getByRole('button', { name: 'Connect a bank' }),
        );
        expect(
            screen.getByText(
                'We could not load the banks. Try again in a moment.',
            ),
        ).toBeInTheDocument();

        reload.mockClear();
        await userEvent.click(
            screen.getByRole('button', { name: 'Try again' }),
        );

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({ only: ['institutions'] }),
        );
    });

    it('updates a bank that is connected, with its own address', async () => {
        const post = vi
            .spyOn(router, 'post')
            .mockImplementation(() => undefined);
        renderPage(Banks, props());

        // Only the bank that reads has it: the other has to be connected again
        expect(screen.getAllByRole('button', { name: 'Update' })).toHaveLength(
            1,
        );
        await userEvent.click(screen.getByRole('button', { name: 'Update' }));

        expect(post).toHaveBeenCalledWith(
            '/settings/banks/1/sync',
            {},
            expect.anything(),
        );
    });

    it('gives a member no Update', () => {
        renderPage(Banks, props({ can_change: false }));

        expect(
            screen.queryByRole('button', { name: 'Update' }),
        ).not.toBeInTheDocument();
    });

    it('does not send the same request twice while one is running', async () => {
        const post = vi
            .spyOn(router, 'post')
            .mockImplementation((_url, _data, options) => {
                options?.onStart?.({} as never);
            });
        renderPage(Banks, props());

        await userEvent.click(screen.getByRole('button', { name: 'Update' }));

        // While it runs, no button of the page starts another visit
        for (const name of ['Update', 'Reconnect', 'Disconnect']) {
            for (const button of screen.getAllByRole('button', { name })) {
                expect(button).toBeDisabled();
            }
        }
        await userEvent.click(
            screen.getByRole('button', { name: 'Reconnect' }),
        );
        expect(post).toHaveBeenCalledTimes(1);

        post.mock.calls[0]![2]!.onFinish?.({} as never);
        await vi.waitFor(() =>
            expect(
                screen.getByRole('button', { name: 'Update' }),
            ).toBeEnabled(),
        );
    });
});
