import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPagina } from '@/test/pagina';
import Register from './Register.vue';

describe('Iscriviti', () => {
    it('chiede le quattro cose della tavola e le condizioni', () => {
        renderPagina(Register, { passwordRules: 'minlength: 10;' });

        expect(screen.getByLabelText('Il tuo nome')).toBeInTheDocument();
        expect(
            screen.getByLabelText('Cognome della famiglia'),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Email')).toHaveAttribute('type', 'email');
        expect(screen.getByLabelText('Scegli una password')).toHaveAttribute(
            'type',
            'password',
        );
        expect(screen.getByText('Almeno 10 caratteri')).toBeInTheDocument();
        expect(
            screen.getByRole('checkbox', { name: /accetto le condizioni/ }),
        ).not.toBeChecked();
        expect(
            screen.getByRole('button', { name: 'Comincia' }),
        ).toHaveAttribute('type', 'submit');
    });
});
