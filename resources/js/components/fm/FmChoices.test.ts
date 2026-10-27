import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmChoices from './FmChoices.vue';

const options = [
    { value: 'to-pay', label: 'To pay' },
    { value: 'paid', label: 'Already paid' },
];

describe('FmChoices', () => {
    it('marks the choice made', () => {
        render(FmChoices, {
            props: {
                label: 'The bill',
                options,
                modelValue: 'to-pay',
            },
        });

        expect(
            screen.getByRole('radiogroup', { name: 'The bill' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('radio', { name: 'To pay' })).toBeChecked();
        expect(
            screen.getByRole('radio', { name: 'Already paid' }),
        ).not.toBeChecked();
    });

    it('changes the choice when tapped', async () => {
        const result = render(FmChoices, {
            props: {
                label: 'The bill',
                options,
                modelValue: 'to-pay',
            },
        });

        await userEvent.click(screen.getByText('Already paid'));

        expect(result.emitted('update:modelValue')).toEqual([['paid']]);
    });
});
