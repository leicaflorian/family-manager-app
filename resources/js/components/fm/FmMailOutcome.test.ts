import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmMailOutcome from './FmMailOutcome.vue';

describe('FmMailOutcome', () => {
    it('writes what was done with the mail', () => {
        render(FmMailOutcome, {
            props: { label: '1 file saved · 2 deliveries', tone: 'success' },
        });

        expect(
            screen.getByText('1 file saved · 2 deliveries'),
        ).toBeInTheDocument();
    });

    it.each([
        ['success', 'bg-paid-soft', 'text-paid'],
        ['info', 'bg-action-soft', 'text-action-dark'],
        ['neutral', 'bg-divider', 'text-ink-soft'],
        ['warning', 'bg-warning-soft', 'text-warning'],
        ['danger', 'bg-overdue-soft', 'text-overdue'],
    ] as const)(
        'has the colours of the board in the %s tone',
        (tone, background, text) => {
            render(FmMailOutcome, { props: { label: 'Label', tone } });

            expect(screen.getByText('Label').closest('span')).toHaveClass(
                background,
                text,
            );
        },
    );

    it('has an icon the screen reader skips', () => {
        const { container } = render(FmMailOutcome, {
            props: { label: 'Discarded', tone: 'neutral' },
        });

        expect(container.querySelector('svg')).toHaveAttribute(
            'aria-hidden',
            'true',
        );
    });
});
