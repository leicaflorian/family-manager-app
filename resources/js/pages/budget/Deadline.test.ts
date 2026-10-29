import { screen } from '@testing-library/vue';
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
    accountOptions,
    danceDeadline,
    paidDeadline,
    salaryDeadline,
} from '@/test/fixtures';
import { renderPage } from '@/test/page';
import Deadline from './Deadline.vue';

/** The car insurance of the Bianchi, due on 21/11/2026. */
const carDeadline: App.Data.DeadlineData = {
    ...danceDeadline,
    planned_id: 6,
    title: 'Rata RC auto',
    planned_title: 'Assicurazione auto',
    date: '2026-11-21',
    original_date: '2026-11-21',
    amount: 418,
    base_amount: null,
    notes: null,
    card: {
        ...danceDeadline.card!,
        type: 'insurance_policy',
        id: 5,
        label: 'RC auto Panda - UnipolSai',
        area: 'insurance',
        area_label: 'Insurance',
    },
    account_id: 1,
    account: 'Conto BPER',
};

const props = {
    deadline: carDeadline,
    next: [{ ...carDeadline, date: '2027-11-21', original_date: '2027-11-21' }],
    accounts: accountOptions,
    canPay: true,
    canChange: true,
    pay: false,
};

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-26T10:00:00+01:00'));
});

afterEach(() => {
    vi.useRealTimers();
});

describe('A due date', () => {
    it('shows the card, the title, the amount and when it falls', () => {
        renderPage(Deadline, props);

        expect(
            screen.getByRole('heading', { name: 'Rata RC auto', level: 1 }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'RC auto Panda - UnipolSai' }),
        ).toHaveAttribute('href', '/cards/insurance_policy/5');
        expect(screen.getByRole('link', { name: 'Insurance' })).toHaveAttribute(
            'href',
            '/areas/insurance',
        );
        // The amount of the due date, then the one of the next due date
        expect(screen.getAllByText('418,00 €')).toHaveLength(2);
        expect(
            screen.getByText('Due on 21/11/2026 · in 26 days · Conto BPER'),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /21\/11/ })).toHaveAttribute(
            'href',
            '/budget/planned/expense/6/occurrences/2027-11-21',
        );
    });

    it('opens the panel when I paid it is tapped', async () => {
        renderPage(Deadline, props);

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'I paid it' }),
        );

        expect(
            await screen.findByRole('dialog', { name: 'I paid it' }),
        ).toBeInTheDocument();
    });

    it('opens the panel to change the due date', async () => {
        renderPage(Deadline, props);

        await userEvent.click(
            screen.getByRole('button', { name: 'Change this due date' }),
        );

        expect(
            await screen.findByRole('dialog', { name: 'Change this due date' }),
        ).toBeInTheDocument();
    });

    it('opens the panel at once with pay', async () => {
        renderPage(Deadline, { ...props, pay: true });

        expect(
            await screen.findByRole('dialog', { name: 'I paid it' }),
        ).toBeInTheDocument();
    });

    it('says It arrived for an income', async () => {
        renderPage(Deadline, {
            ...props,
            deadline: salaryDeadline,
            next: [],
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'It arrived' }),
        );

        expect(
            await screen.findByRole('dialog', { name: 'It arrived' }),
        ).toBeInTheDocument();
    });

    it('shows the payment of a paid due date and no button to pay', () => {
        renderPage(Deadline, { ...props, deadline: paidDeadline, next: [] });

        expect(
            screen.getByText('Paid on 20/10 · 45,00 € · Carta Laura'),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'I paid it' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Change this due date' }),
        ).not.toBeInTheDocument();
    });

    it('does not open the panel of a paid due date', () => {
        renderPage(Deadline, {
            ...props,
            deadline: paidDeadline,
            next: [],
            pay: true,
        });

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('leaves out the buttons the account cannot use', () => {
        renderPage(Deadline, { ...props, canPay: false, canChange: false });

        expect(
            screen.queryByRole('button', { name: 'I paid it' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Change this due date' }),
        ).not.toBeInTheDocument();
    });

    it('shows the note of a changed due date', () => {
        renderPage(Deadline, { ...props, deadline: danceDeadline, next: [] });

        expect(
            screen.getByText('Comprende la quota del saggio di Natale'),
        ).toBeInTheDocument();
    });
});
