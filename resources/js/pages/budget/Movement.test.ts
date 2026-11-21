import { router } from '@inertiajs/vue3';
import { fireEvent, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { danceMovement, healthCard, movementOptions } from '@/test/fixtures';
import {
    choose,
    openMenu,
    renderPage,
    withTrail,
    screenWidth,
    search,
    settle,
    shown,
} from '@/test/page';
import Movement from './Movement.vue';

const props = {
    movement: danceMovement,
    start: { kind: 'expense', planned_id: '4', date: '2026-10-20' },
    occurrences: [{ value: '2026-11-05', label: '05/11/2026', help: null }],
    options: movementOptions,
};

/** A new expense of 26/10/2026. */
const newExpense = {
    ...props,
    movement: null,
    occurrences: [],
    start: { kind: 'expense', planned_id: null, date: '2026-10-26' },
};

beforeEach(() => {
    // Choosing a planned item asks the page for its due dates
    vi.spyOn(router, 'reload').mockImplementation(() => {});
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

/** Writes in a text field, as the person does. */
async function write(label: string, text: string): Promise<void> {
    await fireEvent.update(screen.getByLabelText(label), text);
    await settle();
}

describe('Movement', () => {
    it('writes the trail Budget > movement into the top bar, new or not', () => {
        const saved = withTrail();
        const first = renderPage(Movement, props, saved.provide);

        expect(saved.state.pieces.value).toEqual([
            { label: 'Budget', href: '/budget' },
            { label: 'Movements', href: '/budget/movements?month=2026-10' },
            { label: 'Retta di novembre' },
        ]);
        first.unmount();

        const created = withTrail();
        renderPage(Movement, newExpense, created.provide);

        expect(created.state.pieces.value).toEqual([
            { label: 'Budget', href: '/budget' },
            { label: 'Movements', href: '/budget/movements?month=2026-10' },
            { label: 'New expense or income' },
        ]);
    });

    it('keeps the trail through the month when the expense starts from a card', () => {
        const { state, provide } = withTrail();
        renderPage(
            Movement,
            {
                ...newExpense,
                start: { ...newExpense.start, card: 'health_event:2' },
            },
            provide,
        );

        expect(state.pieces.value).toEqual([
            { label: 'Budget', href: '/budget' },
            { label: 'Movements', href: '/budget/movements?month=2026-10' },
            { label: 'New expense or income' },
        ]);
    });

    it("sends Cancel to the server's back", async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Movement, {
            ...props,
            back: '/budget/comparison?month=2026-10',
        });

        await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(get).toHaveBeenCalledWith('/budget/comparison?month=2026-10');
    });

    it('shows the movement', () => {
        renderPage(Movement, props);

        expect(screen.getByLabelText('Title')).toHaveValue('Retta di novembre');
    });

    it('opens an empty form for a new expense or income', () => {
        renderPage(Movement, {
            ...props,
            movement: null,
            occurrences: [],
            start: { kind: 'expense', planned_id: null, date: '2026-10-26' },
        });

        expect(
            screen.getByRole('heading', { name: 'New expense or income' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'An expense' }),
        ).toBeInTheDocument();
    });

    it('starts a new expense from a card, with its category, and asks to come back to it', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Movement, {
            ...newExpense,
            start: {
                ...newExpense.start,
                card: 'health_event:2',
                category_id: '9',
            },
        });

        expect(
            screen.getByRole('combobox', { name: 'Health event (optional)' }),
        ).toHaveValue('Health event · Visite di Giulia');

        await write('Title', 'Visita');
        await write('Amount', '80');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post.mock.calls[0]![1]).toMatchObject({
            category_id: '9',
            card: 'health_event:2',
            back_to_card: true,
        });

        await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));
        expect(get).toHaveBeenCalledWith('/cards/health_event/2');
    });

    it('starts a new expense from a card with its holder and organization', async () => {
        renderPage(Movement, {
            ...newExpense,
            start: { ...newExpense.start, card: 'health_event:2' },
        });
        await settle();

        expect(screen.getByRole('combobox', { name: 'Category' })).toHaveValue(
            'Salute',
        );
        expect(
            screen.getByRole('combobox', { name: 'Holder (optional)' }),
        ).toHaveValue('Giulia Bianchi');
        expect(
            screen.getByRole('combobox', { name: 'Organization (optional)' }),
        ).toHaveValue('Studio Ferri');
    });

    it('names the card after the type of the category, offers only its cards and sends the one chosen', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Movement, newExpense);

        expect(
            screen.getByRole('combobox', { name: 'Refers to (optional)' }),
        ).toBeInTheDocument();

        await choose('Category', 'Auto');

        expect(shown(await openMenu('Vehicle (optional)'))).toEqual([
            'None',
            'Vehicle · Panda',
        ]);

        const list = await search('Vehicle (optional)', 'Pa');
        await userEvent.click(
            within(list).getByRole('option', { name: 'Vehicle · Panda' }),
        );
        await settle();
        await write('Title', 'Parcheggio');
        await write('Amount', '2,50');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0]![1]).toMatchObject({
            category_id: '10',
            card: 'vehicle:1',
        });
    });

    it('empties the card when the category passes to another type', async () => {
        renderPage(Movement, newExpense);

        await choose('Category', 'Auto');
        await choose('Vehicle (optional)', 'Vehicle · Panda');
        await choose('Category', 'Bollette e utenze');

        expect(
            screen.getByRole('combobox', { name: 'Utility (optional)' }),
        ).toHaveValue('');
    });

    it('fills the empty category, holder and organization from the card chosen', async () => {
        renderPage(Movement, newExpense);

        await choose('Organization (optional)', 'Studio Ferri');
        await choose('Refers to (optional)', 'Enrollment · Danza Giulia');

        expect(screen.getByRole('combobox', { name: 'Category' })).toHaveValue(
            'Svago › Sport',
        );
        expect(
            screen.getByRole('combobox', { name: 'Holder (optional)' }),
        ).toHaveValue('Giulia Bianchi');
        expect(
            screen.getByRole('combobox', { name: 'Organization (optional)' }),
        ).toHaveValue('Studio Ferri');
        expect(
            screen.getByRole('combobox', { name: 'Enrollment (optional)' }),
        ).toHaveValue('Enrollment · Danza Giulia');
    });

    it('takes the card of the planned expense chosen', async () => {
        renderPage(Movement, newExpense);

        await choose('Planned expense (optional)', 'Bollo Panda');

        expect(
            screen.getByRole('combobox', { name: 'Vehicle (optional)' }),
        ).toHaveValue('Vehicle · Panda');
    });

    it('opens from a due date with the card of the planned expense, even of another type than the category, and sends it', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        // The mortgage of the Bianchi: category «Casa e affitto» (home), card its loan
        renderPage(Movement, {
            ...newExpense,
            start: { kind: 'expense', planned_id: '9', date: '2026-11-15' },
            occurrences: [
                { value: '2026-11-15', label: '15/11/2026', help: null },
            ],
            options: {
                ...movementOptions,
                categories: [
                    ...movementOptions.categories,
                    { value: '12', label: 'Casa e affitto', help: null },
                ],
                planned: [
                    ...movementOptions.planned,
                    {
                        kind: 'expense',
                        value: '9',
                        label: 'Mutuo casa',
                        category_id: '12',
                        card: 'loan:1',
                        organization_id: null,
                        financial_account_id: '1',
                        person_id: null,
                        employment_id: null,
                        type: null,
                    },
                ],
                cards: [
                    ...movementOptions.cards,
                    { value: 'home:1', label: 'Home · Casa', help: null },
                    { value: 'loan:1', label: 'Loan · Mutuo casa', help: null },
                ],
                category_card_types: {
                    ...movementOptions.category_card_types,
                    '12': 'home',
                },
                card_type_labels: {
                    ...movementOptions.card_type_labels,
                    home: 'Home',
                    loan: 'Loan',
                },
            },
        });
        await settle();

        expect(
            screen.getByRole('combobox', { name: 'Home (optional)' }),
        ).toHaveValue('Loan · Mutuo casa');

        await write('Amount', '612');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0]![1]).toMatchObject({
            planned_id: '9',
            category_id: '12',
            card: 'loan:1',
        });
    });

    it('has no card for an income', async () => {
        renderPage(Movement, newExpense);

        await userEvent.click(
            screen.getByRole('button', { name: 'An income' }),
        );
        await settle();

        expect(
            screen.queryByRole('combobox', { name: 'Refers to (optional)' }),
        ).not.toBeInTheDocument();
    });

    describe('documents', () => {
        it('shows the Documents column with Link and the documents of the movement', () => {
            renderPage(Movement, {
                ...props,
                movement: { ...danceMovement, documents: [healthCard] },
                hasLinkable: true,
            });

            expect(
                screen.getByRole('heading', { name: 'Documents' }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: 'Link document' }),
            ).toBeInTheDocument();
            expect(screen.getByText(healthCard.title)).toBeInTheDocument();
            expect(
                screen.getByRole('button', {
                    name: `More actions on ${healthCard.title}`,
                }),
            ).toBeInTheDocument();
        });

        it('leaves the documents to be read only when the movement cannot be changed', () => {
            renderPage(Movement, {
                ...props,
                movement: {
                    ...danceMovement,
                    can_edit: false,
                    documents: [healthCard],
                },
                hasLinkable: true,
            });

            expect(screen.getByText(healthCard.title)).toBeInTheDocument();
            expect(
                screen.queryByRole('button', { name: 'Link document' }),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByText(
                    'Its documents? Drag them here, or choose them',
                ),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByRole('button', {
                    name: `More actions on ${healthCard.title}`,
                }),
            ).not.toBeInTheDocument();
        });

        it('says No documents yet for a new movement', () => {
            renderPage(Movement, newExpense);

            expect(screen.getByText('No documents yet')).toBeInTheDocument();
        });

        it('sends the files with the movement when it is saved', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(Movement, newExpense);
            const file = new File(['x'], 'ricevuta.pdf', {
                type: 'application/pdf',
            });

            await userEvent.upload(
                document.querySelector<HTMLInputElement>('input[type=file]')!,
                file,
            );
            await write('Title', 'Parcheggio');
            await write('Amount', '2,50');
            await userEvent.click(screen.getByRole('button', { name: 'Save' }));

            expect(post.mock.calls[0]![1]).toMatchObject({ files: [file] });
        });

        it('has the page title as the heading on the computer, with the form without its own', () => {
            screenWidth(true);
            renderPage(Movement, props);

            expect(
                screen.getAllByRole('heading', { name: 'Retta di novembre' }),
            ).toHaveLength(1);
            expect(
                screen.queryByRole('link', { name: 'Movements' }),
            ).not.toBeInTheDocument();
        });
    });
    describe('recorded by the bank', () => {
        const bank: App.Data.MovementBankData = {
            booked_on: '2026-11-05',
            text: 'ADDEBITO DIRETTO SDD EDISON ENERGIA SPA',
            account: 'Conto BancoPosta ·· 3307',
            undo_url: '/budget/bank/9/payment',
        };

        it('says the bank recorded it, with the text of the bank', () => {
            renderPage(Movement, {
                ...props,
                movement: { ...danceMovement, bank },
            });

            expect(
                screen.getByText('Recorded by the bank on 05/11/2026'),
            ).toBeInTheDocument();
            expect(
                screen.getByText(/ADDEBITO DIRETTO SDD EDISON ENERGIA SPA/),
            ).toBeInTheDocument();
        });

        it('says the bank recorded it without a day when the bank gave none', () => {
            renderPage(Movement, {
                ...props,
                movement: {
                    ...danceMovement,
                    bank: { ...bank, booked_on: null },
                },
            });

            expect(
                screen.getByText('Recorded by the bank'),
            ).toBeInTheDocument();
        });

        it('shows nothing without the bank', () => {
            renderPage(Movement, {
                ...props,
                movement: { ...danceMovement, bank: null },
            });

            expect(
                screen.queryByText(/Recorded by the bank/),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByRole('button', { name: 'It was not this one' }),
            ).not.toBeInTheDocument();
        });

        it('has no button without the undo', () => {
            renderPage(Movement, {
                ...props,
                movement: {
                    ...danceMovement,
                    bank: { ...bank, undo_url: null },
                },
            });

            expect(
                screen.getByText(/Recorded by the bank/),
            ).toBeInTheDocument();
            expect(
                screen.queryByRole('button', { name: 'It was not this one' }),
            ).not.toBeInTheDocument();
        });

        it('asks before taking the payment back, then sends the delete', async () => {
            const spy = vi.spyOn(router, 'delete').mockImplementation(() => {});
            renderPage(Movement, {
                ...props,
                movement: { ...danceMovement, bank },
            });

            await userEvent.click(
                screen.getByRole('button', { name: 'It was not this one' }),
            );
            const dialog = await screen.findByRole('dialog');

            expect(
                within(dialog).getByText(
                    'The expense is removed and the due date is to be paid again.',
                ),
            ).toBeInTheDocument();
            expect(spy).not.toHaveBeenCalled();

            await userEvent.click(
                within(dialog).getByRole('button', {
                    name: 'It was not this one',
                }),
            );

            expect(spy).toHaveBeenCalledWith(
                '/budget/bank/9/payment',
                expect.anything(),
            );
        });

        it('says the income for an income', async () => {
            renderPage(Movement, {
                ...props,
                movement: { ...danceMovement, kind: 'income', bank },
            });

            await userEvent.click(
                screen.getByRole('button', { name: 'It was not this one' }),
            );

            expect(
                await screen.findByText(
                    'The income is removed and the due date is to be paid again.',
                ),
            ).toBeInTheDocument();
        });
    });
});
