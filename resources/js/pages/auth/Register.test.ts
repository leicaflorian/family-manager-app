import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import Register from './Register.vue';

describe('Sign up', () => {
    it('asks for the four things of the board and the terms', () => {
        renderPage(Register, { passwordRules: 'minlength: 10;' });

        expect(screen.getByLabelText('Your first name')).toBeInTheDocument();
        expect(screen.getByLabelText('Family surname')).toBeInTheDocument();
        expect(screen.getByLabelText('Email')).toHaveAttribute('type', 'email');
        expect(screen.getByLabelText('Choose a password')).toHaveAttribute(
            'type',
            'password',
        );
        expect(screen.getByText('At least 10 characters')).toBeInTheDocument();
        expect(
            screen.getByRole('checkbox', { name: /accept the terms/ }),
        ).not.toBeChecked();
        expect(
            screen.getByRole('button', { name: 'Get started' }),
        ).toHaveAttribute('type', 'submit');
    });
});
