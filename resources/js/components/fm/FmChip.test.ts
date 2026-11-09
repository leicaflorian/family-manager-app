import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmChip from './FmChip.vue';

describe('FmChip', () => {
    it('is a toggle button that says if it is on, with its count in the name', () => {
        const { rerender } = render(FmChip, {
            props: { label: 'Documents', count: 3, pressed: true },
        });

        const chip = screen.getByRole('button', { name: 'Documents 3' });
        expect(chip).toHaveAttribute('aria-pressed', 'true');

        return rerender({ label: 'Documents', count: 3, pressed: false }).then(
            () => expect(chip).toHaveAttribute('aria-pressed', 'false'),
        );
    });

    it('works without a count', () => {
        render(FmChip, { props: { label: 'All', pressed: false } });

        expect(screen.getByRole('button', { name: 'All' })).toBeInTheDocument();
    });

    it('emits click', async () => {
        const { emitted } = render(FmChip, {
            props: { label: 'Documents', pressed: false },
        });

        await userEvent.click(screen.getByRole('button'));

        expect(emitted()).toHaveProperty('click');
    });
});
