import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import FmNumberTile from './FmNumberTile.vue';

describe('FmNumberTile', () => {
    it('shows the label, the amount and the line below', () => {
        renderPage(FmNumberTile, {
            number: {
                label: 'Planned',
                amount: 72,
                count: null,
                hint: 'per month, estimated',
                hint_amount: null,
            },
        });

        expect(screen.getByText('Planned')).toBeInTheDocument();
        expect(screen.getByText('72,00 €')).toBeInTheDocument();
        expect(screen.getByText('per month, estimated')).toBeInTheDocument();
    });

    it('shows a number that is not an amount, and writes the amount of the hint', () => {
        renderPage(FmNumberTile, {
            number: {
                label: 'Total to pay',
                amount: null,
                count: 4,
                hint: 'of :amount financed',
                hint_amount: 8000,
            },
        });

        expect(screen.getByText('4')).toBeInTheDocument();
        expect(screen.getByText('of 8.000,00 € financed')).toBeInTheDocument();
    });
});
