import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPagina } from '@/test/pagina';
import ConfirmPassword from './ConfirmPassword.vue';

describe('Conferma la password', () => {
    it('chiede la password prima di andare avanti', () => {
        renderPagina(ConfirmPassword);

        expect(screen.getByLabelText('Password')).toHaveAttribute(
            'type',
            'password',
        );
        expect(
            screen.getByRole('button', { name: 'Conferma' }),
        ).toBeInTheDocument();
    });
});
