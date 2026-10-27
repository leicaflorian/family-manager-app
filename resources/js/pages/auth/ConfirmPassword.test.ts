import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import ConfirmPassword from './ConfirmPassword.vue';

describe('Confirm the password', () => {
    it('asks for the password before going on', () => {
        renderPage(ConfirmPassword);

        expect(screen.getByLabelText('Password')).toHaveAttribute(
            'type',
            'password',
        );
        expect(
            screen.getByRole('button', { name: 'Confirm' }),
        ).toBeInTheDocument();
    });
});
