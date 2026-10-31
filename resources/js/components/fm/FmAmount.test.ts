import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmAmount from './FmAmount.vue';

describe('FmAmount', () => {
    it('writes an amount in euros', () => {
        render(FmAmount, { props: { amount: 84.2 } });

        expect(screen.getByText('84,20 €')).toBeInTheDocument();
    });

    it('marks an estimate with a tilde', () => {
        render(FmAmount, { props: { amount: 72, estimated: true } });

        expect(screen.getByText('~72,00 €')).toBeInTheDocument();
    });

    it('strikes the old amount when it was changed', () => {
        render(FmAmount, { props: { amount: 60, base: 45 } });

        expect(screen.getByText('45,00 €').tagName).toBe('S');
        expect(screen.getByText('60,00 €')).toBeInTheDocument();
    });

    it('writes an income with a plus, in green', () => {
        render(FmAmount, { props: { amount: 1720, income: true } });

        expect(screen.getByText('+1.720,00 €')).toHaveClass('text-paid');
    });
});
