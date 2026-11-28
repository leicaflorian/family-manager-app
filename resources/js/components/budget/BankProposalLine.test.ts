import { fireEvent, screen } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { bankTransactions, bankProposals } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import BankProposalLine from './BankProposalLine.vue';

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-11-20T14:30:00Z'));
});

afterEach(() => vi.useRealTimers());

/**
 * Mounts the line as the lone row does.
 *
 * @param proposal - what the server proposes
 * @param amount - the amount of the movement (the sign says the direction of a transfer)
 * @param extra - the other props
 */
function mount(
    proposal: App.Data.BankProposalData,
    amount = -23.9,
    extra: Record<string, unknown> = {},
): ReturnType<typeof renderPage> {
    return renderPage(BankProposalLine, {
        row: { ...bankTransactions[1], amount, proposal },
        ...extra,
    });
}

/**
 * The text of the line as one sentence, the spans put together.
 *
 * @param view - the mounted line
 * @returns the text with the spaces collapsed
 */
function sentence(view: ReturnType<typeof renderPage>): string {
    return (view.container.textContent ?? '').replace(/\s+/g, ' ').trim();
}

describe('BankProposalLine', () => {
    it('says the expense already recorded, its day and its amount', () => {
        const view = mount(bankProposals.recorded);

        expect(sentence(view)).toContain(
            'Maybe it is: Spesa Famila · already recorded on 15/11/2026 · 23,90 €',
        );
        expect(screen.getByText('Spesa Famila')).toBeInTheDocument();
    });

    it('says a due date as the due date of the payment', () => {
        const view = mount(bankProposals.deadline, 407.6);

        expect(sentence(view)).toContain(
            'Maybe it is: Assegno Unico novembre 2026 · due on 18/11/2026 · 407,60 €',
        );
    });

    it('says a transfer from the other account for money in', () => {
        const view = mount(bankProposals.transfer, 200);

        expect(sentence(view)).toContain(
            'Maybe it is a transfer from Buddy Bank',
        );
        expect(screen.getByText('Buddy Bank')).toBeInTheDocument();
    });

    it('says a transfer to the other account for money out', () => {
        const view = mount(bankProposals.transfer, -200);

        expect(sentence(view)).toContain(
            'Maybe it is a transfer to Buddy Bank',
        );
    });

    it('says yes with the key of the proposal', async () => {
        const view = mount(bankProposals.recorded);

        await fireEvent.click(screen.getByRole('button', { name: 'Yes' }));

        expect(view.emitted().yes).toEqual([
            [expect.objectContaining({ id: 2 })],
        ]);
    });

    it('says no', async () => {
        const view = mount(bankProposals.recorded);

        await fireEvent.click(screen.getByRole('button', { name: 'No' }));

        expect(view.emitted().no).toEqual([
            [expect.objectContaining({ id: 2 })],
        ]);
    });

    it('waits while it is being sent', () => {
        mount(bankProposals.recorded, -23.9, { busy: true });

        expect(screen.getByRole('button', { name: 'Yes' })).toBeDisabled();
        expect(screen.getByRole('button', { name: 'No' })).toBeDisabled();
    });
});
