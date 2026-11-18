import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
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
    danceMovement,
    movementOptions,
    salaryMovement,
} from '@/test/fixtures';
import { hasChanges, leaveQuestion } from '@/lib/unsavedChanges';
import { choose, renderPage, settle } from '@/test/page';
import FmMovementPanel from './FmMovementPanel.vue';

const start = { kind: 'expense', planned_id: null, date: '2026-10-26' };

const props = { movement: null, options: movementOptions, start };

beforeEach(() => {
    // Choosing a planned item asks the page for its due dates
    vi.spyOn(router, 'reload').mockImplementation(() => {});
});

afterEach(() => {
    vi.restoreAllMocks();
});

/** Writes in a text field, as the person does. */
async function write(label: string, text: string): Promise<void> {
    await fireEvent.update(screen.getByLabelText(label), text);
    await settle();
}

describe('FmMovementPanel', () => {
    it('has the padding of the card form on the computer', () => {
        renderPage(FmMovementPanel, props);

        expect(screen.getByRole('form')).toHaveClass('p-5', 'wide:p-7');
    });

    it('asks first whether it is an expense or an income, and has no Delete when new', () => {
        renderPage(FmMovementPanel, props);

        expect(
            screen.getByRole('heading', { name: 'New expense or income' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'An expense' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'An income' }),
        ).toBeInTheDocument();
        expect(
            screen.getByLabelText('Commission (optional)'),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Delete' }),
        ).not.toBeInTheDocument();
    });

    it('sends a new movement as a POST, with the empty fields empty', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmMovementPanel, props);

        await write('Title', 'Gelato');
        await write('Amount', '3,50');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(post).toHaveBeenCalledTimes(1);
        const [url, data] = post.mock.calls[0]!;
        expect(url).toBe('/budget/movements');
        expect(data).toMatchObject({
            kind: 'expense',
            title: 'Gelato',
            amount: 3.5,
            date: '2026-10-26',
            fee_amount: null,
            financial_account_id: null,
            planned_id: null,
        });
        expect(data).not.toHaveProperty('_method');
    });

    it('adds the commission to the amount it sends', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmMovementPanel, props);

        await write('Title', 'Affitto');
        await write('Amount', '650');
        await write('Commission (optional)', '1');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(post.mock.calls[0]![1]).toMatchObject({
            amount: 651,
            fee_amount: 1,
        });
        expect(
            screen.getByText('It is added to the amount.'),
        ).toBeInTheDocument();
    });

    it('shows a saved expense as its cost plus its commission, and saves it back the same', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmMovementPanel, {
            ...props,
            movement: { ...danceMovement, amount: 46, fee_amount: 1 },
        });

        expect(await screen.findByLabelText('Amount')).toHaveValue(
            '45,00\u00a0€',
        );
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(post.mock.calls[0]![1]).toMatchObject({
            amount: 46,
            fee_amount: 1,
        });
    });

    it('does not send a movement without its title and amount', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmMovementPanel, props);

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).not.toHaveBeenCalled();
        expect(screen.getAllByText('This field is required.')).toHaveLength(2);
    });

    it('fills the empty fields when a planned expense is chosen', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmMovementPanel, props);

        await write('Amount', '45');
        await choose('Planned expense (optional)', 'Retta danza');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(screen.getByLabelText('Title')).toHaveValue('Retta danza');
        expect(post.mock.calls[0]![1]).toMatchObject({
            title: 'Retta danza',
            planned_id: '4',
            category_id: '8',
            organization_id: '5',
            financial_account_id: '2',
            person_id: '4',
        });
    });

    it('keeps what is written when a planned expense is chosen', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmMovementPanel, props);

        await write('Title', 'Retta di dicembre');
        await write('Amount', '45');
        await choose('Bank account', 'Conto BPER');
        await choose('Planned expense (optional)', 'Retta danza');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(post.mock.calls[0]![1]).toMatchObject({
            title: 'Retta di dicembre',
            financial_account_id: '1',
            category_id: '8',
        });
    });

    it('starts from the planned expense of the address', async () => {
        renderPage(FmMovementPanel, {
            ...props,
            start: { ...start, planned_id: '4' },
            occurrences: [
                { value: '2026-12-05', label: '05/12/2026', help: null },
                { value: '2027-01-05', label: '05/01/2027', help: null },
            ],
        });

        expect(screen.getByLabelText('Title')).toHaveValue('Retta danza');
        expect(screen.getByLabelText('Due date')).toBeInTheDocument();
    });

    it('shows the income fields instead of the expense ones', async () => {
        renderPage(FmMovementPanel, props);

        await userEvent.click(
            screen.getByRole('button', { name: 'An income' }),
        );
        await settle();

        expect(screen.getByLabelText('Received on')).toBeInTheDocument();
        expect(screen.getByText('Type')).toBeInTheDocument();
        expect(
            screen.queryByLabelText('Commission (optional)'),
        ).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Category')).not.toBeInTheDocument();
        expect(
            screen.getByLabelText('Planned income (optional)'),
        ).toBeInTheDocument();
    });

    it('puts the organization of an income beside the account, and the type on a row of its own', async () => {
        const view = renderPage(FmMovementPanel, props);

        await userEvent.click(
            screen.getByRole('button', { name: 'An income' }),
        );
        await settle();

        const labels = [...view.container.querySelectorAll('label, legend')]
            .map((label) => label.textContent?.replace('(optional)', '').trim())
            .filter((text) =>
                ['Bank account', 'Organization', 'Type', 'Holder'].includes(
                    text ?? '',
                ),
            );
        expect(labels).toEqual([
            'Bank account',
            'Organization',
            'Type',
            'Holder',
        ]);
    });

    it('asks the page for the open due dates of whichever planned expense is chosen', async () => {
        vi.spyOn(router, 'post').mockImplementation(() => {});
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        const view = renderPage(FmMovementPanel, props);
        expect(screen.queryByLabelText('Due date')).not.toBeInTheDocument();

        await choose('Planned expense (optional)', 'Retta danza');

        expect(reload).toHaveBeenCalledWith({
            only: ['occurrences'],
            data: {
                planned_kind: 'expense',
                planned: '4',
                planned_date: '2026-10-26',
            },
            preserveUrl: true,
        });

        await view.rerender({
            occurrences: [
                { value: '2026-12-05', label: '05/12/2026', help: null },
            ],
        });
        expect(screen.getByLabelText('Due date')).toBeInTheDocument();

        // Choosing nothing hides the menu and asks for nothing
        reload.mockClear();
        await choose('Planned expense (optional)', 'None');
        expect(reload).not.toHaveBeenCalled();
        expect(screen.queryByLabelText('Due date')).not.toBeInTheDocument();
    });

    it('sends the due date that the page offered first', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const view = renderPage(FmMovementPanel, props);
        await write('Amount', '45');
        await choose('Planned expense (optional)', 'Retta danza');
        await view.rerender({
            occurrences: [
                { value: '2026-12-05', label: '05/12/2026', help: null },
            ],
        });
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(post.mock.calls[0]![1]).toMatchObject({
            planned_id: '4',
            occurrence_date: '2026-12-05',
        });
    });

    it('asks for the employment of an income and fills it from the planned income', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmMovementPanel, props);

        await userEvent.click(
            screen.getByRole('button', { name: 'An income' }),
        );
        await settle();
        await write('Amount', '1720');
        await choose('Planned income (optional)', 'Stipendio di Laura');
        expect(
            screen.getByLabelText('Employment (optional)'),
        ).toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(post.mock.calls[0]![1]).toMatchObject({
            kind: 'income',
            employment_id: '1',
            type: 'salary',
            planned_id: '2',
        });
        expect(post.mock.calls[0]![1]).not.toHaveProperty('category_id');
    });

    it('never sends an income without its type', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmMovementPanel, props);

        await userEvent.click(
            screen.getByRole('button', { name: 'An income' }),
        );
        await settle();
        await write('Title', 'Rimborso');
        await write('Amount', '20');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        const type = (post.mock.calls[0]![1] as { type: string | null }).type;
        expect(type).toBeTruthy();
    });

    it('shows a movement and saves a change as a POST with the method PUT', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmMovementPanel, {
            ...props,
            movement: danceMovement,
            occurrences: [
                { value: '2026-11-05', label: '05/11/2026', help: null },
            ],
            start: { kind: 'expense', planned_id: '4', date: '2026-10-20' },
        });

        expect(
            screen.getByRole('heading', { name: 'Retta di novembre' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Title')).toHaveValue('Retta di novembre');
        // The kind cannot change on a saved movement
        expect(
            screen.queryByRole('button', { name: 'An expense' }),
        ).not.toBeInTheDocument();

        await write('Amount', '46');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        const [url, data] = post.mock.calls[0]!;
        expect(url).toBe('/budget/movements/expense/31');
        expect(data).toMatchObject({
            _method: 'put',
            title: 'Retta di novembre',
            amount: 46,
            date: '2026-10-20',
            financial_account_id: '2',
            planned_id: '4',
            occurrence_date: '2026-11-05',
        });
    });

    it('asks again before deleting, and only to those who may', async () => {
        const first = renderPage(FmMovementPanel, {
            ...props,
            movement: salaryMovement,
        });

        await userEvent.click(screen.getByRole('button', { name: 'Delete' }));
        expect(
            await screen.findByRole('dialog', {
                name: 'Delete this movement?',
            }),
        ).toBeInTheDocument();
        first.unmount();

        renderPage(FmMovementPanel, {
            ...props,
            movement: { ...salaryMovement, can_delete: false },
        });
        expect(
            screen.queryByRole('button', { name: 'Delete' }),
        ).not.toBeInTheDocument();
    });

    it('tells that a movement cannot be changed, and gives up with Cancel', async () => {
        const panel = renderPage(FmMovementPanel, {
            ...props,
            movement: { ...danceMovement, can_edit: false },
        });

        expect(
            screen.queryByRole('button', { name: 'Save' }),
        ).not.toBeInTheDocument();
        await fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
        expect(panel.emitted()).toHaveProperty('close');
    });

    it('says which fields may stay empty, as the board Nuova-spesa', () => {
        const view = renderPage(FmMovementPanel, props);

        const optional = [...view.container.querySelectorAll('label')]
            .filter((label) => label.textContent?.includes('(optional)'))
            .map((label) =>
                label.textContent?.replace('(optional)', '').trim(),
            );
        expect(optional).toEqual([
            'Commission',
            'Organization',
            'Refers to',
            'Holder',
            'Planned expense',
        ]);
        expect(screen.getByLabelText('Title')).toBeInTheDocument();
    });

    it('puts one field on each line, as the board, but the date beside the amount', () => {
        const view = renderPage(FmMovementPanel, props);

        const labels = [...view.container.querySelectorAll('label, legend')]
            .map((label) => label.textContent?.replace('(optional)', '').trim())
            .filter((text) => text !== '' && text !== undefined);
        expect(labels).toEqual([
            'It is',
            'Title',
            'Paid on',
            'Amount',
            'Commission',
            'Bank account',
            'Category',
            'Organization',
            'Refers to',
            'Holder',
            'Planned expense',
        ]);
    });

    /** The grid row (two columns) that holds a field, by its label. */
    function pairOf(label: string): HTMLElement | null {
        return screen.getByLabelText(label).closest('.grid-cols-2');
    }

    it('puts the expense fields in pairs, as the board Movimento', async () => {
        renderPage(FmMovementPanel, props);

        expect(pairOf('Paid on')).toBe(pairOf('Amount'));
        expect(pairOf('Bank account')).toBe(pairOf('Category'));
        expect(pairOf('Refers to (optional)')).toBe(
            pairOf('Holder (optional)'),
        );
        expect(pairOf('Planned expense (optional)')).not.toBeNull();
        // Organization and Commission have a line of their own
        expect(pairOf('Organization (optional)')).toBeNull();
        expect(pairOf('Commission (optional)')).toBeNull();
        // Without a planned item the field is alone in its half row
        expect(
            pairOf('Planned expense (optional)')!.querySelectorAll('label'),
        ).toHaveLength(1);
    });

    it('puts the planned expense beside its due date', () => {
        renderPage(FmMovementPanel, {
            ...props,
            movement: danceMovement,
            occurrences: [
                { value: '2026-11-05', label: '05/11/2026', help: null },
            ],
        });

        expect(pairOf('Due date')).not.toBeNull();
        expect(pairOf('Planned expense (optional)')).toBe(pairOf('Due date'));
    });

    it('puts the income fields in pairs, as the board Entrata', async () => {
        renderPage(FmMovementPanel, props);
        await userEvent.click(
            screen.getByRole('button', { name: 'An income' }),
        );
        await settle();

        expect(pairOf('Received on')).toBe(pairOf('Amount'));
        expect(pairOf('Bank account')).toBe(pairOf('Organization (optional)'));
        expect(pairOf('Holder (optional)')).toBe(
            pairOf('Employment (optional)'),
        );
        expect(pairOf('Planned income (optional)')).not.toBeNull();
        expect(screen.getByText('Type').closest('.grid-cols-2')).toBeNull();
    });

    it('sends the files, the documents to link and to unlink with a change', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const file = new File(['x'], 'scontrino.jpg', { type: 'image/jpeg' });
        renderPage(FmMovementPanel, {
            ...props,
            movement: danceMovement,
            files: [file],
            link: [{ id: 7 } as App.Data.DocumentData],
            unlink: [9],
            back: '/budget/movements?month=2026-10',
        });

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(post.mock.calls[0]![1]).toMatchObject({
            _method: 'put',
            files: [file],
            link_documents: [7],
            unlink_documents: [9],
            from: '/budget/movements?month=2026-10',
        });
    });

    it('sends the empty lists and no from when nothing was chosen', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmMovementPanel, props);

        await write('Title', 'Gelato');
        await write('Amount', '3,50');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        const data = post.mock.calls[0]![1] as Record<string, unknown>;
        expect(data).toMatchObject({
            files: [],
            link_documents: [],
            unlink_documents: [],
        });
        expect(data).not.toHaveProperty('from');
    });

    it('counts a file chosen as a change not saved', async () => {
        const view = renderPage(FmMovementPanel, {
            ...props,
            movement: danceMovement,
        });
        await settle();
        expect(hasChanges()).toBe(false);

        await view.rerender({ files: [new File(['x'], 'scontrino.jpg')] });
        await settle();

        expect(hasChanges()).toBe(true);
    });

    it('has no change when nothing was touched', async () => {
        renderPage(FmMovementPanel, { ...props, movement: danceMovement });
        await settle();

        expect(hasChanges()).toBe(false);
    });

    it('has no heading of its own on the computer, where the page has the title', () => {
        const matchMedia = window.matchMedia;
        window.matchMedia = ((query: string) => {
            const media = matchMedia(query);

            return Object.assign(media, { matches: true });
        }) as typeof window.matchMedia;
        renderPage(FmMovementPanel, props);
        window.matchMedia = matchMedia;

        expect(
            screen.queryByRole('heading', { name: 'New expense or income' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('form', { name: 'New expense or income' }),
        ).toBeInTheDocument();
    });

    it('has Cancel white with a grey edge and Save taking the rest of the line', () => {
        renderPage(FmMovementPanel, props);

        expect(screen.getByRole('button', { name: 'Cancel' })).toHaveClass(
            'border-edge-soft',
        );
        expect(screen.getByRole('button', { name: 'Save' })).toHaveClass(
            'grow',
        );
    });

    it('gives up the changes with Cancel without asking, and the page does not ask either', async () => {
        const panel = renderPage(FmMovementPanel, props);
        await write('Title', 'Spesa al mercato');

        await fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(leaveQuestion.open).toBe(false);
        expect(hasChanges()).toBe(false);
        expect(panel.emitted()).toHaveProperty('close');
    });
});
