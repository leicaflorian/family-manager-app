import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import FmLoanFigures from './FmLoanFigures.vue';

describe('FmLoanFigures', () => {
    it('writes the total to pay and the cost', () => {
        renderPage(FmLoanFigures, {
            figures: { total_to_pay: 1200, cost: 100 },
        });

        expect(screen.getByText('Total to pay')).toBeInTheDocument();
        expect(screen.getByText('1.200,00 €')).toBeInTheDocument();
        expect(screen.getByText('Cost')).toBeInTheDocument();
        expect(screen.getByText('100,00 €')).toBeInTheDocument();
    });

    it('writes a dash where the figure cannot be worked out yet', () => {
        renderPage(FmLoanFigures, {
            figures: { total_to_pay: null, cost: null },
        });

        expect(screen.getAllByText('—')).toHaveLength(2);
    });

    it('writes dashes while the first figures are coming', () => {
        renderPage(FmLoanFigures, { figures: null });

        expect(screen.getAllByText('—')).toHaveLength(2);
    });
});
