import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmActiveFilter from './FmActiveFilter.vue';

describe('FmActiveFilter', () => {
    it('shows the filter and removes it with the ×', async () => {
        const result = render(FmActiveFilter, {
            props: { label: 'Laura' },
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove the filter Laura' }),
        );

        expect(screen.getByText('Laura')).toBeInTheDocument();
        expect(result.emitted('remove')).toHaveLength(1);
    });
});
