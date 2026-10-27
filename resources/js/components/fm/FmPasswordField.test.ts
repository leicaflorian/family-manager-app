import { plugin as formkit } from '@formkit/vue';
import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import formkitConfig from '@/formkit.config';
import FmPasswordField from './FmPasswordField.vue';

/**
 * The password field, as in the *Sign up* form.
 */
function field(errors: string[] = []) {
    return render(FmPasswordField, {
        props: {
            label: 'Choose a password',
            name: 'password',
            help: 'At least 10 characters',
            errors,
        },
        global: { plugins: [[formkit, formkitConfig]] },
    });
}

describe('FmPasswordField', () => {
    it('hides the password and shows it with the eye', async () => {
        field();
        const input = screen.getByLabelText('Choose a password');

        expect(input).toHaveAttribute('type', 'password');
        await userEvent.click(
            screen.getByRole('button', { name: 'Show the password' }),
        );
        expect(input).toHaveAttribute('type', 'text');
        await userEvent.click(
            screen.getByRole('button', { name: 'Hide the password' }),
        );
        expect(input).toHaveAttribute('type', 'password');
    });

    it('shows the hint and the errors of the server', () => {
        field(['The password must be at least 10 characters.']);

        expect(screen.getByText('At least 10 characters')).toBeInTheDocument();
        expect(
            screen.getByText('The password must be at least 10 characters.'),
        ).toBeInTheDocument();
    });
});
