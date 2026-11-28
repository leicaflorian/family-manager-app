import { screen } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { renderPage } from '@/test/page';
import BankFeeLine from './BankFeeLine.vue';

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-11-20T14:30:00Z'));
});

afterEach(() => vi.useRealTimers());

describe('BankFeeLine', () => {
    it('says the commission and the day it was booked', () => {
        renderPage(BankFeeLine, {
            fee: { id: 41, amount: -0.4, booked_on: '2026-11-25' },
            currency: 'EUR',
        });

        expect(
            screen.getByText('+ 0,40 € commission, 25/11'),
        ).toBeInTheDocument();
    });
});
