import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmTotal from './FmTotal.vue';

describe('FmTotal', () => {
    it('shows the sum and how many payments, one already late', () => {
        render(FmTotal, {
            props: { total: 1488.79, payments: 8, late: 1 },
        });

        expect(screen.getByText('1.488,79 €')).toBeInTheDocument();
        expect(
            screen.getByText('8 payments, one already late'),
        ).toBeInTheDocument();
    });

    it('says only how many payments when none is late', () => {
        render(FmTotal, { props: { total: 84.2, payments: 1, late: 0 } });

        expect(screen.getByText('1 payment')).toBeInTheDocument();
    });

    it('counts the late ones when they are more than one', () => {
        render(FmTotal, { props: { total: 300, payments: 5, late: 2 } });

        expect(
            screen.getByText('5 payments, 2 already late'),
        ).toBeInTheDocument();
    });

    it('explains what to do when there is nothing to pay', () => {
        render(FmTotal, { props: { total: 0, payments: 0, late: 0 } });

        expect(screen.getByText('0,00 €')).toBeInTheDocument();
        expect(
            screen.getByText(
                "Nothing for now. Upload a bill and you'll see it here, with its due date.",
            ),
        ).toBeInTheDocument();
    });
});
