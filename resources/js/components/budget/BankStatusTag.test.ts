import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import BankStatusTag from './BankStatusTag.vue';

describe('BankStatusTag', () => {
    it.each([
        ['unsorted', undefined, 'Left to sort', 'bg-action-soft'],
        ['linked', 'expense', 'An expense', 'bg-paid-soft'],
        ['linked', 'income', 'An income', 'bg-paid-soft'],
        ['fee', undefined, 'Commission', 'bg-paid-soft'],
        ['transfer', undefined, 'Transfer', 'bg-banks-soft'],
        ['ignored', undefined, 'Set aside', 'bg-card-sunken'],
        ['pending', undefined, 'Pending at the bank', 'bg-warning-soft'],
    ] as const)(
        'draws %s %s as «%s» with its colour',
        (status, kind, label, background) => {
            renderPage(BankStatusTag, { status, kind });

            expect(screen.getByText(label)).toHaveClass(background);
        },
    );
});
