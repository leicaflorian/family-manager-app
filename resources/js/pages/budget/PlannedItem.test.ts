import { router } from '@inertiajs/vue3';
import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { toast } from 'vue-sonner';
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
    danceDetail,
    danceExceptions,
    lateDeadline,
} from '@/test/fixtures';
import { renderPage, settle, withTrail } from '@/test/page';
import PlannedItem from './PlannedItem.vue';

const props = {
    kind: 'expense',
    detail: danceDetail,
    accounts: accountOptions,
    opening: null,
    pay: false,
    back: null,
};

/** The open due date of 05/12/2026, as the server sends it to open on arrival. */
const december = danceDetail.next.find(
    (deadline) => deadline.original_date === '2026-12-05',
)!;

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-26T10:00:00+01:00'));
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
});

describe('A planned expense', () => {
    it('writes the trail Budget > Planned expenses > planned expense into the top bar', () => {
        const { state, provide } = withTrail();
        renderPage(PlannedItem, props, provide);

        expect(state.pieces.value).toEqual([
            { label: 'Budget', href: '/budget' },
            { label: 'Planned expenses', href: '/budget/planned' },
            { label: 'Retta danza' },
        ]);
    });

    it('shows the head with the card, the amount, the year and the skipped months', () => {
        renderPage(PlannedItem, props);

        expect(
            screen.getByRole('heading', { name: 'Retta danza', level: 1 }),
        ).toBeInTheDocument();
        expect(screen.getByText('Planned expense')).toBeInTheDocument();
        expect(
            screen.getAllByRole('link', { name: 'Danza Giulia' })[0],
        ).toHaveAttribute('href', '/cards/enrollment/1');
        expect(
            screen.getByText(
                '45,00 € every month, on day 5 · 465,00 € in a year, July and August skipped',
            ),
        ).toBeInTheDocument();
    });

    it('leads to the form with Edit, and back to the list', () => {
        renderPage(PlannedItem, props);

        expect(screen.getByRole('link', { name: 'Edit' })).toHaveAttribute(
            'href',
            '/budget/planned/expense/4/edit',
        );
        expect(
            screen.getByRole('link', { name: /Planned expenses/ }),
        ).toHaveAttribute('href', '/budget/planned');
    });

    it('lists the next due dates with where each stands', () => {
        renderPage(PlannedItem, props);

        const next = screen.getByRole('region', { name: 'Next due dates' });
        expect(within(next).getByText('05/11/2026')).toBeInTheDocument();
        expect(within(next).getByText('Paid on 20/10')).toBeInTheDocument();
        expect(
            within(next).getByText(
                'Different amount: Comprende la quota del saggio di Natale',
            ),
        ).toBeInTheDocument();
        expect(
            within(next).getByText('45,00 €', { selector: 's' }),
        ).toBeInTheDocument();
        expect(within(next).getAllByText('60,00 €').length).toBeGreaterThan(0);
        expect(within(next).getByText('05/02/2027')).toBeInTheDocument();
    });

    it('opens Change this due date from an open due date', async () => {
        renderPage(PlannedItem, props);
        const next = screen.getByRole('region', { name: 'Next due dates' });

        await userEvent.click(
            within(next).getByRole('button', { name: /05\/01\/2027/ }),
        );

        expect(
            await screen.findByRole('dialog', { name: 'Change this due date' }),
        ).toBeInTheDocument();
    });

    it('pays an open due date from Change this due date', async () => {
        renderPage(PlannedItem, props);
        const next = screen.getByRole('region', { name: 'Next due dates' });

        await userEvent.click(
            within(next).getByRole('button', { name: /05\/01\/2027/ }),
        );
        await screen.findByRole('dialog', { name: 'Change this due date' });
        await userEvent.click(
            screen.getByRole('button', { name: 'I paid it' }),
        );

        expect(
            await screen.findByRole('dialog', { name: 'I paid it' }),
        ).toBeInTheDocument();
    });

    it('leads a paid due date to the movement that paid it', () => {
        renderPage(PlannedItem, props);
        const next = screen.getByRole('region', { name: 'Next due dates' });

        expect(
            within(next).getByRole('link', { name: /05\/11\/2026/ }),
        ).toHaveAttribute('href', '/budget/movements/expense/31');
    });

    describe('on arrival from the address of a due date', () => {
        let replace: ReturnType<typeof vi.spyOn>;

        beforeEach(() => {
            replace = vi.spyOn(router, 'replace').mockImplementation(() => {});
            window.history.replaceState(
                null,
                '',
                '/budget/planned/expense/4?deadline=2026-12-05&pay=1&from=%2Fagenda',
            );
        });

        it('opens I paid it when asked, takes the request out of the address, and goes back where it came from', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(PlannedItem, {
                ...props,
                opening: december,
                pay: true,
                back: '/agenda',
            });

            expect(
                await screen.findByRole('dialog', { name: 'I paid it' }),
            ).toBeInTheDocument();
            expect(replace).toHaveBeenCalledWith(
                expect.objectContaining({ url: '/budget/planned/expense/4' }),
            );

            await userEvent.click(
                screen.getByRole('button', { name: 'Confirm' }),
            );
            await settle();

            expect(post.mock.calls[0]![0]).toBe(
                '/budget/planned/expense/4/occurrences/2026-12-05/payment?back=%2Fagenda',
            );
        });

        it('opens Change this due date for an admin who did not ask to pay', async () => {
            renderPage(PlannedItem, { ...props, opening: december });

            expect(
                await screen.findByRole('dialog', {
                    name: 'Change this due date',
                }),
            ).toBeInTheDocument();
        });

        it('opens I paid it for a member who can only pay', async () => {
            renderPage(PlannedItem, {
                ...props,
                detail: { ...danceDetail, can_edit: false },
                opening: december,
            });

            expect(
                await screen.findByRole('dialog', { name: 'I paid it' }),
            ).toBeInTheDocument();
        });

        it('opens nothing for who can neither pay nor change', async () => {
            renderPage(PlannedItem, {
                ...props,
                detail: { ...danceDetail, can_edit: false, can_pay: false },
                opening: december,
                pay: true,
            });
            await settle();

            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });

        it('goes back where it came from only from the panel opened on arrival', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(PlannedItem, {
                ...props,
                detail: { ...danceDetail, can_edit: false },
                opening: december,
                back: '/agenda',
            });
            await screen.findByRole('dialog', { name: 'I paid it' });

            await userEvent.keyboard('{Escape}');
            await settle();
            const next = screen.getByRole('region', { name: 'Next due dates' });
            await userEvent.click(
                within(next).getByRole('button', { name: /05\/12\/2026/ }),
            );
            await userEvent.click(
                await screen.findByRole('button', { name: 'Confirm' }),
            );
            await settle();

            expect(post.mock.calls[0]![0]).toBe(
                '/budget/planned/expense/4/occurrences/2026-12-05/payment',
            );
        });

        it('still goes back where it came from when Change this due date leads to I paid it', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(PlannedItem, {
                ...props,
                opening: december,
                back: '/agenda',
            });
            await screen.findByRole('dialog', { name: 'Change this due date' });

            await userEvent.click(
                screen.getByRole('button', { name: 'I paid it' }),
            );
            await userEvent.click(
                await screen.findByRole('button', { name: 'Confirm' }),
            );
            await settle();

            expect(post.mock.calls[0]![0]).toBe(
                '/budget/planned/expense/4/occurrences/2026-12-05/payment?back=%2Fagenda',
            );
        });

        it('says a skipped due date is not paid to who cannot change it', async () => {
            const info = vi.spyOn(toast, 'info');
            renderPage(PlannedItem, {
                ...props,
                detail: { ...danceDetail, can_edit: false },
                opening: { ...december, skipped: true },
            });
            await settle();

            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
            expect(info).toHaveBeenCalledWith(
                'This due date is skipped: it is not paid.',
            );
        });
    });

    it('shows a late due date first, in red', () => {
        renderPage(PlannedItem, {
            ...props,
            detail: {
                ...danceDetail,
                next: [
                    { ...lateDeadline, planned_id: 4 },
                    ...danceDetail.next.slice(1),
                ],
            },
        });

        expect(screen.getByText('Overdue by 3 days')).toHaveClass(
            'text-overdue',
        );
    });

    it('lists the exceptions, the latest first, with their notes', () => {
        renderPage(PlannedItem, props);

        const exceptions = screen.getByRole('region', { name: 'Exceptions' });
        expect(
            within(exceptions).getByText(
                '05/12/2026 · Different amount, 60,00 €',
            ),
        ).toBeInTheDocument();
        expect(
            within(exceptions).getByText('05/08/2026 · Skipped'),
        ).toBeInTheDocument();
        expect(
            within(exceptions).getByText('05/07/2026 · Moved to 10/07/2026'),
        ).toBeInTheDocument();
        expect(within(exceptions).getByText('Vacanze')).toBeInTheDocument();
    });

    it('opens Change this due date from the pencil of an exception', async () => {
        renderPage(PlannedItem, props);
        const exceptions = screen.getByRole('region', { name: 'Exceptions' });

        await userEvent.click(
            within(exceptions).getAllByRole('button', { name: 'Edit' })[1]!,
        );

        const sheet = await screen.findByRole('dialog', {
            name: 'Change this due date',
        });
        expect(
            within(sheet).getByText(/due on 05\/08\/2026/),
        ).toBeInTheDocument();
        // The due date the server sends: it is skipped, so the choice starts on
        expect(
            within(sheet).getByLabelText('Skip this due date'),
        ).toBeChecked();
        expect(
            within(sheet).getByRole('textbox', { name: 'Note' }),
        ).toHaveValue('Vacanze');
    });

    it('has no pencil for an exception whose day is no longer a due date', () => {
        renderPage(PlannedItem, {
            ...props,
            detail: {
                ...danceDetail,
                exceptions: [{ ...danceExceptions[1]!, deadline: null }],
            },
        });
        const exceptions = screen.getByRole('region', { name: 'Exceptions' });

        expect(
            within(exceptions).queryByRole('button', { name: 'Edit' }),
        ).not.toBeInTheDocument();
    });

    it('adds an exception to the first open due date', async () => {
        renderPage(PlannedItem, props);

        await userEvent.click(screen.getByRole('button', { name: 'Add' }));

        const sheet = await screen.findByRole('dialog', {
            name: 'Change this due date',
        });
        expect(
            within(sheet).getByText(/due on 05\/12\/2026/),
        ).toBeInTheDocument();
    });

    it('shows the data, with the card as a link', () => {
        renderPage(PlannedItem, props);

        const data = screen.getByRole('region', { name: 'Data' });
        expect(
            within(data).getByText('Scuola e attività › Sport'),
        ).toBeInTheDocument();
        expect(within(data).getByText('Carta Laura')).toBeInTheDocument();
        expect(
            within(data).getByRole('link', { name: 'Danza Giulia' }),
        ).toHaveAttribute('href', '/cards/enrollment/1');
        expect(within(data).getByText('Amount')).toBeInTheDocument();
        expect(
            within(data).getByText('Every month, on day 5'),
        ).toBeInTheDocument();
        expect(within(data).getByText('05/09/2025')).toBeInTheDocument();
    });

    it('shows the payments by year and records a new one on the next open due date', async () => {
        renderPage(PlannedItem, props);

        const payments = screen.getByRole('region', { name: 'Payments' });
        expect(within(payments).getByText('2026')).toBeInTheDocument();
        expect(within(payments).getByText('135,00 €')).toBeInTheDocument();
        expect(
            within(payments).getByText('Retta di novembre'),
        ).toBeInTheDocument();

        await userEvent.click(
            within(payments).getByRole('button', { name: 'Record payment' }),
        );

        expect(
            await screen.findByRole('dialog', { name: 'I paid it' }),
        ).toBeInTheDocument();
    });

    it('leads each payment to its movement', () => {
        renderPage(PlannedItem, props);

        const payments = screen.getByRole('region', { name: 'Payments' });
        expect(
            within(payments).getByRole('link', { name: /Retta di novembre/ }),
        ).toHaveAttribute('href', '/budget/movements/expense/31');
    });

    it('pays an open due date from its row for a member who can only pay', async () => {
        renderPage(PlannedItem, {
            ...props,
            detail: { ...danceDetail, can_edit: false },
        });
        const next = screen.getByRole('region', { name: 'Next due dates' });

        await userEvent.click(
            within(next).getByRole('button', { name: /05\/01\/2027/ }),
        );

        expect(
            await screen.findByRole('dialog', { name: 'I paid it' }),
        ).toBeInTheDocument();
    });

    it('has the links beside the titles as plain links, not buttons', () => {
        renderPage(PlannedItem, props);

        for (const name of ['Payments', 'Exceptions']) {
            const section = screen.getByRole('region', { name });
            const link = within(section).getByText(
                name === 'Payments' ? 'Record payment' : 'Add',
            );
            expect(link.closest('a, button')).toHaveClass(
                'text-body',
                'hover:underline',
            );
        }
    });

    it('has no Edit, Add or Record payment for a member who cannot', () => {
        renderPage(PlannedItem, {
            ...props,
            detail: { ...danceDetail, can_edit: false, can_pay: false },
        });

        expect(
            screen.queryByRole('link', { name: 'Edit' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Add' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Record payment' }),
        ).not.toBeInTheDocument();
        // an open due date is only written: there is nothing to do with it
        const next = screen.getByRole('region', { name: 'Next due dates' });
        expect(
            within(next).queryByRole('link', { name: /05\/01\/2027/ }),
        ).not.toBeInTheDocument();
        expect(
            within(next).queryByRole('button', { name: /05\/01\/2027/ }),
        ).not.toBeInTheDocument();
        expect(within(next).getByText('05/01/2027')).toBeInTheDocument();
    });
});
