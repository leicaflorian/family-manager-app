import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmNotice from './FmNotice.vue';

describe('FmNotice', () => {
    it('is a message the screen reader announces', () => {
        render(FmNotice, {
            slots: { default: 'I will remind you 3 days before.' },
        });

        expect(screen.getByRole('status')).toHaveTextContent(
            'I will remind you 3 days before.',
        );
    });

    it('is green in the ok tone, orange in the warning one', () => {
        const result = render(FmNotice, {
            props: { tone: 'ok' },
            slots: { default: 'Done' },
        });
        expect(screen.getByRole('status')).toHaveClass('text-[#14533F]');
        result.unmount();

        render(FmNotice, {
            props: { tone: 'warning' },
            slots: { default: 'The logbook is missing' },
        });
        expect(screen.getByRole('status')).toHaveClass('text-warning');
    });
});
