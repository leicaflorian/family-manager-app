import { plugin as formkit } from '@formkit/vue';
import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import formkitConfig from '@/formkit.config';
import FieldStory from './FieldStory.vue';

/**
 * Mounts the field of a story with the plugins of the app.
 *
 * @param props - The props of the story.
 * @returns The result of Testing Library.
 */
function field(props: Record<string, unknown>) {
    return render(FieldStory, {
        props: { type: 'text', name: 'title', label: 'What it is', ...props },
        global: { plugins: [[formkit, formkitConfig]] },
    });
}

describe('FieldStory', () => {
    it('shows an empty field under its label', () => {
        field({});

        expect(screen.getByLabelText('What it is')).toHaveValue('');
    });

    it('starts with the value of the story', () => {
        field({ value: 'Bolletta luce di ottobre' });

        expect(screen.getByLabelText('What it is')).toHaveValue(
            'Bolletta luce di ottobre',
        );
    });

    it('shows the hint under the field', () => {
        field({ help: 'As it is on the bill' });

        expect(screen.getByText('As it is on the bill')).toBeInTheDocument();
    });

    it('shows the errors at once, as after a failed save', () => {
        field({ errors: ['This field is required.'] });

        expect(screen.getByText('This field is required.')).toBeInTheDocument();
    });

    it('disables the field', () => {
        field({ disabled: true });

        expect(screen.getByLabelText('What it is')).toBeDisabled();
    });

    it('gives the options to a field with a list', async () => {
        field({
            type: 'dropdown',
            label: 'Holder',
            options: [{ value: 1, label: 'Marco Bianchi' }],
            value: 1,
        });

        expect(
            (await screen.findAllByText('Marco Bianchi')).length,
        ).toBeGreaterThan(0);
    });

    it('shows a checkbox, which has no options', () => {
        field({ type: 'checkbox', label: 'Stay signed in', value: true });

        expect(screen.getByLabelText('Stay signed in')).toBeChecked();
    });

    it('gives the other attributes to the field', () => {
        field({ autocomplete: 'off' });

        expect(screen.getByLabelText('What it is')).toHaveAttribute(
            'autocomplete',
            'off',
        );
    });
});
