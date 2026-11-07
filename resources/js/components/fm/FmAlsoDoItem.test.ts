import { fireEvent, render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmAlsoDoItem from './FmAlsoDoItem.vue';

describe('FmAlsoDoItem', () => {
    it('is a checkbox with its text', () => {
        render(FmAlsoDoItem, {
            props: { label: 'Create the expense', modelValue: true },
        });

        expect(
            screen.getByRole('checkbox', { name: 'Create the expense' }),
        ).toBeChecked();
    });

    it('shows the fields only while it is ticked', async () => {
        render(FmAlsoDoItem, {
            props: { label: 'Create the expense', modelValue: false },
            slots: { default: '<p>Amount to correct</p>' },
        });

        expect(screen.queryByText('Amount to correct')).not.toBeInTheDocument();

        await fireEvent.click(screen.getByRole('checkbox'));

        expect(screen.getByText('Amount to correct')).toBeInTheDocument();
    });

    it('says the new value when it is ticked', async () => {
        const view = render(FmAlsoDoItem, {
            props: { label: 'Create the expense', modelValue: false },
        });

        await fireEvent.click(screen.getByRole('checkbox'));

        expect(view.emitted('update:modelValue')).toEqual([[true]]);
    });

    it('reads the hint and the warnings', () => {
        render(FmAlsoDoItem, {
            props: {
                label: 'Create the expense',
                modelValue: true,
                hint: 'It is the amount of the bill.',
                warnings: ['The date is missing.', 'The fee does not match.'],
            },
        });

        expect(
            screen.getByText('It is the amount of the bill.'),
        ).toBeInTheDocument();
        expect(screen.getByText('The date is missing.')).toBeInTheDocument();
        expect(screen.getByText('The fee does not match.')).toBeInTheDocument();
    });

    it('shows the actions', () => {
        render(FmAlsoDoItem, {
            props: { label: 'Link the policy', modelValue: true },
            slots: {
                actions: '<button type="button">Back to the proposal</button>',
            },
        });

        expect(
            screen.getByRole('button', { name: 'Back to the proposal' }),
        ).toBeInTheDocument();
    });

    it('cannot be ticked when it is disabled', () => {
        render(FmAlsoDoItem, {
            props: {
                label: 'Create the expense',
                modelValue: false,
                disabled: true,
            },
        });

        expect(screen.getByRole('checkbox')).toBeDisabled();
    });
});
