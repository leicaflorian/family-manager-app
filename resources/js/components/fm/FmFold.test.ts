import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmFold from './FmFold.vue';

describe('FmFold', () => {
    it('keeps its content folded until the person opens it', async () => {
        render(FmFold, {
            props: { label: 'Closed (2)' },
            slots: { default: '<p>The rows</p>' },
        });

        const button = screen.getByRole('button', { name: 'Closed (2)' });
        expect(button).toHaveAttribute('aria-expanded', 'false');
        expect(screen.queryByText('The rows')).not.toBeInTheDocument();

        await userEvent.click(button);

        expect(button).toHaveAttribute('aria-expanded', 'true');
        expect(screen.getByText('The rows')).toBeInTheDocument();

        await userEvent.click(button);

        expect(screen.queryByText('The rows')).not.toBeInTheDocument();
    });
});
