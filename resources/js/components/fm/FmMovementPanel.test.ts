import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    danceMovement,
    movementOptions,
    salaryMovement,
} from '@/test/fixtures';
import { choose, renderPage, settle } from '@/test/page';
import FmMovementPanel from './FmMovementPanel.vue';

const start = { kind: 'expense', planned_id: null, date: '2026-10-26' };

const props = { movement: null, options: movementOptions, start };

afterEach(() => {
    vi.restoreAllMocks();
});

/** Writes in a text field, as the person does. */
async function write(label: string, text: string): Promise<void> {
    await fireEvent.update(screen.getByLabelText(label), text);
    await settle();
}

describe('FmMovementPanel', () => {
    it('asks first whether it is an expense or an income, and has no Delete when new', () => {
        renderPage(FmMovementPanel, props);

        expect(
            screen.getByRole('heading', { name: 'New expense or income' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Expense' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Income' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Commission')).toBeInTheDocument();
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
        await choose('Planned expense', 'Retta danza');
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
        await choose('Account', 'Conto BPER');
        await choose('Planned expense', 'Retta danza');
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

        await userEvent.click(screen.getByRole('button', { name: 'Income' }));
        await settle();

        expect(screen.getByLabelText('Received on')).toBeInTheDocument();
        expect(screen.getByText('Type')).toBeInTheDocument();
        expect(screen.queryByLabelText('Commission')).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Category')).not.toBeInTheDocument();
        expect(screen.getByLabelText('Planned income')).toBeInTheDocument();
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
            screen.queryByRole('button', { name: 'Expense' }),
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

    it('lists the documents of a movement as links', () => {
        renderPage(FmMovementPanel, {
            ...props,
            movement: {
                ...danceMovement,
                documents: [
                    {
                        id: 7,
                        title: 'Ricevuta retta',
                        category: 'Scuola',
                        person: null,
                        organization: 'Scuola Passo a Due',
                        date: '2026-10-20',
                        expiry_date: null,
                        status: 'active',
                        mime_type: 'application/pdf',
                        file_url: '/files/9',
                    },
                ],
            },
        });

        expect(
            screen.getByRole('link', { name: /Ricevuta retta/ }),
        ).toHaveAttribute('href', '/documents/7');
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
});
